<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Exceptions\Pmo\BoardColumnException;
use App\Livewire\Forms\Pmo\BoardColumnForm;
use App\Models\BoardColumn;
use App\Services\Pmo\BoardColumnService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, BoardColumn> $columns
 * @property-read array<int, int> $cardCounts
 * @property-read array{columns: int, cards: int, wip_limited: int, over_wip: int} $kpis
 */
#[Title('Kanban Board')]
class KanbanBoard extends Component
{
    public BoardColumnForm $columnForm;

    public bool $showColumnModal = false;

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deleteId = null;

    #[Locked]
    public string $deleteName = '';

    #[Locked]
    public int $deleteCards = 0;

    #[Locked]
    public bool $deleteAllowed = true;

    /** @var array<int, string> */
    #[Locked]
    public array $deleteTargets = [];

    public ?int $deleteTargetId = null;

    public function mount(BoardColumnService $service): void
    {
        if ($service->ensureDefaults()) {
            Flux::toast(heading: 'Board ready', text: 'Your board has been set up with the standard PMO workflow.');
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Read
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, BoardColumn>
     */
    #[Computed]
    public function columns(): Collection
    {
        return app(BoardColumnService::class)->list();
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function cardCounts(): array
    {
        return app(BoardColumnService::class)->cardCounts($this->columns);
    }

    /**
     * @return array{columns: int, cards: int, wip_limited: int, over_wip: int}
     */
    #[Computed]
    public function kpis(): array
    {
        return [
            'columns' => $this->columns->count(),
            'cards' => array_sum($this->cardCounts),
            'wip_limited' => $this->columns->filter(fn (BoardColumn $c): bool => $c->hasWipLimit())->count(),
            'over_wip' => $this->columns->filter(fn (BoardColumn $c): bool => $c->isOverWip($this->cardCounts[$c->id] ?? 0))->count(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Create / Update
    // ──────────────────────────────────────────────────────────────────

    public function createColumn(): void
    {
        $this->columnForm->reset();
        $this->columnForm->resetErrorBag();
        $this->showColumnModal = true;
    }

    public function editColumn(int $id): void
    {
        $this->columnForm->fillFrom(app(BoardColumnService::class)->find($id));
        $this->showColumnModal = true;
    }

    public function saveColumn(): void
    {
        $data = $this->columnForm->payload();
        $service = app(BoardColumnService::class);

        if ($this->columnForm->id !== null) {
            $column = $service->update($this->columnForm->id, $data);
            Flux::toast(variant: 'success', heading: 'Column updated', text: "\"{$column->name}\" has been saved.");
        } else {
            $column = $service->create($data);
            Flux::toast(variant: 'success', heading: 'Column added', text: "\"{$column->name}\" was added to your board.");
        }

        $this->closeModals();
    }

    // ──────────────────────────────────────────────────────────────────
    // Reorder (Alpine x-sort drag-and-drop + keyboard-friendly menu)
    // ──────────────────────────────────────────────────────────────────

    public function moveColumn(int $id, int $position): void
    {
        app(BoardColumnService::class)->moveColumn($id, $position);
    }

    public function shiftColumn(int $id, int $direction): void
    {
        $ids = array_map(intval(...), $this->columns->modelKeys());
        $index = array_search($id, $ids, true);

        if ($index === false) {
            return;
        }

        app(BoardColumnService::class)->moveColumn($id, $index + ($direction < 0 ? -1 : 1));
        unset($this->columns);
    }

    // ──────────────────────────────────────────────────────────────────
    // Delete (with Confirmation Modal)
    // ──────────────────────────────────────────────────────────────────

    public function confirmDeleteColumn(int $id): void
    {
        $preview = app(BoardColumnService::class)->deletePreview($id);

        $this->deleteId = $id;
        $this->deleteName = $preview['name'];
        $this->deleteCards = $preview['cards'];
        $this->deleteAllowed = $preview['can_delete'];
        $this->deleteTargets = $preview['targets'];
        $this->deleteTargetId = array_key_first($preview['targets']);
        $this->showColumnModal = false;
        $this->showDeleteModal = true;
    }

    public function destroyColumn(): void
    {
        if ($this->deleteId === null) {
            $this->closeModals();

            return;
        }

        $name = $this->deleteName;

        try {
            $moved = app(BoardColumnService::class)->delete(
                $this->deleteId,
                $this->deleteCards > 0 ? $this->deleteTargetId : null,
            );
        } catch (BoardColumnException $e) {
            Flux::toast(variant: 'danger', heading: 'Column not deleted', text: $e->getMessage());

            return;
        }

        $text = $moved > 0
            ? "\"{$name}\" was deleted. {$moved} task card(s) were moved to \"".($this->deleteTargets[(int) $this->deleteTargetId] ?? 'the selected column').'".'
            : "\"{$name}\" was deleted.";

        Flux::toast(variant: 'success', heading: 'Column deleted', text: $text);

        $this->closeModals();
    }

    public function closeModals(): void
    {
        $this->showColumnModal = false;
        $this->showDeleteModal = false;
        $this->deleteId = null;
        $this->deleteName = '';
        $this->deleteCards = 0;
        $this->deleteAllowed = true;
        $this->deleteTargets = [];
        $this->deleteTargetId = null;
        $this->columnForm->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.pmo.kanban-board');
    }
}
