<?php
require 'config.php';

$pdo = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Confirm account activation for online quiz system.">
    <title>Verify Account | Online Quiz System</title>
    <style>
        body { font-family: 'Montserrat', sans-serif; background: linear-gradient(to right, #e2e2e2, #c9d6ff); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,.2); text-align: center; max-width: 480px; width: 90%; }
        .box h2 { margin-bottom: 10px; }
        .box p  { color: #555; font-size: 15px; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 28px; background: #512da8; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .btn:hover { background: #4527a0; }
    </style>
</head>
<body>
<div class="box">
    <?php
    $token = trim($_GET['token'] ?? '');

    if (!$token) {
        echo "<h2>Verification code not found.</h2><p>Please check the link in your email again.</p>";
    } elseif (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
        echo "<h2><span style='color:red;'>✘</span> Invalid token!</h2><p>The verification code has an incorrect format.</p>";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?")
                ->execute([$user['id']]);
            echo "<h2><span style='color:green;'>✔</span> Verification successful!</h2><p>Your account has been activated.</p>";
        } else {
            echo "<h2><span style='color:red;'>✘</span> Invalid link!</h2><p>The verification code does not exist or has already been activated.</p>";
        }
    }
    ?>
    <a href="index.php" class="btn">Back to Login</a>
</div>
</body>
</html>
