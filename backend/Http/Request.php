<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Immutable view over the current HTTP request.
 *
 * The route path is everything after the `api/` segment of the request URI,
 * with leading and trailing slashes trimmed, so a deployment in a
 * subdirectory works without configuration.
 */
final class Request
{
    private string $method;
    private string $path;
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private array $payload;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $payload = [],
        private readonly string $ip = '',
        private readonly string $userAgent = ''
    ) {
        $this->method = strtoupper($method);
        $this->path = trim($path, '/');
        $this->query = $query;
        $this->payload = $payload;
    }

    /**
     * Build a request from the PHP superglobals.
     *
     * @param array<string, mixed> $server Typically $_SERVER.
     * @param array<string, mixed> $query Typically $_GET.
     */
    public static function fromGlobals(array $server, array $query = []): self
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '');

        if ($query === [] && ($server['QUERY_STRING'] ?? '') !== '') {
            parse_str((string) $server['QUERY_STRING'], $query);
        }

        return new self(
            (string) ($server['REQUEST_METHOD'] ?? 'GET'),
            self::relativePath($path),
            $query,
            self::decodeBody(),
            (string) ($server['REMOTE_ADDR'] ?? ''),
            (string) ($server['HTTP_USER_AGENT'] ?? '')
        );
    }

    /**
     * Strip everything up to and including the `api/` segment of the path.
     */
    public static function relativePath(string $path): string
    {
        $path = trim($path, '/');
        $position = strpos($path, 'api/');

        if ($position === false) {
            return '';
        }

        return trim(substr($path, $position + 4), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeBody(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return $_POST;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryAll(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->payload;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }

    /**
     * Trimmed string input, or the default when the key is absent or blank.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->payload[$key] ?? null;

        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return $default;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }
}
