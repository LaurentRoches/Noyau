<?php

declare(strict_types=1);

namespace App\Http;

use App\Persistence\RunNotFoundException;

final class Router
{
    /**
     * @var list<array{method: string, pattern: string, handler: callable(array<string, string>, Request): ApiResponse}>
     */
    private array $routes = [];

    /**
     * @param callable(array<string, string>, Request): ApiResponse $handler
     */
    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * @param callable(array<string, string>, Request): ApiResponse $handler
     */
    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * @param callable(array<string, string>, Request): ApiResponse $handler
     */
    private function addRoute(string $method, string $path, callable $handler): void
    {
        $pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path) . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): ApiResponse
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            if (preg_match($route['pattern'], $request->uri(), $matches) === 1) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                try {
                    return ($route['handler'])($params, $request);
                } catch (RunNotFoundException $e) {
                    return ApiResponse::error($e->getMessage(), 404);
                } catch (\InvalidArgumentException $e) {
                    return ApiResponse::error($e->getMessage(), 400);
                } catch (\LogicException $e) {
                    return ApiResponse::error($e->getMessage(), 409);
                }
            }
        }

        return ApiResponse::error('Route not found', 404);
    }
}
