<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Guards cross-module access to the `tasks` table.
 *
 * The Kanban Tasks module is built after Module 01. Until its migration has
 * run, roster/project features that read or write `tasks` degrade
 * gracefully (zero workload, nothing to snapshot) instead of failing.
 */
final class TaskTable
{
    public const NAME = 'tasks';

    /** @var array<string, bool> */
    private static array $cache = [];

    /**
     * @param  list<string>  $columns
     */
    public static function supports(array $columns): bool
    {
        $key = implode(',', $columns);

        return self::$cache[$key] ??= Schema::hasTable(self::NAME)
            && Schema::hasColumns(self::NAME, $columns);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
