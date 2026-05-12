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

    if ($action === 'create_subject') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');

        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (name, code, description, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$name, $code, $description]);
            flashAndRedirect('success', 'Thêm môn học thành công!');
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    if ($action === 'update_subject') {
        $id = (int) $_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE subjects SET name = ?, code = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $code, $description, $is_active, $id]);
            flashAndRedirect('success', 'Cập nhật môn học thành công!');
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    if ($action === 'delete_subject') {
        $id = (int) $_POST['id'];
        try {
            $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
            flashAndRedirect('success', 'Đã xóa môn học!');
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Không thể xóa môn học này vì có dữ liệu liên quan.');
        }
    }
}

// --- LẤY DANH SÁCH ---
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

$pageTitle = "Quản lý môn học | Online Quiz";
$headerIcon = "fa-solid fa-book";
$headerTitle = "Quản lý môn học";
// Nếu là giáo viên, quay lại teacher_dashboard.php, nếu là admin quay lại dashboard.php
$backUrl = ($currentUser['role'] === 'teacher') ? 'teacher_dashboard.php' : 'dashboard.php';
require __DIR__ . '/layouts/header.php';
?>

<div class="container">
    <?= $message ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="margin: 0; border: none; padding: 0;">Danh sách môn học</h3>
            <button class="btn btn-primary" onclick="showModal('createModal')">
                <i class="fa-solid fa-plus"></i> Thêm môn học
            </button>
        </div>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mã môn</th>
                        <th>Tên môn học</th>
                        <th>Mô tả</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($s['code'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="font-size: 13px; color: var(--muted);"><?= htmlspecialchars($s['description'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= $s['is_active'] ? '<span style="color: var(--success); font-weight: 600;">Hoạt động</span>' : '<span style="color: var(--danger); font-weight: 600;">Ngừng</span>' ?>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-warning" onclick='editSubject(<?= json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_subject">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
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

<!-- Modal Thêm -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <h3>Thêm môn học mới</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_subject">
            
            <label>Mã môn học</label>
            <input type="text" name="code" placeholder="Ví dụ: MATH101" required>
            
            <label>Tên môn học</label>
            <input type="text" name="name" placeholder="Ví dụ: Toán cao cấp" required>
            
            <label>Mô tả</label>
            <textarea name="description" rows="3" placeholder="Mô tả ngắn gọn về môn học"></textarea>
            
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 11px;">Lưu</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 11px;" onclick="hideModal('createModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Chỉnh sửa môn học</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_subject">
            <input type="hidden" name="id" id="edit_id">
            
            <label>Mã môn học</label>
            <input type="text" name="code" id="edit_code" required>
            
            <label>Tên môn học</label>
            <input type="text" name="name" id="edit_name" required>
            
            <label>Mô tả</label>
            <textarea name="description" id="edit_description" rows="3"></textarea>
            
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-top: 10px;">
                <input type="checkbox" name="is_active" id="edit_active" style="width: auto; margin: 0;"> Đang hoạt động
            </label>
            
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 11px;">Cập nhật</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 11px;" onclick="hideModal('editModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSubject(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_code').value = s.code;
    document.getElementById('edit_name').value = s.name;
    document.getElementById('edit_description').value = s.description || '';
    document.getElementById('edit_active').checked = (s.is_active == 1);
    showModal('editModal');
}
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
