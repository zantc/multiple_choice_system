<?php
/**
 * Authentication and authorization middleware.
 * Centralizes session access checks, role restrictions, and cached user queries.
 */

/**
 * Retrieve current user record from database, cached statically per request.
 */
function getCurrentUser(PDO $pdo): ?array
{
    static $currentUser = false;

    if ($currentUser !== false) {
        return $currentUser;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        $currentUser = null;
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, phone, avatar, is_active, is_verified FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active']) {
        // User disabled or deleted
        $currentUser = null;
        return null;
    }

    $currentUser = $user;
    return $currentUser;
}

/**
 * Require active logged-in session. Redirects to index.php if absent.
 */
function requireLogin(): array
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }

    $pdo = getDB();
    $user = getCurrentUser($pdo);

    if (!$user) {
        // Session invalid or user blocked
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }

    return $user;
}

/**
 * Require specific user role(s) to access the page.
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles): array
{
    $user = requireLogin();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $safeRoleText = htmlspecialchars(implode(', ', $roles), ENT_QUOTES, 'UTF-8');
        echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>Access Denied</title>";
        echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f4f6fb;color:#333;}</style></head>";
        echo "<body><h2 style=\"color:#b91c1c;\">Bạn không có quyền truy cập trang này.</h2>";
        echo "<p>Trang này yêu cầu quyền: <b>{$safeRoleText}</b>.</p>";
        echo "<p><a href=\"dashboard.php\" style=\"color:#512da8;font-weight:bold;\">Quay lại Dashboard</a></p></body></html>";
        exit;
    }

    return $user;
}
