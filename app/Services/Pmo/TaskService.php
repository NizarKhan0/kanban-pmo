<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\ActivityLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TeamMember;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Task & Subtask business logic, filtering and drag-and-drop sync.
 * Every query is scoped to Auth::id() (subtasks through their parent task).
 */
final class TaskService
{
    /** @var list<string> Names auto-registered during the last create/update. */
    private array $registered = [];

    public function __construct(private readonly ActivityLogger $activity) {}

    // ──────────────────────────────────────────────────────────────────
    // Read
    // ──────────────────────────────────────────────────────────────────

    /**
     * Visible tasks grouped by column id, ordered per the active sort.
     *
     * @return array<int, Collection<int, Task>>
     */
    public function board(TaskFilters $filters): array
    {
        $query = $this->filteredQuery($filters)->with(['column', 'project', 'teamMember', 'subtasks']);
        $this->applySort($query, $filters);

        $tasks = $query->get();

        if ($filters->sort === 'progress') {
            $tasks = $tasks->sortByDesc(fn (Task $task): int => $task->progressPercentage())->values();
        }

        $grouped = [];
        foreach ($tasks as $task) {
            $grouped[$task->board_column_id] ??= new Collection;
            $grouped[$task->board_column_id]->push($task);
        }

        return $grouped;
    }

