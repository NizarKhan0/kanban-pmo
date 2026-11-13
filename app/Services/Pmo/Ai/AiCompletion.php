<?php

declare(strict_types=1);

namespace App\Services\Pmo\Ai;

/**
 * Text returned by a live AI provider call.
 */
final readonly class AiCompletion
{
    public function __construct(
        public string $text,
        public int $tokens,
        public string $model,
    ) {}
}
