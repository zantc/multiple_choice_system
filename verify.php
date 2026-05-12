<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Confirm account activation for online quiz system.">
    <title>Verify Account | Online Quiz System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
<div class="auth-box" style="width: 520px; min-height: 320px; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center;">
    <?php
    $token = trim($_GET['token'] ?? '');

    if (!$token) {
        echo "<h2 style=\"color:var(--danger);\"><i class=\"fa-solid fa-triangle-exclamation\"></i> Verification code not found.</h2><p>Please check the link in your email again.</p>";
    } elseif (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
        echo "<h2 style=\"color:var(--danger);\"><i class=\"fa-solid fa-circle-xmark\"></i> Invalid token!</h2><p>The verification code has an incorrect format.</p>";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?")
                ->execute([$user['id']]);
            echo "<h2 style=\"color:var(--success);\"><i class=\"fa-solid fa-circle-check\"></i> Verification successful!</h2><p>Your account has been activated.</p>";
        } else {
            echo "<h2 style=\"color:var(--danger);\"><i class=\"fa-solid fa-circle-xmark\"></i> Invalid link!</h2><p>The verification code does not exist or has already been activated.</p>";
        }
    }
    ?>
    <a href="index.php" class="btn btn-primary" style="margin-top: 25px; padding: 12px 30px; font-size: 14px;">Back to Login</a>
</div>
</body>
</html>
