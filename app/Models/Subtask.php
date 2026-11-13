<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Checklist item belonging to a Task. Tenancy is inherited through `task.user_id`.
 *
 * @property int $id
 * @property int $task_id
 * @property string $title
 * @property bool $is_completed
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Task $task
 */
#[Fillable(['task_id', 'title', 'is_completed', 'sort_order'])]
class Subtask extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'is_completed' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'is_completed' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
