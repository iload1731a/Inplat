<?php

declare(strict_types=1);

namespace App\Libraries;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[strtoupper($method)][rtrim($path, '/') ?: '/'] = $handler;
    }

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/') ?: '/';

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;
            $instance = new $class();
            $instance->{$action}($request);
            return;
        }

        $handler($request);
    }
}
