<?php
/**
 * Shared flash message handler.
 * Simplifies session flash setting, retrieval, and automated redirection.
 */

/**
 * Set a flash message in session.
 */
function setFlash(string $type, string $message, string $key = 'app_flash'): void
{
    $_SESSION[$key] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Get and clear a flash message from session.
 */
function getFlash(string $key = 'app_flash'): ?array
{
    $flash = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    return $flash;
}

/**
 * Render flash message as HTML alert if present.
 */
function renderFlash(string $key = 'app_flash'): void
{
    $flash = getFlash($key);
    if ($flash) {
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $msg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        echo "<div class=\"alert {$type}\">{$msg}</div>";
    }
}

/**
 * Set flash message and redirect immediately.
 * If URL is empty, redirects to current script path.
 */
function flashAndRedirect(string $type, string $message, string $urlOrSuffix = '', string $key = 'app_flash'): void
{
    // For legacy pages like exam_management using custom flash keys:
    $actualKey = str_contains($_SERVER['SCRIPT_NAME'], 'exam_management.php') ? 'exam_management_flash' : $key;
    
    setFlash($type, $message, $actualKey);

    $dest = $urlOrSuffix;
    if ($dest === '' || str_starts_with($dest, '?')) {
        $dest = basename($_SERVER['SCRIPT_NAME']) . $dest;
    }

    header('Location: ' . $dest);
    exit;
}
