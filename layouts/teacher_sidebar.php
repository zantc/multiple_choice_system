<?php
/**
 * Shared teacher sidebar portal layout template.
 * Assumes app.css is loaded.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fa-solid fa-graduation-cap"></i> E-Exam Pro</h2>
        <div class="role-badge teacher">Teacher Portal</div>
    </div>
    
    <div class="sidebar-menu teacher">
        <a href="teacher_dashboard.php" class="<?= $currentPage === 'teacher_dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> Tổng quan
        </a>
        <a href="teacher_questions.php" class="<?= $currentPage === 'teacher_questions.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-database"></i> Ngân hàng câu hỏi
        </a>
        <a href="teacher_exams.php" class="<?= $currentPage === 'teacher_exams.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-signature"></i> Tạo đề thi
        </a>
        <a href="teacher_sessions.php" class="<?= $currentPage === 'teacher_sessions.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-check"></i> Tổ chức kỳ thi
        </a>
        <a href="teacher_results.php" class="<?= $currentPage === 'teacher_results.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-marker"></i> Chấm điểm & Đánh giá
        </a>
        <a href="teacher_analytics.php" class="<?= $currentPage === 'teacher_analytics.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i> Phân tích kết quả
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="user-info teacher">
            <i class="fa-solid fa-circle-user"></i>
            <span><?= htmlspecialchars($_SESSION['full_name'] ?? 'Giáo viên', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
    </div>
</div>
