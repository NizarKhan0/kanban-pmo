<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\TeamMember;
use App\Support\TaskTable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Team Roster & PIC business logic. Every query is scoped to Auth::id().
 */
final class TeamRosterService
{
    public function __construct(private readonly WorkloadCalculator $workload) {}

    /**
     * @return Collection<int, TeamMember>
     */
    public function list(): Collection
    {
        return TeamMember::query()
            ->where('user_id', $this->ownerId())
            ->orderByDesc('is_active')
            ->orderByDesc('is_me')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): TeamMember
    {
        return TeamMember::query()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id);
    }

    /**
     * @param  Collection<int, TeamMember>  $members
     * @return array<int, array{open: int, done: int, overdue: int}>
     */
    public function workloadFor(Collection $members): array
    {
        return $this->workload->forMembers(
            $this->ownerId(),
            array_values(array_map(intval(...), $members->modelKeys())),
        );
    }

    public function overloadThreshold(): int
    {
        return max(1, (int) config('pmo.roster.overload_threshold', 5));
    }

    /**
     * @param  array{open: int, done: int, overdue: int}  $stats
     */
    public function isOverloaded(TeamMember $member, array $stats): bool
    {
        return $member->is_active && $stats['open'] >= $this->overloadThreshold();
    }

    /**
     * @param  array{name: string, role: ?string, email: ?string, phone_whatsapp: ?string, icon: string, color: string, is_me: bool, is_active: bool}  $data
     */
    public function create(array $data): TeamMember
    {
        $ownerId = $this->ownerId();

        return DB::transaction(function () use ($ownerId, $data): TeamMember {
            $nextOrder = (int) TeamMember::query()->where('user_id', $ownerId)->max('sort_order') + 1;

            $member = TeamMember::query()->create([
                ...$data,
                'is_me' => false,
                'user_id' => $ownerId,
                'sort_order' => $nextOrder,
            ]);

            if ($data['is_me']) {
                $member->setAsMe();
            }

            return $member;
        });
    }

    /**
     * Update a PIC. On rename, `tasks.pic_name_snapshot` is synced to the new name.
     *
     * @param  array{name: string, role: ?string, email: ?string, phone_whatsapp: ?string, icon: string, color: string, is_me: bool, is_active: bool}  $data
     */
    public function update(int $id, array $data): TeamMember
    {
        $member = $this->find($id);

        return DB::transaction(function () use ($member, $data): TeamMember {
            $becomesMe = $data['is_me'] && ! $member->is_me;

            $member->fill([...$data, 'is_me' => $becomesMe ? false : $data['is_me']]);
            $renamed = $member->isDirty('name');
            $member->save();

            if ($becomesMe) {
                $member->setAsMe();
            }

            if ($renamed && TaskTable::supports(['user_id', 'team_member_id', 'pic_name_snapshot'])) {
                DB::table(TaskTable::NAME)
                    ->where('user_id', $member->user_id)
                    ->where('team_member_id', $member->id)
                    ->update(['pic_name_snapshot' => $member->name]);
            }

            return $member;
        });
    }

    public function setAsMe(int $id): TeamMember
    {
        $member = $this->find($id);
        $member->setAsMe();

        return $member;
    }

    /**
     * @return array{name: string, open: int, total: int}
     */
    public function deletePreview(int $id): array
    {
        $member = $this->find($id);
        $stats = $this->workload->forMembers($member->user_id, [$member->id])[$member->id];

        return [
            'name' => $member->name,
            'open' => $stats['open'],
            'total' => $stats['open'] + $stats['done'],
        ];
    }

    /**
     * Remove a PIC without deleting their task cards (PIC History Preservation SOP):
     * `pic_name_snapshot` keeps the PIC's name and `team_member_id` becomes NULL.
     *
     * @return int Number of task cards retained.
     */
    public function delete(int $id): int
    {
        $member = $this->find($id);

        return DB::transaction(function () use ($member): int {
            $retained = 0;

            if (TaskTable::supports(['user_id', 'team_member_id', 'pic_name_snapshot'])) {
                $retained = DB::table(TaskTable::NAME)
                    ->where('user_id', $member->user_id)
                    ->where('team_member_id', $member->id)
                    ->update([
                        'pic_name_snapshot' => $member->name,
                        'team_member_id' => null,
                    ]);
            }

            $member->delete();

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
