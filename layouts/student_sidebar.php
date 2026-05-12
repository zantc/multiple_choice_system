<?php
/**
 * Shared student sidebar portal layout template.
 * Assumes app.css is loaded.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fa-solid fa-graduation-cap"></i> E-Exam Pro</h2>
        <div class="role-badge student">Student Portal</div>
    </div>
    
    <div class="sidebar-menu student">
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-id-card"></i> Thông tin cá nhân
        </a>
        <a href="student_exams.php" class="<?= $currentPage === 'student_exams.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-check"></i> Kỳ thi của tôi
        </a>
        <a href="student_history.php" class="<?= $currentPage === 'student_history.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> Lịch sử & Kết quả
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="user-info student">
            <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../' . $currentUser['avatar'])): ?>
                <img src="<?= htmlspecialchars($currentUser['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="Avatar" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 2px solid #2ecc71; margin-right: 4px;">
            <?php else: ?>
                <i class="fa-solid fa-circle-user"></i>
            <?php endif; ?>
            <span><?= htmlspecialchars($currentUser['full_name'] ?? 'Học viên', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
    </div>
</div>
