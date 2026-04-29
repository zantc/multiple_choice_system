<?php
// =============================================
// FILE MẪU CẤU HÌNH - COPY THÀNH .env.php VÀ ĐIỀN THÔNG TIN
// =============================================
// Hướng dẫn:
//   1. Copy file này thành `.env.php`
//   2. Điền đầy đủ thông tin thật vào file `.env.php`
//   3. KHÔNG được commit file `.env.php` lên Git
// =============================================

// --- Database ---
define('DB_HOST',   'localhost');
define('DB_NAME',   'your_database_name');
define('DB_USER',   'your_db_username');
define('DB_PASS',   'your_db_password');

// --- SMTP (Email) ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_NAME', 'Online Quiz System');

// --- App ---
// Để trống để tự detect, hoặc điền URL cứng: 'https://yourdomain.com'
define('APP_BASE_URL', '');
