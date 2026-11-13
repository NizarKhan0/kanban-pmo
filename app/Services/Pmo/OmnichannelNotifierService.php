<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\AutomationLog;
use App\Services\Pmo\Ai\AiProviderGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Telegram / Gmail / WhatsApp dispatcher. Every attempt is written to
 * automation_logs as "sent", "failed" or — when the channel is disabled or
 * not configured in .env — "simulated" (never an exception).
 * Secrets (bot token, API token, SMTP password) are never logged.
 */
final class OmnichannelNotifierService
{
    private const TELEGRAM_CHUNK = 4000;

    public function __construct(
        private readonly AutomationLogger $logs,
        private readonly AiProviderGateway $ai,
    ) {}

    // ──────────────────────────────────────────────────────────────────
    // Telegram
    // ──────────────────────────────────────────────────────────────────

    public function sendTelegram(string $message, ?string $chatId = null, string $command = 'notify:manual'): NotificationResult
    {
        $chatId = trim((string) ($chatId ?: config('pmo.telegram.chat_id')));
        $token = (string) config('pmo.telegram.bot_token');
        $missing = $this->missing('telegram', $chatId);

        if ($missing !== []) {
            return $this->simulated('telegram', $command, $message, $chatId ?: null, 'Telegram is in Simulation Mode (.env not set: '.implode(', ', $missing).').');
        }

        try {
            foreach (mb_str_split($message, self::TELEGRAM_CHUNK) ?: [$message] as $chunk) {
                $response = Http::timeout(20)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $chunk,
                    'disable_web_page_preview' => 'true',
                ]);

                if ($response->failed() || $response->json('ok') !== true) {
                    $detail = (string) ($response->json('description') ?? 'HTTP '.$response->status());

                    return $this->failed('telegram', $command, $message, $chatId, "Telegram rejected the message: {$detail}");
                }
            }
        } catch (ConnectionException) {
            // Never surface the exception text: the request URL contains the bot token.
            return $this->failed('telegram', $command, $message, $chatId, 'Could not reach the Telegram Bot API.');
        }

        return $this->sent('telegram', $command, $message, $chatId, 'Message delivered to Telegram.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Gmail / Email (Laravel Mail, MAIL_* settings)
    // ──────────────────────────────────────────────────────────────────

    public function sendEmail(string $subject, string $body, ?string $toEmail = null, string $command = 'notify:manual'): NotificationResult
    {
        $to = trim((string) ($toEmail ?: config('pmo.email.recipient')));
        $missing = $this->missing('gmail', $to);
        $logged = "Subject: {$subject}\n\n{$body}";

        if ($missing !== []) {
            return $this->simulated('gmail', $command, $logged, $to ?: null, 'Email is in Simulation Mode (.env not set: '.implode(', ', $missing).').');
        }

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->failed('gmail', $command, $logged, $to, 'The recipient email address is not valid.');
        }

        try {
            Mail::raw($body, function (Message $mail) use ($to, $subject): void {
                $mail->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            return $this->failed('gmail', $command, $logged, $to, 'The mail server rejected the message: '.Str::limit($e->getMessage(), 300));
        }

        return $this->sent('gmail', $command, $logged, $to, "Email sent to {$to}.");
    }

    // ──────────────────────────────────────────────────────────────────
    // WhatsApp (gateway webhook, or wa.me quick link)
    // ──────────────────────────────────────────────────────────────────

    public function sendWhatsApp(string $message, ?string $phone = null, string $command = 'notify:manual'): NotificationResult
    {
        $digits = (string) preg_replace('/\D+/', '', (string) ($phone ?: config('pmo.whatsapp.default_phone')));
        $link = 'https://wa.me/'.($digits !== '' ? $digits : '').'?text='.rawurlencode($message);
        $missing = $this->missing('whatsapp', $digits);

        if ($missing !== []) {
            return $this->simulated('whatsapp', $command, $message, $digits ?: null, 'WhatsApp is in Simulation Mode (.env not set: '.implode(', ', $missing).'). Use the quick link to send it yourself.', $link);
        }

        $request = Http::timeout(20)->acceptJson();
        if (filled(config('pmo.whatsapp.api_token'))) {
            $request = $request->withToken((string) config('pmo.whatsapp.api_token'));
        }

        try {
            $response = $request->post((string) config('pmo.whatsapp.gateway_url'), [
                'phone' => $digits,
                'message' => $message,
            ]);
        } catch (ConnectionException) {
            return $this->failed('whatsapp', $command, $message, $digits, 'Could not reach the WhatsApp gateway.', $link);
        }

        if ($response->failed()) {
            return $this->failed('whatsapp', $command, $message, $digits, 'The WhatsApp gateway returned HTTP '.$response->status().'.', $link, $response->body());
        }

        return $this->sent('whatsapp', $command, $message, $digits, "WhatsApp message sent to +{$digits}.", $link, $response->body());
    }

    // ──────────────────────────────────────────────────────────────────
    // Channel status (for the hub's status cards)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{label: string, icon: string, connected: bool, detail: string, missing: list<string>}>
     */
    public function channelStatus(): array
    {
        $provider = $this->ai->provider();

        return [
            'ai' => [
                'label' => 'AI Engine',
                'icon' => '🤖',
                'connected' => $this->ai->isConfigured(),
                'detail' => ['claude' => 'Anthropic Claude', 'openai' => 'OpenAI', 'custom' => 'Custom endpoint'][$provider].' · '.$this->ai->model(),
                'missing' => $this->ai->isConfigured() ? [] : [$provider === 'custom' ? 'PMO_AI_CUSTOM_ENDPOINT' : 'PMO_AI_API_KEY'],
            ],
            'telegram' => [
                'label' => 'Telegram Bot',
                'icon' => '✈️',
                'connected' => ($m = $this->missing('telegram', (string) config('pmo.telegram.chat_id'))) === [],
                'detail' => filled(config('pmo.telegram.chat_id')) ? 'Chat ID '.config('pmo.telegram.chat_id') : 'No default chat',
                'missing' => $m,
            ],
            'gmail' => [
                'label' => 'Gmail SMTP',
                'icon' => '✉️',
                'connected' => ($m = $this->missing('gmail', (string) config('pmo.email.recipient'))) === [],
                'detail' => filled(config('pmo.email.recipient')) ? 'To '.config('pmo.email.recipient') : 'No default recipient',
                'missing' => $m,
            ],
            'whatsapp' => [
                'label' => 'WhatsApp Gateway',
                'icon' => '💬',
                'connected' => ($m = $this->missing('whatsapp', (string) preg_replace('/\D+/', '', (string) config('pmo.whatsapp.default_phone')))) === [],
                'detail' => filled(config('pmo.whatsapp.default_phone')) ? 'Default +'.preg_replace('/\D+/', '', (string) config('pmo.whatsapp.default_phone')) : 'wa.me quick links',
                'missing' => $m,
            ],
        ];
    }

    /**
     * .env keys still missing for a live dispatch on $channel.
     *
     * @return list<string>
     */
    private function missing(string $channel, string $recipient): array
    {
        $missing = [];

        switch ($channel) {
            case 'telegram':
                config('pmo.telegram.enabled') || $missing[] = 'PMO_TELEGRAM_ENABLED=true';
                filled(config('pmo.telegram.bot_token')) || $missing[] = 'PMO_TELEGRAM_BOT_TOKEN';
                $recipient !== '' || $missing[] = 'PMO_TELEGRAM_CHAT_ID';
                break;
            case 'gmail':
                config('pmo.email.enabled') || $missing[] = 'PMO_EMAIL_ENABLED=true';
                $mailer = (string) config('mail.default');
                if (in_array($mailer, ['log', 'array', ''], true)) {
                    $missing[] = 'MAIL_MAILER=smtp';
                } elseif ($mailer === 'smtp') {
                    filled(config('mail.mailers.smtp.host')) || $missing[] = 'MAIL_HOST';
                    filled(config('mail.mailers.smtp.username')) || $missing[] = 'MAIL_USERNAME';
                    filled(config('mail.mailers.smtp.password')) || $missing[] = 'MAIL_PASSWORD';
                }
                $recipient !== '' || $missing[] = 'PMO_EMAIL_RECIPIENT';
                break;
            case 'whatsapp':
                config('pmo.whatsapp.enabled') || $missing[] = 'PMO_WHATSAPP_ENABLED=true';
                filled(config('pmo.whatsapp.gateway_url')) || $missing[] = 'PMO_WHATSAPP_GATEWAY_URL';
                $recipient !== '' || $missing[] = 'PMO_WHATSAPP_DEFAULT_PHONE';
                break;
        }

        return $missing;
    }

    private function simulated(string $channel, string $command, string $message, ?string $recipient, string $summary, ?string $link = null): NotificationResult
    {
        $log = $this->logs->record($channel, $command, $message, AutomationLog::STATUS_SIMULATED, $recipient, $summary);

        return new NotificationResult($channel, AutomationLog::STATUS_SIMULATED, $summary, $recipient, $link, null, $log->id);
    }

    private function failed(string $channel, string $command, string $message, ?string $recipient, string $error, ?string $link = null, ?string $response = null): NotificationResult
    {
        $log = $this->logs->record($channel, $command, $message, AutomationLog::STATUS_FAILED, $recipient, $response === null ? null : Str::limit($response, 5000), 0, $error);

        return new NotificationResult($channel, AutomationLog::STATUS_FAILED, $error, $recipient, $link, $error, $log->id);
    }

    private function sent(string $channel, string $command, string $message, ?string $recipient, string $summary, ?string $link = null, ?string $response = null): NotificationResult
    {
        $log = $this->logs->record($channel, $command, $message, AutomationLog::STATUS_SENT, $recipient, $response === null ? $summary : Str::limit($response, 5000));

        return new NotificationResult($channel, AutomationLog::STATUS_SENT, $summary, $recipient, $link, null, $log->id);
    }
}
