<?php

declare(strict_types=1);

namespace Tests\Feature\Pmo;

use App\Livewire\Pmo\AnalyticsWidgets;
use App\Livewire\Pmo\KanbanBoard;
use App\Livewire\Pmo\RosterProjectIndex;
use App\Livewire\Pmo\StandupModal;
use App\Models\ActivityLog;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Pmo\ActivityLogger;
use App\Services\Pmo\PmoAnalyticsService;
use App\Services\Pmo\StandupGeneratorService;
use App\Services\Pmo\TaskFilters;
use App\Support\TaskTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsAndStandupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var array<string, BoardColumn> */
    private array $cols = [];

    protected function setUp(): void
    {
        parent::setUp();
        TaskTable::flush();

        $this->user = User::factory()->create(['name' => 'Account Owner']);
        $this->actingAs($this->user);
        Livewire::test(KanbanBoard::class);

        $this->cols = BoardColumn::query()->where('user_id', $this->user->id)->get()->keyBy('name')->all();
    }

    public function test_board_renders_widgets_and_standup_components(): void
    {
        $this->get(route('pmo.board'))
            ->assertOk()
            ->assertSeeLivewire(AnalyticsWidgets::class)
            ->assertSeeLivewire(StandupModal::class)
            ->assertSee('Daily Standup Report')
            ->assertSee('Task Summary')
            ->assertSee('Completion Rate')
            ->assertSee('Tasks by Priority')
            ->assertSee('Recent Activity')
            ->assertSee('Team Workload');
    }

    public function test_board_actions_are_logged_automatically(): void
    {
        TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Ikhwan A.', 'is_me' => true]);
        $afqah = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Afqah N.']);

        $board = Livewire::test(KanbanBoard::class)
            ->call('createTask', $this->cols['To Do']->id)
            ->set('taskForm.title', 'FPX payment integration')
            ->set('taskForm.team_member_id', (string) $afqah->id)
            ->set('taskForm.newSubtask', 'Sandbox')
            ->call('addSubtaskRow')
            ->call('saveTask')
            ->assertDispatched('pmo-board-changed');

        $task = Task::query()->where('user_id', $this->user->id)->with('subtasks')->sole();

        $board->call('toggleSubtask', $task->subtasks->first()?->id)
            ->call('moveCard', $task->id, $this->cols['Done']->id, 0)
            ->call('createColumn')
            ->set('columnForm.name', 'UAT')
            ->call('saveColumn')
            ->call('confirmDeleteTask', $task->id)
            ->call('destroyTask');

        Livewire::test(RosterProjectIndex::class)
            ->call('createMember')
            ->set('memberForm.name', 'Haziq M.')
            ->set('memberForm.role', 'QA / Tester')
            ->call('saveMember');

        $logs = ActivityLog::query()->where('user_id', $this->user->id)->orderBy('id')->get();

        $this->assertSame(
            ['created', 'updated', 'moved', 'col_added', 'deleted', 'pic_added'],
            $logs->pluck('action_type')->all(),
        );
        $this->assertSame(['Ikhwan A.'], $logs->pluck('actor_name')->unique()->values()->all(), 'Actor is the PIC marked as Me');
        $this->assertSame('To Do → Done', $logs->firstWhere('action_type', 'moved')?->meta_details);
        $this->assertSame('Subtask completed: Sandbox', $logs->firstWhere('action_type', 'updated')?->meta_details);
    }

    public function test_actor_falls_back_to_account_name(): void
    {
        Livewire::test(KanbanBoard::class)
            ->call('createTask')
            ->set('taskForm.title', 'Solo task')
            ->call('saveTask');

        $this->assertSame('Account Owner', ActivityLog::query()->where('user_id', $this->user->id)->value('actor_name'));
    }

    public function test_widget_calculations(): void
    {
        $threshold = (int) config('pmo.roster.overload_threshold');
        $me = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Ikhwan A.', 'is_me' => true]);
        $idle = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Farah L.']);
        TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Inactive Idle', 'is_active' => false]);

        for ($i = 0; $i < $threshold; $i++) {
            $this->task("Busy {$i}", 'In Progress', $i === 0 ? 'high' : 'medium', $me);
        }
        $this->task('Low one', 'Backlog', 'low');
        $this->task('Late one', 'To Do', 'high', due: Carbon::parse(Task::today())->subDay()->toDateString(), snapshot: 'Gone PIC');
        $this->task('Shipped', 'Done', 'done', $me);
        $this->task('Done by priority', 'Review', 'done');

        $w = app(PmoAnalyticsService::class)->widgets(new TaskFilters);
        $total = $threshold + 4;

        $this->assertSame(['total' => $total, 'open' => $threshold + 2, 'high' => 2, 'medium' => $threshold - 1, 'low' => 1, 'done' => 2], $w['summary']);
        $this->assertSame(['done' => 2, 'total' => $total, 'rate' => (int) round(2 / $total * 100)], $w['completion']);
        $this->assertSame([2, $threshold - 1, 1, 2], array_column($w['priorities'], 'count'));
        $this->assertSame(100, array_sum(array_column($w['priorities'], 'pct')) + (100 - array_sum(array_column($w['priorities'], 'pct'))));

        $rows = collect($w['workload']['rows'])->keyBy('name');
        $this->assertSame(['Ikhwan A.', 'Farah L.', 'Unassigned'], $rows->keys()->all(), 'Inactive members with no work are hidden');
        $this->assertSame($threshold, $rows['Ikhwan A.']['active']);
        $this->assertTrue($rows['Ikhwan A.']['overloaded']);
        $this->assertSame(100, $rows['Ikhwan A.']['pct']);
        $this->assertSame(0, $rows['Farah L.']['active']);
        $this->assertSame(2, $rows['Unassigned']['active']);
        $this->assertSame(1, $rows['Unassigned']['overdue']);
        $this->assertTrue($rows['Unassigned']['former'], 'Preserved PIC snapshot is flagged');
        $this->assertSame($idle->id, $rows['Farah L.']['id']);
    }

    public function test_widgets_react_to_filters_and_board_events(): void
    {
        $this->task('High A', 'To Do', 'high');
        $this->task('Low B', 'To Do', 'low');

        $widgets = Livewire::test(AnalyticsWidgets::class, ['filters' => (new TaskFilters(priority: 'high'))->toArray()]);
        $this->assertSame(1, $widgets->instance()->widgets['summary']['total']);
        $widgets->assertSee('Filtered view');

        $all = Livewire::test(AnalyticsWidgets::class, ['filters' => (new TaskFilters)->toArray()]);
        $this->assertSame(2, $all->instance()->widgets['summary']['total']);

        $this->task('High C', 'Review', 'high');
        $all->dispatch('pmo-board-changed');
        $this->assertSame(3, $all->instance()->widgets['summary']['total']);
    }

    public function test_recent_activity_shows_relative_time_and_limits_to_ten(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            ActivityLog::query()->create(['user_id' => $this->user->id, 'actor_name' => 'Bot', 'action_type' => 'created', 'task_title' => "Entry {$i}"]);
        }

        $component = Livewire::test(AnalyticsWidgets::class, ['filters' => []]);
        $this->assertCount(10, $component->instance()->widgets['recent']);
        $component->assertSee('Entry 12')->assertDontSee('Entry 2<')->assertSee('ago');
    }

    public function test_clear_activity_logs_with_confirmation(): void
    {
        $other = User::factory()->create();
        ActivityLog::query()->create(['user_id' => $other->id, 'actor_name' => 'X', 'action_type' => 'created', 'task_title' => 'Foreign']);

        ActivityLog::withoutTimestamps(function (): void {
            foreach ([40, 10, 1] as $days) {
                $at = Carbon::now()->subDays($days);
                (new ActivityLog)->forceFill(['user_id' => $this->user->id, 'actor_name' => 'A', 'action_type' => 'created', 'task_title' => "{$days}d", 'created_at' => $at, 'updated_at' => $at])->save();
            }
        });

        Livewire::test(AnalyticsWidgets::class, ['filters' => []])
            ->call('confirmClear')
            ->assertSet('showClearModal', true)
            ->assertSet('clearCount', 1)
            ->set('clearRange', '7')
            ->assertSet('clearCount', 2)
            ->call('clearLogs')
            ->assertSet('showClearModal', false);

        $this->assertSame(['1d'], ActivityLog::query()->where('user_id', $this->user->id)->pluck('task_title')->all());

        Livewire::test(AnalyticsWidgets::class, ['filters' => []])
            ->call('confirmClear')
            ->set('clearRange', 'all')
            ->call('clearLogs');

        $this->assertSame(0, ActivityLog::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(1, ActivityLog::query()->where('user_id', $other->id)->count(), 'Other tenants are untouched');
    }

    public function test_standup_report_format(): void
    {
        $me = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Ikhwan A.', 'is_me' => true]);
        $late = $this->task('FPX payment integration', 'In Progress', 'high', $me, due: Carbon::parse(Task::today())->subDays(2)->toDateString());
        $late->update(['notes' => 'Awaiting gateway credentials.']);
        $late->subtasks()->create(['title' => 'a', 'is_completed' => true]);
        $late->subtasks()->create(['title' => 'b']);
        $this->task('Kick-off', 'Done', 'done', $me);
        app(ActivityLogger::class)->addNote('UAT moved to Thursday');

        $chat = app(StandupGeneratorService::class)->generate();

        $this->assertStringContainsString('*Daily Standup Report*', $chat);
        $this->assertStringContainsString('Prepared by: Ikhwan A.', $chat);
        $this->assertStringContainsString('Summary: 2 tasks · 1 done (50%) · 1 overdue', $chat);
        $this->assertStringContainsString('*In Progress* (1)', $chat);
        $this->assertStringContainsString('• [OVERDUE] FPX payment integration (High) — Ikhwan A. · Due ', $chat);
        $this->assertStringContainsString('1/2 subtasks', $chat);
        $this->assertStringContainsString('↳ Blocker / Notes: Awaiting gateway credentials.', $chat);
        $this->assertStringContainsString('*Backlog* (0)'."\n".'• No cards', $chat);
        $this->assertStringContainsString('• UAT moved to Thursday — Ikhwan A.', $chat);

        $plain = app(StandupGeneratorService::class)->generate(null, StandupGeneratorService::FORMAT_PLAIN, includeNotes: false);
        $this->assertStringNotContainsString('*', $plain);
        $this->assertStringContainsString('DAILY STANDUP REPORT', $plain);
        $this->assertStringNotContainsString('UAT moved to Thursday', $plain);

        $scoped = app(StandupGeneratorService::class)->generate(new TaskFilters(view: 'completed'));
        $this->assertStringContainsString('Scope: Completed', $scoped);
        $this->assertStringNotContainsString('FPX payment integration', $scoped);
    }

    public function test_standup_modal_notes_crud(): void
    {
        $modal = Livewire::test(StandupModal::class, ['filters' => []])
            ->dispatch('open-standup')
            ->assertSet('showStandupModal', true)
            ->assertSee('Copy to Clipboard')
            ->set('noteText', '')
            ->call('addNote')
            ->assertHasErrors(['noteText' => 'required'])
            ->set('noteText', 'Server maintenance tonight')
            ->call('addNote')
            ->assertHasNoErrors()
            ->assertDispatched('pmo-board-changed')
            ->assertSee('Server maintenance tonight');

        $this->assertStringContainsString('Server maintenance tonight', $modal->instance()->report);

        $note = ActivityLog::query()->where('user_id', $this->user->id)->where('action_type', 'note')->sole();

        $modal->call('editNote', $note->id)
            ->assertSet('editingText', 'Server maintenance tonight')
            ->set('editingText', 'Server maintenance moved to Friday')
            ->call('saveNote')
            ->assertSet('editingNoteId', null);

        $this->assertSame('Server maintenance moved to Friday', $note->fresh()?->task_title);

        $modal->call('confirmDeleteNote', $note->id)
            ->assertSet('showNoteDeleteModal', true)
            ->assertSet('showStandupModal', true)
            ->call('destroyNote')
            ->assertSet('showNoteDeleteModal', false);

        $this->assertModelMissing($note);
    }

    public function test_notes_and_auto_logs_are_tenant_isolated(): void
    {
        $note = app(ActivityLogger::class)->addNote('Owner private note');
        $auto = ActivityLog::query()->create(['user_id' => $this->user->id, 'actor_name' => 'A', 'action_type' => 'created', 'task_title' => 'Owner audit entry']);

        $this->actingAs(User::factory()->create());

        Livewire::test(StandupModal::class, ['filters' => []])
            ->dispatch('open-standup')
            ->assertDontSee('Owner private note')
            ->call('editNote', $note->id)
            ->assertStatus(404);

        Livewire::test(StandupModal::class, ['filters' => []])->call('confirmDeleteNote', $note->id)->assertStatus(404);

        Livewire::test(AnalyticsWidgets::class, ['filters' => []])
            ->assertDontSee('Owner audit entry')
            ->call('confirmClear')
            ->set('clearRange', 'all')
            ->call('clearLogs');

        $this->assertModelExists($note);
        $this->assertModelExists($auto);

        // Auto-generated entries are never editable as notes.
        $this->actingAs($this->user);
        Livewire::test(StandupModal::class, ['filters' => []])->call('editNote', $auto->id)->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────

    private function task(string $title, string $column, string $priority = 'medium', ?TeamMember $member = null, ?string $due = null, ?string $snapshot = null): Task
    {
        $col = $this->cols[$column];

        return Task::query()->create([
            'user_id' => $this->user->id,
            'board_column_id' => $col->id,
            'title' => $title,
            'priority' => $col->is_done_column ? 'done' : $priority,
            'team_member_id' => $member?->id,
            'pic_name_snapshot' => $member->name ?? $snapshot,
            'due_date' => $due,
            'sort_order' => (int) Task::query()->where('board_column_id', $col->id)->max('sort_order') + 10,
        ]);
    }
}
