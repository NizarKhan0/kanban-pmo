<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsPerTenant;
use App\Models\Task;
use App\Models\User;
use App\Services\Pmo\OmnichannelNotifierService;
use App\Services\Pmo\TaskFilters;
use App\Services\Pmo\TaskService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Finds overdue cards (due_date < today, not done) in each workspace and
 * alerts the PMO — plus each PIC who has an email / WhatsApp number on the roster.
 */
class CheckOverdueTasks extends Command
{
    use RunsPerTenant;

    /** @var string */
    protected $signature = 'pmo:check-overdue-tasks
        {--user= : Only process this user ID}
        {--channel= : Comma-separated channels (telegram,email,whatsapp). Default: all}
        {--pmo-only : Alert the PMO only, not individual PICs}';

    /** @var string */
    protected $description = 'Send overdue-task reminders to the PMO and PICs via Telegram, Email and WhatsApp (or simulate).';

    private const COMMAND = 'notify:overdue_alert';

    public function handle(TaskService $tasks, OmnichannelNotifierService $notifier): int
    {
        $channels = $this->selectedChannels();

        $processed = $this->forEachTenant(function (User $user) use ($channels, $tasks, $notifier): void {
            $this->components->twoColumnDetail("Workspace #{$user->id}", $user->name);

            /** @var Collection<int, Task> $overdue */
            $overdue = $tasks->filteredQuery(new TaskFilters(due: 'overdue'))
                ->with(['column', 'teamMember', 'project'])
                ->orderBy('due_date')
                ->get();

            if ($overdue->isEmpty()) {
                $this->components->info('No overdue tasks — nothing to send.');

                return;
            }

            // 1) PMO summary on every selected channel.
            $message = $this->message('⏰ OVERDUE ALERT — '.$overdue->count().' '.Str::plural('task', $overdue->count()), $overdue);
            foreach ($channels as $channel) {
                $result = match ($channel) {
                    'telegram' => $notifier->sendTelegram($message, null, self::COMMAND),
                    'email' => $notifier->sendEmail('Overdue Alert — '.$overdue->count().' task(s) need attention', $message, null, self::COMMAND),
                    default => $notifier->sendWhatsApp($message, null, self::COMMAND),
                };
                $this->reportResult($this->channelLabel($channel), $result->status, 'PMO · '.$result->summary);
            }

            if ($this->option('pmo-only')) {
                return;
            }

            // 2) Personal reminders to each PIC with contact details.
            foreach ($overdue->whereNotNull('team_member_id')->groupBy('team_member_id') as $cards) {
                $member = $cards->first()?->teamMember;
                if ($member === null) {
                    continue;
                }

                $personal = $this->message("Hi {$member->name}, you have ".$cards->count().' overdue '.Str::plural('task', $cards->count()).':', $cards);

                if (in_array('email', $channels, true) && filled($member->email)) {
                    $r = $notifier->sendEmail('Reminder: your overdue tasks', $personal, $member->email, self::COMMAND);
                    $this->reportResult('Email', $r->status, "{$member->name} · {$r->summary}");
                }
                if (in_array('whatsapp', $channels, true) && filled($member->phone_whatsapp)) {
                    $r = $notifier->sendWhatsApp($personal, $member->phone_whatsapp, self::COMMAND);
                    $this->reportResult('WhatsApp', $r->status, "{$member->name} · {$r->summary}");
                }
            }
        });

        $this->components->info("Overdue check processed for {$processed} workspace(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Task>  $cards
     */
    private function message(string $heading, Collection $cards): string
    {
        $today = Carbon::parse(Task::today());
        $lines = [$heading, ''];

        foreach ($cards as $task) {
            $late = (int) $task->due_date?->diffInDays($today);
            $meta = array_filter([$task->picLabel(), $task->projectLabel(), $task->column->name]);
            $lines[] = "• [OVERDUE] {$task->title} — due ".$task->due_date?->format('j M')." ({$late} day(s) late)".($meta === [] ? '' : ' · '.implode(' · ', $meta));
        }

        $lines[] = '';
        $lines[] = 'Please update the card or agree a new date in today’s standup.';

        return implode("\n", $lines);
    }
}
