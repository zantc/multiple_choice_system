<?php
session_start();

require 'config.php';
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception;

$pdo = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert error'>Invalid email address!</div>";
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime('+15 minutes'));

            $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?")
                ->execute([$token, $expires, $email]);

            $reset_link = getBaseUrl() . "/reset_password.php?token={$token}";
            $content = "
                <h2 style='color:#512da8;margin-top:0;text-align:center;'>Password Recovery</h2>
                <p>Hi <b>{$user['full_name']}</b>,</p>
                <p>We received a request to reset your password. Please click the button below:</p>
                <div style='text-align:center;margin:35px 0;'>
                    <a href='{$reset_link}' style='background:#512da8;color:#fff;padding:14px 35px;text-decoration:none;border-radius:8px;font-weight:bold;font-size:16px;display:inline-block;'>🔒 Reset Password Now</a>
                </div>
                <p style='font-size:14px;color:#d32f2f;font-weight:bold;text-align:center;'>⚠️ The link is only valid for 15 minutes.</p>
                <p style='font-size:13px;color:#777;word-break:break-all;background:#f1f3f9;padding:10px;border-radius:5px;'>
                    <a href='{$reset_link}' style='color:#512da8;text-decoration:none;'>{$reset_link}</a>
                </p>
                <hr style='border:none;border-top:1px solid #eee;margin:25px 0;'>
                <p style='font-size:14px;color:#777;margin-bottom:0;'>If you did not request a password reset, please ignore this email.</p>";

            try {
                $mail = createMailer();
                $mail->addAddress($email, $user['full_name']);
                $mail->Subject = 'Password recovery request';
                $mail->Body    = emailLayout($content);
                $mail->send();
                $message = "<div class='alert success'>Recovery link sent! Please check your email.</div>";
            } catch (Exception $e) {
                $pdo->prepare("UPDATE users SET reset_token = NULL, reset_expires_at = NULL WHERE email = ?")
                    ->execute([$email]);
                $message = "<div class='alert error'>Could not send email: {$mail->ErrorInfo}</div>";
            }
        } else {
            $message = "<div class='alert success'>If the email exists in our system, we have sent a recovery link to you.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recover password for online quiz system account.">
    <link rel="stylesheet" href="style.css">
    <title>Forgot Password | Online Quiz System</title>
</head>
<body>
<div class="container" style="width:400px;min-height:350px;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:40px;">
    <form method="POST" style="width:100%;display:flex;flex-direction:column;align-items:center;padding:0;">
        <h1 style="margin-bottom:10px;">Forgot Password</h1>
        <p style="text-align:center;font-size:13px;margin-bottom:20px;">Enter your email to receive a password reset link.</p>
        <?= $message ?>
        <input type="email" name="email" placeholder="Enter your email" required style="width:100%;" autocomplete="email">
        <button type="submit" style="width:100%;margin-top:15px;">Send Link</button>
        <a href="index.php" style="margin-top:20px;font-weight:bold;">Back to Login</a>
    </form>
</div>
</body>
</html>
