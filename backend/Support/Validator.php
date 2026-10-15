<?php
declare(strict_types=1);

namespace App\Support;

use App\Http\ValidationException;
use DateTimeImmutable;

/**
 * Small accumulating validator for decoded JSON payloads.
 *
 * Every check records a message instead of throwing, so a caller can report
 * all field problems at once via `validate()`. Numeric helpers return null
 * when the value is absent or invalid; `validate()` is what rejects the
 * request.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== '' && $this->data[$key] !== null;
    }

    public function addError(string $key, string $message): void
    {
        $this->errors[$key] ??= $message;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Throw when any check failed.
     *
     * @throws ValidationException
     */
    public function validate(string $message = 'The submitted data is invalid.'): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($message, $this->errors);
        }
    }

    public function string(string $key, string $label, ?int $maxLength = 255): ?string
    {
        if (!$this->has($key)) {
            $this->errors[$key] = $label . ' is required.';

            return null;
        }

        $value = trim((string) $this->data[$key]);

        if ($value === '') {
            $this->errors[$key] = $label . ' is required.';

            return null;
        }

        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            $this->errors[$key] = $label . ' must be at most ' . $maxLength . ' characters.';

            return null;
        }

        return $value;
    }

    public function optionalString(string $key, string $label, ?int $maxLength = 255): ?string
    {
        if (!$this->has($key)) {
            return null;
        }

        $value = trim((string) $this->data[$key]);

        if ($value === '') {
            return null;
        }

        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            $this->errors[$key] = $label . ' must be at most ' . $maxLength . ' characters.';

            return null;
        }

        return $value;
    }

    /**
     * @param array<int, string> $allowed
     */
    public function enum(string $key, string $label, array $allowed, bool $required = true): ?string
    {
        if (!$this->has($key)) {
            if ($required) {
                $this->errors[$key] = $label . ' is required.';
            }

            return null;
        }

        $value = trim((string) $this->data[$key]);

        if (!in_array($value, $allowed, true)) {
            $this->errors[$key] = $label . ' must be one of: ' . implode(', ', $allowed) . '.';

            return null;
        }

        return $value;
    }

    public function integer(string $key, string $label, int $min = 1, ?int $max = null, bool $required = true): ?int
    {
        if (!$this->has($key)) {
            if ($required) {
                $this->errors[$key] = $label . ' is required.';
            }

            return null;
        }

        if (!is_numeric($this->data[$key])) {
            $this->errors[$key] = $label . ' must be a number.';

            return null;
        }

        $value = (int) $this->data[$key];

        if ($value < $min) {
            $this->errors[$key] = $label . ' must be at least ' . $min . '.';

            return null;
        }

        if ($max !== null && $value > $max) {
            $this->errors[$key] = $label . ' must be at most ' . $max . '.';

            return null;
        }

        return $value;
    }

    public function number(string $key, string $label, bool $required = false): int|float|null
    {
        if (!$this->has($key)) {
            if ($required) {
                $this->errors[$key] = $label . ' is required.';
            }

            return null;
        }

        if (!is_numeric($this->data[$key])) {
            $this->errors[$key] = $label . ' must be a number.';

            return null;
        }

        $value = $this->data[$key];

        return is_int($value) ? $value : (float) $value;
    }

    /**
     * ISO date (YYYY-MM-DD).
     */
    public function date(string $key, string $label, bool $required = false): ?string
    {
        if (!$this->has($key)) {
            if ($required) {
                $this->errors[$key] = $label . ' is required.';
            }

            return null;
        }

        $value = trim((string) $this->data[$key]);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            $this->errors[$key] = $label . ' must be a valid date in YYYY-MM-DD format.';

            return null;
        }

        return $value;
    }

    public function pattern(string $key, string $label, string $regex): ?string
    {
        $value = $this->string($key, $label);

        if ($value === null) {
            return null;
        }

        if (preg_match($regex, $value) !== 1) {
            $this->errors[$key] = $label . ' has an invalid format.';

            return null;
        }

        return $value;
    }

    public function boolean(string $key, string $label, ?bool $default = null): ?bool
    {
        if (!$this->has($key)) {
            return $default;
        }

        $value = $this->data[$key];

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        $this->errors[$key] = $label . ' must be true or false.';

        return $default;
    }

    /**
     * Raw access for values validated elsewhere.
     */
    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
