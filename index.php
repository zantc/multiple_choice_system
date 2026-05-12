<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception;

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $pdo = getDB();
    $user = getCurrentUser($pdo);
    if ($user) {
        header('Location: ' . ($user['role'] === 'teacher' ? 'teacher_dashboard.php' : 'dashboard.php'));
        exit;
    }
}

$pdo = getDB();
$message = '';
$is_register_panel = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ĐĂNG KÝ ---
    if ($action === 'register') {
        $is_register_panel = true;
        $fullName     = trim($_POST['full_name'] ?? '');
        $email        = trim($_POST['email']     ?? '');
        $password     = trim($_POST['password']  ?? '');

        if (empty($fullName) || empty($email) || empty($password)) {
            $message = "<div class='alert error'>Please fill in all fields!</div>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='alert error'>Invalid email address!</div>";
        } elseif (strlen($password) < 6) {
            $message = "<div class='alert error'>Password must be at least 6 characters!</div>";
        } else {
            // Auto-generate unique username from email
            $reg_username = explode('@', $email)[0];
            $base_username = preg_replace('/[^a-zA-Z0-9]/', '', $reg_username);
            if (empty($base_username)) {
                $base_username = 'user';
            }
            $reg_username = $base_username;
            $suffix = 1;
            while (true) {
                $checkUser = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
                $checkUser->execute([$reg_username]);
                if (!$checkUser->fetch()) {
                    break;
                }
                $reg_username = $base_username . $suffix;
                $suffix++;
            }

            $check = $pdo->prepare("SELECT email FROM users WHERE email = ?");
            $check->execute([$email]);
            $existing = $check->fetch();

            if ($existing) {
                $message = "<div class='alert error'>This email is already in use!</div>";
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
                        $message = "<div class='alert error'>Failed to send confirmation email (" . h($mail->ErrorInfo) . "). Please try again!</div>";
                    }
                } catch (PDOException $e) {
                    $message = "<div class='alert error'>A system error occurred! Please try again later.</div>";
                }
            }
        }
    }

    // --- ĐĂNG NHẬP ---
    if ($action === 'login') {
        $login_id = trim($_POST['login_id'] ?? '');
        $password  = trim($_POST['password']  ?? '');

        if (empty($login_id) || empty($password)) {
            $message = "<div class='alert error'>Please fill in all fields!</div>";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$login_id, $login_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (!(int) $user['is_verified']) {
                    $message = "<div class='alert error'>Account not verified! Please check your email.</div>";
                } elseif (!(int) $user['is_active']) {
                    $message = "<div class='alert error'>Account has been disabled by Administrator.</div>";
                } else {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role']      = $user['role'];
                    
                    if ($user['role'] === 'teacher') {
                        header('Location: teacher_dashboard.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit;
                }
            } else {
                $message = "<div class='alert error'>Incorrect account or password!</div>";
            }
        }
    }

    // PRG: Lưu flash vào session rồi redirect về GET
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
    <link rel="stylesheet" href="assets/css/app.css">
    <title>Login | Online Quiz System</title>
</head>
<body class="auth-page">

<div class="auth-box <?= $is_register_panel ? 'active' : '' ?>" id="container">

    <div class="form-container sign-up">
        <form method="POST">
            <?= csrf_field() ?>
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
            <input type="text"     name="full_name" placeholder="Name"            required autocomplete="name">
            <input type="email"    name="email"     placeholder="Email"           required autocomplete="email">
            <input type="password" name="password"  placeholder="Password"        required autocomplete="new-password">
            <button type="submit">Sign Up</button>
        </form>
    </div>

    <div class="form-container sign-in">
        <form method="POST">
            <?= csrf_field() ?>
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
            <input type="text"     name="login_id" placeholder="Username or Email" required autocomplete="off">
            <input type="password" name="password"  placeholder="Password"        required autocomplete="new-password">
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

<script src="assets/js/app.js"></script>
<script>
    const container = document.getElementById('container');
    const registerBtn = document.getElementById('register');
    const loginBtn = document.getElementById('login');

    if (registerBtn && container) {
        registerBtn.addEventListener('click', () => {
            container.classList.add("active");
        });
    }

    if (loginBtn && container) {
        loginBtn.addEventListener('click', () => {
            container.classList.remove("active");
        });
    }
</script>
</body>
</html>
