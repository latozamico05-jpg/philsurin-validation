<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Thin wrapper over the PHP session so services never touch $_SESSION directly.
 *
 * config.php is responsible for configuring and starting the session; this class
 * only reads and writes keys inside it.
 */
final class SessionStore
{
    private const USER_KEY = 'user';

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function userId(): ?int
    {
        $user = $this->get(self::USER_KEY);

        if (!is_array($user) || empty($user['id'])) {
            return null;
        }

        return (int) $user['id'];
    }

    /**
     * @param array{id: int|string, username: string, role: string} $user
     */
    public function login(array $user): void
    {
        $this->put(self::USER_KEY, [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
        ]);
    }

    /**
     * Issue a new session id and copy the authenticated user across.
     *
     * @param array{id: int|string, username: string, role: string} $user
     */
    public function regenerateAndLogin(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->login($user);
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
