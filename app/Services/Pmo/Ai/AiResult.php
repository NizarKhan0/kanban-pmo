<?php

declare(strict_types=1);

namespace App\Services\Pmo\Ai;

/**
 * Outcome of one AI assistant action.
 *
 * source: "live" (provider answered), "simulation" (Smart Simulation), "manual" (pasted answer)
 */
final readonly class AiResult
{
    /**
     * @param  array<string, mixed>  $data  Structured payload (tasks / subtasks / proposed changes).
     */
    public function __construct(
        public string $action,
        public string $status,
        public string $text,
        public array $data,
        public string $prompt,
        public string $source,
        public ?string $error = null,
        public ?int $logId = null,
    ) {}

    /**
     * @return array{action: string, status: string, text: string, data: array<string, mixed>, prompt: string, source: string, error: ?string, log_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'status' => $this->status,
            'text' => $this->text,
            'data' => $this->data,
            'prompt' => $this->prompt,
            'source' => $this->source,
            'error' => $this->error,
            'log_id' => $this->logId,
        ];
    }
}
