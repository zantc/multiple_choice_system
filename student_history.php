<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['student']);

$pdo = getDB();
$userId = (int) $currentUser['id'];

// Get exam history
$stmt = $pdo->prepare("
    SELECT er.id, er.score, er.correct_count, er.total_questions, er.submitted_at, 
           es.title as session_title, e.title as exam_title, e.pass_score,
           (er.score >= e.pass_score) as is_passed
    FROM exam_results er
    JOIN exam_sessions es ON es.id = er.session_id
    JOIN exams e ON e.id = er.exam_id
    WHERE er.student_id = ? AND er.submitted_at IS NOT NULL
    ORDER BY er.submitted_at DESC
");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = "Lịch sử & Kết quả | Portal Học viên";
$hideNavbar = true;
require __DIR__ . '/layouts/header.php';
?>

<?php require __DIR__ . '/layouts/student_sidebar.php'; ?>

<style>
.history-table-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(24, 32, 51, .03);
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
    background: var(--surface);
    border: 1px dashed var(--line);
    border-radius: 8px;
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
.score-badge {
    font-size: 15px;
    font-weight: 700;
}
.score-badge.passed {
    color: var(--success);
}
.score-badge.failed {
    color: var(--danger);
}
</style>

<div class="main-content">
    <div style="border-bottom: 2px solid var(--line); padding-bottom: 15px; margin-bottom: 25px;">
        <h1 style="margin: 0; color: var(--sidebar-bg); font-size: 24px;">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Lịch sử làm bài & Kết quả
        </h1>
        <p style="margin: 4px 0 0; color: var(--muted); font-size: 13px;">Theo dõi lịch sử làm bài, điểm số chi tiết và trạng thái hoàn thành các kỳ thi.</p>
    </div>

    <div class="history-table-card">
        <?php if ($history): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kỳ thi</th>
                            <th>Môn / Đề thi</th>
                            <th>Thời gian nộp</th>
                            <th>Điểm số</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><strong style="color: var(--text);"><?= h($row['session_title']) ?></strong></td>
                                <td><span style="font-weight: 500; color: var(--primary);"><?= h($row['exam_title']) ?></span></td>
                                <td><?= displayDateTime($row['submitted_at']) ?></td>
                                <td>
                                    <span class="score-badge <?= $row['is_passed'] ? 'passed' : 'failed' ?>">
                                        <?= displayNumber($row['score']) ?>
                                    </span>
                                    <span style="color: var(--muted); font-size: 12px; margin-left: 2px;">
                                        (<?= (int)$row['correct_count'] ?>/<?= (int)$row['total_questions'] ?>)
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['is_passed']): ?>
                                        <span class="badge active" style="font-weight: 700; font-size: 11px;">ĐẠT</span>
                                    <?php else: ?>
                                        <span class="badge closed" style="font-weight: 700; font-size: 11px; background: #fee2e2; color: var(--danger);">KHÔNG ĐẠT</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_result.php?id=<?= h($row['id']) ?>" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px; min-height: unset; border-radius: 4px;">
                                        <i class="fa-solid fa-eye"></i> Xem chi tiết
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state" style="border: none; padding: 40px 0;">
                <i class="fa-solid fa-folder-open"></i>
                <h3>Chưa có lịch sử làm bài</h3>
                <p>Bạn chưa hoàn thành lượt thi chính thức nào trong hệ thống.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
