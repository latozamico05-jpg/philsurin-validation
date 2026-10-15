<?php
/**
 * Backend bootstrap: constants and a PSR-4 style autoloader for the App\ namespace.
 *
 * The application has no Composer dependency, so the autoloader below maps
 * App\Foo\Bar to backend/Foo/Bar.php. Requiring this file is side-effect free;
 * use App\Http\Kernel to actually serve a request.
 */
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('BACKEND_ROOT')) {
    define('BACKEND_ROOT', __DIR__);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $prefixLength = strlen($prefix);

    if (strncmp($class, $prefix, $prefixLength) !== 0) {
        return;
    }

    $relative = substr($class, $prefixLength);
    $path = BACKEND_ROOT . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
