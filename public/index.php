<?php
/**
 * Front Controller - Entry Point
 * Tất cả request đều đi qua file này
 */

// Autoload Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Start session
session_start();

// Load config
require_once __DIR__ . '/../app/Config/config.php';

// Load helpers
require_once __DIR__ . '/../app/Helpers/Database.php';
require_once __DIR__ . '/../app/Helpers/Session.php';
require_once __DIR__ . '/../app/Helpers/Validator.php';

// Simple Router
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
$url = filter_var($url, FILTER_SANITIZE_URL);
$parts = explode('/', $url);

// Determine controller, action, params
$controllerName = !empty($parts[0]) ? ucfirst($parts[0]) . 'Controller' : 'HomeController';
$action = $parts[1] ?? 'index';
$params = array_slice($parts, 2);

// Load controller
$controllerFile = __DIR__ . '/../app/Controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    $controllerClass = "App\\Controllers\\{$controllerName}";
    $controller = new $controllerClass();
    
    if (method_exists($controller, $action)) {
        call_user_func_array([$controller, $action], $params);
    } else {
        http_response_code(404);
        echo "<h1>404 - Action not found</h1>";
        echo "<p>Action <strong>{$action}</strong> does not exist in {$controllerName}.</p>";
    }
} else {
    http_response_code(404);
    echo "<h1>404 - Page not found</h1>";
    echo "<p>Controller <strong>{$controllerName}</strong> does not exist.</p>";
    echo "<p><a href='" . BASE_URL . "'>← Về trang chủ</a></p>";
}
