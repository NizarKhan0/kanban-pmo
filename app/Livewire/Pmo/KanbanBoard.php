<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Exceptions\Pmo\BoardColumnException;
use App\Livewire\Forms\Pmo\BoardColumnForm;
use App\Livewire\Forms\Pmo\TaskForm;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TeamMember;
use App\Services\Pmo\BoardColumnService;
use App\Services\Pmo\TaskFilters;
use App\Services\Pmo\TaskService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, BoardColumn> $columns
 * @property-read array<int, int> $cardCounts
 * @property-read TaskFilters $filters
 * @property-read array{view: string, search: string, project: string, assignee: string, priority: string, due: string, sort: string} $filterState
 * @property-read array<int, Collection<int, Task>> $board
 * @property-read array{open: int, overdue: int, due_week: int, completed: int, over_wip: int} $kpis
 * @property-read Collection<int, Project> $projectOptions
 * @property-read Collection<int, TeamMember> $memberOptions
 * @property-read ?int $meId
 */
#[Title('Kanban Board')]
class KanbanBoard extends Component
{
    public BoardColumnForm $columnForm;

    public TaskForm $taskForm;

    // ── Views & filters (reactive, shareable via URL) ────────────────
    #[Url(except: 'all')]
    public string $view = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $assignee = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: 'any')]
    public string $due = 'any';

    #[Url(except: 'manual')]
    public string $sort = 'manual';

    // ── Modals ───────────────────────────────────────────────────────
    public bool $showColumnModal = false;

    public bool $showDeleteModal = false;

    public bool $showTaskModal = false;

    public bool $showTaskDeleteModal = false;

    public bool $showSubtaskDeleteModal = false;

    // ── Column delete state ──────────────────────────────────────────
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

    // ── Task / subtask delete state ──────────────────────────────────
    #[Locked]
    public ?int $deleteTaskId = null;

    #[Locked]
    public string $deleteTaskTitle = '';

    #[Locked]
    public int $deleteTaskSubtasks = 0;

    #[Locked]
    public ?int $deleteSubtaskId = null;

    #[Locked]
    public string $deleteSubtaskTitle = '';

    /**
     * Re-render after Settings & Data Portability replaced the workspace.
     */
    #[On('pmo-workspace-replaced')]
    public function refreshWorkspace(): void
    {
        // Computed properties are rebuilt on the next render.
    }

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

    #[Computed]
    public function filters(): TaskFilters
    {
        return new TaskFilters(
            view: array_key_exists($this->view, TaskFilters::VIEWS) ? $this->view : 'all',
            search: mb_substr($this->search, 0, 100),
            project: $this->project,
            assignee: $this->assignee,
            priority: $this->priority,
            due: array_key_exists($this->due, TaskFilters::DUE) ? $this->due : 'any',
            sort: array_key_exists($this->sort, TaskFilters::SORTS) ? $this->sort : 'manual',
        );
    }

    /**
     * Serialisable filter state shared with the analytics + standup child components.
     *
     * @return array{view: string, search: string, project: string, assignee: string, priority: string, due: string, sort: string}
     */
    #[Computed]
    public function filterState(): array
    {
        return $this->filters->toArray();
    }

    /**
     * @return array<int, Collection<int, Task>>
     */
    #[Computed]
    public function board(): array
    {
        return app(TaskService::class)->board($this->filters);
    }

    /**
     * @return array{open: int, overdue: int, due_week: int, completed: int, over_wip: int}
     */
    #[Computed]
    public function kpis(): array
    {
        return [
            ...app(TaskService::class)->kpis(),
            'over_wip' => $this->columns->filter(fn (BoardColumn $c): bool => $c->isOverWip($this->cardCounts[$c->id] ?? 0))->count(),
        ];
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projectOptions(): Collection
    {
        return Project::query()
            ->where('user_id', Auth::id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    /**
     * @return Collection<int, TeamMember>
     */
    #[Computed]
    public function memberOptions(): Collection
    {
        return TeamMember::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('is_me')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_me', 'is_active']);
    }

    #[Computed]
    public function meId(): ?int
    {
        return app(TaskService::class)->meId();
    }

    // ──────────────────────────────────────────────────────────────────
    // Views & filters
    // ──────────────────────────────────────────────────────────────────

    public function setView(string $view): void
    {
        $this->view = array_key_exists($view, TaskFilters::VIEWS) ? $view : 'all';
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'project', 'assignee', 'priority', 'due', 'sort');
    }

    // ──────────────────────────────────────────────────────────────────
    // Columns: Create / Update / Reorder / Delete
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
        $this->boardChanged();
    }

    public function moveColumn(int $id, int $position): void
    {
        app(BoardColumnService::class)->moveColumn($id, $position);
        $this->boardChanged();
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
        $this->boardChanged();
    }

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
        $this->boardChanged();
    }

    // ──────────────────────────────────────────────────────────────────
    // Tasks: Create / Update / Move / Delete
    // ──────────────────────────────────────────────────────────────────

    public function createTask(?int $columnId = null): void
    {
        $column = $columnId === null
            ? $this->columns->first()
            : $this->columns->firstWhere('id', $columnId);

        $this->taskForm->startFor($column?->id);
        $this->showTaskModal = true;
    }

    public function editTask(int $id): void
    {
        $this->taskForm->fillFrom(app(TaskService::class)->find($id));
        $this->showTaskModal = true;
    }

    public function saveTask(): void
    {
        [$data, $subtasks] = $this->taskForm->payload();
        $service = app(TaskService::class);

        if ($this->taskForm->id !== null) {
            $task = $service->update($this->taskForm->id, $data, $subtasks);
            $heading = 'Task updated';
        } else {
            $task = $service->create($data, $subtasks);
            $heading = 'Task created';
        }

        $text = "\"{$task->title}\" has been saved.";
        if ($registered = $service->registered()) {
            $text .= ' Newly registered — '.implode(', ', $registered).'.';
        }

        Flux::toast(variant: 'success', heading: $heading, text: $text);

        $this->closeModals();
        $this->boardChanged();
    }

    /**
     * Drag-and-drop handler (Alpine x-sort). $position is the zero-based
     * index among the cards *visible* in the target column after the drop.
     */
    public function moveCard(int $taskId, int $columnId, int $position): void
    {
        if (! $this->filters->isManualSort()) {
            Flux::toast(variant: 'warning', heading: 'Reordering disabled', text: 'Switch the sort to "Manual Drag" to move cards.');

            return;
        }

        $service = app(TaskService::class);
        $wasCompleted = $service->find($taskId)->isCompleted();

        $visible = ($this->board[$columnId] ?? new Collection)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $taskId)
            ->values()
            ->all();

        array_splice($visible, max(0, min($position, count($visible))), 0, [$taskId]);

        $task = $service->syncCardMove($taskId, $columnId, $visible);

        if (! $wasCompleted && $task->isCompleted()) {
            Flux::toast(variant: 'success', heading: 'Task completed', text: "\"{$task->title}\" was marked as Done.");
        } elseif ($wasCompleted && ! $task->isCompleted()) {
            Flux::toast(heading: 'Task reopened', text: "\"{$task->title}\" is back in progress (priority reset to Medium).");
        }

        unset($this->board, $this->cardCounts, $this->kpis);
        $this->boardChanged();
    }

    public function confirmDeleteTask(int $id): void
    {
        $task = app(TaskService::class)->find($id);

        $this->deleteTaskId = $task->id;
        $this->deleteTaskTitle = $task->title;
        $this->deleteTaskSubtasks = $task->subtasks->count();
        $this->showTaskModal = false;
        $this->showTaskDeleteModal = true;
    }

    public function destroyTask(): void
    {
        if ($this->deleteTaskId !== null) {
            app(TaskService::class)->delete($this->deleteTaskId);
            Flux::toast(variant: 'success', heading: 'Task deleted', text: "\"{$this->deleteTaskTitle}\" was removed from the board.");
            $this->boardChanged();
        }

        $this->closeModals();
    }

    // ──────────────────────────────────────────────────────────────────
    // Subtasks
    // ──────────────────────────────────────────────────────────────────

    public function addSubtaskRow(): void
    {
        $this->taskForm->addSubtask();
    }

    public function removeSubtaskRow(int $index): void
    {
        $this->taskForm->removeRow($index);
    }

    public function toggleSubtask(int $id): void
    {
        $subtask = app(TaskService::class)->toggleSubtask($id);

        if ($this->taskForm->id === $subtask->task_id) {
            $this->taskForm->markSubtask($subtask->id, $subtask->is_completed);
        }

        unset($this->board);
        $this->boardChanged();
    }

    public function confirmDeleteSubtask(int $id): void
    {
        $subtask = app(TaskService::class)->findSubtask($id);

        $this->deleteSubtaskId = $subtask->id;
        $this->deleteSubtaskTitle = $subtask->title;
        $this->showSubtaskDeleteModal = true;
    }

    public function destroySubtask(): void
    {
        if ($this->deleteSubtaskId !== null) {
            $subtask = app(TaskService::class)->deleteSubtask($this->deleteSubtaskId);
            $this->taskForm->forgetSubtask($subtask->id);
            Flux::toast(variant: 'success', heading: 'Subtask deleted', text: "\"{$subtask->title}\" was removed.");
            $this->boardChanged();
        }

        $this->cancelSubtaskDelete();
    }

    public function cancelSubtaskDelete(): void
    {
        $this->showSubtaskDeleteModal = false;
        $this->deleteSubtaskId = null;
        $this->deleteSubtaskTitle = '';
    }

    // ──────────────────────────────────────────────────────────────────

    public function closeModals(): void
    {
        $this->showColumnModal = false;
        $this->showDeleteModal = false;
        $this->showTaskModal = false;
        $this->showTaskDeleteModal = false;
        $this->deleteId = null;
        $this->deleteName = '';
        $this->deleteCards = 0;
        $this->deleteAllowed = true;
        $this->deleteTargets = [];
        $this->deleteTargetId = null;
        $this->deleteTaskId = null;
        $this->deleteTaskTitle = '';
        $this->deleteTaskSubtasks = 0;
        $this->cancelSubtaskDelete();
        $this->columnForm->resetErrorBag();
        $this->taskForm->resetErrorBag();
    }

    /**
     * Tell the analytics widgets to recompute after any board mutation.
     */
    private function boardChanged(): void
    {
        $this->dispatch('pmo-board-changed');
    }

    public function render(): View
    {
        return view('livewire.pmo.kanban-board', [
            'views' => TaskFilters::VIEWS,
            'dueOptions' => TaskFilters::DUE,
            'sortOptions' => TaskFilters::SORTS,
            'priorities' => Task::PRIORITIES,
        ]);
    }
}
