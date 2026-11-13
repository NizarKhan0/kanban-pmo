<?php

declare(strict_types=1);

namespace App\Exceptions\Pmo;

use DomainException;

/**
 * A board-column business rule was violated. The message is user-facing English.
 */
final class BoardColumnException extends DomainException
{
    public static function lastColumn(): self
    {
        return new self('Your board must keep at least one column.');
    }

    public static function targetRequired(int $cards): self
    {
        return new self("This column holds {$cards} task card(s). Choose a column to move them to before deleting.");
    }

    public static function invalidTarget(): self
    {
        return new self('The selected destination column is not available.');
    }
}