    /**
     * Owner-scoped query with the view + filter bar applied (no sorting, no eager loads).
     *
     * @return Builder<Task>
     */
    public function filteredQuery(TaskFilters $filters): Builder
    {
        $query = Task::query()->where('tasks.user_id', $this->ownerId());
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @return array{open: int, overdue: int, due_week: int, completed: int}
     */
    public function kpis(): array
    {
        $today = Task::today();
        $week = Carbon::parse($today)->addDays(7)->toDateString();

        $open = fn (): Builder => $this->openQuery();

        return [
            'open' => $open()->count(),
            'overdue' => $open()->whereNotNull('due_date')->where('due_date', '<', $today)->count(),
            'due_week' => $open()->whereBetween('due_date', [$today, $week])->count(),
            'completed' => Task::query()
                ->where('user_id', $this->ownerId())
                ->where(fn (Builder $q) => $q->where('priority', 'done')->orWhereRelation('column', 'is_done_column', true))
                ->count(),
        ];
    }

    public function find(int $id): Task
    {
        return Task::query()
            ->where('user_id', $this->ownerId())
            ->with(['column', 'project', 'teamMember', 'subtasks'])
            ->findOrFail($id);
    }

    public function findSubtask(int $id): Subtask
    {
        return Subtask::query()
            ->whereHas('task', fn (Builder $q) => $q->where('user_id', $this->ownerId()))
            ->with('task')
            ->findOrFail($id);
    }

    public function meId(): ?int
    {
        $id = TeamMember::query()
            ->where('user_id', $this->ownerId())
            ->where('is_me', true)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    // ──────────────────────────────────────────────────────────────────
    // Create / Update / Delete
    // ──────────────────────────────────────────────────────────────────

    /**
     * @param  array{board_column_id: int, title: string, project_name: ?string, pic_name: ?string, priority: string, icon: string, color: string, due_date: ?string, tags: list<string>, notes: ?string}  $data
     * @param  list<array{id: ?int, title: string, is_completed: bool}>  $subtasks
     */
    public function create(array $data, array $subtasks = []): Task
    {
        $ownerId = $this->ownerId();
        $this->registered = [];

        return DB::transaction(function () use ($ownerId, $data, $subtasks): Task {
            $column = $this->column($data['board_column_id']);

            $task = new Task([
                ...$this->attributes($data),
                'user_id' => $ownerId,
                'board_column_id' => $column->id,
                'sort_order' => $this->nextSortOrder($column->id),
            ]);
            $this->syncCompletion($task, null, $column);
            $task->save();

            $this->syncSubtasks($task, $subtasks);

            $this->activity->log(ActivityLog::CREATED, $task->title, 'Added to '.$column->name.$this->picSuffix($task));

            return $task->load(['column', 'subtasks']);
        });
    }

    /**
     * @param  array{board_column_id: int, title: string, project_name: ?string, pic_name: ?string, priority: string, icon: string, color: string, due_date: ?string, tags: list<string>, notes: ?string}  $data
     * @param  list<array{id: ?int, title: string, is_completed: bool}>  $subtasks
     */
    public function update(int $id, array $data, array $subtasks = []): Task
    {
        $task = $this->find($id);
        $this->registered = [];

        return DB::transaction(function () use ($task, $data, $subtasks): Task {
            $from = $task->column;
            $to = $this->column($data['board_column_id']);

            $task->fill($this->attributes($data));

            if ($from->id !== $to->id) {
                $task->board_column_id = $to->id;
                $task->sort_order = $this->nextSortOrder($to->id);
            }

            $this->syncCompletion($task, $from, $to);
            $changed = array_values(array_diff(array_keys($task->getDirty()), ['board_column_id', 'sort_order', 'updated_at', 'completed_at']));
            $task->save();

            $this->syncSubtasks($task, $subtasks);

            if ($from->id !== $to->id) {
                $this->activity->log(ActivityLog::MOVED, $task->title, "{$from->name} → {$to->name}");
            }
            if ($changed !== [] || $from->id === $to->id) {
                $this->activity->log(ActivityLog::UPDATED, $task->title, $changed === []
                    ? 'Details saved'
                    : 'Changed: '.implode(', ', array_map(fn (string $f): string => str_replace(['_snapshot', '_id', '_'], ['', '', ' '], $f), $changed)));
            }

            return $task->load(['column', 'subtasks']);
        });
    }

    public function delete(int $id): void
    {
        $task = $this->find($id);
        $task->delete(); // subtasks cascade at DB level

        $this->activity->log(ActivityLog::DELETED, $task->title, 'Removed from '.$task->column->name);
    }

    /**
     * @return list<string> "Project: X" / "PIC: Y" entries registered by the last create/update.
     */
    public function registered(): array
    {
        return $this->registered;
    }

    // ──────────────────────────────────────────────────────────────────
    // Subtasks
    // ──────────────────────────────────────────────────────────────────

    public function toggleSubtask(int $id): Subtask
    {
        $subtask = $this->findSubtask($id);
        $subtask->update(['is_completed' => ! $subtask->is_completed]);

        $this->activity->log(
            ActivityLog::UPDATED,
            $subtask->task->title,
            ($subtask->is_completed ? 'Subtask completed: ' : 'Subtask reopened: ').$subtask->title,
        );

        return $subtask;
    }

    public function deleteSubtask(int $id): Subtask
    {
        $subtask = $this->findSubtask($id);
        $subtask->delete();

        $this->activity->log(ActivityLog::UPDATED, $subtask->task->title, 'Subtask removed: '.$subtask->title);

        return $subtask;
    }

    // ──────────────────────────────────────────────────────────────────
    // Drag-and-Drop
    // ──────────────────────────────────────────────────────────────────

    /**
     * Move a card into $targetColumnId and persist the order the user sees.
     *
     * $orderedTaskIds is the *visible* order of the target column after the
     * drop (it includes $taskId). Cards hidden by the current filters keep
     * their original slots; visible cards are re-ordered within the visible
     * slots, and the moved card is anchored next to its visible neighbour.
     *
     * Crossing into a Done column sets priority 'done' + completed_at; leaving
     * one resets priority to 'medium' and clears completed_at.
     *
     * @param  array<int, int|string>  $orderedTaskIds
     */
    public function syncCardMove(int $taskId, int $targetColumnId, array $orderedTaskIds): Task
    {
        $ownerId = $this->ownerId();

        return DB::transaction(function () use ($ownerId, $taskId, $targetColumnId, $orderedTaskIds): Task {
            $task = Task::query()->where('user_id', $ownerId)->with('column')->lockForUpdate()->findOrFail($taskId);
            $from = $task->column;
            $to = $this->column($targetColumnId);

            $columnIds = array_values(Task::query()
                ->where('user_id', $ownerId)
                ->where('board_column_id', $to->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all());

            $originalIndex = array_search($task->id, $columnIds, true);
            $others = array_values(array_filter($columnIds, fn (int $id): bool => $id !== $task->id));
            $otherSet = array_flip($others);

            // Visible order restricted to cards that really are in the target column (+ the moved card).
            $visible = [];
            foreach (array_map(intval(...), $orderedTaskIds) as $id) {
                if (($id === $task->id || isset($otherSet[$id])) && ! in_array($id, $visible, true)) {
                    $visible[] = $id;
                }
            }
            if (! in_array($task->id, $visible, true)) {
                $visible[] = $task->id;
            }

            if ($originalIndex !== false) {
                // Same-column reorder: visible cards (incl. the moved one) are permuted
                // among the slots they already occupy; hidden cards keep their exact slot.
                $result = $this->permuteVisibleSlots($columnIds, $visible);
            } else {
                // Cross-column move: re-order visible cards inside their slots, then
                // anchor the incoming card next to its visible neighbour.
                $visibleOthers = array_values(array_filter($visible, fn (int $id): bool => $id !== $task->id));
                $result = $this->permuteVisibleSlots($others, $visibleOthers);

                $position = (int) array_search($task->id, $visible, true);
                $before = $visible[$position - 1] ?? null;
                $after = $visible[$position + 1] ?? null;

                $insertAt = match (true) {
                    $before !== null => (int) array_search($before, $result, true) + 1,
                    $after !== null => (int) array_search($after, $result, true),
                    default => count($result),
                };
                array_splice($result, $insertAt, 0, [$task->id]);
            }

            // 3) Persist column change + completion sync, then renumber 10, 20, 30...
            if ($from->id !== $to->id) {
                $task->board_column_id = $to->id;
                $this->syncCompletion($task, $from, $to);
            }

            foreach ($result as $index => $id) {
                $sortOrder = ($index + 1) * Task::SORT_STEP;

                if ($id === $task->id) {
                    $task->sort_order = $sortOrder;

                    continue;
                }

                Task::query()
                    ->where('user_id', $ownerId)
                    ->whereKey($id)
                    ->where('sort_order', '!=', $sortOrder)
                    ->update(['sort_order' => $sortOrder]);
            }

            $task->save();

            if ($from->id !== $to->id) {
                $this->activity->log(ActivityLog::MOVED, $task->title, "{$from->name} → {$to->name}");
            }

            return $task->setRelation('column', $to);
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────────────

    /**
     * Write $visibleOrder into the slots of $ids occupied by visible cards,
     * leaving every hidden card at its original index.
     *
     * @param  list<int>  $ids
     * @param  list<int>  $visibleOrder
     * @return list<int>
     */
    private function permuteVisibleSlots(array $ids, array $visibleOrder): array
    {
        $visibleSet = array_flip($visibleOrder);
        $cursor = 0;

        foreach ($ids as $slot => $id) {
            if (isset($visibleSet[$id])) {
                $ids[$slot] = $visibleOrder[$cursor++];
            }
        }

        return $ids;
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyFilters(Builder $query, TaskFilters $f): void
    {
        $today = Task::today();

        match ($f->view) {
            'my' => ($meId = $this->meId()) === null
                ? $query->whereRaw('1 = 0')
                : $query->where('team_member_id', $meId),
            'due' => $this->whereOpen($query)
                ->whereNotNull('due_date')
                ->where('due_date', '<=', Carbon::parse($today)->addDays(7)->toDateString()),
            'completed' => $query->where(fn (Builder $q) => $q->where('priority', 'done')->orWhereRelation('column', 'is_done_column', true)),
            default => null,
        };

        $search = trim($f->search);
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhere('tags', 'like', $like)
                ->orWhere('project_name_snapshot', 'like', $like)
                ->orWhere('pic_name_snapshot', 'like', $like)
                ->orWhereHas('subtasks', fn (Builder $s) => $s->where('title', 'like', $like)));
        }

        if ($f->project === TaskFilters::NONE) {
            $query->whereNull('project_id');
        } elseif (ctype_digit($f->project)) {
            $query->where('project_id', (int) $f->project);
        }

        if ($f->assignee === TaskFilters::NONE) {
            $query->whereNull('team_member_id');
        } elseif (ctype_digit($f->assignee)) {
            $query->where('team_member_id', (int) $f->assignee);
        }

        if (array_key_exists($f->priority, Task::PRIORITIES)) {
            $query->where('priority', $f->priority);
        }

        match ($f->due) {
            'overdue' => $this->whereOpen($query)->whereNotNull('due_date')->where('due_date', '<', $today),
            'today' => $query->where('due_date', $today),
            'week' => $query->whereBetween('due_date', [$today, Carbon::parse($today)->addDays(7)->toDateString()]),
            'month' => $query->whereBetween('due_date', [$today, Carbon::parse($today)->addDays(30)->toDateString()]),
            'none' => $query->whereNull('due_date'),
            default => null,
        };
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applySort(Builder $query, TaskFilters $f): void
    {
        match ($f->sort) {
            'due' => $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END"),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'az' => $query->orderBy('title'),
            default => null,
        };

        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private function whereOpen(Builder $query): Builder
    {
        return $query->where('priority', '!=', 'done')->whereRelation('column', 'is_done_column', false);
    }

    /**
     * @return Builder<Task>
     */
    private function openQuery(): Builder
    {
        return $this->whereOpen(Task::query()->where('user_id', $this->ownerId()));
    }

    /**
     * Apply the Auto-Done Priority Sync when a card enters / leaves a Done column,
     * and keep completed_at consistent with the priority.
     */
    private function syncCompletion(Task $task, ?BoardColumn $from, BoardColumn $to): void
    {
        if ($to->is_done_column) {
            $task->priority = 'done';
        } elseif ($from !== null && $from->is_done_column && $from->id !== $to->id) {
            $task->priority = 'medium';
        }

        if ($task->priority === 'done') {
            $task->completed_at ??= Carbon::now();
        } else {
            $task->completed_at = null;
        }
    }

    /**
     * @param  array{title: string, project_name: ?string, pic_name: ?string, priority: string, icon: string, color: string, due_date: ?string, tags: list<string>, notes: ?string}  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $project = $this->resolveProject($data['project_name']);
        $member = $this->resolveMember($data['pic_name']);

        return [
            'title' => $data['title'],
            'priority' => $data['priority'],
            'icon' => $data['icon'],
            'color' => $data['color'],
            'due_date' => $data['due_date'],
            'tags' => $data['tags'] === [] ? null : $data['tags'],
            'notes' => $data['notes'],
            'project_id' => $project?->id,
            'project_name_snapshot' => $project?->name,
            'team_member_id' => $member?->id,
            'pic_name_snapshot' => $member?->name,
        ];
    }

    /**
     * Link an existing project by name (case-insensitive) or auto-register a new one.
     */
    private function resolveProject(?string $name): ?Project
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);
        $ownerId = $this->ownerId();

        $project = Project::query()
            ->where('user_id', $ownerId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($project === null) {
            $project = Project::query()->create([
                'user_id' => $ownerId,
                'name' => $name,
                'sort_order' => (int) Project::query()->where('user_id', $ownerId)->max('sort_order') + 1,
            ]);
            $this->registered[] = "Project: {$name}";
            $this->activity->log(ActivityLog::CREATED, $name, 'Project auto-registered from a task card');
        }

        return $project;
    }

    /**
     * Link an existing PIC by name (case-insensitive) or auto-register a new one.
     */
    private function resolveMember(?string $name): ?TeamMember
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);
        $ownerId = $this->ownerId();

        $member = TeamMember::query()
            ->where('user_id', $ownerId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($member === null) {
            $member = TeamMember::query()->create([
                'user_id' => $ownerId,
                'name' => $name,
                'sort_order' => (int) TeamMember::query()->where('user_id', $ownerId)->max('sort_order') + 1,
            ]);
            $this->registered[] = "PIC: {$name}";
            $this->activity->log(ActivityLog::PIC_ADDED, $name, 'Auto-registered from a task card');
        }

        return $member;
    }

    /**
     * Create new rows and update existing ones (only those owned by this task).
     * Existing subtasks are removed exclusively through deleteSubtask().
     *
     * @param  list<array{id: ?int, title: string, is_completed: bool}>  $rows
     */
    private function syncSubtasks(Task $task, array $rows): void
    {
        $existing = $task->subtasks()->get()->keyBy('id');

        foreach ($rows as $index => $row) {
            $attributes = [
                'title' => $row['title'],
                'is_completed' => $row['is_completed'],
                'sort_order' => ($index + 1) * 10,
            ];

            if ($row['id'] !== null && $existing->has($row['id'])) {
                $existing[$row['id']]->update($attributes);
            } elseif ($row['id'] === null) {
                $task->subtasks()->create($attributes);
            }
        }
    }

    private function picSuffix(Task $task): string
    {
        return $task->pic_name_snapshot === null ? '' : ' · PIC: '.$task->pic_name_snapshot;
    }

    private function column(int $id): BoardColumn
    {
        return BoardColumn::query()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id);
    }

    private function nextSortOrder(int $columnId): int
    {
        $max = (int) Task::query()
            ->where('user_id', $this->ownerId())
            ->where('board_column_id', $columnId)
            ->max('sort_order');

        return (intdiv($max, Task::SORT_STEP) + 1) * Task::SORT_STEP;
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
