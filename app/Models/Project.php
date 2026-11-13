<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property string $icon
 * @property string $color
 * @property string $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'code', 'description', 'icon', 'color', 'status', 'sort_order'])]
class Project extends Model
{
    /** @var array<string, string> */
    public const STATUSES = [
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ];

    /** @var array<string, string> */
    public const STATUS_BADGES = [
        'active' => 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
        'on_hold' => 'bg-amber-500/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
        'completed' => 'bg-[#8b5cf6]/15 text-violet-700 ring-[#8b5cf6]/30 dark:text-violet-300',
        'archived' => 'bg-zinc-500/15 text-zinc-600 ring-zinc-500/30 dark:text-zinc-400',
    ];

    /** @var list<string> */
    public const ICONS = ['📁', '🚀', '🕋', '🔬', '🏛️', '🏘️', '📊', '🤝', '💡', '🛡️', '🧩', '⚙️'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'icon' => '📁',
        'color' => 'violet',
        'status' => 'active',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? self::STATUS_BADGES['archived'];
    }
}
