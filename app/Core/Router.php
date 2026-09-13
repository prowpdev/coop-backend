<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register a route.
     */
    private function addRoute(
        string $method,
        string $path,
        array $handler
    ): void {
        $path = $this->normalizePath($path);

        /*
         * Convert:
         *
         * /api/users/:id
         *
         * into:
         *
         * #^/api/users/(?P<id>[^/]+)$#
         */

        $pattern = preg_replace_callback(
            '/:([a-zA-Z0-9_]+)/',
            static function (array $matches): string {
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $path
        );

        /*
         * Also support:
         *
         * /api/users/{id}
         */
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            static function (array $matches): string {
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $pattern
        );

        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'rawPath' => $path,
        ];
    }

    /**
     * Normalize request/route paths.
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        // Remove query string if accidentally supplied
        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        // Ensure leading slash
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        // Remove trailing slash except root
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Dispatch request.
     */
    public function dispatch(
        string $method,
        string $uri,
        PDO $db
    ): void {
        $method = strtoupper($method);

        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        $uri = $this->normalizePath($uri);

        foreach ($this->routes as $route) {

            // HTTP method must match
            if ($route['method'] !== $method) {
                continue;
            }

            // URI must match route pattern
            if (preg_match($route['pattern'], $uri, $matches) !== 1) {
                continue;
            }

            /*
             * Extract only named parameters.
             */
            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            [$controllerClass, $action] = $route['handler'];

            // -------------------------------------------------
            // Controller exists?
            // -------------------------------------------------
            if (!class_exists($controllerClass)) {

                $this->json([
                    'success' => false,
                    'error' => "Controller class '{$controllerClass}' not found.",
                ], 500);

                return;
            }

            // -------------------------------------------------
            // Instantiate controller
            // -------------------------------------------------
            try {
                $controller = new $controllerClass($db);
            } catch (Throwable $e) {

                $this->json([
                    'success' => false,
                    'error' => 'Unable to instantiate controller.',
                    'details' => $e->getMessage(),
                ], 500);

                return;
            }

            // -------------------------------------------------
            // Controller method exists?
            // -------------------------------------------------
            if (!method_exists($controller, $action)) {

                $this->json([
                    'success' => false,
                    'error' =>
                        "Action method '{$action}' not found in '{$controllerClass}'.",
                ], 500);

                return;
            }

            // -------------------------------------------------
            // Call controller
            // -------------------------------------------------
            call_user_func_array(
                [$controller, $action],
                array_values($params)
            );

            return;
        }

        // -----------------------------------------------------
        // No route matched
        // -----------------------------------------------------
        $this->json([
            'success' => false,
            'error' => "Route '{$method} {$uri}' not found.",
            'hint' => 'Check routes/api.php for available endpoints.',
        ], 404);
    }

    /**
     * JSON response helper.
     */
    private function json(
        array $data,
        int $status = 200
    ): void {
        http_response_code($status);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }
}