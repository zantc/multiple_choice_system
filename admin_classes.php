<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['admin', 'teacher']);

$pdo = getDB();
$userId = (int) $currentUser['id'];
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
    $view_param = !empty($_GET['view_class']) ? '?view_class=' . ((int) $_GET['view_class']) : '';

    if ($action === 'create_class') {
        $name = trim($_POST['name'] ?? '');
        $subject_id = (int) ($_POST['subject_id'] ?? 0);
        $school_year = trim($_POST['school_year'] ?? '');

        try {
            $stmt = $pdo->prepare("INSERT INTO classes (name, subject_id, school_year, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$name, $subject_id > 0 ? $subject_id : null, $school_year]);
            flashAndRedirect('success', 'Tạo lớp học thành công!', $view_param);
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage(), $view_param);
        }
    }

    if ($action === 'assign_student') {
        $class_id = (int) ($_POST['class_id'] ?? 0);
        $student_id = (int) ($_POST['student_id'] ?? 0);

        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?, ?)");
            $stmt->execute([$class_id, $student_id]);
            flashAndRedirect('success', 'Đã thêm sinh viên vào lớp!', "?view_class={$class_id}");
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage(), "?view_class={$class_id}");
        }
    }

    if ($action === 'remove_student') {
        $class_id = (int) ($_POST['class_id'] ?? 0);
        $student_id = (int) ($_POST['student_id'] ?? 0);
        $pdo->prepare("DELETE FROM class_students WHERE class_id = ? AND student_id = ?")->execute([$class_id, $student_id]);
        flashAndRedirect('success', 'Đã xóa sinh viên khỏi lớp!', "?view_class={$class_id}");
    }

    if ($action === 'delete_class') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);
        flashAndRedirect('success', 'Đã xóa lớp học!');
    }
}

// --- LẤY DỮ LIỆU ---
$classes = $pdo->query("
    SELECT c.*, s.name as subject_name, (SELECT COUNT(*) FROM class_students WHERE class_id = c.id) as student_count 
    FROM classes c 
    LEFT JOIN subjects s ON c.subject_id = s.id 
    ORDER BY c.created_at DESC
")->fetchAll();

$subjects = $pdo->query("SELECT id, name FROM subjects WHERE is_active = 1")->fetchAll();
$students = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'student' AND is_active = 1")->fetchAll();

// Lấy danh sách sinh viên theo lớp nếu có yêu cầu
$selected_class_id = (int) ($_GET['view_class'] ?? 0);
$class_members = [];
$selected_class_name = '';

if ($selected_class_id > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.email 
        FROM users u 
        JOIN class_students cs ON u.id = cs.student_id 
        WHERE cs.class_id = ?
    ");
    $stmt->execute([$selected_class_id]);
    $class_members = $stmt->fetchAll();
    
    // Tìm tên lớp học đã chọn
    foreach ($classes as $c) {
        if ((int) $c['id'] === $selected_class_id) {
            $selected_class_name = $c['name'];
            break;
        }
    }
}

$pageTitle = "Quản lý lớp học | Online Quiz";
$headerIcon = "fa-solid fa-chalkboard-user";
$headerTitle = "Quản lý lớp học";
$backUrl = ($currentUser['role'] === 'teacher') ? 'teacher_dashboard.php' : 'dashboard.php';
require __DIR__ . '/layouts/header.php';
?>

<div class="page" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <?= $message ?>

    <div class="dash-left-panel">
        <div class="card">
            <h3><i class="fa-solid fa-circle-plus"></i> Tạo lớp học mới</h3>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_class">
                
                <label>Tên lớp</label>
                <input type="text" name="name" placeholder="Ví dụ: Lớp Toán A1" required>
                
                <label>Môn học</label>
                <select name="subject_id">
                    <option value="0">-- Không bắt buộc --</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label>Niên khóa</label>
                <input type="text" name="school_year" placeholder="Ví dụ: 2025-2026">
                
                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 13px; padding: 11px; margin-top: 10px;">Tạo lớp</button>
            </form>
        </div>
    </div>

    <div class="dash-right-panel">
        <div class="card">
            <h3><i class="fa-solid fa-list-check"></i> Danh sách lớp học</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tên lớp</th>
                            <th>Môn học</th>
                            <th>Sĩ số</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $c): ?>
                        <tr style="<?= $selected_class_id === (int) $c['id'] ? 'background: #f0f7ff;' : '' ?>">
                            <td><b><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></b></td>
                            <td><?= htmlspecialchars($c['subject_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $c['student_count'] ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?view_class=<?= $c['id'] ?>" class="btn btn-success" title="Xem danh sách">
                                        <i class="fa-solid fa-users"></i>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Xóa lớp học này?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_class">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($selected_class_id > 0): ?>
    <div class="card wide-card" style="grid-column: 1 / -1; margin-top: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 20px;">
            <h3 style="margin: 0; border: none; padding: 0;">Thành viên lớp: <?= htmlspecialchars($selected_class_name, ENT_QUOTES, 'UTF-8') ?></h3>
            <a href="admin_classes.php" class="btn btn-warning" style="font-size: 12px;"><i class="fa-solid fa-xmark"></i> Đóng chi tiết</a>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
            <div>
                <h4 style="margin-top:0; color:var(--primary);"><i class="fa-solid fa-user-plus"></i> Thêm sinh viên</h4>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_student">
                    <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
                    
                    <select name="student_id" required style="padding: 11px;">
                        <option value="">-- Chọn sinh viên --</option>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?= $stu['id'] ?>">
                                <?= htmlspecialchars($stu['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($stu['username'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; margin-top: 10px;">Thêm vào lớp</button>
                </form>
            </div>
            
            <div>
                <h4 style="margin-top:0; color:var(--primary);"><i class="fa-solid fa-users-viewfinder"></i> Danh sách sinh viên trong lớp</h4>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tài khoản</th>
                                <th>Họ và tên</th>
                                <th>Email</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($class_members)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--muted); padding: 20px;">Lớp học chưa có sinh viên nào</td></tr>
                            <?php endif; ?>
                            <?php foreach ($class_members as $m): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($m['username'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                <td><?= htmlspecialchars($m['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($m['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Xóa sinh viên này khỏi lớp?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_student">
                                        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
                                        <input type="hidden" name="student_id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-danger" title="Xóa khỏi lớp"><i class="fa-solid fa-user-minus"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
