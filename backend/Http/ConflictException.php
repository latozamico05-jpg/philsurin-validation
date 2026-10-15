<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Raised when a write would violate a uniqueness rule or an illegal
 * state transition (for example verifying an already verified session).
 */
final class ConflictException extends ApiException
{
    public function __construct(string $message = 'The request conflicts with the current state of the resource.')
    {
        parent::__construct('CONFLICT', $message, 409);
    }
}
