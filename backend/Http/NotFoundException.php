<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Raised when a route or a referenced record does not exist.
 */
final class NotFoundException extends ApiException
{
    public function __construct(string $message = 'The requested resource was not found.')
    {
        parent::__construct('NOT_FOUND', $message, 404);
    }
}
