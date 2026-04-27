<?php
/**
 * Base Controller - All controllers extend this
 */

namespace App\Controllers;

use App\Helpers\Session;

class BaseController
{
    /**
     * Render a view with data
     */
    protected function view(string $viewPath, array $data = []): void
    {
        // Extract data to variables
        extract($data);
        
        // Flash message
        $flash = Session::getFlash();
        
        // Current user info
        $currentUser = [
            'id' => Session::getUserId(),
            'role' => Session::getUserRole(),
            'name' => Session::get('user_name'),
        ];

        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $viewPath) . '.php';
        
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<h1>View not found</h1><p>{$viewFile}</p>";
        }
    }

    /**
     * Redirect to a URL
     */
    protected function redirect(string $url): void
    {
        header("Location: " . BASE_URL . "/{$url}");
        exit;
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Get POST data
     */
    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get GET data
     */
    protected function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Check if request is POST
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Require login - redirect to login if not logged in
     */
    protected function requireLogin(): void
    {
        if (!Session::isLoggedIn()) {
            Session::setFlash('error', 'Vui lòng đăng nhập để tiếp tục.');
            $this->redirect('auth/login');
        }
    }

    /**
     * Require specific role
     */
    protected function requireRole(string ...$roles): void
    {
        $this->requireLogin();
        if (!in_array(Session::getUserRole(), $roles)) {
            Session::setFlash('error', 'Bạn không có quyền truy cập trang này.');
            $this->redirect('');
        }
    }
}
