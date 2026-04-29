<?php
session_start();

require 'config.php';
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception;

$pdo = getDB();
$message = '';
$is_register_panel = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- ĐĂNG KÝ ---
    if (($_POST['action'] ?? '') === 'register') {
        $is_register_panel = true;
        $fullName     = trim($_POST['full_name'] ?? '');
        $reg_username = trim($_POST['username']  ?? '');
        $email        = trim($_POST['email']     ?? '');
        $password     = trim($_POST['password']  ?? '');

        if (empty($fullName) || empty($reg_username) || empty($email) || empty($password)) {
            $message = "<div class='alert error'>Please fill in all fields!</div>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='alert error'>Invalid email address!</div>";
        } elseif (preg_match('/\s/', $reg_username)) {
            $message = "<div class='alert error'>Username cannot contain spaces!</div>";
        } elseif (strlen($password) < 6) {
            $message = "<div class='alert error'>Password must be at least 6 characters!</div>";
        } else {
            $check = $pdo->prepare("SELECT email, username FROM users WHERE email = ? OR username = ?");
            $check->execute([$email, $reg_username]);
            $existing = $check->fetch();

            if ($existing) {
                $message = $existing['email'] === $email
                    ? "<div class='alert error'>This email is already in use!</div>"
                    : "<div class='alert error'>This username is already taken!</div>";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, verification_token) VALUES (?, ?, ?, ?, ?)");
                    $token = bin2hex(random_bytes(16));
                    $stmt->execute([$fullName, $reg_username, $email, password_hash($password, PASSWORD_DEFAULT), $token]);
                    $newUserId = $pdo->lastInsertId();

                    $verify_link = getBaseUrl() . "/verify.php?token={$token}";
                    $content = "
                        <h2 style='color:#512da8;margin-top:0;'>Hello, {$fullName}!</h2>
                        <p>Thank you for registering. Username: <b>{$reg_username}</b></p>
                        <p>Please verify your email by clicking the button below:</p>
                        <div style='text-align:center;margin:35px 0;'>
                            <a href='{$verify_link}' style='background:#512da8;color:#fff;padding:14px 35px;text-decoration:none;border-radius:8px;font-weight:bold;font-size:16px;display:inline-block;'>✔ Activate Account</a>
                        </div>
                        <p style='font-size:13px;color:#777;'>If the button doesn't work: <a href='{$verify_link}' style='color:#512da8;'>{$verify_link}</a></p>";

                    try {
                        $mail = createMailer();
                        $mail->addAddress($email, $fullName);
                        $mail->Subject = 'Account registration confirmation';
                        $mail->Body    = emailLayout($content);
                        $mail->send();

                        $message = "<div class='alert success'>Registration successful! Please check your email to activate your account.</div>";
                        $is_register_panel = false;
                    } catch (Exception $e) {
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$newUserId]);
                        $message = "<div class='alert error'>Failed to send confirmation email ({$mail->ErrorInfo}). Please try again!</div>";
                    }
                } catch (PDOException $e) {
                    $message = "<div class='alert error'>A system error occurred! Please try again later.</div>";
                }
            }
        }
    }

    // --- ĐĂNG NHẬP ---
    if (($_POST['action'] ?? '') === 'login') {
        $login_id = trim($_POST['login_id'] ?? '');
        $password  = trim($_POST['password']  ?? '');

        if (empty($login_id) || empty($password)) {
            $message = "<div class='alert error'>Please fill in all fields!</div>";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$login_id, $login_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_verified']) {
                    $message = "<div class='alert error'>Account not verified! Please check your email.</div>";
                } else {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['username']  = $user['username'];
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $message = "<div class='alert error'>Incorrect account or password!</div>";
            }
        }
    }

    // PRG: Lưu flash vào session rồi redirect về GET
    // Ngăn lỗi hiện lại khi F5 hoặc browser restore POST request
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_panel']   = $is_register_panel ? 'register' : 'login';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Đọc và xóa session flash (chỉ chạy khi GET)
if (isset($_SESSION['flash_message'])) {
    $message           = $_SESSION['flash_message'];
    $is_register_panel = ($_SESSION['flash_panel'] === 'register');
    unset($_SESSION['flash_message'], $_SESSION['flash_panel']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Log in or register for the online quiz system account.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <title>Login | Online Quiz System</title>
</head>
<body>
<div class="container <?= $is_register_panel ? 'active' : '' ?>" id="container">

    <div class="form-container sign-up">
        <form method="POST">
            <h1>Create Account</h1>
            <div class="social-icons">
                <a href="#" class="icon" aria-label="Google"><i class="fa-brands fa-google-plus-g"></i></a>
                <a href="#" class="icon" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="icon" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
                <a href="#" class="icon" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
            </div>
            <span>or use your email for registration</span>
            <?= $is_register_panel ? $message : '' ?>
            <input type="hidden" name="action" value="register">
            <input type="text"     name="full_name" placeholder="Full Name"       required autocomplete="name">
            <input type="text"     name="username"  placeholder="Username"        required autocomplete="username">
            <input type="email"    name="email"     placeholder="Email"           required autocomplete="email">
            <input type="password" name="password"  placeholder="Password"        required autocomplete="new-password">
            <button type="submit">Sign Up</button>
        </form>
    </div>

    <div class="form-container sign-in">
        <form method="POST">
            <h1>Sign In</h1>
            <div class="social-icons">
                <a href="#" class="icon" aria-label="Google"><i class="fa-brands fa-google-plus-g"></i></a>
                <a href="#" class="icon" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="icon" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
                <a href="#" class="icon" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
            </div>
            <span>or use your account</span>
            <?= !$is_register_panel ? $message : '' ?>
            <input type="hidden"   name="action"   value="login">
            <input type="text"     name="login_id" placeholder="Email or Username" required autocomplete="username">
            <input type="password" name="password"  placeholder="Password"          required autocomplete="current-password">
            <a href="forgot_password.php">Forget Your Password?</a>
            <button type="submit">Sign In</button>
        </form>
    </div>

    <div class="toggle-container">
        <div class="toggle">
            <div class="toggle-panel toggle-left">
                <h1>Welcome Back!</h1>
                <p>Enter your personal details to use all of site features</p>
                <button class="hidden" id="login" type="button">Sign In</button>
            </div>
            <div class="toggle-panel toggle-right">
                <h1>Hello, Friend!</h1>
                <p>Register with your personal details to use all of site features</p>
                <button class="hidden" id="register" type="button">Sign Up</button>
            </div>
        </div>
    </div>
</div>
<script src="script.js"></script>
</body>
</html>