<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;
use Throwable;

/**
 * Base class for every error the API renders as a JSON error envelope.
 *
 * Anything that extends this class is treated as a client-facing failure:
 * the code and message are sent verbatim. All other exceptions are logged and
 * rendered as a generic 500 so database errors and stack traces never leak.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }
}
