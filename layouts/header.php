<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Online Quiz System', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <?= $extraHead ?? '' ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">

<?php if (!($hideNavbar ?? false)): ?>
    <div class="navbar">
        <h2>
            <?php if (!empty($headerIcon)): ?>
                <i class="<?= htmlspecialchars($headerIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
            <?php endif; ?>
            <?= htmlspecialchars($headerTitle ?? $pageTitle ?? 'Quản trị hệ thống', ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <div class="nav-actions">
            <?php if (!empty($customNavActions)): ?>
                <?= $customNavActions ?>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($backUrl ?? 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-arrow-left"></i> Quay lại
            </a>
            <a href="logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Thoát
            </a>
        </div>
    </div>
<?php endif; ?>
