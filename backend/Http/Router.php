<?php
declare(strict_types=1);

namespace App\Http;

use Closure;

/**
 * Tiny pattern router.
 *
 * Patterns use `{name}` for path parameters, for example
 * `masterlist/{id}` or `validations/{id}/submit`. A trailing `*` captures the
 * remainder of the path, which keeps the controller list short.
 */
final class Router
{
    /** @var array<int, array{method: string, regex: string, names: array<int, string>, handler: Closure}> */
    private array $routes = [];

    /** @var array<int, string> */
    private array $allowedMethods = [];

    /**
     * @param Closure(Request, array<string, string>): Response $handler
     */
    public function add(string $method, string $pattern, Closure $handler): void
    {
        ['regex' => $regex, 'names' => $names] = self::compile($pattern);

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $regex,
            'names' => $names,
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, Closure $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, Closure $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function patch(string $pattern, Closure $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function put(string $pattern, Closure $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, Closure $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    /**
     * Match a request and invoke its handler.
     *
     * @throws NotFoundException when no route matches the path.
     */
    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $request->method()) {
                $this->allowedMethods[$route['method']] = $route['method'];
                continue;
            }

            array_shift($matches);
            $params = [];

            foreach ($route['names'] as $index => $name) {
                $params[$name] = $matches[$index] ?? '';
            }

            return ($route['handler'])($request, $params);
        }

        if ($pathMatched && $this->allowedMethods !== []) {
            $allowed = implode(', ', array_keys($this->allowedMethods));

            throw new ApiException('METHOD_NOT_ALLOWED', 'Allowed methods: ' . $allowed . '.', 405);
        }

        throw new NotFoundException('Endpoint not found.');
    }

    /**
     * @return array{regex: string, names: array<int, string>}
     */
    private static function compile(string $pattern): array
    {
        $pattern = trim($pattern, '/');
        $names = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $match) use (&$names): string {
                $names[] = $match[1];

                return '([^/]+)';
            },
            $pattern
        );

        if ($regex === null) {
            $regex = $pattern;
        }

        if (str_ends_with($regex, '*')) {
            $regex = substr($regex, 0, -1) . '(.*)';
            $names[] = 'rest';
        }

        return ['regex' => '#^' . $regex . '$#', 'names' => $names];
    }
}
