<?php
// =============================================
// CẤU HÌNH TRUNG TÂM
// =============================================
// Tất cả thông tin nhạy cảm (DB, SMTP, API keys)
// được lưu trong file .env.php (KHÔNG commit lên Git).
// =============================================

$envPath = __DIR__ . '/.env.php';
if (!file_exists($envPath)) {
    die(
        '<h2 style="color:red;">⚠️ Missing configuration file!</h2>'
        . '<p>Copy the <code>.env.example.php</code> file to <code>.env.php</code> and fill in the real information.</p>'
    );
}
require_once $envPath;

function getDB(): PDO
{
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'utf8mb4'");
    return $pdo;
}

function getBaseUrl(): string
{
    if (APP_BASE_URL !== '') {
        return rtrim(APP_BASE_URL, '/');
    }
    $scheme    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $dir       = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
    return $scheme . '://' . $host . $dir;
}

/**
 * Tạo và cấu hình một instance PHPMailer đã sẵn sàng gửi.
 */
function createMailer(): PHPMailer\PHPMailer\PHPMailer
{
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(SMTP_USER, SMTP_NAME);
    $mail->isHTML(true);
    return $mail;
}

/**
 * Bọc nội dung HTML trong layout email chuẩn.
 */
function emailLayout(string $content): string
{
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
    <body style='font-family:Arial,sans-serif;background:#f4f5f7;margin:0;padding:20px;'>
      <table width='100%' cellpadding='0' cellspacing='0'>
        <tr><td align='center'>
          <table style='max-width:600px;width:100%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,.1);margin:20px 0;' cellpadding='0' cellspacing='0'>
            <tr>
              <td style='background:linear-gradient(to right,#5c6bc0,#512da8);padding:30px 20px;text-align:center;'>
                <h1 style='margin:0;color:#fff;font-size:24px;letter-spacing:1px;text-transform:uppercase;'>Online Quiz System</h1>
              </td>
            </tr>
            <tr>
              <td style='padding:30px 40px;color:#333;line-height:1.6;font-size:16px;'>
                {$content}
              </td>
            </tr>
            <tr>
              <td style='background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#999;border-top:1px solid #eee;'>
                <p style='margin:0;'>This is an automated email. Please do not reply.</p>
                <p style='margin:5px 0 0;'>&copy; " . date('Y') . " NhqIT. All rights reserved.</p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body></html>";
}
