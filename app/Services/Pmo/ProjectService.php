<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\Project;
use App\Support\TaskTable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Project Portfolio business logic. Every query is scoped to Auth::id().
 */
final class ProjectService
{
    public function __construct(private readonly WorkloadCalculator $workload) {}

    /**
     * @return Collection<int, Project>
     */
    public function list(?string $status = null): Collection
    {
        return Project::query()
            ->where('user_id', $this->ownerId())
            ->when(
                $status !== null && array_key_exists($status, Project::STATUSES),
                fn ($query) => $query->where('status', $status),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = Project::query()
            ->where('user_id', $this->ownerId())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = ['all' => (int) $counts->sum()];

        foreach (array_keys(Project::STATUSES) as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }

    public function find(int $id): Project
    {
        return Project::query()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id);
    }

    /**
     * @param  array{name: string, code: ?string, description: ?string, icon: string, color: string, status: string}  $data
     */
    public function create(array $data): Project
    {
        $ownerId = $this->ownerId();

        $nextOrder = (int) Project::query()->where('user_id', $ownerId)->max('sort_order') + 1;

        return Project::query()->create([
            ...$data,
            'user_id' => $ownerId,
            'sort_order' => $nextOrder,
        ]);
    }

    /**
     * Update a project and keep `tasks.project_name_snapshot` in sync on rename.
     *
     * @param  array{name: string, code: ?string, description: ?string, icon: string, color: string, status: string}  $data
     */
    public function update(int $id, array $data): Project
    {
        $project = $this->find($id);

        return DB::transaction(function () use ($project, $data): Project {
            $project->fill($data);
            $renamed = $project->isDirty('name');
            $project->save();

            if ($renamed && TaskTable::supports(['user_id', 'project_id', 'project_name_snapshot'])) {
                DB::table(TaskTable::NAME)
                    ->where('user_id', $project->user_id)
                    ->where('project_id', $project->id)
                    ->update(['project_name_snapshot' => $project->name]);
            }

            return $project;
        });
    }

    /**
     * @return array{name: string, open: int, total: int}
     */
    public function deletePreview(int $id): array
    {
        $project = $this->find($id);
        $stats = $this->workload->forProjects($project->user_id, [$project->id])[$project->id];

        return [
            'name' => $project->name,
            'open' => $stats['open'],
            'total' => $stats['open'] + $stats['done'],
        ];
    }

    /**
     * Delete a project while preserving its task cards (History Preservation SOP):
     * tasks keep `project_name_snapshot` and their `project_id` becomes NULL.
     *
     * @return int Number of task cards retained.
     */
    public function delete(int $id): int
    {
        $project = $this->find($id);

        return DB::transaction(function () use ($project): int {
            $retained = 0;

            if (TaskTable::supports(['user_id', 'project_id', 'project_name_snapshot'])) {
                $retained = DB::table(TaskTable::NAME)
                    ->where('user_id', $project->user_id)
                    ->where('project_id', $project->id)
                    ->update([
                        'project_name_snapshot' => $project->name,
                        'project_id' => null,
                    ]);
            }

            $project->delete();

            return $retained;
        });
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
