<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception;

$currentUser = requireRole(['admin', 'teacher']);
$pdo = getDB();
$message = '';

// Check flash messages
$flash = getFlash();
if ($flash) {
    $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $msg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    $message = "<div class='alert {$type}'>{$msg}</div>";
}

// --- XỬ LÝ ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Sửa điểm
    if ($action === 'edit_score') {
        $result_id = (int)($_POST['result_id'] ?? 0);
        $new_score = (float)($_POST['new_score'] ?? 0);

        try {
            $stmt = $pdo->prepare("UPDATE exam_results SET score = ? WHERE id = ?");
            $stmt->execute([$new_score, $result_id]);
            flashAndRedirect('success', 'Đã cập nhật điểm thành công!');
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    // 2. Gửi Email kết quả
    if ($action === 'email_result') {
        $result_id = (int)($_POST['result_id'] ?? 0);
        
        // Lấy thông tin bài thi và học sinh
        $stmt = $pdo->prepare("
            SELECT er.score, er.correct_count AS total_correct, er.total_questions, e.title as exam_title, u.email, u.full_name
            FROM exam_results er
            JOIN exams e ON er.exam_id = e.id
            JOIN users u ON er.student_id = u.id
            WHERE er.id = ?
        ");
        $stmt->execute([$result_id]);
        $info = $stmt->fetch();

        // Nếu bảng gốc có cột khác hoặc null, thử fallback tra cứu theo cấu trúc bảng 1:
        if (!$info) {
            $stmtFallback = $pdo->prepare("
                SELECT er.score, er.total_correct, er.total_questions, e.title as exam_title, u.email, u.full_name
                FROM exam_results er
                JOIN exams e ON er.exam_id = e.id
                JOIN users u ON er.user_id = u.id
                WHERE er.id = ?
            ");
            $stmtFallback->execute([$result_id]);
            $info = $stmtFallback->fetch();
        }

        if ($info && !empty($info['email'])) {
            try {
                $mail = createMailer();
                $mail->addAddress($info['email'], $info['full_name']);
                $mail->Subject = "Ket qua thi: " . $info['exam_title'];
                
                $safeScore = displayNumber($info['score'], 2);
                $content = "
                    <h2 style='color:#512da8;margin-top:0;'>Chào " . h($info['full_name']) . ",</h2>
                    <p>Giáo viên vừa cập nhật/gửi kết quả bài thi của bạn.</p>
                    <table style='width:100%; border-collapse: collapse; margin: 20px 0;'>
                        <tr>
                            <th style='border:1px solid #ddd; padding: 12px; background:#f8f9fa; text-align:left;'>Kỳ thi</th>
                            <td style='border:1px solid #ddd; padding: 12px; font-weight:bold;'>" . h($info['exam_title']) . "</td>
                        </tr>
                        <tr>
                            <th style='border:1px solid #ddd; padding: 12px; background:#f8f9fa; text-align:left;'>Số câu đúng</th>
                            <td style='border:1px solid #ddd; padding: 12px;'>{$info['total_correct']} / {$info['total_questions']}</td>
                        </tr>
                        <tr>
                            <th style='border:1px solid #ddd; padding: 12px; background:#f8f9fa; text-align:left;'>Điểm số</th>
                            <td style='border:1px solid #ddd; padding: 12px; font-weight:bold; color:var(--primary); font-size:18px;'>{$safeScore}</td>
                        </tr>
                    </table>
                    <p style='color:#555;'>Chúc bạn học tập tốt!</p>
                ";
                $mail->Body = emailLayout($content);
                $mail->send();
                flashAndRedirect('success', "Đã gửi kết quả qua email cho học viên " . h($info['full_name']) . "!");
            } catch (Exception $e) {
                flashAndRedirect('error', "Lỗi gửi mail: " . h($mail->ErrorInfo));
            }
        } else {
            flashAndRedirect('error', 'Không tìm thấy thông tin hoặc học viên không có email hợp lệ.');
        }
    }
}

// --- LẤY DỮ LIỆU HIỂN THỊ ---
// Thử lấy danh sách kết quả mới nhất theo cấu trúc chuẩn:
try {
    $results = $pdo->query("
        SELECT er.id, er.score, COALESCE(er.correct_count, er.total_correct) AS total_correct, er.total_questions, COALESCE(er.submitted_at, er.started_at, er.completed_at) AS completed_at,
               u.full_name, u.username, u.email,
               e.title as exam_title
        FROM exam_results er
        JOIN users u ON COALESCE(er.student_id, er.user_id) = u.id
        JOIN exams e ON er.exam_id = e.id
        ORDER BY completed_at DESC
        LIMIT 100
    ")->fetchAll();
} catch (PDOException $e) {
    // Fallback query cho bảng 1 thuần túy
    $results = $pdo->query("
        SELECT er.id, er.score, er.total_correct, er.total_questions, er.completed_at,
               u.full_name, u.username, u.email,
               e.title as exam_title
        FROM exam_results er
        JOIN users u ON er.user_id = u.id
        JOIN exams e ON er.exam_id = e.id
        ORDER BY er.completed_at DESC
        LIMIT 100
    ")->fetchAll();
}

$pageTitle = "Quản lý Điểm thi | Online Quiz";
$headerIcon = "fa-solid fa-star-half-stroke";
$headerTitle = "Quản lý điểm thi & Kết quả";
$backUrl = ($currentUser['role'] === 'teacher') ? 'teacher_dashboard.php' : 'dashboard.php';
require __DIR__ . '/layouts/header.php';
?>

<div class="container">
    <?= $message ?>

    <div class="card">
        <h3 style="margin-top:0; border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 16px;"><i class="fa-solid fa-award"></i> Danh sách kết quả thi gần đây</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Học viên</th>
                        <th>Đề thi</th>
                        <th>Số câu đúng</th>
                        <th>Điểm số</th>
                        <th>Ngày nộp</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td>
                            <b><?= htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') ?></b>
                            <div style="font-size: 12px; color: var(--muted);"><?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td style="font-weight: 500;"><?= htmlspecialchars($r['exam_title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $r['total_correct'] ?> / <?= $r['total_questions'] ?></td>
                        <td>
                            <span class="badge" style="font-size: 14px; padding: 4px 10px; background: <?= $r['score'] >= 5 ? '#dcfce7; color: #166534' : '#fee2e2; color: #991b1b' ?>">
                                <?= displayNumber($r['score'], 2) ?>
                            </span>
                        </td>
                        <td><?= displayDateTime($r['completed_at']) ?></td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-warning" title="Sửa điểm thủ công" onclick='editScore(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <i class="fa-solid fa-pen"></i> Sửa điểm
                                </button>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn muốn gửi điểm này về email của học sinh?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="email_result">
                                    <input type="hidden" name="result_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-success" title="Gửi Email báo điểm">
                                        <i class="fa-solid fa-envelope"></i> Báo điểm
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($results)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 30px; color: var(--muted);">Chưa có kết quả thi nào được ghi nhận.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Sửa điểm -->
<div id="editScoreModal" class="modal">
    <div class="modal-content">
        <h3>Chỉnh sửa điểm thủ công</h3>
        <p style="font-size: 13px; color: var(--muted); margin-bottom: 15px;">Dùng trong trường hợp hệ thống chấm sai hoặc cần cộng điểm ưu tiên.</p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_score">
            <input type="hidden" name="result_id" id="edit_result_id">
            
            <label>Học sinh</label>
            <input type="text" id="edit_student_name" disabled style="background: #eee;">
            
            <label>Điểm mới (Hệ số 10)</label>
            <input type="number" step="0.01" name="new_score" id="edit_score_val" required style="padding: 11px;">
            
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 11px;">Lưu thay đổi</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 11px;" onclick="hideModal('editScoreModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
function editScore(r) {
    document.getElementById('edit_result_id').value = r.id;
    document.getElementById('edit_student_name').value = r.full_name + ' (' + r.exam_title + ')';
    document.getElementById('edit_score_val').value = parseFloat(r.score).toFixed(2);
    showModal('editScoreModal');
}
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
