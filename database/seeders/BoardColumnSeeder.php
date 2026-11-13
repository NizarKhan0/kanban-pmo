<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\User;
use Database\Seeders\Concerns\SeedsForOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Module 02 demo data: the 5 standard PMO columns for user_id = 1.
 * Idempotent — safe to run repeatedly.
 */
class BoardColumnSeeder extends Seeder
{
    use SeedsForOwner;

    public function run(): void
    {
        if (! User::query()->whereKey($this->ownerId)->exists()) {
            $this->command?->warn('BoardColumnSeeder skipped: user #'.$this->ownerId.' does not exist.');

            return;
        }

        DB::transaction(function (): void {
            foreach (BoardColumn::DEFAULTS as $index => $column) {
                BoardColumn::query()->updateOrCreate(
                    ['user_id' => $this->ownerId, 'name' => $column['name']],
                    [...$column, 'sort_order' => ($index + 1) * BoardColumn::SORT_STEP],
                );
            }
        });

        $this->command?->info('Kanban board seeded: '.count(BoardColumn::DEFAULTS).' columns for user #'.$this->ownerId.'.');
    }
}
