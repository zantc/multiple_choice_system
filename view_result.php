<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['student']);

if (!isset($_GET['id'])) {
    flashAndRedirect("Không tìm thấy kết quả phù hợp.", "student_history.php", "danger");
}

$pdo = getDB();
$userId = (int) $currentUser['id'];
$resultId = (int) $_GET['id'];

// Fetch result
$stmt = $pdo->prepare("
    SELECT er.*, e.title as exam_title, e.show_result, e.show_explanation, e.pass_score, es.title as session_title
    FROM exam_results er
    JOIN exams e ON e.id = er.exam_id
    JOIN exam_sessions es ON es.id = er.session_id
    WHERE er.id = ? AND er.student_id = ?
");
$stmt->execute([$resultId, $userId]);
$result = $stmt->fetch();

if (!$result) {
    flashAndRedirect("Kết quả không tồn tại hoặc bạn không có quyền xem.", "student_history.php", "danger");
}

if (is_null($result['submitted_at'])) {
    flashAndRedirect("Bài thi này hiện chưa được nộp hoàn chỉnh.", "student_history.php", "warning");
}

// Fetch answers and questions
$answersStmt = $pdo->prepare("
    SELECT sa.selected_option, sa.is_correct, 
           q.content, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation
    FROM student_answers sa
    JOIN questions q ON q.id = sa.question_id
    WHERE sa.result_id = ?
");
$answersStmt->execute([$resultId]);
$answers = $answersStmt->fetchAll();

$isPassed = $result['score'] >= $result['pass_score'];

$pageTitle = "Chi tiết kết quả thi | Portal Học viên";
$hideNavbar = true;
require __DIR__ . '/layouts/header.php';
?>

<?php require __DIR__ . '/layouts/student_sidebar.php'; ?>

<style>
.result-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(24, 32, 51, .03);
    text-align: center;
    max-width: 600px;
    margin: 0 auto 30px;
    border-top: 5px solid var(--primary);
}
.result-card.passed {
    border-top-color: var(--success);
}
.result-card.failed {
    border-top-color: var(--danger);
}
.score-circle {
    width: 130px;
    height: 130px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    font-weight: 700;
    margin: 24px auto;
    color: #fff;
}
.score-circle.passed {
    background: var(--success);
    border: 6px solid #dcfce7;
}
.score-circle.failed {
    background: var(--danger);
    border: 6px solid #fee2e2;
}
.stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
    margin-top: 24px;
    border-top: 1px solid var(--line);
    padding-top: 24px;
}
.stat-item {
    text-align: center;
}
.stat-val {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
}
.stat-label {
    font-size: 13px;
    color: var(--muted);
    margin-top: 4px;
}
.q-review {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.01);
}
.q-text {
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 15px;
    line-height: 1.5;
}
.opt-row {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 8px;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 14px;
    transition: background 0.15s;
}
.opt-row.opt-correct {
    background: #dcfce7;
    border-color: #a7f3d0;
    color: #166534;
    font-weight: 600;
}
.opt-row.opt-wrong {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
}
.explanation-box {
    background: #f8fafc;
    border-left: 4px solid var(--primary);
    padding: 15px;
    margin-top: 15px;
    font-size: 13.5px;
    color: #475569;
    border-radius: 0 6px 6px 0;
}
@media print {
    .sidebar, .navbar, .btn {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    body {
        background: #fff !important;
    }
}
</style>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; flex-wrap: wrap;">
        <a href="student_history.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Quay lại lịch sử
        </a>
        <?php if ($isPassed): ?>
            <button class="btn btn-success" onclick="window.print()">
                <i class="fa-solid fa-print"></i> In chứng nhận kết quả (PDF)
            </button>
        <?php endif; ?>
    </div>

    <div class="result-card <?= $isPassed ? 'passed' : 'failed' ?>">
        <h2 style="margin: 0; font-size: 20px; color: var(--text);"><?= h($result['session_title']) ?></h2>
        <p style="margin: 6px 0 0; color: var(--muted); font-size: 13px;"><i class="fa-solid fa-book"></i> Đề thi: <?= h($result['exam_title']) ?></p>
        
        <div class="score-circle <?= $isPassed ? 'passed' : 'failed' ?>">
            <?= h(displayNumber($result['score'])) ?>
        </div>
        
        <div style="font-size: 18px; font-weight: 700; color: <?= $isPassed ? 'var(--success)' : 'var(--danger)' ?>;">
            <?= $isPassed ? '<i class="fa-solid fa-circle-check"></i> ĐÃ ĐẠT YÊU CẦU' : '<i class="fa-solid fa-circle-xmark"></i> KHÔNG ĐẠT YÊU CẦU' ?>
        </div>

        <div class="stat-grid">
            <div class="stat-item">
                <div class="stat-val"><?= (int)$result['correct_count'] ?> / <?= (int)$result['total_questions'] ?></div>
                <div class="stat-label">Câu trả lời đúng</div>
            </div>
            <div class="stat-item">
                <div class="stat-val">
                    <?php 
                    $totalSecs = (int)$result['time_spent_seconds'];
                    echo floor($totalSecs / 60) . 'm ' . ($totalSecs % 60) . 's';
                    ?>
                </div>
                <div class="stat-label">Thời gian làm bài</div>
            </div>
            <div class="stat-item">
                <div class="stat-val"><?= displayDateTime($result['submitted_at']) ?></div>
                <div class="stat-label">Thời gian hoàn thành</div>
            </div>
        </div>
    </div>

    <?php if ($result['show_result']): ?>
        <h3 style="color: var(--sidebar-bg); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin: 40px 0 20px; font-size: 18px;">
            <i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Review chi tiết đáp án câu hỏi
        </h3>
        
        <?php foreach ($answers as $idx => $ans): ?>
            <div class="q-review">
                <div class="q-text">
                    <span style="color: <?= $ans['is_correct'] ? 'var(--success)' : 'var(--danger)' ?>; font-weight: 700; margin-right: 6px;">
                        <i class="fa-solid <?= $ans['is_correct'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> Câu <?= $idx + 1 ?>:
                    </span>
                    <?= nl2br(h($ans['content'])) ?>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php 
                    $options = [
                        'A' => $ans['option_a'],
                        'B' => $ans['option_b'],
                        'C' => $ans['option_c'],
                        'D' => $ans['option_d']
                    ];
                    foreach ($options as $key => $val): 
                        $class = '';
                        $icon = '';
                        if ($key === $ans['correct_answer']) {
                            $class = 'opt-correct';
                            $icon = '<i class="fa-solid fa-check-circle"></i> Đáp án đúng';
                        } elseif ($key === $ans['selected_option']) {
                            $class = 'opt-wrong';
                            $icon = '<i class="fa-solid fa-times-circle"></i> Lựa chọn của bạn';
                        }
                    ?>
                        <div class="opt-row <?= $class ?>">
                            <span><b><?= h($key) ?>.</b> <?= h($val) ?></span>
                            <?php if ($icon): ?>
                                <span style="font-size: 12px; font-weight: 600;"><?= $icon ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($result['show_explanation'] && !empty($ans['explanation'])): ?>
                    <div class="explanation-box">
                        <b><i class="fa-solid fa-circle-info" style="color: var(--primary);"></i> Giải thích chi tiết:</b><br>
                        <div style="margin-top: 6px; line-height: 1.5;"><?= nl2br(h($ans['explanation'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; background: #fff; border: 1px dashed var(--line); border-radius: 8px; color: var(--muted); margin-top: 20px;">
            <i class="fa-solid fa-eye-slash" style="font-size: 32px; color: #cbd5e1; margin-bottom: 12px;"></i>
            <p style="margin: 0; font-size: 14px;">Review chi tiết đáp án đã bị quản trị viên ẩn cho ca thi này.</p>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
