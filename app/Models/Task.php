<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kanban task card.
 *
 * @property int $id
 * @property int $user_id
 * @property int $board_column_id
 * @property int|null $project_id
 * @property int|null $team_member_id
 * @property string $title
 * @property string|null $project_name_snapshot
 * @property string|null $pic_name_snapshot
 * @property string $priority
 * @property string $icon
 * @property string $color
 * @property Carbon|null $due_date
 * @property list<string>|null $tags
 * @property string|null $notes
 * @property int $sort_order
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BoardColumn $column
 * @property-read Project|null $project
 * @property-read TeamMember|null $teamMember
 * @property-read Collection<int, Subtask> $subtasks
 */
#[Fillable([
    'user_id', 'board_column_id', 'project_id', 'team_member_id', 'title',
    'project_name_snapshot', 'pic_name_snapshot', 'priority', 'icon', 'color',
    'due_date', 'tags', 'notes', 'sort_order', 'completed_at',
])]
class Task extends Model
{
    /** Gap between consecutive sort_order values within a column. */
    public const SORT_STEP = 10;

    /** @var array<string, string> */
    public const PRIORITIES = [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        'done' => 'Done',
    ];

    /** @var array<string, string> */
    public const PRIORITY_BADGES = [
        'high' => 'bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300',
        'medium' => 'bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:text-amber-300',
        'low' => 'bg-sky-500/15 text-sky-700 ring-sky-500/40 dark:text-sky-300',
        'done' => 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300',
    ];

    /** @var list<string> */
    public const ICONS = ['📌', '🐞', '✨', '📄', '💳', '🧪', '🔐', '📊', '🛠️', '🚀', '📣', '🤝'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'priority' => 'medium',
        'icon' => '📌',
        'color' => 'violet',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'board_column_id' => 'integer',
            'project_id' => 'integer',
            'team_member_id' => 'integer',
            'sort_order' => 'integer',
            'due_date' => 'date',
            'tags' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Always store due_date as a plain Y-m-d string so equality / range
     * comparisons behave the same on every database driver.
     */
    public function setDueDateAttribute(mixed $value): void
    {
        $this->attributes['due_date'] = $value === null || $value === ''
            ? null
            : Carbon::parse($value)->toDateString();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BoardColumn, $this>
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<TeamMember, $this>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * @return HasMany<Subtask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Percentage (0-100) of completed subtasks; 0 when there are none.
     */
    public function progressPercentage(): int
    {
        [$done, $total] = $this->subtaskCounts();

        return $total === 0 ? 0 : (int) round($done / $total * 100);
    }

    /**
     * @return array{0: int, 1: int} [completed, total]
     */
    public function subtaskCounts(): array
    {
        $subtasks = $this->subtasks;

        return [$subtasks->where('is_completed', true)->count(), $subtasks->count()];
    }

    /**
     * Past due, not marked done, and not sitting in a Done column.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->toDateString() < self::today()
            && $this->priority !== 'done'
            && ! $this->column->is_done_column;
    }

    public function isDueToday(): bool
    {
        return $this->due_date !== null
            && $this->due_date->toDateString() === self::today()
            && $this->priority !== 'done'
            && ! $this->column->is_done_column;
    }

    public function isCompleted(): bool
    {
        return $this->priority === 'done' || $this->column->is_done_column;
    }

    /** Current project name, falling back to the preserved snapshot. */
    public function projectLabel(): ?string
    {
        return $this->project->name ?? $this->project_name_snapshot;
    }

    /** Current PIC name, falling back to the preserved snapshot. */
    public function picLabel(): ?string
    {
        return $this->teamMember->name ?? $this->pic_name_snapshot;
    }

    public function picInitials(): ?string
    {
        $name = $this->picLabel();

        return $name === null ? null : TeamMember::initialsFor($name);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst($this->priority);
    }

    public function priorityBadge(): string
    {
        return self::PRIORITY_BADGES[$this->priority] ?? self::PRIORITY_BADGES['medium'];
    }

    /** Today's date (Y-m-d) in the PMO business timezone. */
    public static function today(): string
    {
        return Carbon::now((string) config('pmo.timezone', 'Asia/Kuala_Lumpur'))->toDateString();
    }
}
