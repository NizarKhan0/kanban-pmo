<?php

declare(strict_types=1);

namespace App\Services\Pmo;

/**
 * Outcome of one notification dispatch.
 */
final readonly class NotificationResult
{
    public function __construct(
        public string $channel,
        public string $status,
        public string $summary,
        public ?string $recipient = null,
        public ?string $link = null,
        public ?string $error = null,
        public ?int $logId = null,
    ) {}

    /**
     * @return array{channel: string, status: string, summary: string, recipient: ?string, link: ?string, error: ?string, log_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'status' => $this->status,
            'summary' => $this->summary,
            'recipient' => $this->recipient,
            'link' => $this->link,
            'error' => $this->error,
            'log_id' => $this->logId,
        ];
    }
}
