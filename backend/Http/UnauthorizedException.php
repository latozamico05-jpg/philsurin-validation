<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Raised when a route requires a session and none (or an inactive one) exists.
 */
final class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'Authentication required.')
    {
        parent::__construct('UNAUTHORIZED', $message, 401);
    }
}
