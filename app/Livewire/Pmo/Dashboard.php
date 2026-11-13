<?php

declare(strict_types=1);

namespace App\Livewire\Pmo;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TeamMember;
use App\Services\Pmo\BoardColumnService;
use App\Services\Pmo\OmnichannelNotifierService;
use App\Services\Pmo\PmoAnalyticsService;
use App\Services\Pmo\TaskFilters;
use App\Services\Pmo\TaskService;
use App\Services\Pmo\WorkloadCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * PMO command-center overview. Read-only: every figure is scoped to Auth::id()
 * through the module services; actions link to the module pages.
 *
 * @property-read array{open: int, overdue: int, due_week: int, completed: int} $kpis
 * @property-read array<string, mixed> $analytics
 * @property-read BaseCollection<int, Task> $focus
 * @property-read Collection<int, Task> $deadlines
 * @property-read list<array{column: BoardColumn, count: int, pct: int, over: bool}> $flow
 * @property-read list<array{project: Project, open: int, done: int, overdue: int, pct: int}> $portfolio
 * @property-read array<string, array{label: string, icon: string, connected: bool, detail: string, missing: list<string>}> $channels
 * @property-read int $activeProjects
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[On('pmo-workspace-replaced')]
    public function refreshDashboard(): void
    {
        // Computed properties are rebuilt on the next render.
    }

    /**
     * @return array{open: int, overdue: int, due_week: int, completed: int}
     */
    #[Computed]
    public function kpis(): array
    {
        return app(TaskService::class)->kpis();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function analytics(): array
    {
        return app(PmoAnalyticsService::class)->widgets(new TaskFilters);
    }

    /**
     * "My Focus": the Me-PIC's open cards (or everyone's when no Me is set),
     * overdue first, then by due date and priority.
     *
     * @return BaseCollection<int, Task>
     */
    #[Computed]
    public function focus(): BaseCollection
    {
        $service = app(TaskService::class);
        $view = $service->meId() === null ? 'all' : 'my';

        return $this->rank(
            $service->filteredQuery(new TaskFilters(view: $view))
                ->with(['column', 'project', 'teamMember', 'subtasks'])
                ->get()
                ->reject(fn (Task $t): bool => $t->isCompleted())
        )->take(5)->values();
    }

    /**
     * Open cards that are overdue or due within 7 days, soonest first.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function deadlines(): Collection
    {
        return app(TaskService::class)
            ->filteredQuery(new TaskFilters(view: 'due'))
            ->with(['column', 'teamMember', 'project'])
            ->orderBy('due_date')
            ->limit(6)
            ->get();
    }

    /**
     * Cards per workflow stage, with WIP state.
     *
     * @return list<array{column: BoardColumn, count: int, pct: int, over: bool}>
     */
    #[Computed]
    public function flow(): array
    {
        $service = app(BoardColumnService::class);
        $columns = $service->list();
        $counts = $service->cardCounts($columns);
        $max = max(1, ...array_values($counts ?: [0]));

        return array_values($columns->map(fn (BoardColumn $c): array => [
            'column' => $c,
            'count' => $counts[$c->id] ?? 0,
            'pct' => (int) round(($counts[$c->id] ?? 0) / $max * 100),
            'over' => $c->isOverWip($counts[$c->id] ?? 0),
        ])->all());
    }

    /**
     * Active / on-hold projects with progress (done ÷ all cards).
     *
     * @return list<array{project: Project, open: int, done: int, overdue: int, pct: int}>
     */
    #[Computed]
    public function portfolio(): array
    {
        $owner = (int) Auth::id();
        $projects = Project::query()
            ->where('user_id', $owner)
            ->whereIn('status', ['active', 'on_hold'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(6)
            ->get();

        $stats = app(WorkloadCalculator::class)->forProjects($owner, array_values(array_map(intval(...), $projects->modelKeys())));

        return array_values($projects->map(function (Project $p) use ($stats): array {
            $s = $stats[$p->id] ?? ['open' => 0, 'done' => 0, 'overdue' => 0];
            $total = $s['open'] + $s['done'];

            return ['project' => $p, ...$s, 'pct' => $total === 0 ? 0 : (int) round($s['done'] / $total * 100)];
        })->all());
    }

    #[Computed]
    public function activeProjects(): int
    {
        return Project::query()->where('user_id', Auth::id())->where('status', 'active')->count();
    }

    /**
     * @return array<string, array{label: string, icon: string, connected: bool, detail: string, missing: list<string>}>
     */
    #[Computed]
    public function channels(): array
    {
        return app(OmnichannelNotifierService::class)->channelStatus();
    }

    public function render(): View
    {
        $tz = (string) config('pmo.timezone', 'Asia/Kuala_Lumpur');
        $hour = (int) Carbon::now($tz)->format('G');
        $me = app(TaskService::class)->meId();

        return view('livewire.pmo.dashboard', [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'name' => $me === null
                ? Str::before((string) Auth::user()?->name, ' ')
                : (string) TeamMember::query()->where('user_id', Auth::id())->whereKey($me)->value('name'),
            'today' => Carbon::now($tz)->format('l, j F Y'),
            'hasMe' => $me !== null,
        ]);
    }

    /**
     * @param  BaseCollection<int, Task>  $tasks
     * @return BaseCollection<int, Task>
     */
    private function rank(BaseCollection $tasks): BaseCollection
    {
        return $tasks->sortBy([
            fn (Task $a, Task $b): int => (int) $b->isOverdue() <=> (int) $a->isOverdue(),
            fn (Task $a, Task $b): int => ($a->due_date?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->due_date?->getTimestamp() ?? PHP_INT_MAX),
            fn (Task $a, Task $b): int => (['high' => 0, 'medium' => 1, 'low' => 2][$a->priority] ?? 3) <=> (['high' => 0, 'medium' => 1, 'low' => 2][$b->priority] ?? 3),
        ]);
    }
}
