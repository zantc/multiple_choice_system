<?php
/**
 * Shared application bootstrap file.
 * Required at the very beginning of every entry-point PHP file.
 * Handles sessions, timezone, database connection, common includes, and CSRF security.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Bangkok');

// Include core configuration and shared components
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/auth.php';

/**
 * Generate and retrieve current CSRF token string.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render hidden CSRF token input field for forms.
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}

/**
 * Verify CSRF token from POST payload. Throws or aborts if invalid.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (empty($submitted) || empty($stored) || !hash_equals($stored, $submitted)) {
        http_response_code(419);
        die("<div style=\"font-family:sans-serif;padding:30px;color:#b91c1c;\"><b>Lỗi bảo mật (CSRF Token không hợp lệ hoặc đã hết hạn).</b> Vui lòng tải lại trang và thử lại.</div>");
    }
}

// Automatically apply CSRF verification for all standard form submissions if action is specified
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
}
