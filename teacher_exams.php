<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['teacher']);

$pdo = getDB();
$userId = (int) $currentUser['id'];

// --- XỬ LÝ ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $view_param = !empty($_GET['view_exam']) ? '?view_exam=' . ((int) $_GET['view_exam']) : '';

    if ($action === 'save_exam') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $duration = (int)($_POST['duration_minutes'] ?? 60);
        $pass_score = (float)($_POST['pass_score'] ?? 5.0);
        $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
        $shuffle_answers = isset($_POST['shuffle_answers']) ? 1 : 0;

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE exams SET title=?, subject_id=?, duration_minutes=?, pass_score=?, shuffle_questions=?, shuffle_answers=? WHERE id=?");
                $stmt->execute([$title, $subject_id, $duration, $pass_score, $shuffle_questions, $shuffle_answers, $id]);
                flashAndRedirect('success', 'Cập nhật đề thi thành công!', $view_param);
            } else {
                $stmt = $pdo->prepare("INSERT INTO exams (title, subject_id, duration_minutes, pass_score, shuffle_questions, shuffle_answers, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')");
                $stmt->execute([$title, $subject_id, $duration, $pass_score, $shuffle_questions, $shuffle_answers, $userId]);
                flashAndRedirect('success', 'Tạo đề thi mới thành công!', $view_param);
            }
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage(), $view_param);
        }
    }

    if ($action === 'add_question_to_exam') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        $question_id = (int)($_POST['question_id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO exam_questions (exam_id, question_id, order_num) VALUES (?, ?, (SELECT COALESCE(MAX(order_num), 0) + 1 FROM exam_questions eq WHERE eq.exam_id = ?))");
            $stmt->execute([$exam_id, $question_id, $exam_id]);
            
            // Cập nhật số lượng câu hỏi trong bảng exams
            $pdo->prepare("UPDATE exams SET total_questions = (SELECT COUNT(*) FROM exam_questions WHERE exam_id = ?) WHERE id = ?")->execute([$exam_id, $exam_id]);
            
            flashAndRedirect('success', 'Đã thêm câu hỏi vào đề thi!', "?view_exam={$exam_id}");
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage(), "?view_exam={$exam_id}");
        }
    }

    if ($action === 'remove_question_from_exam') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        $question_id = (int)($_POST['question_id'] ?? 0);
        
        $pdo->prepare("DELETE FROM exam_questions WHERE exam_id = ? AND question_id = ?")->execute([$exam_id, $question_id]);
        $pdo->prepare("UPDATE exams SET total_questions = (SELECT COUNT(*) FROM exam_questions WHERE exam_id = ?) WHERE id = ?")->execute([$exam_id, $exam_id]);
        
        flashAndRedirect('success', 'Đã xóa câu hỏi khỏi đề thi!', "?view_exam={$exam_id}");
    }

    if ($action === 'generate_random_questions') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        $num_easy = (int)($_POST['num_easy'] ?? 0);
        $num_medium = (int)($_POST['num_medium'] ?? 0);
        $num_hard = (int)($_POST['num_hard'] ?? 0);
        
        $exam_info = $pdo->prepare("SELECT subject_id FROM exams WHERE id = ?");
        $exam_info->execute([$exam_id]);
        $subject_id = $exam_info->fetchColumn();

        $pdo->beginTransaction();
        try {
            $levels = ['easy' => $num_easy, 'medium' => $num_medium, 'hard' => $num_hard];
            $added_count = 0;
            
            foreach ($levels as $diff => $qty) {
                if ($qty <= 0) continue;
                
                $sql = "SELECT id FROM questions WHERE subject_id = ? AND difficulty = ? AND is_active = 1 AND id NOT IN (SELECT question_id FROM exam_questions WHERE exam_id = ?) ORDER BY RAND() LIMIT $qty";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$subject_id, $diff, $exam_id]);
                $q_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($q_ids as $q_id) {
                    $ins = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_id, order_num) VALUES (?, ?, (SELECT COALESCE(MAX(order_num), 0) + 1 FROM exam_questions eq WHERE eq.exam_id = ?))");
                    $ins->execute([$exam_id, $q_id, $exam_id]);
                    $added_count++;
                }
            }
            
            $pdo->prepare("UPDATE exams SET total_questions = (SELECT COUNT(*) FROM exam_questions WHERE exam_id = ?) WHERE id = ?")->execute([$exam_id, $exam_id]);
            $pdo->commit();
            flashAndRedirect('success', "Đã thêm ngẫu nhiên {$added_count} câu hỏi vào đề thi!", "?view_exam={$exam_id}");
        } catch (Exception $e) {
            $pdo->rollBack();
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage(), "?view_exam={$exam_id}");
        }
    }

    if ($action === 'delete_exam') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$id]);
        flashAndRedirect('success', 'Đã xóa đề thi thành công!');
    }
}

