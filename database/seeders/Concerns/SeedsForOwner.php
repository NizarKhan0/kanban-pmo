<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

/**
 * Lets a PMO sample-data seeder target any workspace owner.
 * `php artisan db:seed` keeps the default (user #1); the workspace reset
 * feature calls `->forOwner(Auth::id())` to rebuild one user's sample data.
 */
trait SeedsForOwner
{
    protected int $ownerId = 1;

    public function forOwner(int $ownerId): static
    {
        $this->ownerId = $ownerId;

        return $this;
    }
}
