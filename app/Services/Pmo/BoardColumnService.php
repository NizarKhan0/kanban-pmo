<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Exceptions\Pmo\BoardColumnException;
use App\Models\ActivityLog;
use App\Models\BoardColumn;
use App\Support\TaskTable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Kanban column engine: CRUD, positioning and WIP. Every query is scoped to Auth::id().
 */
final class BoardColumnService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @return Collection<int, BoardColumn>
     */
    public function list(): Collection
    {
        return BoardColumn::query()
            ->where('user_id', $this->ownerId())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): BoardColumn
    {
        return BoardColumn::query()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id);
    }

    /**
     * Provision the standard PMO workflow for a user who has no columns yet.
     *
     * @return bool True when defaults were created.
     */
    public function ensureDefaults(): bool
    {
        $ownerId = $this->ownerId();

        return DB::transaction(function () use ($ownerId): bool {
            if (BoardColumn::query()->where('user_id', $ownerId)->lockForUpdate()->exists()) {
                return false;
            }

            foreach (BoardColumn::DEFAULTS as $index => $column) {
                BoardColumn::query()->create([
                    ...$column,
                    'user_id' => $ownerId,
                    'sort_order' => ($index + 1) * BoardColumn::SORT_STEP,
                ]);
            }

            return true;
        });
    }

    /**
     * Card count per column in a single grouped query.
     *
     * @param  Collection<int, BoardColumn>  $columns
     * @return array<int, int>
     */
    public function cardCounts(Collection $columns): array
    {
        $counts = array_fill_keys(array_map(intval(...), $columns->modelKeys()), 0);

        if ($counts === [] || ! TaskTable::supports(['user_id', 'board_column_id'])) {
            return $counts;
        }

        $rows = DB::table(TaskTable::NAME)
            ->where('user_id', $this->ownerId())
            ->whereIn('board_column_id', array_keys($counts))
            ->groupBy('board_column_id')
            ->selectRaw('board_column_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'board_column_id');

        foreach ($rows as $columnId => $aggregate) {
            $counts[(int) $columnId] = (int) $aggregate;
        }

        return $counts;
    }

    /**
     * @param  array{name: string, icon: string, color: string, wip_limit: int, is_done_column: bool}  $data
     */
    public function create(array $data): BoardColumn
    {
        $ownerId = $this->ownerId();
        $max = (int) BoardColumn::query()->where('user_id', $ownerId)->max('sort_order');

        $column = BoardColumn::query()->create([
            ...$data,
            'user_id' => $ownerId,
            'sort_order' => $this->nextStep($max),
        ]);

        $this->activity->log(
            ActivityLog::COL_ADDED,
            $column->name,
            $column->wip_limit > 0 ? "WIP limit {$column->wip_limit}" : 'No WIP limit',
        );

        return $column;
    }

    /**
     * @param  array{name: string, icon: string, color: string, wip_limit: int, is_done_column: bool}  $data
     */
    public function update(int $id, array $data): BoardColumn
    {
        $column = $this->find($id);
        $column->fill($data);
        $changed = array_keys($column->getDirty());
        $column->save();

        if ($changed !== []) {
            $this->activity->log(ActivityLog::UPDATED, $column->name, 'Column settings: '.implode(', ', array_map(fn (string $f): string => str_replace('_', ' ', $f), $changed)));
        }

        return $column;
    }

    /**
     * Persist a new left-to-right order. sort_order becomes 10, 20, 30...
     * Foreign or unknown IDs are ignored; owned columns missing from the list
     * keep their relative order after the listed ones.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function updateColumnOrder(array $orderedIds): void
    {
        $ownerId = $this->ownerId();

        DB::transaction(function () use ($ownerId, $orderedIds): void {
            $columns = BoardColumn::query()
                ->where('user_id', $ownerId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $requested = array_values(array_unique(array_filter(
                array_map(intval(...), $orderedIds),
                static fn (int $id): bool => $columns->has($id),
            )));

            $final = [...$requested, ...array_values(array_diff($columns->keys()->all(), $requested))];

            foreach ($final as $index => $id) {
                $sortOrder = ($index + 1) * BoardColumn::SORT_STEP;
                $column = $columns[$id];

                if ($column->sort_order !== $sortOrder) {
                    BoardColumn::query()
                        ->where('user_id', $ownerId)
                        ->whereKey($id)
                        ->update(['sort_order' => $sortOrder]);
                }
            }
        });
    }

    /**
     * Move one column to a zero-based position (drag-and-drop / move left-right).
     */
    public function moveColumn(int $id, int $position): void
    {
        $ids = array_map(intval(...), $this->list()->modelKeys());
        $from = array_search($id, $ids, true);

        if ($from === false) {
            $this->find($id); // throws ModelNotFoundException for foreign IDs

            return;
        }

        array_splice($ids, $from, 1);
        array_splice($ids, max(0, min($position, count($ids))), 0, [$id]);

        $this->updateColumnOrder($ids);
    }

    /**
     * @return array{name: string, cards: int, can_delete: bool, targets: array<int, string>}
     */
    public function deletePreview(int $id): array
    {
        $column = $this->find($id);
        $others = $this->list()->reject(fn (BoardColumn $c): bool => $c->id === $column->id);

        return [
            'name' => $column->name,
            'cards' => $column->cardCount(),
            'can_delete' => $others->isNotEmpty(),
            'targets' => $others->mapWithKeys(fn (BoardColumn $c): array => [$c->id => $c->name])->all(),
        ];
    }

    /**
     * Delete a column. Task cards are never deleted: they move to $targetColumnId,
     * applying the Auto-Done Priority Sync when crossing a Done column boundary.
     *
     * @return int Number of task cards moved.
     *
     * @throws BoardColumnException
     */
    public function delete(int $id, ?int $targetColumnId = null): int
    {
        $column = $this->find($id);
        $ownerId = $column->user_id;

        return DB::transaction(function () use ($column, $ownerId, $targetColumnId): int {
            $remaining = BoardColumn::query()
                ->where('user_id', $ownerId)
                ->whereKeyNot($column->id)
                ->lockForUpdate()
                ->count();

            if ($remaining === 0) {
                throw BoardColumnException::lastColumn();
            }

            $cards = $column->cardCount();
            $moved = 0;
            $targetName = null;

            if ($cards > 0) {
                if ($targetColumnId === null) {
                    throw BoardColumnException::targetRequired($cards);
                }

                $target = BoardColumn::query()
                    ->where('user_id', $ownerId)
                    ->whereKeyNot($column->id)
                    ->find($targetColumnId);

                if ($target === null) {
                    throw BoardColumnException::invalidTarget();
                }

                $moved = $this->moveCards($column, $target);
                $targetName = $target->name;
            }

            $column->delete();

            $this->activity->log(
                ActivityLog::DELETED,
                $column->name,
                $targetName !== null ? "Column removed · {$moved} card(s) moved to {$targetName}" : 'Column removed',
            );

            return $moved;
        });
    }

    private function moveCards(BoardColumn $from, BoardColumn $to): int
    {
        $changes = ['board_column_id' => $to->id];

        if (TaskTable::supports(['priority'])) {
            $trackCompletion = TaskTable::supports(['completed_at']);

            if ($to->is_done_column && ! $from->is_done_column) {
                $changes['priority'] = 'done';
                if ($trackCompletion) {
                    $changes['completed_at'] = now();
                }
            } elseif ($from->is_done_column && ! $to->is_done_column) {
                $changes['priority'] = 'medium';
                if ($trackCompletion) {
                    $changes['completed_at'] = null;
                }
            }
        }

        $cards = DB::table(TaskTable::NAME)
            ->where('user_id', $from->user_id)
            ->where('board_column_id', $from->id);

        if (! TaskTable::supports(['sort_order'])) {
            return $cards->update($changes);
        }

        // Append moved cards after the destination's existing cards.
        $offset = (int) DB::table(TaskTable::NAME)
            ->where('user_id', $to->user_id)
            ->where('board_column_id', $to->id)
            ->max('sort_order');

        return $cards->increment('sort_order', $offset, $changes);
    }

    private function nextStep(int $max): int
    {
        return (intdiv($max, BoardColumn::SORT_STEP) + 1) * BoardColumn::SORT_STEP;
    }

    private function ownerId(): int
    {
        $id = Auth::id();

        if ($id === null) {
            throw new AuthenticationException;
        }

        return (int) $id;
    }
}
