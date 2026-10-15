<?php
declare(strict_types=1);

namespace App\Http;

/**
 * JSON response value object.
 *
 * Controllers return a Response instead of echoing, which keeps the whole
 * request flow testable in-process. `send()` is the only place that writes to
 * the output buffer.
 */
final class Response
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly array $body,
        private readonly array $headers = []
    ) {
    }

    /**
     * A successful envelope: { success: true, data: ... }.
     *
     * @param array<string, mixed> $meta Optional pagination or aggregate metadata.
     */
    public static function success(mixed $data = [], array $meta = [], int $status = 200): self
    {
        $body = ['success' => true, 'data' => $data];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new self($status, $body);
    }

    public static function created(mixed $data = []): self
    {
        return self::success($data, [], 201);
    }

    /**
     * An error envelope: { success: false, error: { code, message } }.
     *
     * @param array<string, string> $fields Field-level validation messages.
     */
    public static function error(string $code, string $message, int $status = 400, array $fields = []): self
    {
        $error = ['code' => $code, 'message' => $message];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return new self($status, ['success' => false, 'error' => $error]);
    }

    public static function noContent(): self
    {
        return self::success([]);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): mixed
    {
        return $this->body['data'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function errorFields(): array
    {
        return $this->body['error']['fields'] ?? [];
    }

    /**
     * Emit the response. Only called by the front controller.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('Content-Type: application/json; charset=utf-8');

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo self::encode($this->body);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{"success":false,"error":{"code":"ENCODING_ERROR","message":"The response could not be encoded."}}' : $json;
    }
}
