<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit trail entry for board activity (and user-written standup notes).
 *
 * @property int $id
 * @property int $user_id
 * @property string $actor_name
 * @property string $action_type
 * @property string $task_title Subject of the action (task, column, PIC or note text).
 * @property string|null $meta_details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'actor_name', 'action_type', 'task_title', 'meta_details'])]
class ActivityLog extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const MOVED = 'moved';

    public const DELETED = 'deleted';

    public const COL_ADDED = 'col_added';

    public const PIC_ADDED = 'pic_added';

    /** User-written standup note (the only editable action type). */
    public const NOTE = 'note';

    /** @var array<string, array{label: string, icon: string, badge: string}> */
    public const ACTIONS = [
        self::CREATED => ['label' => 'Created', 'icon' => '✨', 'badge' => 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300'],
        self::UPDATED => ['label' => 'Updated', 'icon' => '✏️', 'badge' => 'bg-sky-500/15 text-sky-700 ring-sky-500/40 dark:text-sky-300'],
        self::MOVED => ['label' => 'Moved', 'icon' => '↔️', 'badge' => 'bg-[#8b5cf6]/15 text-violet-700 ring-[#8b5cf6]/40 dark:text-violet-300'],
        self::DELETED => ['label' => 'Deleted', 'icon' => '🗑', 'badge' => 'bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300'],
        self::COL_ADDED => ['label' => 'Column Added', 'icon' => '🧱', 'badge' => 'bg-cyan-500/15 text-cyan-700 ring-cyan-500/40 dark:text-cyan-300'],
        self::PIC_ADDED => ['label' => 'PIC Added', 'icon' => '👤', 'badge' => 'bg-[#ff2e9a]/15 text-[#c4106f] ring-[#ff2e9a]/40 dark:text-[#ff7ac0]'],
        self::NOTE => ['label' => 'Standup Note', 'icon' => '📝', 'badge' => 'bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300'],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action_type]['label'] ?? ucfirst(str_replace('_', ' ', $this->action_type));
    }

    public function actionIcon(): string
    {
        return self::ACTIONS[$this->action_type]['icon'] ?? '•';
    }

    public function actionBadge(): string
    {
        return self::ACTIONS[$this->action_type]['badge'] ?? self::ACTIONS[self::UPDATED]['badge'];
    }

    public function isNote(): bool
    {
        return $this->action_type === self::NOTE;
    }
}
