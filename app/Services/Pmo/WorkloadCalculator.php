<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Support\TaskTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aggregates open / done / overdue task counts per PIC or per project in a
 * single grouped query.
 *
 * Contract with the Tasks module (ARCHITECTURE.md §4.3):
 *  - a task is "done" when `priority = 'done'` (Auto-Done Priority Sync);
 *  - a task is "overdue" when it is not done and `due_date` is before today.
 */
final class WorkloadCalculator
{
    /**
     * @param  list<int>  $memberIds
     * @return array<int, array{open: int, done: int, overdue: int}>
     */
    public function forMembers(int $ownerId, array $memberIds): array
    {
        return $this->aggregate($ownerId, 'team_member_id', $memberIds);
    }

    /**
     * @param  list<int>  $projectIds
     * @return array<int, array{open: int, done: int, overdue: int}>
     */
    public function forProjects(int $ownerId, array $projectIds): array
    {
        return $this->aggregate($ownerId, 'project_id', $projectIds);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{open: int, done: int, overdue: int}>
     */
    private function aggregate(int $ownerId, string $foreignKey, array $ids): array
    {
        $empty = ['open' => 0, 'done' => 0, 'overdue' => 0];
        $result = array_fill_keys($ids, $empty);

        if ($ids === [] || ! TaskTable::supports(['user_id', $foreignKey, 'priority', 'due_date'])) {
            return $result;
        }

        $ownerKeySelect = match ($foreignKey) {
            'team_member_id' => 'team_member_id as owner_key',
            'project_id' => 'project_id as owner_key',
            default => throw new InvalidArgumentException("Unsupported workload key [{$foreignKey}]."),
        };

        $today = Carbon::now((string) config('pmo.timezone', 'Asia/Kuala_Lumpur'))->toDateString();

        $rows = DB::table(TaskTable::NAME)
            ->where('user_id', $ownerId)
            ->whereIn($foreignKey, $ids)
            ->groupBy($foreignKey)
            ->selectRaw($ownerKeySelect)
            ->selectRaw("SUM(CASE WHEN priority <> 'done' THEN 1 ELSE 0 END) as open_count")
            ->selectRaw("SUM(CASE WHEN priority = 'done' THEN 1 ELSE 0 END) as done_count")
            ->selectRaw(
                "SUM(CASE WHEN priority <> 'done' AND due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as overdue_count",
                [$today],
            )
            ->get();

        foreach ($rows as $row) {
            $result[(int) $row->owner_key] = [
                'open' => (int) $row->open_count,
                'done' => (int) $row->done_count,
                'overdue' => (int) $row->overdue_count,
            ];
        }

        return $result;
    }
}
