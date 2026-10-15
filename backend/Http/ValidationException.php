<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Raised when a request payload fails validation. The message is a
 * human-readable summary; field errors travel alongside it.
 */
final class ValidationException extends ApiException
{
    /** @var array<string, string> */
    private array $fieldErrors;

    /**
     * @param array<string, string> $fieldErrors Field name => message.
     */
    public function __construct(string $message, array $fieldErrors = [])
    {
        parent::__construct('VALIDATION_ERROR', $message, 422);
        $this->fieldErrors = $fieldErrors;
    }

    /**
     * @return array<string, string>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
