<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['student']);

$pdo = getDB();
$userId = (int) $currentUser['id'];

// Get assigned active sessions
$assignedStmt = $pdo->prepare("
    SELECT DISTINCT
        es.id as session_id,
        es.title,
        e.title AS exam_title,
        e.duration_minutes,
        e.total_questions,
        es.start_time,
        es.end_time,
        es.mode,
        es.max_attempts,
        (SELECT COUNT(*) FROM exam_results WHERE student_id = ? AND session_id = es.id) as attempts_used
    FROM exam_sessions es
    JOIN exams e ON e.id = es.exam_id
    JOIN session_classes sc ON sc.session_id = es.id
    JOIN class_students cs ON cs.class_id = sc.class_id
    WHERE cs.student_id = ? AND es.is_active = 1
    ORDER BY es.start_time ASC
");
$assignedStmt->execute([$userId, $userId]);
$assignedSessions = $assignedStmt->fetchAll();

function getStudentSessionStatus(array $session): array
{
    $now = time();
    $start = strtotime($session['start_time']);
    $end = strtotime($session['end_time']);

    if ($start && $now < $start) {
        return ['badge waiting', 'Chưa mở', false];
    }
    if ($end && $now > $end) {
        return ['badge closed', 'Đã đóng', false];
    }
    if ($session['max_attempts'] > 0 && $session['attempts_used'] >= $session['max_attempts']) {
        return ['badge closed', 'Hết lượt thi', false];
    }
    return ['badge active', 'Đang diễn ra', true];
}

$pageTitle = "Kỳ thi của tôi | Portal Học viên";
$hideNavbar = true;
require __DIR__ . '/layouts/header.php';
?>

<?php require __DIR__ . '/layouts/student_sidebar.php'; ?>

<style>
.exam-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 20px;
    margin-top: 15px;
}
.exam-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(24, 32, 51, .03);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}
.exam-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(24, 32, 51, .06);
    border-color: var(--primary);
}
.exam-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}
.exam-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--text);
    margin: 0;
    line-height: 1.4;
    flex: 1;
}
.exam-subject {
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 15px;
}
.exam-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 20px;
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid var(--line);
}
.exam-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.exam-meta-item i {
    color: var(--primary);
    width: 16px;
    text-align: center;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
    background: var(--surface);
    border: 1px dashed var(--line);
    border-radius: 8px;
    margin-top: 15px;
}
.empty-state i {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 16px;
}
.empty-state h3 {
    margin: 0 0 8px;
    color: var(--text);
    font-size: 18px;
}
.empty-state p {
    margin: 0;
    font-size: 14px;
}
</style>

<div class="main-content">
    <div style="border-bottom: 2px solid var(--line); padding-bottom: 15px; margin-bottom: 25px;">
        <h1 style="margin: 0; color: var(--sidebar-bg); font-size: 24px;">
            <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i> Kỳ thi của tôi
        </h1>
        <p style="margin: 4px 0 0; color: var(--muted); font-size: 13px;">Danh sách tất cả các ca thi và kỳ thi đã được gán cho lớp của bạn.</p>
    </div>

    <?php if ($assignedSessions): ?>
        <div class="exam-grid">
            <?php foreach ($assignedSessions as $session): ?>
                <?php [$badgeClass, $statusText, $canTake] = getStudentSessionStatus($session); ?>
                <div class="exam-card">
                    <div class="exam-header">
                        <h3 class="exam-title"><?= h($session['title']) ?></h3>
                        <span class="<?= h($badgeClass) ?>"><?= h($statusText) ?></span>
                    </div>
                    <div class="exam-subject">
                        <i class="fa-solid fa-book-bookmark"></i> Môn/Đề: <?= h($session['exam_title']) ?>
                    </div>
                    
                    <div class="exam-meta">
                        <div class="exam-meta-item">
                            <i class="fa-solid fa-clock"></i>
                            <span><?= h($session['duration_minutes']) ?> phút</span>
                        </div>
                        <div class="exam-meta-item">
                            <i class="fa-solid fa-circle-question"></i>
                            <span><?= h($session['total_questions']) ?> câu hỏi</span>
                        </div>
                        <div class="exam-meta-item">
                            <i class="fa-solid fa-hourglass-start"></i>
                            <span><?= displayDateTime($session['start_time']) ?></span>
                        </div>
                        <div class="exam-meta-item">
                            <i class="fa-solid fa-hourglass-end"></i>
                            <span><?= displayDateTime($session['end_time']) ?></span>
                        </div>
                        <div class="exam-meta-item">
                            <i class="fa-solid fa-retweet"></i>
                            <span>Lượt thi: <?= (int)$session['attempts_used'] ?>/<?= $session['max_attempts'] ?: '∞' ?></span>
                        </div>
                        <div class="exam-meta-item">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span><?= $session['mode'] === 'practice' ? 'Luyện tập' : 'Chính thức' ?></span>
                        </div>
                    </div>

                    <div style="margin-top: auto;">
                        <?php if ($canTake): ?>
                            <a href="take_exam.php?session_id=<?= h($session['session_id']) ?>" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px; font-weight: 700;">
                                <i class="fa-solid fa-play"></i> Bắt đầu làm bài
                            </a>
                        <?php else: ?>
                            <button class="btn" style="width: 100%; justify-content: center; padding: 11px; background: #e2e8f0; color: #94a3b8; cursor: not-allowed; font-weight: 700;" disabled>
                                KHÔNG THỂ THAM GIA
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-mug-hot"></i>
            <h3>Chưa có kỳ thi nào</h3>
            <p>Tuyệt vời! Hiện tại bạn không có ca thi hoặc kỳ thi nào cần hoàn thành.</p>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
