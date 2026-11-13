<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkspaceBackup;
use App\Services\PmoPortabilityService;
use Database\Seeders\Concerns\SeedsForOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Module 06 demo data: one initial snapshot of the sample workspace for user_id = 1.
 * Runs last, after the Module 01–05 seeders. Idempotent — the initial snapshot
 * is refreshed instead of duplicated.
 */
class WorkspaceBackupSeeder extends Seeder
{
    use SeedsForOwner;

    public const INITIAL_NOTE = 'Initial sample workspace';

    public function run(): void
    {
        $user = User::query()->find($this->ownerId);

        if ($user === null) {
            $this->command?->warn('WorkspaceBackupSeeder skipped: user #'.$this->ownerId.' does not exist.');

            return;
        }

        // The portability service is tenant-scoped through Auth::id().
        $previous = Auth::user();
        Auth::setUser($user);

        try {
            WorkspaceBackup::query()
                ->where('user_id', $this->ownerId)
                ->where('notes', self::INITIAL_NOTE)
                ->delete();

            $snapshot = app(PmoPortabilityService::class)->createSnapshot(self::INITIAL_NOTE);
        } finally {
            $previous === null ? Auth::forgetUser() : Auth::setUser($previous);
        }

        $this->command?->info("Workspace snapshot seeded: {$snapshot->columns_count} columns, {$snapshot->tasks_count} cards ({$snapshot->sizeLabel()}) for user #{$this->ownerId}.");
    }
}
