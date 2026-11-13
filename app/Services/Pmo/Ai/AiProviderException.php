<?php

declare(strict_types=1);

namespace App\Services\Pmo\Ai;

use RuntimeException;

/**
 * A live AI provider call failed. The message is user-facing English.
 */
final class AiProviderException extends RuntimeException {}
