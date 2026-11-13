<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Internal snapshot of a user's whole board (JSON export format).
 *
 * @property int $id
 * @property int $user_id
 * @property string $filename
 * @property string $file_size_kb Decimal string, e.g. "12.48".
 * @property int $columns_count
 * @property int $tasks_count
 * @property string $payload_json
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'filename', 'file_size_kb', 'columns_count', 'tasks_count', 'payload_json', 'notes'])]
#[Hidden(['payload_json'])]
class WorkspaceBackup extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'file_size_kb' => 'decimal:2',
            'columns_count' => 'integer',
            'tasks_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sizeLabel(): string
    {
        $kb = (float) $this->file_size_kb;

        return $kb >= 1024 ? number_format($kb / 1024, 2).' MB' : number_format($kb, 2).' KB';
    }
}
