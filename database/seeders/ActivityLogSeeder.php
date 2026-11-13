<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\Concerns\SeedsForOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Module 04 demo data: 5 realistic activity entries for user_id = 1, timed
 * relative to "now" so the Recent Activity widget always looks current.
 * Idempotent — safe to run repeatedly (timestamps are refreshed).
 */
class ActivityLogSeeder extends Seeder
{
    use SeedsForOwner;

    public function run(): void
    {
        if (! User::query()->whereKey($this->ownerId)->exists()) {
            $this->command?->warn('ActivityLogSeeder skipped: user #'.$this->ownerId.' does not exist.');

            return;
        }

        $entries = [
            ['minutes' => 5, 'actor' => 'Afqah N.', 'action' => ActivityLog::MOVED, 'subject' => 'FPX payment integration', 'meta' => 'To Do → In Progress'],
            ['minutes' => 25, 'actor' => 'Haziq M.', 'action' => ActivityLog::UPDATED, 'subject' => 'Monthly report module UAT', 'meta' => 'Subtask completed: Log and triage defects'],
            ['minutes' => 90, 'actor' => 'Ikhwan A.', 'action' => ActivityLog::CREATED, 'subject' => 'Prepare SRS document for application module', 'meta' => 'Added to To Do · PIC: Farah L.'],
            ['minutes' => 60 * 5, 'actor' => 'Ikhwan A.', 'action' => ActivityLog::PIC_ADDED, 'subject' => 'Farah L.', 'meta' => 'Business Analyst'],
            ['minutes' => 60 * 26, 'actor' => 'Ikhwan A.', 'action' => ActivityLog::COL_ADDED, 'subject' => 'Review', 'meta' => 'No WIP limit'],
        ];

        ActivityLog::withoutTimestamps(function () use ($entries): void {
            foreach ($entries as $entry) {
                $at = Carbon::now()->subMinutes($entry['minutes']);

                ActivityLog::query()
                    ->firstOrNew([
                        'user_id' => $this->ownerId,
                        'action_type' => $entry['action'],
                        'task_title' => $entry['subject'],
                    ])
                    ->forceFill([
                        'actor_name' => $entry['actor'],
                        'meta_details' => $entry['meta'],
                        'created_at' => $at,
                        'updated_at' => $at,
                    ])
                    ->save();
            }
        });

        $this->command?->info('Activity log seeded: '.count($entries).' entries for user #'.$this->ownerId.'.');
    }
}
