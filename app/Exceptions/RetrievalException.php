<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Failures while preparing semantic retrieval.
 */
final class RetrievalException extends DomainException
{
    public static function emptyQuery(): self
    {
        return new self('Retrieval query must not be empty.');
    }
}
