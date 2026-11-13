<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TeamMember;
use App\Support\NeoPalette;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Real-time analytics for the Kanban board. Every figure is computed from
 * the owner's tasks (Auth::id()) after the current view + filters are applied.
 *
 * @phpstan-type PriorityRow array{key: string, label: string, count: int, pct: int}
 * @phpstan-type WorkloadRow array{id: ?int, name: string, initials: string, swatch: string, active: int, overdue: int, pct: int, is_me: bool, overloaded: bool, former: bool}
 */
final class PmoAnalyticsService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * All five widgets in one pass over the filtered task set.
     *
     * @return array{
     *     summary: array{total: int, open: int, high: int, medium: int, low: int, done: int},
     *     completion: array{done: int, total: int, rate: int},
     *     priorities: list<PriorityRow>,
     *     workload: array{rows: list<WorkloadRow>, threshold: int, max: int},
     *     recent: Collection<int, ActivityLog>,
     * }
     */
    public function widgets(TaskFilters $filters): array
    {
        $tasks = $this->tasks->filteredQuery($filters)->with(['column', 'teamMember'])->get();

        return [
            'summary' => $this->taskSummary($tasks),
            'completion' => $this->completionRate($tasks),
            'priorities' => $this->tasksByPriority($tasks),
            'workload' => $this->teamWorkload($tasks),
            'recent' => $this->recentActivity(),
        ];
    }

    /**
     * Widget 1 — Task Summary: total plus open breakdown by priority.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array{total: int, open: int, high: int, medium: int, low: int, done: int}
     */
    public function taskSummary(Collection $tasks): array
    {
        $open = $tasks->reject(fn (Task $t): bool => $t->isCompleted());

        return [
            'total' => $tasks->count(),
            'open' => $open->count(),
            'high' => $open->where('priority', 'high')->count(),
            'medium' => $open->where('priority', 'medium')->count(),
            'low' => $open->where('priority', 'low')->count(),
            'done' => $tasks->count() - $open->count(),
        ];
    }

    /**
     * Widget 2 — Completion Rate (done / total) for the donut chart.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array{done: int, total: int, rate: int}
     */
    public function completionRate(Collection $tasks): array
    {
        $total = $tasks->count();
        $done = $tasks->filter(fn (Task $t): bool => $t->isCompleted())->count();

        return ['done' => $done, 'total' => $total, 'rate' => $total === 0 ? 0 : (int) round($done / $total * 100)];
    }

    /**
     * Widget 3 — Tasks by Priority (count + share), completed cards counted as Done.
     *
     * @param  Collection<int, Task>  $tasks
     * @return list<PriorityRow>
     */
    public function tasksByPriority(Collection $tasks): array
    {
        $total = $tasks->count();
        $counts = array_fill_keys(array_keys(Task::PRIORITIES), 0);

        foreach ($tasks as $task) {
            $key = $task->isCompleted() ? 'done' : $task->priority;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $rows = [];
        foreach (Task::PRIORITIES as $key => $label) {
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'count' => $counts[$key],
                'pct' => $total === 0 ? 0 : (int) round($counts[$key] / $total * 100),
            ];
        }

        return $rows;
    }

    /**
     * Widget 4 — Recent Activity (latest 10 log entries).
     *
     * @return Collection<int, ActivityLog>
     */
    public function recentActivity(): Collection
    {
        return $this->activity->recent(10);
    }

    /**
     * Widget 5 — Team Workload: active (open) tasks per PIC.
     * Every active roster member is listed (even at zero); unassigned cards
     * — including those whose PIC was removed — are grouped last.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array{rows: list<WorkloadRow>, threshold: int, max: int}
     */
    public function teamWorkload(Collection $tasks): array
    {
        $threshold = max(1, (int) config('pmo.roster.overload_threshold', 5));
        $open = $tasks->reject(fn (Task $t): bool => $t->isCompleted());

        $members = TeamMember::query()
            ->where('user_id', $this->ownerId())
            ->orderByDesc('is_me')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($members as $member) {
            $mine = $open->where('team_member_id', $member->id);

            if (! $member->is_active && $mine->isEmpty()) {
                continue;
            }

            $rows[] = [
                'id' => $member->id,
                'name' => $member->name,
                'initials' => $member->initials(),
                'swatch' => NeoPalette::swatch($member->color),
                'active' => $mine->count(),
                'overdue' => $mine->filter(fn (Task $t): bool => $t->isOverdue())->count(),
                'pct' => 0,
                'is_me' => $member->is_me,
                'overloaded' => $mine->count() >= $threshold,
                'former' => false,
            ];
        }

        $unassigned = $open->whereNull('team_member_id');
        if ($unassigned->isNotEmpty()) {
            $rows[] = [
                'id' => null,
                'name' => 'Unassigned',
                'initials' => '—',
                'swatch' => 'bg-zinc-400',
                'active' => $unassigned->count(),
                'overdue' => $unassigned->filter(fn (Task $t): bool => $t->isOverdue())->count(),
                'pct' => 0,
                'is_me' => false,
                'overloaded' => false,
                'former' => $unassigned->whereNotNull('pic_name_snapshot')->isNotEmpty(),
            ];
        }

        // Scale bars against the larger of the busiest PIC and the overload threshold,
        // so a full bar always means "at or beyond capacity".
        $max = max($threshold, ...array_map(fn (array $r): int => $r['active'], $rows ?: [['active' => 0]]));
        foreach ($rows as $i => $row) {
            $rows[$i]['pct'] = (int) round($row['active'] / $max * 100);
        }

        return ['rows' => $rows, 'threshold' => $threshold, 'max' => $max];
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
