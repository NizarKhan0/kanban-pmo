<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TaskTable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A Kanban board column (workflow stage).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $icon
 * @property string $color Hex colour, e.g. "#38bdf8".
 * @property int $wip_limit 0 = no limit.
 * @property bool $is_done_column
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'icon', 'color', 'wip_limit', 'is_done_column', 'sort_order'])]
class BoardColumn extends Model
{
    /** Gap between consecutive sort_order values (10, 20, 30...). */
    public const SORT_STEP = 10;

    /** @var list<string> */
    public const COLOR_PRESETS = [
        '#a78bfa', '#60a5fa', '#38bdf8', '#c084fc', '#34d399',
        '#ff2e9a', '#8b5cf6', '#fbbf24', '#f87171', '#94a3b8',
    ];

    /** @var list<string> */
    public const ICONS = ['📋', '🗂️', '📝', '⚡', '🔍', '✅', '🚧', '🧪', '🚀', '⏸️', '📦', '🎯'];

    /**
     * Standard PMO workflow used by the seeder and for first-time boards.
     *
     * @var list<array{name: string, icon: string, color: string, wip_limit: int, is_done_column: bool}>
     */
    public const DEFAULTS = [
        ['name' => 'Backlog', 'icon' => '🗂️', 'color' => '#a78bfa', 'wip_limit' => 0, 'is_done_column' => false],
        ['name' => 'To Do', 'icon' => '📝', 'color' => '#60a5fa', 'wip_limit' => 0, 'is_done_column' => false],
        ['name' => 'In Progress', 'icon' => '⚡', 'color' => '#38bdf8', 'wip_limit' => 7, 'is_done_column' => false],
        ['name' => 'Review', 'icon' => '🔍', 'color' => '#c084fc', 'wip_limit' => 0, 'is_done_column' => false],
        ['name' => 'Done', 'icon' => '✅', 'color' => '#34d399', 'wip_limit' => 0, 'is_done_column' => true],
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'icon' => '📋',
        'color' => '#38bdf8',
        'wip_limit' => 0,
        'is_done_column' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'wip_limit' => 'integer',
            'is_done_column' => 'boolean',
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

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Number of task cards currently in this column (0 until the Tasks module exists).
     */
    public function cardCount(): int
    {
        if (! TaskTable::supports(['user_id', 'board_column_id'])) {
            return 0;
        }

        return DB::table(TaskTable::NAME)
            ->where('user_id', $this->user_id)
            ->where('board_column_id', $this->id)
            ->count();
    }

    /**
     * True when a WIP limit is set and the card count exceeds it.
     *
     * @param  int|null  $cardCount  Pre-computed count (avoids a query per column on the board).
     */
    public function isOverWip(?int $cardCount = null): bool
    {
        return $this->wip_limit > 0 && ($cardCount ?? $this->cardCount()) > $this->wip_limit;
    }

    public function hasWipLimit(): bool
    {
        return $this->wip_limit > 0;
    }
}
