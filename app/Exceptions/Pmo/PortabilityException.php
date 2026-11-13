<?php

declare(strict_types=1);

namespace App\Exceptions\Pmo;

use RuntimeException;

/**
 * An import / restore payload was rejected. The message is user-facing English.
 */
final class PortabilityException extends RuntimeException {}