// --- LẤY DỮ LIỆU ---
$exams = $pdo->query("
    SELECT e.*, s.name as subject_name 
    FROM exams e 
    LEFT JOIN subjects s ON e.subject_id = s.id 
    ORDER BY e.created_at DESC
")->fetchAll();

$subjects = $pdo->query("SELECT id, name FROM subjects WHERE is_active = 1")->fetchAll();

// Chi tiết đề thi nếu được chọn
$selected_exam_id = (int)($_GET['view_exam'] ?? 0);
$selected_exam_title = '';
$selected_subject_name = '';
$exam_questions = [];
$available_questions = [];

if ($selected_exam_id > 0) {
    // Câu hỏi đã có trong đề
    $stmt = $pdo->prepare("
        SELECT q.*, eq.order_num 
        FROM questions q 
        JOIN exam_questions eq ON q.id = eq.question_id 
        WHERE eq.exam_id = ? 
        ORDER BY eq.order_num ASC
    ");
    $stmt->execute([$selected_exam_id]);
    $exam_questions = $stmt->fetchAll();

    // Tìm thông tin đề thi
    $exam_info = null;
    foreach ($exams as $e) {
        if ((int)$e['id'] === $selected_exam_id) {
            $exam_info = $e;
            $selected_exam_title = $e['title'];
            $selected_subject_name = $e['subject_name'] ?? 'N/A';
            break;
        }
    }

    if ($exam_info) {
        // Câu hỏi chưa có trong đề (để thêm vào)
        $stmt = $pdo->prepare("
            SELECT * FROM questions 
            WHERE subject_id = ? AND is_active = 1 
            AND id NOT IN (SELECT question_id FROM exam_questions WHERE exam_id = ?)
            LIMIT 50
        ");
        $stmt->execute([$exam_info['subject_id'], $selected_exam_id]);
        $available_questions = $stmt->fetchAll();
    }
}

$pageTitle = "Tạo và Quản lý Đề thi | Portal Giáo viên";
$hideNavbar = true;
require __DIR__ . '/layouts/header.php';
?>

<?php require __DIR__ . '/layouts/teacher_sidebar.php'; ?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--line); padding-bottom: 15px; margin-bottom: 20px;">
        <h1 style="margin: 0; color: var(--sidebar-bg); font-size: 24px;">
            <i class="fa-solid fa-file-signature" style="color: var(--primary);"></i> Tạo & Quản lý Đề thi
        </h1>
        <button class="btn btn-primary" onclick="showModal('saveModal')">
            <i class="fa-solid fa-plus"></i> Tạo đề thi mới
        </button>
    </div>

    <?php 
    $flash = getFlash();
    if ($flash): 
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $msg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    ?>
        <div class="alert <?= $type ?>"><?= $msg ?></div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top: 0;">Danh sách đề thi cấu trúc</h3>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tên đề thi</th>
                        <th>Môn học</th>
                        <th>Thời gian</th>
                        <th>Số câu hỏi</th>
                        <th>Điểm đạt</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $e): ?>
                    <tr style="<?= $selected_exam_id === (int)$e['id'] ? 'background: #f0f7ff;' : '' ?>">
                        <td><b style="color: var(--text);"><?= htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><span class="badge" style="background:#f1f5f9; color:#475569; font-weight:600;"><?= htmlspecialchars($e['subject_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="font-weight: 500;"><?= $e['duration_minutes'] ?> phút</td>
                        <td><span class="badge" style="background:#e0e7ff; color:#3730a3;"><?= $e['total_questions'] ?> câu</span></td>
                        <td>
                            <span style="font-weight: 600; color: var(--success);"><?= displayNumber($e['pass_score'], 1) ?></span> / <?= isset($e['max_score']) ? displayNumber($e['max_score'], 1) : '10' ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="?view_exam=<?= $e['id'] ?>" class="btn btn-success" title="Quản lý chi tiết câu hỏi trong đề">
                                    <i class="fa-solid fa-list-check"></i> Cấu hình
                                public
                                </a>
                                <button class="btn btn-warning" onclick='editExam(<?= json_encode($e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Xóa đề thi này khỏi hệ thống?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_exam">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn btn-danger" title="Xóa đề thi"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($exams)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 30px; color: var(--muted);">Chưa có đề thi nào được tạo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($selected_exam_id > 0): ?>
    <div class="grid-2" style="grid-template-columns: 1.5fr 1fr; gap: 20px; margin-top: 20px;">
        <div class="card" style="margin:0;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 16px;">
                <h3 style="margin:0; border:none; padding:0; color:var(--primary);"><i class="fa-solid fa-rectangle-list"></i> Câu hỏi trong đề: <?= htmlspecialchars($selected_exam_title, ENT_QUOTES, 'UTF-8') ?></h3>
                <a href="teacher_exams.php" class="btn btn-warning" style="font-size:12px;"><i class="fa-solid fa-xmark"></i> Đóng</a>
            </div>
            
            <div style="overflow-y: auto; max-height: 520px; padding-right: 5px;">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th width="40" style="text-align:center;">#</th>
                                <th>Nội dung câu hỏi</th>
                                <th width="60" style="text-align:center;">Gỡ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exam_questions as $eq): ?>
                            <tr>
                                <td style="text-align:center; font-weight:bold; color:var(--primary);"><?= $eq['order_num'] ?></td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;">
                                        <?= htmlspecialchars($eq['content'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <span style="font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; background: <?= $eq['difficulty'] === 'easy' ? '#dcfce7; color: #166534' : ($eq['difficulty'] === 'hard' ? '#fee2e2; color: #991b1b' : '#fef3c7; color: #b45309') ?>">
                                        <?= ucfirst($eq['difficulty']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <form method="POST" onsubmit="return confirm('Gỡ câu hỏi này khỏi đề thi?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_question_from_exam">
                                        <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">
                                        <input type="hidden" name="question_id" value="<?= $eq['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 4px 8px;" title="Gỡ câu hỏi"><i class="fa-solid fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($exam_questions)): ?>
                                <tr><td colspan="3" style="text-align: center; padding: 30px; color: var(--muted);">Đề thi chưa có câu hỏi nào. Hãy thêm từ danh sách bên phải.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card" style="margin:0;">
            <h3 style="margin-top:0; border-bottom: 1px solid var(--line); padding-bottom: 10px; margin-bottom: 12px; color:var(--primary);"><i class="fa-solid fa-wand-magic-sparkles"></i> Thêm ngẫu nhiên</h3>
            <form method="POST" style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid var(--line); margin: 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="generate_random_questions">
                <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 11px; font-weight:600; color:var(--success); margin-top:0;">Số câu Dễ</label>
                        <input type="number" name="num_easy" value="0" min="0" style="padding: 6px; margin: 0; font-size:13px;">
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight:600; color:#d97706; margin-top:0;">Số câu TB</label>
                        <input type="number" name="num_medium" value="0" min="0" style="padding: 6px; margin: 0; font-size:13px;">
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="font-size: 11px; font-weight:600; color:var(--danger); margin-top:0;">Số câu Khó</label>
                        <input type="number" name="num_hard" value="0" min="0" style="padding: 6px; margin: 0; font-size:13px;">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-warning" style="width: 100%; padding: 10px; font-size: 13px;">
                    <i class="fa-solid fa-shuffle"></i> Tự động bốc câu hỏi
                </button>
            </form>

            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 8px; margin-top: 20px; margin-bottom: 12px;">
                <h3 style="margin:0; border:none; padding:0; font-size:15px; color:var(--primary);"><i class="fa-solid fa-hand-pointer"></i> Thêm thủ công</h3>
                <span style="font-size: 11px; color: var(--muted);">Môn: <b><?= htmlspecialchars($selected_subject_name, ENT_QUOTES, 'UTF-8') ?></b></span>
            </div>
            
            <div style="overflow-y: auto; max-height: 280px; padding-right: 5px;">
                <?php foreach ($available_questions as $aq): ?>
                <div style="border: 1px solid var(--line); padding: 10px; margin-bottom: 8px; border-radius: 6px; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="font-size: 10px; font-weight: bold; padding: 2px 5px; border-radius: 3px; background: <?= $aq['difficulty'] === 'easy' ? '#dcfce7; color: #166534' : ($aq['difficulty'] === 'hard' ? '#fee2e2; color: #991b1b' : '#fef3c7; color: #b45309') ?>">
                            <?= strtoupper($aq['difficulty']) ?>
                        </span>
                    </div>
                    <p style="margin: 0 0 10px; font-size: 13px; font-weight: 500; color: var(--text); line-height: 1.4;">
                        <?= htmlspecialchars($aq['content'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <form method="POST" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_question_to_exam">
                        <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">
                        <input type="hidden" name="question_id" value="<?= $aq['id'] ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 6px; font-size: 12px;">
                            <i class="fa-solid fa-plus"></i> Thêm vào đề
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php if (empty($available_questions)): ?>
                    <p style="text-align: center; color: var(--muted); font-size: 12px; padding: 20px 0;">Không còn câu hỏi khả dụng nào trong ngân hàng môn này.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Thêm/Sửa đề thi -->
<div id="saveModal" class="modal">
    <div class="modal-content" style="max-width: 540px;">
        <h3 id="modalTitle">Thiết lập đề thi</h3>
        <form method="POST" id="examForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_exam">
            <input type="hidden" name="id" id="e_id">
            
            <label>Tiêu đề đề thi</label>
            <input type="text" name="title" id="e_title" required placeholder="Ví dụ: Đề thi cuối kỳ môn Toán" style="padding: 11px;">

            <label>Môn học</label>
            <select name="subject_id" id="e_subject" required style="padding: 11px;">
                <option value="">-- Chọn môn học --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Thời gian (phút)</label>
                    <input type="number" name="duration_minutes" id="e_duration" value="60" required style="padding: 11px;">
                </div>
                <div>
                    <label>Điểm đạt</label>
                    <input type="number" step="0.1" name="pass_score" id="e_pass" value="5.0" required style="padding: 11px;">
                </div>
            </div>

            <div style="margin: 15px 0 5px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--line);">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 8px;">
                    <input type="checkbox" name="shuffle_questions" id="e_shuffle_q" style="width: auto; margin: 0;"> Xáo trộn thứ tự câu hỏi khi thi
                </label>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="shuffle_answers" id="e_shuffle_a" style="width: auto; margin: 0;"> Xáo trộn thứ tự đáp án (A,B,C,D)
                </label>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px;">Lưu thiết lập</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 12px;" onclick="hideModal('saveModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
function editExam(e) {
    document.getElementById('e_id').value = e.id;
    document.getElementById('e_title').value = e.title;
    document.getElementById('e_subject').value = e.subject_id;
    document.getElementById('e_duration').value = e.duration_minutes;
    document.getElementById('e_pass').value = e.pass_score;
    document.getElementById('e_shuffle_q').checked = (e.shuffle_questions == 1);
    document.getElementById('e_shuffle_a').checked = (e.shuffle_answers == 1);
    showModal('saveModal');
}

// Intercept hideModal to reset values cleanly
const origHideModal = hideModal;
window.hideModal = function(id) {
    origHideModal(id);
    if (id === 'saveModal') {
        document.getElementById('e_id').value = '';
        document.getElementById('e_title').value = '';
        document.getElementById('e_duration').value = '60';
        document.getElementById('e_pass').value = '5.0';
        document.getElementById('e_shuffle_q').checked = false;
        document.getElementById('e_shuffle_a').checked = false;
    }
};
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
