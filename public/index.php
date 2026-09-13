<?php

declare(strict_types=1);

// Pre-flight CORS handling for modern web single page applications
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(204);
    exit;
}

// Ensure error reporting is enabled in development
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Autoload classes (Composer or Built-in Zero-Config Autoloader)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../app/Core/Autoloader.php';
    \App\Core\Autoloader::register();
}

use App\Core\Database;
use App\Core\Router;

try {
    $db = Database::getConnection();
    $router = new Router();

    // Register all routes
    require_once __DIR__ . '/../routes/api.php';

    // Dispatch current HTTP request
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';

    $router->dispatch($method, $uri, $db);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
