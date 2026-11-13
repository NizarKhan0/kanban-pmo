<?php

declare(strict_types=1);

namespace Tests\Feature\Pmo;

use App\Livewire\Pmo\KanbanBoard;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Pmo\TaskFilters;
use App\Services\Pmo\TaskService;
use App\Support\TaskTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TasksAndSubtasksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var array<string, BoardColumn> */
    private array $cols = [];

    protected function setUp(): void
    {
        parent::setUp();
        TaskTable::flush();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Livewire::test(KanbanBoard::class); // provisions the 5 default columns

        $this->cols = BoardColumn::query()->where('user_id', $this->user->id)->get()->keyBy('name')->all();
    }

    public function test_create_task_with_subtasks_and_auto_registration(): void
    {
        Livewire::test(KanbanBoard::class)
            ->call('createTask', $this->cols['To Do']->id)
            ->assertSet('showTaskModal', true)
            ->assertSet('taskForm.board_column_id', $this->cols['To Do']->id)
            ->set('taskForm.title', 'FPX payment integration')
            ->set('taskForm.project_name', 'eMyHaji 2.0')
            ->set('taskForm.pic_name', 'Afqah N.')
            ->set('taskForm.priority', 'high')
            ->set('taskForm.tags', 'payment, #FPX, payment, ')
            ->set('taskForm.newSubtask', 'Register sandbox')
            ->call('addSubtaskRow')
            ->set('taskForm.newSubtask', 'Callback handling')
            ->call('addSubtaskRow')
            ->call('addSubtaskRow') // blank row is dropped on save
            ->set('taskForm.subtasks.0.is_completed', true)
            ->call('saveTask')
            ->assertHasNoErrors()
            ->assertSet('showTaskModal', false)
            ->assertSee('FPX payment integration')
            ->assertSee('1/2')
            ->assertSee('AN');

        $task = Task::query()->where('user_id', $this->user->id)->with('subtasks')->sole();
        $project = Project::query()->where('user_id', $this->user->id)->sole();
        $member = TeamMember::query()->where('user_id', $this->user->id)->sole();

        $this->assertSame('eMyHaji 2.0', $project->name);
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame('eMyHaji 2.0', $task->project_name_snapshot);
        $this->assertSame($member->id, $task->team_member_id);
        $this->assertSame('Afqah N.', $task->pic_name_snapshot);
        $this->assertSame(['payment', 'FPX'], $task->tags);
        $this->assertCount(2, $task->subtasks);
        $this->assertSame(50, $task->progressPercentage());

        // Existing names are linked case-insensitively, not duplicated.
        Livewire::test(KanbanBoard::class)
            ->call('createTask')
            ->set('taskForm.title', 'Second task')
            ->set('taskForm.project_name', 'EMYHAJI 2.0')
            ->set('taskForm.pic_name', 'afqah n.')
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertSame(1, Project::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(1, TeamMember::query()->where('user_id', $this->user->id)->count());
    }

    public function test_validation(): void
    {
        $foreignColumn = BoardColumn::query()->create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign']);

        Livewire::test(KanbanBoard::class)
            ->call('createTask')
            ->set('taskForm.title', '')
            ->set('taskForm.board_column_id', $foreignColumn->id)
            ->set('taskForm.priority', 'urgent')
            ->set('taskForm.due_date', 'tomorrow')
            ->call('saveTask')
            ->assertHasErrors([
                'taskForm.title' => 'required',
                'taskForm.board_column_id' => 'exists',
                'taskForm.priority' => 'in',
                'taskForm.due_date' => 'date_format',
            ]);

        $this->assertSame(0, Task::query()->count());
    }

    public function test_edit_task_updates_subtasks_and_done_sync(): void
    {
        $task = $this->makeTask('Prepare SRS', 'To Do', subtasks: ['Draft', 'Review']);
        [$draft] = $task->subtasks->all();

        Livewire::test(KanbanBoard::class)
            ->call('editTask', $task->id)
            ->assertSet('taskForm.title', 'Prepare SRS')
            ->assertCount('taskForm.subtasks', 2)
            ->set('taskForm.title', 'Prepare SRS document')
            ->set('taskForm.subtasks.0.title', 'Draft v2')
            ->set('taskForm.subtasks.0.is_completed', true)
            ->set('taskForm.newSubtask', 'Sign-off')
            ->call('addSubtaskRow')
            ->set('taskForm.board_column_id', $this->cols['Done']->id)
            ->set('taskForm.priority', 'high')
            ->call('saveTask')
            ->assertHasNoErrors();

        $task->refresh()->load('subtasks');
        $this->assertSame('Prepare SRS document', $task->title);
        $this->assertSame('done', $task->priority, 'Done column forces priority = done');
        $this->assertNotNull($task->completed_at);
        $this->assertSame(['Draft v2', 'Review', 'Sign-off'], $task->subtasks->pluck('title')->all());
        $this->assertTrue($task->subtasks->firstWhere('id', $draft->id)?->is_completed);

        // Moving out of Done via the form resets priority to medium.
        Livewire::test(KanbanBoard::class)
            ->call('editTask', $task->id)
            ->set('taskForm.board_column_id', $this->cols['Review']->id)
            ->call('saveTask');

        $task->refresh();
        $this->assertSame('medium', $task->priority);
        $this->assertNull($task->completed_at);
    }

    public function test_delete_task_with_confirmation(): void
    {
        $task = $this->makeTask('Throwaway', 'Backlog', subtasks: ['One', 'Two']);

        Livewire::test(KanbanBoard::class)
            ->call('editTask', $task->id)
            ->call('confirmDeleteTask', $task->id)
            ->assertSet('showTaskModal', false)
            ->assertSet('showTaskDeleteModal', true)
            ->assertSet('deleteTaskSubtasks', 2)
            ->assertSee('Delete Task')
            ->call('destroyTask')
            ->assertSet('showTaskDeleteModal', false);

        $this->assertModelMissing($task);
        $this->assertSame(0, Subtask::query()->count());
    }

    public function test_subtask_toggle_and_delete_with_confirmation(): void
    {
        $task = $this->makeTask('Checklist', 'To Do', subtasks: ['Alpha', 'Beta']);
        [$alpha, $beta] = $task->subtasks->all();

        $component = Livewire::test(KanbanBoard::class)
            ->call('toggleSubtask', $alpha->id);
        $this->assertTrue($alpha->fresh()?->is_completed);

        $component->call('editTask', $task->id)
            ->call('confirmDeleteSubtask', $beta->id)
            ->assertSet('showSubtaskDeleteModal', true)
            ->assertSet('showTaskModal', true) // stacked on top of the task modal
            ->assertSee('Delete Subtask')
            ->call('destroySubtask')
            ->assertSet('showSubtaskDeleteModal', false)
            ->assertSet('showTaskModal', true)
            ->assertCount('taskForm.subtasks', 1);

        $this->assertModelMissing($beta);

        // Unsaved rows are removed without touching the database.
        $component->set('taskForm.newSubtask', 'Gamma')
            ->call('addSubtaskRow')
            ->call('removeSubtaskRow', 1)
            ->assertCount('taskForm.subtasks', 1);
    }

    public function test_sync_card_move_keeps_hidden_cards_in_place(): void
    {
        $col = 'In Progress';
        $a = $this->makeTask('A', $col, priority: 'high');
        $b = $this->makeTask('B', $col, priority: 'low');
        $c = $this->makeTask('C', $col, priority: 'high');
        $d = $this->makeTask('D', $col, priority: 'low');

        // Filter shows only high-priority cards (A, C); drag C above A.
        Livewire::test(KanbanBoard::class)
            ->set('priority', 'high')
            ->call('moveCard', $c->id, $this->cols[$col]->id, 0);

        $this->assertSame(['C', 'B', 'A', 'D'], $this->titlesIn($col));
        $this->assertSame([10, 20, 30, 40], $this->sortOrdersIn($col));

        // Bring X from To Do in right after the first visible card (C).
        $x = $this->makeTask('X', 'To Do', priority: 'high');
        Livewire::test(KanbanBoard::class)
            ->set('priority', 'high')
            ->call('moveCard', $x->id, $this->cols[$col]->id, 1);

        $this->assertSame(['C', 'X', 'B', 'A', 'D'], $this->titlesIn($col));
        $this->assertSame($this->cols[$col]->id, $x->fresh()?->board_column_id);
    }

    public function test_drag_into_and_out_of_done_column(): void
    {
        $task = $this->makeTask('Ship it', 'Review', priority: 'high');

        Livewire::test(KanbanBoard::class)
            ->call('moveCard', $task->id, $this->cols['Done']->id, 0);

        $task->refresh();
        $this->assertSame('done', $task->priority);
        $this->assertNotNull($task->completed_at);

        app(TaskService::class)->syncCardMove($task->id, $this->cols['To Do']->id, [$task->id]);

        $task->refresh();
        $this->assertSame('medium', $task->priority);
        $this->assertNull($task->completed_at);
    }

    public function test_move_is_blocked_when_not_in_manual_sort(): void
    {
        $task = $this->makeTask('Stay', 'Backlog');

        Livewire::test(KanbanBoard::class)
            ->set('sort', 'az')
            ->call('moveCard', $task->id, $this->cols['Done']->id, 0);

        $this->assertSame($this->cols['Backlog']->id, $task->fresh()?->board_column_id);
    }

    public function test_overdue_and_progress_helpers(): void
    {
        $today = Carbon::parse(Task::today());

        $late = $this->makeTask('Late', 'In Progress', due: $today->copy()->subDay()->toDateString(), subtasks: ['a', 'b', 'c']);
        $late->subtasks->first()?->update(['is_completed' => true]);
        $doneLate = $this->makeTask('Done late', 'Done', due: $today->copy()->subDay()->toDateString());
        $future = $this->makeTask('Future', 'To Do', due: $today->copy()->addDay()->toDateString());

        $this->assertTrue($late->fresh(['column', 'subtasks'])?->isOverdue());
        $this->assertSame(33, $late->fresh(['subtasks'])?->progressPercentage());
        $this->assertFalse($doneLate->fresh(['column'])?->isOverdue());
        $this->assertFalse($future->fresh(['column'])?->isOverdue());
        $this->assertSame(0, $future->fresh(['subtasks'])?->progressPercentage());
    }

    public function test_filters_views_and_sorting(): void
    {
        $today = Carbon::parse(Task::today());
        $me = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Ikhwan A.', 'is_me' => true]);
        $other = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Haziq M.']);
        $project = Project::query()->create(['user_id' => $this->user->id, 'name' => 'ePJS']);

        $this->makeTask('Mine overdue', 'In Progress', due: $today->copy()->subDays(2)->toDateString(), member: $me, project: $project, tags: ['urgent']);
        $this->makeTask('Theirs today', 'To Do', due: $today->toDateString(), member: $other);
        $this->makeTask('Unassigned later', 'Backlog', due: $today->copy()->addDays(20)->toDateString());
        $this->makeTask('Closed', 'Done', member: $me);

        $visible = fn (array $state): array => $this->visibleTitles($state);

        $this->assertSame(['Mine overdue', 'Closed'], $visible(['view' => 'my']));
        $this->assertSame(['Theirs today', 'Mine overdue'], $visible(['view' => 'due']));
        $this->assertSame(['Closed'], $visible(['view' => 'completed']));
        $this->assertSame(['Mine overdue'], $visible(['search' => 'urgent']));
        $this->assertSame(['Mine overdue'], $visible(['project' => (string) $project->id]));
        $this->assertSame(['Unassigned later'], $visible(['assignee' => 'none']));
        $this->assertSame(['Mine overdue'], $visible(['due' => 'overdue']));
        $this->assertSame(['Theirs today'], $visible(['due' => 'today']));
        $this->assertSame(['Unassigned later', 'Theirs today'], $visible(['due' => 'month']));
        $this->assertSame(['Closed'], $visible(['due' => 'none']));

        $sorted = app(TaskService::class)->board(new TaskFilters(sort: 'az'));
        $this->assertSame(['Unassigned later'], $sorted[$this->cols['Backlog']->id]->pluck('title')->all());

        Livewire::test(KanbanBoard::class)
            ->set('search', 'zzz')
            ->set('due', 'today')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('due', 'any')
            ->assertSee('Unassigned later');
    }

    public function test_my_tasks_without_me_shows_hint(): void
    {
        $this->makeTask('Someone', 'To Do');

        Livewire::test(KanbanBoard::class)
            ->call('setView', 'my')
            ->assertSee('No team member is marked as')
            ->assertDontSee('Someone');
    }

    public function test_tenant_isolation_for_tasks_and_subtasks(): void
    {
        $task = $this->makeTask('Owner secret task', 'To Do', subtasks: ['Owner secret subtask']);
        $subtask = $task->subtasks->first();

        $this->actingAs(User::factory()->create());

        Livewire::test(KanbanBoard::class)
            ->assertDontSee('Owner secret task')
            ->call('editTask', $task->id)
            ->assertStatus(404);

        Livewire::test(KanbanBoard::class)->call('toggleSubtask', $subtask?->id)->assertStatus(404);
        Livewire::test(KanbanBoard::class)->call('confirmDeleteTask', $task->id)->assertStatus(404);
        Livewire::test(KanbanBoard::class)->call('moveCard', $task->id, $this->cols['Done']->id, 0)->assertStatus(404);

        $this->assertFalse($subtask?->fresh()?->is_completed);
        $this->assertSame($this->cols['To Do']->id, $task->fresh()?->board_column_id);
    }

    public function test_pic_removal_preserves_task_history(): void
    {
        $member = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Farah L.']);
        $task = $this->makeTask('Analysis', 'To Do', member: $member);

        $member->delete(); // DB-level nullOnDelete

        $task = $task->fresh(['teamMember', 'column']);
        $this->assertNull($task?->team_member_id);
        $this->assertSame('Farah L.', $task?->picLabel());
        $this->assertSame('FL', $task?->picInitials());

        Livewire::test(KanbanBoard::class)->assertSee('Former PIC: Farah L.');
    }

    // ──────────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $subtasks
     * @param  list<string>|null  $tags
     */
    private function makeTask(
        string $title,
        string $column,
        string $priority = 'medium',
        ?string $due = null,
        array $subtasks = [],
        ?TeamMember $member = null,
        ?Project $project = null,
        ?array $tags = null,
    ): Task {
        $columnModel = $this->cols[$column];
        $max = (int) Task::query()->where('board_column_id', $columnModel->id)->max('sort_order');

        $task = Task::query()->create([
            'user_id' => $this->user->id,
            'board_column_id' => $columnModel->id,
            'title' => $title,
            'priority' => $columnModel->is_done_column ? 'done' : $priority,
            'due_date' => $due,
            'team_member_id' => $member?->id,
            'pic_name_snapshot' => $member?->name,
            'project_id' => $project?->id,
            'project_name_snapshot' => $project?->name,
            'tags' => $tags,
            'sort_order' => $max + 10,
        ]);

        foreach ($subtasks as $i => $subtask) {
            $task->subtasks()->create(['title' => $subtask, 'sort_order' => ($i + 1) * 10]);
        }

        return $task->load(['subtasks', 'column']);
    }

    /**
     * @return list<string>
     */
    private function titlesIn(string $column): array
    {
        return Task::query()->where('board_column_id', $this->cols[$column]->id)->orderBy('sort_order')->pluck('title')->values()->all();
    }

    /**
     * @return list<int>
     */
    private function sortOrdersIn(string $column): array
    {
        return Task::query()->where('board_column_id', $this->cols[$column]->id)->orderBy('sort_order')->pluck('sort_order')->map(fn ($v): int => (int) $v)->values()->all();
    }

    /**
     * Titles visible on the board for the given component state, in column order.
     *
     * @param  array<string, string>  $state
     * @return list<string>
     */
    private function visibleTitles(array $state): array
    {
        $component = Livewire::test(KanbanBoard::class);
        foreach ($state as $key => $value) {
            $component->set($key, $value);
        }

        /** @var KanbanBoard $instance */
        $instance = $component->instance();
        $titles = [];

        foreach ($instance->columns as $column) {
            foreach ($instance->board[$column->id] ?? [] as $task) {
                $titles[] = $task->title;
            }
        }

        return $titles;
    }
}
