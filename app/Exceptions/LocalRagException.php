<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Failures while orchestrating the Python ingestion process.
 */
final class LocalRagException extends DomainException {}
