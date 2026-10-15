<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Raised when an authenticated user lacks the role required by a route.
 */
final class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'You do not have permission to perform this action.')
    {
        parent::__construct('FORBIDDEN', $message, 403);
    }
}
