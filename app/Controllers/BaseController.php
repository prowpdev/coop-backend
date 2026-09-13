<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;

abstract class BaseController
{
    public function __construct(
        protected PDO $db
    ) {
    }

    /**
     * Send JSON response and terminate execution
     */
    public function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send formatted success response
     */
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): never
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Send formatted error response
     */
    protected function error(string $message = 'Error', int $status = 400, mixed $details = null): never
    {
        $this->json([
            'success' => false,
            'error'   => $message,
            'details' => $details,
        ], $status);
    }

    /**
     * Read and decode JSON request payload
     */
    protected function getRequestBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get GET query parameter safely
     */
    protected function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
