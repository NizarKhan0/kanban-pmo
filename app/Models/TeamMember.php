<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Pmo\WorkloadCalculator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Team member / PIC (Person In Charge).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $role
 * @property string|null $email
 * @property string|null $phone_whatsapp
 * @property string $icon
 * @property string $color
 * @property bool $is_me
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'role', 'email', 'phone_whatsapp', 'icon', 'color', 'is_me', 'is_active', 'sort_order'])]
class TeamMember extends Model
{
    /** @var list<string> */
    public const ICONS = ['👤', '🧑‍💼', '👩‍💻', '👨‍💻', '🧪', '📋', '🎨', '🛠️', '📈', '🧠'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'icon' => '👤',
        'color' => 'pink',
        'is_me' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_me' => 'boolean',
            'is_active' => 'boolean',
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
     * Two-letter initials, e.g. "Ikhwan A." -> "IA", "Farah" -> "FA".
     */
    public function initials(): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L}\s]/u', ' ', $this->name))) ?: [],
            static fn (string $word): bool => $word !== '',
        ));

        if ($words === []) {
            return '?';
        }

        $initials = count($words) === 1
            ? Str::substr($words[0], 0, 2)
            : Str::substr($words[0], 0, 1).Str::substr($words[count($words) - 1], 0, 1);

        return Str::upper($initials);
    }

    /**
     * Workload counts across this PIC's tasks.
     *
     * @return array{open: int, done: int, overdue: int}
     */
    public function workloadStats(): array
    {
        return app(WorkloadCalculator::class)
            ->forMembers($this->user_id, [$this->id])[$this->id];
    }

    /**
     * Mark this member as the current user ("Me") and clear the flag on every
     * other member owned by the same user.
     */
    public function setAsMe(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('user_id', $this->user_id)
                ->whereKeyNot($this->id)
                ->where('is_me', true)
                ->update(['is_me' => false]);

            $this->forceFill(['is_me' => true])->save();
        });
    }

    /**
     * WhatsApp deep link (wa.me) built from the stored phone number.
     */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone_whatsapp);

        return $digits === '' || $digits === null ? null : 'https://wa.me/'.$digits;
    }
}
