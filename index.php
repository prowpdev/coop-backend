<?php

declare(strict_types=1);

// ---------------------------------------------------------
// CORS
// ---------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------
// Error reporting
// ---------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ---------------------------------------------------------
// Autoload
// ---------------------------------------------------------
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/app/Core/Autoloader.php';

    \App\Core\Autoloader::register();
}

// ---------------------------------------------------------
// Imports
// ---------------------------------------------------------
use App\Core\Database;
use App\Core\Router;

// ---------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------
try {

    // Database
    $db = Database::getConnection();

    // Router
    $router = new Router();

    // -----------------------------------------------------
    // Load API routes
    // -----------------------------------------------------
    $routesFile = __DIR__ . '/routes/api.php';

    if (!file_exists($routesFile)) {
        throw new RuntimeException(
            'Routes file not found: ' . $routesFile
        );
    }

    require $routesFile;

    // -----------------------------------------------------
    // Request
    // -----------------------------------------------------
    $method = strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );

    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    // Remove query string
    $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

    // -----------------------------------------------------
    // Dispatch
    // -----------------------------------------------------
    $router->dispatch(
        $method,
        $uri,
        $db
    );

} catch (\Throwable $e) {

    http_response_code(500);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    exit;
}