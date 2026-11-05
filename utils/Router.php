<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';

/**
 * Minimal router: maps an HTTP method and a path to a handler.
 * Paths may contain numeric placeholders such as /api/admin/employees/{id};
 * their values are passed to the handler as an array of integers.
 */
class Router
{
    /** @var array<string, array{regex: string, names: string[], handlers: array<string, callable>}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        if (!isset($this->routes[$path])) {
            preg_match_all('/\{(\w+)\}/', $path, $names);
            $regex = preg_replace('/\\\\\{\w+\\\\\}/', '(\d+)', preg_quote($path, '#'));
            $this->routes[$path] = ['regex' => "#^$regex$#", 'names' => $names[1], 'handlers' => []];
        }
        $this->routes[$path]['handlers'][strtoupper($method)] = $handler;
    }

    /**
     * Runs the handler for the request, or throws 404 / 405.
     */
    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if (!isset($route['handlers'][$method])) {
                header('Allow: ' . implode(', ', array_keys($route['handlers'])));
                throw new HttpException(405, 'Method not allowed');
            }
            $params = array_combine($route['names'], array_map('intval', array_slice($matches, 1)));
            $route['handlers'][$method]($params);
            return;
        }

        throw new HttpException(404, 'Not found');
    }
}
