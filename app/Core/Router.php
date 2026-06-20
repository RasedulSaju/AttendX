<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private static array $routes = [];

    // ─── Route Registration ───────────────────────────────────────────────────

    public static function get(string $path, string $handler): void
    {
        self::add('GET', $path, $handler);
    }

    public static function post(string $path, string $handler): void
    {
        self::add('POST', $path, $handler);
    }

    public static function put(string $path, string $handler): void
    {
        self::add('PUT', $path, $handler);
    }

    public static function delete(string $path, string $handler): void
    {
        self::add('DELETE', $path, $handler);
    }

    public static function any(string $path, string $handler): void
    {
        foreach (['GET','POST','PUT','PATCH','DELETE'] as $m) {
            self::add($m, $path, $handler);
        }
    }

    private static function add(string $method, string $path, string $handler): void
    {
        self::$routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler,
            'regex'   => self::pathToRegex($path),
            'params'  => self::extractParamNames($path),
        ];
    }

    // ─── Dispatch ─────────────────────────────────────────────────────────────

    public static function dispatch(): void
    {
        $method = Request::method();
        $path   = Request::path();

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            // Extract named params
            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $matches[$name] ?? '';
            }
            Request::setRouteParams($params);

            // Resolve handler: 'ControllerName@method'
            [$controllerName, $actionName] = explode('@', $route['handler']);
            $className = "App\\Controllers\\{$controllerName}";

            if (!class_exists($className)) {
                Response::abort(500, "Controller not found: $className");
            }

            $controller = new $className();

            if (!method_exists($controller, $actionName)) {
                Response::abort(500, "Action not found: {$className}::{$actionName}");
            }

            // Pass route params as typed arguments
            $typedParams = array_values(array_map(fn($v) => is_numeric($v) ? (int)$v : $v, $params));

            Response::setSecurityHeaders();
            $controller->$actionName(...$typedParams);
            return;
        }

        // No route matched
        Response::abort(404, 'Page not found.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function pathToRegex(string $path): string
    {
        // Replace {param} with named capture group (?P<param>[^/]+)
        $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $regex . '/?$#i';
    }

    private static function extractParamNames(string $path): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $path, $matches);
        return $matches[1];
    }

    public static function getRoutes(): array
    {
        return self::$routes;
    }
}
