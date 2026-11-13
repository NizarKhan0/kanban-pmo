<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Module 02 demo data: the 5 standard PMO columns for user_id = 1.
 * Idempotent — safe to run repeatedly.
 */
class BoardColumnSeeder extends Seeder
{
    private const OWNER_ID = 1;

    public function run(): void
    {
        if (! User::query()->whereKey(self::OWNER_ID)->exists()) {
            $this->command?->warn('BoardColumnSeeder skipped: user #'.self::OWNER_ID.' does not exist.');

            return;
        }

        DB::transaction(function (): void {
            foreach (BoardColumn::DEFAULTS as $index => $column) {
                BoardColumn::query()->updateOrCreate(
                    ['user_id' => self::OWNER_ID, 'name' => $column['name']],
                    [...$column, 'sort_order' => ($index + 1) * BoardColumn::SORT_STEP],
                );
            }
        });

        $this->command?->info('Kanban board seeded: '.count(BoardColumn::DEFAULTS).' columns for user #'.self::OWNER_ID.'.');
    }
}
