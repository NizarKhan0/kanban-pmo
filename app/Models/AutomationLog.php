<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit record for every AI call and notification dispatch.
 *
 * @property int $id
 * @property int $user_id
 * @property string $channel
 * @property string $command_name
 * @property string|null $recipient
 * @property string $message
 * @property string|null $response_payload
 * @property int $tokens_used
 * @property string $status
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'channel', 'command_name', 'recipient', 'message', 'response_payload', 'tokens_used', 'status', 'error_message'])]
class AutomationLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_SIMULATED = 'simulated';

    public const STATUS_FAILED = 'failed';

    /** @var array<string, array{label: string, icon: string, group: string}> */
    public const CHANNELS = [
        'ai_claude' => ['label' => 'AI · Claude', 'icon' => '🤖', 'group' => 'ai'],
        'ai_openai' => ['label' => 'AI · OpenAI', 'icon' => '🤖', 'group' => 'ai'],
        'ai_custom' => ['label' => 'AI · Custom', 'icon' => '🤖', 'group' => 'ai'],
        'telegram' => ['label' => 'Telegram', 'icon' => '✈️', 'group' => 'telegram'],
        'gmail' => ['label' => 'Gmail', 'icon' => '✉️', 'group' => 'gmail'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => '💬', 'group' => 'whatsapp'],
    ];

    /** @var array<string, string> Audit-table filter groups. */
    public const CHANNEL_GROUPS = [
        'all' => 'All',
        'ai' => 'AI',
        'telegram' => 'Telegram',
        'gmail' => 'Gmail',
        'whatsapp' => 'WhatsApp',
    ];

    /** @var array<string, array{label: string, badge: string}> */
    public const STATUSES = [
        self::STATUS_SENT => ['label' => 'Sent', 'badge' => 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300'],
        self::STATUS_SIMULATED => ['label' => 'Simulated', 'badge' => 'bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300'],
        self::STATUS_FAILED => ['label' => 'Failed', 'badge' => 'bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300'],
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'tokens_used' => 0,
        'status' => self::STATUS_SIMULATED,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tokens_used' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public static function channelsInGroup(string $group): array
    {
        return array_keys(array_filter(self::CHANNELS, fn (array $c): bool => $c['group'] === $group));
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel]['label'] ?? $this->channel;
    }

    public function channelIcon(): string
    {
        return self::CHANNELS[$this->channel]['icon'] ?? '•';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? ucfirst($this->status);
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status]['badge'] ?? self::STATUSES[self::STATUS_SIMULATED]['badge'];
    }
}
