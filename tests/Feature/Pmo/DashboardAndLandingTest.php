<?php

declare(strict_types=1);

namespace Tests\Feature\Pmo;

use App\Livewire\Pmo\Dashboard;
use App\Livewire\Pmo\KanbanBoard;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\TaskTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAndLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TaskTable::flush();
    }

    // ── Landing page ─────────────────────────────────────────────────

    public function test_guests_see_the_kanban_landing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kanban PMO — Project Command Center', false)
            ->assertSee('one Kanban board.')
            ->assertSee('Features')
            ->assertSee('Get Started Free')
            ->assertSee('Log in')
            ->assertDontSee('Explore Features')
            ->assertDontSee('I already have an account')
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }

    public function test_signed_in_users_are_offered_the_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('dashboard'), false)
            ->assertDontSee('Get Started Free')
            ->assertDontSee('Log in');

        // No redundant actions: the dashboard link appears once.
        $this->assertSame(1, substr_count((string) $response->getContent(), 'href="'.route('dashboard').'"'));
    }

    public function test_the_original_welcome_view_is_kept(): void
    {
        $this->assertTrue(view()->exists('welcome'));
    }

    // ── Dashboard ────────────────────────────────────────────────────

    public function test_dashboard_renders_live_workspace_data(): void
    {
        $user = User::factory()->create(['name' => 'Account Owner']);
        $this->actingAs($user);
        Livewire::test(KanbanBoard::class);
        $cols = BoardColumn::query()->where('user_id', $user->id)->get()->keyBy('name');

        $me = TeamMember::query()->create(['user_id' => $user->id, 'name' => 'Ikhwan A.', 'is_me' => true]);
        $project = Project::query()->create(['user_id' => $user->id, 'name' => 'eMyHaji 2.0']);
        $today = Carbon::parse(Task::today());

        $late = Task::query()->create(['user_id' => $user->id, 'board_column_id' => $cols['In Progress']->id, 'title' => 'FPX payment integration', 'priority' => 'high', 'team_member_id' => $me->id, 'pic_name_snapshot' => 'Ikhwan A.', 'project_id' => $project->id, 'project_name_snapshot' => 'eMyHaji 2.0', 'due_date' => $today->copy()->subDay()->toDateString(), 'sort_order' => 10]);
        Task::query()->create(['user_id' => $user->id, 'board_column_id' => $cols['To Do']->id, 'title' => 'Draft SRS', 'priority' => 'medium', 'team_member_id' => $me->id, 'pic_name_snapshot' => 'Ikhwan A.', 'due_date' => $today->copy()->addDays(3)->toDateString(), 'sort_order' => 10]);
        Task::query()->create(['user_id' => $user->id, 'board_column_id' => $cols['Done']->id, 'title' => 'Kick-off meeting', 'priority' => 'done', 'project_id' => $project->id, 'project_name_snapshot' => 'eMyHaji 2.0', 'sort_order' => 10]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class)
            ->assertSee('Ikhwan A.')
            ->assertSee('My Focus')
            ->assertSee('Upcoming Deadlines')
            ->assertSee('Project Portfolio')
            ->assertSee('Team Workload')
            ->assertSee('Automation Channels')
            ->assertSee('1 overdue card');

        $dashboard = Livewire::test(Dashboard::class)->instance();
        $this->assertSame(['open' => 2, 'overdue' => 1, 'due_week' => 1, 'completed' => 1], $dashboard->kpis);
        $this->assertSame(['FPX payment integration', 'Draft SRS'], $dashboard->focus->pluck('title')->all(), 'Overdue first, then by due date');
        $this->assertSame(['FPX payment integration', 'Draft SRS'], $dashboard->deadlines->pluck('title')->all());
        $this->assertSame(1, $dashboard->activeProjects);
        $this->assertSame(['open' => 1, 'done' => 1, 'overdue' => 1, 'pct' => 50], array_diff_key($dashboard->portfolio[0], ['project' => 1]));
        $this->assertSame([0, 1, 1, 0, 1], array_column($dashboard->flow, 'count'));
        $this->assertSame($late->id, $dashboard->focus->first()?->id);
    }

    public function test_dashboard_empty_state(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('Your board is clear.')
            ->assertSee('Team Focus')
            ->assertSee('No open cards.');
    }

    public function test_dashboard_is_tenant_isolated(): void
    {
        $other = User::factory()->create();
        $col = BoardColumn::query()->create(['user_id' => $other->id, 'name' => 'Theirs']);
        Task::query()->create(['user_id' => $other->id, 'board_column_id' => $col->id, 'title' => 'Other workspace secret', 'priority' => 'high', 'due_date' => now()->subDay()->toDateString()]);
        Project::query()->create(['user_id' => $other->id, 'name' => 'Other project']);

        $this->actingAs(User::factory()->create());

        $dashboard = Livewire::test(Dashboard::class)
            ->assertDontSee('Other workspace secret')
            ->assertDontSee('Other project');

        $this->assertSame(0, $dashboard->instance()->kpis['open']);
    }
}
