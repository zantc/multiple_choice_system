<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['teacher']);

$pdo = getDB();
$teacher_id = (int) $currentUser['id'];

// Get basic stats for the teacher dashboard
$stats = [
    'total_questions' => $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
    'total_exams' => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
    'active_sessions' => $pdo->query("SELECT COUNT(*) FROM exam_sessions WHERE is_active = 1")->fetchColumn(),
];

// Get recent exams
$recent_exams = $pdo->query("SELECT title, duration_minutes as duration, created_at FROM exams ORDER BY created_at DESC LIMIT 5")->fetchAll();

$pageTitle = "Tổng quan Portal Giáo viên | E-Exam Pro";
$hideNavbar = true; // Bỏ qua thanh header chung để dùng trọn vẹn sidebar chuyên dụng
require __DIR__ . '/layouts/header.php';
?>

<?php require __DIR__ . '/layouts/teacher_sidebar.php'; ?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--line); padding-bottom: 15px; margin-bottom: 25px;">
        <h1 style="margin: 0; color: var(--sidebar-bg); font-size: 24px;">
            <i class="fa-solid fa-house" style="color: var(--primary);"></i> Tổng quan hệ thống Portal Giáo viên
        </h1>
        <span style="font-size: 13px; color: var(--muted);">Vai trò: <b style="color: #e74c3c;">Giáo viên</b></span>
    </div>
    
    <div class="grid-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card" style="border-left: 4px solid #3498db; text-align: center; margin: 0;">
            <i class="fa-solid fa-database" style="font-size: 32px; color: #3498db; margin-bottom: 12px;"></i>
            <h3 style="margin: 0; font-size: 28px; border: none; padding: 0; justify-content: center;"><?= displayNumber($stats['total_questions'], 0) ?></h3>
            <p style="margin: 6px 0 0; color: var(--muted); font-size: 13px; font-weight: 500;">Câu hỏi trong ngân hàng</p>
        </div>
        
        <div class="card" style="border-left: 4px solid #2ecc71; text-align: center; margin: 0;">
            <i class="fa-solid fa-file-signature" style="font-size: 32px; color: #2ecc71; margin-bottom: 12px;"></i>
            <h3 style="margin: 0; font-size: 28px; border: none; padding: 0; justify-content: center;"><?= displayNumber($stats['total_exams'], 0) ?></h3>
            <p style="margin: 6px 0 0; color: var(--muted); font-size: 13px; font-weight: 500;">Đề thi cấu trúc đã tạo</p>
        </div>
        
        <div class="card" style="border-left: 4px solid #f39c12; text-align: center; margin: 0;">
            <i class="fa-solid fa-calendar-check" style="font-size: 32px; color: #f39c12; margin-bottom: 12px;"></i>
            <h3 style="margin: 0; font-size: 28px; border: none; padding: 0; justify-content: center;"><?= displayNumber($stats['active_sessions'], 0) ?></h3>
            <p style="margin: 6px 0 0; color: var(--muted); font-size: 13px; font-weight: 500;">Kỳ thi đang mở</p>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="margin: 0; border: none; padding: 0; color: var(--sidebar-bg);"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Đề thi mới tạo gần đây</h3>
            <a href="teacher_exams.php" class="btn btn-primary" style="font-size: 12px;"><i class="fa-solid fa-plus"></i> Tạo đề mới</a>
        </div>
        
        <?php if (count($recent_exams) > 0): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tên đề thi</th>
                        <th>Thời gian làm bài</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_exams as $exam): ?>
                    <tr>
                        <td><b style="color: var(--text);"><?= htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 600;"><?= $exam['duration'] ?> phút</span></td>
                        <td><span style="color: #475569; font-size: 13px;"><?= displayDateTime($exam['created_at']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty">Chưa có đề thi nào được tạo trong hệ thống.</div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
