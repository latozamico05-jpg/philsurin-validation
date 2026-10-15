<?php
declare(strict_types=1);

namespace App;

use App\Http\Request;
use Closure;
use PDO;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Minimal service container with constructor autowiring.
 *
 * Everything the application needs is either the shared PDO connection, the
 * current Request, or another service in the App\ namespace. Explicit
 * factories can be registered with `set()` when a type needs configuration.
 */
final class Container
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, Closure(self): object> */
    private array $factories = [];

    public function __construct(
        private readonly PDO $database,
        private readonly Request $request
    ) {
    }

    public function database(): PDO
    {
        return $this->database;
    }

    public function request(): Request
    {
        return $this->request;
    }

    /**
     * Register an explicit factory for a type.
     *
     * @param Closure(self): object $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Resolve a shared instance of the given class.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T $instance */
            $instance = $this->instances[$id];

            return $instance;
        }

        if (isset($this->factories[$id])) {
            $instance = ($this->factories[$id])($this);
        } else {
            $instance = $this->autowire($id);
        }

        $this->instances[$id] = $instance;

        return $instance;
    }

    /**
     * @param class-string $id
     */
    private function autowire(string $id): object
    {
        if (!class_exists($id)) {
            throw new RuntimeException('Cannot resolve unknown service: ' . $id);
        }

        $reflection = new ReflectionClass($id);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException('Service is not instantiable: ' . $id);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter, $id);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveParameter(ReflectionParameter $parameter, string $owner): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new RuntimeException(
                'Cannot autowire parameter $' . $parameter->getName() . ' of ' . $owner . '.'
            );
        }

        $name = $type->getName();

        if ($name === PDO::class) {
            return $this->database;
        }

        if ($name === Request::class) {
            return $this->request;
        }

        if ($name === self::class) {
            return $this;
        }

        return $this->get($name);
    }
}
