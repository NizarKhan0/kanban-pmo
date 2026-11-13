<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Runs a callback once per workspace owner with that user set on the auth
 * guard, so every service keeps its strict `where('user_id', Auth::id())` scope.
 */
trait RunsPerTenant
{
    /**
     * @param  Closure(User): void  $callback
     */
    protected function forEachTenant(Closure $callback): int
    {
        $userOption = $this->option('user');

        $users = User::query()
            ->when(
                $userOption !== null && $userOption !== '',
                fn ($q) => $q->whereKey((int) $userOption),
                fn ($q) => $q->whereHas('tasks'),
            )
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->components->warn('No workspaces to process.');

            return 0;
        }

        foreach ($users as $user) {
            Auth::setUser($user);

            try {
                $callback($user);
            } finally {
                Auth::forgetUser();
            }
        }

        return $users->count();
    }

    /**
     * @return list<string>
     */
    protected function selectedChannels(): array
    {
        $valid = ['telegram', 'email', 'whatsapp'];
        $requested = array_filter(array_map('trim', explode(',', (string) $this->option('channel'))));

        $channels = array_values(array_intersect($requested === [] ? $valid : $requested, $valid));

        return $channels === [] ? $valid : $channels;
    }

    protected function channelLabel(string $channel): string
    {
        return ['telegram' => 'Telegram', 'email' => 'Email', 'whatsapp' => 'WhatsApp'][$channel] ?? ucfirst($channel);
    }

    protected function reportResult(string $label, string $status, string $summary): void
    {
        $line = sprintf('%-9s %-10s %s', $label, strtoupper($status), $summary);

        match ($status) {
            'sent' => $this->components->info($line),
            'failed' => $this->components->error($line),
            default => $this->components->warn($line),
        };
    }
}
