<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\ActivityLog;
use App\Models\TeamMember;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes and manages the activity audit trail. Every query is scoped to Auth::id().
 */
final class ActivityLogger
{
    /** @var array<int|string, string> Numeric keys are day counts. */
    public const CLEAR_RANGES = [
        '30' => 'Older than 30 days',
        '7' => 'Older than 7 days',
        'all' => 'All activity',
    ];

    private ?string $actorCache = null;

    /**
     * Record an automatic board event. Silently skipped when nobody is signed in
     * (e.g. seeders / console), so logging never breaks a business operation.
     */
    public function log(string $action, string $subject, ?string $meta = null): ?ActivityLog
    {
        if (Auth::id() === null) {
            return null;
        }

        return ActivityLog::query()->create([
            'user_id' => $this->ownerId(),
            'actor_name' => $this->actorName(),
            'action_type' => $action,
            'task_title' => Str::limit($subject, 252),
            'meta_details' => $meta === null ? null : Str::limit($meta, 252),
        ]);
    }

    /**
     * The "Me" PIC's name when set, otherwise the account name.
     */
    public function actorName(): string
    {
        return $this->actorCache ??= (string) (TeamMember::query()
            ->where('user_id', $this->ownerId())
            ->where('is_me', true)
            ->value('name') ?? Auth::user()->name ?? 'System');
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function recent(int $limit = 10): Collection
    {
        return ActivityLog::query()
            ->where('user_id', $this->ownerId())
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function total(): int
    {
        return ActivityLog::query()->where('user_id', $this->ownerId())->count();
    }

    // ── Standup notes (the only user-editable entries) ───────────────

    /**
     * Notes written today (PMO timezone), oldest first.
     *
     * @return Collection<int, ActivityLog>
     */
    public function todaysNotes(): Collection
    {
        [$start, $end] = $this->todayBounds();

        return $this->notes()
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function addNote(string $text): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => $this->ownerId(),
            'actor_name' => $this->actorName(),
            'action_type' => ActivityLog::NOTE,
            'task_title' => Str::limit(trim($text), 252),
            'meta_details' => 'Standup note',
        ]);
    }

    public function findNote(int $id): ActivityLog
    {
        return $this->notes()->findOrFail($id);
    }

    public function updateNote(int $id, string $text): ActivityLog
    {
        $note = $this->findNote($id);
        $note->update(['task_title' => Str::limit(trim($text), 252), 'meta_details' => 'Standup note (edited)']);

        return $note;
    }

    public function deleteNote(int $id): void
    {
        $this->findNote($id)->delete();
    }

    // ── Clear history ────────────────────────────────────────────────

    public function clearPreview(string $range): int
    {
        return $this->clearQuery($range)->count();
    }

    /**
     * @return int Number of entries removed.
     */
    public function clear(string $range): int
    {
        return $this->clearQuery($range)->delete();
    }

    /**
     * @return Builder<ActivityLog>
     */
    private function clearQuery(string $range): Builder
    {
        $query = ActivityLog::query()->where('user_id', $this->ownerId());

        if ($range !== 'all') {
            $days = array_key_exists($range, self::CLEAR_RANGES) ? (int) $range : 30;
            $query->where('created_at', '<', Carbon::now()->subDays($days));
        }

        return $query;
    }

    /**
     * @return Builder<ActivityLog>
     */
    private function notes(): Builder
    {
        return ActivityLog::query()
            ->where('user_id', $this->ownerId())
            ->where('action_type', ActivityLog::NOTE);
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Today's bounds in app time, computed in the PMO timezone.
     */
    private function todayBounds(): array
    {
        $tz = (string) config('pmo.timezone', 'Asia/Kuala_Lumpur');
        $start = Carbon::now($tz)->startOfDay();

        return [
            $start->copy()->setTimezone((string) config('app.timezone')),
            $start->copy()->endOfDay()->setTimezone((string) config('app.timezone')),
        ];
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
