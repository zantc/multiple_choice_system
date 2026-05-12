<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['teacher']);

$pdo = getDB();
$userId = (int) $currentUser['id'];

// --- XỬ LÝ ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Preserve current filter parameters for redirects
    $filter_params = [];
    if (!empty($_GET['subject_id'])) $filter_params[] = 'subject_id=' . ((int)$_GET['subject_id']);
    if (!empty($_GET['search']))     $filter_params[] = 'search=' . urlencode($_GET['search']);
    $query_str = !empty($filter_params) ? '?' . implode('&', $filter_params) : '';

    if ($action === 'save_question') {
        $id = (int)($_POST['id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $correct_answer = $_POST['correct_answer'] ?? 'A';
        $difficulty = $_POST['difficulty'] ?? 'medium';
        $explanation = trim($_POST['explanation'] ?? '');

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE questions SET subject_id=?, content=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, difficulty=?, explanation=? WHERE id=?");
                $stmt->execute([$subject_id, $content, $option_a, $option_b, $option_c, $option_d, $correct_answer, $difficulty, $explanation, $id]);
                flashAndRedirect('success', 'Cập nhật câu hỏi thành công!', $query_str);
            } else {
                $stmt = $pdo->prepare("INSERT INTO questions (subject_id, content, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$subject_id, $content, $option_a, $option_b, $option_c, $option_d, $correct_answer, $difficulty, $explanation, $userId]);
                flashAndRedirect('success', 'Thêm câu hỏi mới thành công!', $query_str);
            }
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage(), $query_str);
        }
    }

    if ($action === 'delete_question') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE questions SET is_active = 0 WHERE id = ?")->execute([$id]);
        flashAndRedirect('success', 'Đã ẩn câu hỏi khỏi ngân hàng!', $query_str);
    }

    if ($action === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");
            $subject_id = (int)($_POST['import_subject_id'] ?? 0);
            
            if ($subject_id <= 0) {
                flashAndRedirect('error', 'Vui lòng chọn môn học để nhập câu hỏi!', $query_str);
            } else {
                $count = 0;
                $row = 0;
                $pdo->beginTransaction();
                try {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $row++;
                        if ($row === 1) continue; // Skip header
                        
                        if (count($data) < 7) continue;

                        $content = trim($data[0]);
                        $a = trim($data[1]);
                        $b = trim($data[2]);
                        $c = trim($data[3]);
                        $d = trim($data[4]);
                        $ans = strtoupper(trim($data[5]));
                        $diff = strtolower(trim($data[6] ?? 'medium'));
                        $exp = trim($data[7] ?? '');

                        if ($content === '' || $a === '' || $b === '') continue;

                        $stmt = $pdo->prepare("INSERT INTO questions (subject_id, content, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$subject_id, $content, $a, $b, $c, $d, $ans, $diff, $exp, $userId]);
                        $count++;
                    }
                    $pdo->commit();
                    flashAndRedirect('success', "Đã nhập thành công {$count} câu hỏi!", $query_str);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flashAndRedirect('error', 'Lỗi khi nhập dữ liệu: ' . $e->getMessage(), $query_str);
                }
                if ($handle !== false) {
                    fclose($handle);
                }
            }
        } else {
            flashAndRedirect('error', 'Vui lòng chọn file CSV hợp lệ!', $query_str);
        }
    }
}

