<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reusable AI prompt template: an action plus the user's own instruction.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $action_type
 * @property string $custom_instruction
 * @property string $icon
 * @property string $color
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'title', 'action_type', 'custom_instruction', 'icon', 'color', 'sort_order'])]
class SavedAiPrompt extends Model
{
    /** @var array<string, array{label: string, icon: string, needs: string|null, hint: string}> */
    public const ACTIONS = [
        'standup' => ['label' => 'Standup Summary', 'icon' => '📋', 'needs' => null, 'hint' => 'Achievements, work in progress, blockers and today’s top 3 focus areas.'],
        'risk' => ['label' => 'Risks & Blockers', 'icon' => '🚨', 'needs' => null, 'hint' => 'Overdue cards, WIP breaches, workload imbalance and 3 corrective actions.'],
        'capture' => ['label' => 'Capture Tasks from Notes', 'icon' => '🧲', 'needs' => 'notes', 'hint' => 'Turn meeting notes or an email into reviewable task cards.'],
        'subtasks' => ['label' => 'Break into Subtasks', 'icon' => '🧩', 'needs' => 'task', 'hint' => 'Split one task into 3–7 actionable subtasks.'],
        'improve_task' => ['label' => 'Improve Task Definition', 'icon' => '✨', 'needs' => 'task', 'hint' => 'Sharpen the title, priority and due date of a card.'],
    ];

    /** @var list<string> */
    public const ICONS = ['✨', '📋', '🚨', '🧲', '🧩', '🧠', '📈', '🛡️', '🗓️', '💡'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'icon' => '✨',
        'color' => 'pink',
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

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action_type]['label'] ?? $this->action_type;
    }
}
