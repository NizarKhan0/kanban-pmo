<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\AutomationLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes and reads the `automation_logs` audit trail. Every query is scoped to Auth::id().
 */
final class AutomationLogger
{
    /** @var array<int|string, string> Numeric keys are day counts. */
    public const CLEAR_RANGES = [
        '30' => 'Older than 30 days',
        '7' => 'Older than 7 days',
        'all' => 'All audit logs',
    ];

    public function record(
        string $channel,
        string $command,
        string $message,
        string $status,
        ?string $recipient = null,
        ?string $response = null,
        int $tokens = 0,
        ?string $error = null,
    ): AutomationLog {
        return AutomationLog::query()->create([
            'user_id' => $this->ownerId(),
            'channel' => $channel,
            'command_name' => Str::limit($command, 250),
            'recipient' => $recipient === null ? null : Str::limit($recipient, 250),
            'message' => Str::limit($message, 60000, "\n[…truncated for the audit log]"),
            'response_payload' => $response,
            'tokens_used' => max(0, $tokens),
            'status' => $status,
            'error_message' => $error === null ? null : Str::limit($error, 2000),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, AutomationLog>
     */
    public function paginate(string $group = 'all', string $status = 'all', int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($group, $status)
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return array{total: int, sent: int, simulated: int, failed: int, tokens: int}
     */
    public function stats(): array
    {
        $rows = AutomationLog::query()
            ->where('user_id', $this->ownerId())
            ->selectRaw('status, COUNT(*) as aggregate, COALESCE(SUM(tokens_used), 0) as tokens')
            ->groupBy('status')
            ->get();

        $count = fn (string $status): int => (int) ($rows->firstWhere('status', $status)->aggregate ?? 0);

        return [
            'total' => (int) $rows->sum('aggregate'),
            'sent' => $count(AutomationLog::STATUS_SENT),
            'simulated' => $count(AutomationLog::STATUS_SIMULATED),
            'failed' => $count(AutomationLog::STATUS_FAILED),
            'tokens' => (int) $rows->sum('tokens'),
        ];
    }

    public function clearPreview(string $range): int
    {
        return $this->clearQuery($range)->count();
    }

    public function clear(string $range): int
    {
        return $this->clearQuery($range)->delete();
    }

    /**
     * @return Builder<AutomationLog>
     */
    private function filtered(string $group, string $status): Builder
    {
        $query = AutomationLog::query()->where('user_id', $this->ownerId());

        if ($group !== 'all' && array_key_exists($group, AutomationLog::CHANNEL_GROUPS)) {
            $query->whereIn('channel', AutomationLog::channelsInGroup($group));
        }

        if (array_key_exists($status, AutomationLog::STATUSES)) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * @return Builder<AutomationLog>
     */
    private function clearQuery(string $range): Builder
    {
        $query = AutomationLog::query()->where('user_id', $this->ownerId());

        if ($range !== 'all') {
            $days = array_key_exists($range, self::CLEAR_RANGES) ? (int) $range : 30;
            $query->where('created_at', '<', Carbon::now()->subDays($days));
        }

        return $query;
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
