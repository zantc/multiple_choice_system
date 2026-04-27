<?php
/**
 * Application Configuration
 */

// Base URL - adjust based on your XAMPP setup
define('BASE_URL', $_ENV['APP_URL'] ?? 'http://localhost/multiple_choice_system/public');
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Hệ Thống Thi Trắc Nghiệm');
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');

// Path constants
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', ROOT_PATH . '/resources/views');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// Pagination
define('ITEMS_PER_PAGE', 10);

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');
