<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AutomationLog;
use App\Models\SavedAiPrompt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Module 05 demo data for user_id = 1: 3 saved AI prompt templates and
 * 4 simulated automation_logs (ai_claude, telegram, gmail, whatsapp).
 * Idempotent — safe to run repeatedly.
 */
class AiOmnichannelSeeder extends Seeder
{
    private const OWNER_ID = 1;

    public function run(): void
    {
        if (! User::query()->whereKey(self::OWNER_ID)->exists()) {
            $this->command?->warn('AiOmnichannelSeeder skipped: user #'.self::OWNER_ID.' does not exist.');

            return;
        }

        $templates = [
            [
                'title' => 'Monday Standup Brief', 'action_type' => 'standup', 'icon' => '📋', 'color' => 'pink',
                'custom_instruction' => 'Keep it under 150 words, lead with anything overdue, and end with one line the PMO can read out in the meeting.',
            ],
            [
                'title' => 'Weekly Risk Review', 'action_type' => 'risk', 'icon' => '🚨', 'color' => 'rose',
                'custom_instruction' => 'Group findings by project, flag any PIC above capacity, and phrase the 3 actions as decisions for the steering committee.',
            ],
            [
                'title' => 'Meeting Minutes Capture', 'action_type' => 'capture', 'icon' => '🧲', 'color' => 'violet',
                'custom_instruction' => 'Only extract items with a clear owner or deadline. Treat anything marked "urgent" or "blocker" as high priority.',
            ],
        ];

        foreach ($templates as $index => $template) {
            SavedAiPrompt::query()->updateOrCreate(
                ['user_id' => self::OWNER_ID, 'title' => $template['title']],
                [...$template, 'sort_order' => $index + 1],
            );
        }

        $logs = [
            [
                'minutes' => 30, 'channel' => 'ai_claude', 'command' => 'ai:standup', 'recipient' => 'claude-sonnet-5-5', 'tokens' => 0,
                'message' => 'Write today\'s daily standup summary with four short sections: Achievements, Work in Progress, Blockers & Overdue, and Today\'s Top 3 Focus.',
                'response' => "⚙️ Simulation Mode — generated from your live board (no AI key configured).\n\n🎯 Today’s Top 3 Focus\n1. FPX payment integration (Afqah N.) — overdue\n2. Regression test suite for case management (Haziq M.) — overdue\n3. Prepare monthly steering committee slides (Ikhwan A.) — due today",
            ],
            [
                'minutes' => 60 * 24, 'channel' => 'telegram', 'command' => 'notify:daily_standup', 'recipient' => null, 'tokens' => 0,
                'message' => "📋 *Daily Standup Report*\nSummary: 14 tasks · 2 done (14%) · 2 overdue",
                'response' => 'Telegram is in Simulation Mode (.env not set: PMO_TELEGRAM_ENABLED=true, PMO_TELEGRAM_BOT_TOKEN, PMO_TELEGRAM_CHAT_ID).',
            ],
            [
                'minutes' => 60 * 24 - 1, 'channel' => 'gmail', 'command' => 'notify:daily_standup', 'recipient' => null, 'tokens' => 0,
                'message' => "Subject: Daily Standup Report\n\nDAILY STANDUP REPORT\nSummary: 14 tasks · 2 done (14%) · 2 overdue",
                'response' => 'Email is in Simulation Mode (.env not set: PMO_EMAIL_ENABLED=true, MAIL_MAILER=smtp, PMO_EMAIL_RECIPIENT).',
            ],
            [
                'minutes' => 60 * 23, 'channel' => 'whatsapp', 'command' => 'notify:overdue_alert', 'recipient' => '60123456702', 'tokens' => 0,
                'message' => "Hi Afqah N., you have 1 overdue task:\n\n• [OVERDUE] FPX payment integration — due 12 Nov",
                'response' => 'WhatsApp is in Simulation Mode (.env not set: PMO_WHATSAPP_ENABLED=true, PMO_WHATSAPP_GATEWAY_URL). Use the quick link to send it yourself.',
            ],
        ];

        AutomationLog::withoutTimestamps(function () use ($logs): void {
            foreach ($logs as $entry) {
                $at = Carbon::now()->subMinutes($entry['minutes']);

                AutomationLog::query()
                    ->firstOrNew([
                        'user_id' => self::OWNER_ID,
                        'channel' => $entry['channel'],
                        'command_name' => $entry['command'],
                    ])
                    ->forceFill([
                        'recipient' => $entry['recipient'],
                        'message' => $entry['message'],
                        'response_payload' => $entry['response'],
                        'tokens_used' => $entry['tokens'],
                        'status' => AutomationLog::STATUS_SIMULATED,
                        'error_message' => null,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ])
                    ->save();
            }
        });

        $this->command?->info('AI & Omnichannel seeded: '.count($templates).' prompt templates, '.count($logs).' simulated automation logs for user #'.self::OWNER_ID.'.');
    }
}
