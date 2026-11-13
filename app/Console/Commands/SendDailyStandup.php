<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsPerTenant;
use App\Models\User;
use App\Services\Pmo\OmnichannelNotifierService;
use App\Services\Pmo\StandupGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generates each workspace's Daily Standup Report and dispatches it to the
 * enabled channels (or records a simulated dispatch when a channel is not set up).
 */
class SendDailyStandup extends Command
{
    use RunsPerTenant;

    /** @var string */
    protected $signature = 'pmo:send-daily-standup
        {--user= : Only process this user ID}
        {--channel= : Comma-separated channels (telegram,email,whatsapp). Default: all}';

    /** @var string */
    protected $description = 'Generate the Daily Standup Report and send it to Telegram, Email and WhatsApp (or simulate).';

    public function handle(StandupGeneratorService $standup, OmnichannelNotifierService $notifier): int
    {
        $channels = $this->selectedChannels();

        $processed = $this->forEachTenant(function (User $user) use ($channels, $standup, $notifier): void {
            $this->components->twoColumnDetail("Workspace #{$user->id}", $user->name);
            $subject = 'Daily Standup Report — '.Carbon::now((string) config('pmo.timezone'))->format('j M Y');

            foreach ($channels as $channel) {
                $result = match ($channel) {
                    'telegram' => $notifier->sendTelegram($standup->generate(), null, 'notify:daily_standup'),
                    'email' => $notifier->sendEmail($subject, $standup->generate(null, StandupGeneratorService::FORMAT_PLAIN), null, 'notify:daily_standup'),
                    default => $notifier->sendWhatsApp($standup->generate(), null, 'notify:daily_standup'),
                };

                $this->reportResult($this->channelLabel($channel), $result->status, $result->summary);
            }
        });

        $this->components->info("Daily standup processed for {$processed} workspace(s).");

        return self::SUCCESS;
    }
}