// --- LỌC VÀ LẤY DỮ LIỆU ---
$filter_subject = (int)($_GET['subject_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "SELECT q.*, s.name as subject_name FROM questions q LEFT JOIN subjects s ON q.subject_id = s.id WHERE q.is_active = 1";
$params = [];

if ($filter_subject > 0) {
    $sql .= " AND q.subject_id = ?";
    $params[] = $filter_subject;
}
if ($search !== '') {
    $sql .= " AND q.content LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY q.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

$subjects = $pdo->query("SELECT id, name FROM subjects WHERE is_active = 1")->fetchAll();

$pageTitle = "Quản lý Ngân hàng câu hỏi | Portal Giáo viên";
$hideNavbar = true;
require __DIR__ . '/layouts/header.php';
?>

<?php require __DIR__ . '/layouts/teacher_sidebar.php'; ?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--line); padding-bottom: 15px; margin-bottom: 20px;">
        <h1 style="margin: 0; color: var(--sidebar-bg); font-size: 24px;">
            <i class="fa-solid fa-database" style="color: var(--primary);"></i> Ngân hàng câu hỏi
        </h1>
        <button class="btn btn-primary" onclick="showModal('saveModal')">
            <i class="fa-solid fa-plus"></i> Thêm câu hỏi
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

    <div class="grid-2" style="grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
        <div class="card" style="margin:0;">
            <h3 style="margin-top:0;"><i class="fa-solid fa-filter"></i> Bộ lọc & Tìm kiếm</h3>
            <form method="GET" style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 10px; align-items: flex-end; margin: 0;">
                <div>
                    <label style="font-size: 12px; margin-top:0;">Môn học</label>
                    <select name="subject_id" style="padding: 10px; margin-bottom:0;">
                        <option value="0">-- Tất cả môn học --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $filter_subject === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 12px; margin-top:0;">Nội dung câu hỏi</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nhập từ khóa cần tìm..." style="padding: 10px; margin-bottom:0;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 11px 20px; height: 41px;">
                    <i class="fa-solid fa-magnifying-glass"></i> Lọc
                </button>
            </form>
        </div>
        
        <div class="card" style="margin:0;">
            <h3 style="margin-top:0;"><i class="fa-solid fa-file-csv"></i> Nhập từ CSV</h3>
            <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 8px; margin: 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_csv">
                
                <select name="import_subject_id" required style="padding: 8px; font-size: 12px; margin-bottom:0;">
                    <option value="">-- Chọn môn học --</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                
                <input type="file" name="csv_file" accept=".csv" required style="font-size: 12px; padding: 4px; margin-bottom:0;">
                
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="submit" class="btn btn-success" style="padding: 8px 12px; font-size: 12px;">
                        <i class="fa-solid fa-file-import"></i> Tải lên CSV
                    </button>
                    <a href="#" onclick="alert('Định dạng CSV:\nNội dung, A, B, C, D, Đáp án(A/B/C/D), Độ khó(easy/medium/hard), Giải thích\n(Bỏ qua dòng đầu tiên)')" style="font-size: 11px; color: var(--primary); text-decoration: underline;">Định dạng mẫu</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Danh sách câu hỏi trong ngân hàng</h3>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th width="140">Môn học</th>
                        <th>Nội dung</th>
                        <th width="100">Độ khó</th>
                        <th width="100">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td><?= $q['id'] ?></td>
                        <td><span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 600;"><?= htmlspecialchars($q['subject_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <div style="font-weight: 600; color: var(--text); margin-bottom: 8px;">
                                <?= htmlspecialchars($q['content'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 13px; color: var(--muted);">
                                <div style="<?= $q['correct_answer'] === 'A' ? 'color: var(--success); font-weight: bold;' : '' ?>">A. <?= htmlspecialchars($q['option_a'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="<?= $q['correct_answer'] === 'B' ? 'color: var(--success); font-weight: bold;' : '' ?>">B. <?= htmlspecialchars($q['option_b'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="<?= $q['correct_answer'] === 'C' ? 'color: var(--success); font-weight: bold;' : '' ?>">C. <?= htmlspecialchars($q['option_c'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="<?= $q['correct_answer'] === 'D' ? 'color: var(--success); font-weight: bold;' : '' ?>">D. <?= htmlspecialchars($q['option_d'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <?php if (!empty($q['explanation'])): ?>
                                <div style="margin-top: 6px; font-size: 12px; color: var(--primary); background: #f8fafc; padding: 4px 8px; border-radius: 4px;">
                                    <i class="fa-solid fa-lightbulb"></i> <?= htmlspecialchars($q['explanation'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight:bold; color: <?= $q['difficulty'] === 'easy' ? 'var(--success)' : ($q['difficulty'] === 'hard' ? 'var(--danger)' : '#f59e0b') ?>">
                                <?= ucfirst($q['difficulty']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-warning" onclick='editQuestion(<?= json_encode($q, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Ẩn câu hỏi này?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-eye-slash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($questions)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--muted);">Không tìm thấy câu hỏi nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div id="saveModal" class="modal">
    <div class="modal-content" style="max-width: 760px; width: 100%;">
        <h3 id="modalTitle">Thêm câu hỏi mới</h3>
        <form method="POST" id="questionForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_question">
            <input type="hidden" name="id" id="q_id">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div>
                    <label>Môn học</label>
                    <select name="subject_id" id="q_subject" required>
                        <option value="">-- Chọn môn học --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Độ khó</label>
                    <select name="difficulty" id="q_difficulty">
                        <option value="easy">Dễ</option>
                        <option value="medium" selected>Trung bình</option>
                        <option value="hard">Khó</option>
                    </select>
                </div>
            </div>

            <label>Nội dung câu hỏi</label>
            <textarea name="content" id="q_content" rows="3" required placeholder="Nhập nội dung câu hỏi..."></textarea>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 5px;">
                <div>
                    <label>Phương án A</label>
                    <input type="text" name="option_a" id="q_a" required>
                </div>
                <div>
                    <label>Phương án B</label>
                    <input type="text" name="option_b" id="q_b" required>
                </div>
                <div>
                    <label>Phương án C</label>
                    <input type="text" name="option_c" id="q_c" required>
                </div>
                <div>
                    <label>Phương án D</label>
                    <input type="text" name="option_d" id="q_d" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-top: 5px;">
                <div>
                    <label>Đáp án đúng</label>
                    <select name="correct_answer" id="q_correct">
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div>
                    <label>Giải thích (không bắt buộc)</label>
                    <input type="text" name="explanation" id="q_explanation" placeholder="Giải thích vì sao đáp án này đúng...">
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="flex: 2; padding: 12px;">Lưu câu hỏi</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 12px;" onclick="hideModal('saveModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
function editQuestion(q) {
    document.getElementById('modalTitle').innerText = 'Chỉnh sửa câu hỏi #' + q.id;
    document.getElementById('q_id').value = q.id;
    document.getElementById('q_subject').value = q.subject_id;
    document.getElementById('q_difficulty').value = q.difficulty;
    document.getElementById('q_content').value = q.content;
    document.getElementById('q_a').value = q.option_a;
    document.getElementById('q_b').value = q.option_b;
    document.getElementById('q_c').value = q.option_c;
    document.getElementById('q_d').value = q.option_d;
    document.getElementById('q_correct').value = q.correct_answer;
    document.getElementById('q_explanation').value = q.explanation || '';
    showModal('saveModal');
}

// Override modal hide to custom reset title for saveModal
const originalHideModal = hideModal;
window.hideModal = function(id) {
    originalHideModal(id);
    if (id === 'saveModal') {
        const titleEl = document.getElementById('modalTitle');
        if (titleEl) titleEl.innerText = 'Thêm câu hỏi mới';
    }
};
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
