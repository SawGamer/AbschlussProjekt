<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';

/**
 * Minimal router: maps an HTTP method and an exact path to a handler.
 */
class Router
{
    /** @var array<string, array<string, callable>> path => method => handler */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][strtoupper($method)] = $handler;
    }

    /**
     * Runs the handler for the request, or throws 404 / 405.
     */
    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');

        if (!isset($this->routes[$path])) {
            throw new HttpException(404, 'Not found');
        }

        $handlers = $this->routes[$path];
        $method = strtoupper($method);
        if (!isset($handlers[$method])) {
            header('Allow: ' . implode(', ', array_keys($handlers)));
            throw new HttpException(405, 'Method not allowed');
        }

        $handlers[$method]();
    }
}
