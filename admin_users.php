<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole('admin');

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

    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
        $role = $_POST['role'] ?? 'student';
        $phone = trim($_POST['phone'] ?? '');

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password, role, phone, is_active, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
            $stmt->execute([$username, $email, $full_name, $password, $role, $phone]);
            flashAndRedirect('success', 'Tạo người dùng thành công!');
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    if ($action === 'update_user') {
        $id = (int) $_POST['id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'student';
        $phone = trim($_POST['phone'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $role, $phone, $id]);
            flashAndRedirect('success', 'Cập nhật thành công!');
        } catch (PDOException $e) {
            flashAndRedirect('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    if ($action === 'toggle_status') {
        $id = (int) $_POST['id'];
        $status = (int) $_POST['status'];
        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$status, $id]);
        flashAndRedirect('success', 'Đã cập nhật trạng thái hoạt động!');
    }

    if ($action === 'reset_password') {
        $id = (int) $_POST['id'];
        $new_pass = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_pass, $id]);
        flashAndRedirect('success', 'Đã đặt lại mật khẩu về 123456!');
    }

    if ($action === 'delete_user') {
        $id = (int) $_POST['id'];
        if ($id !== $userId) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            flashAndRedirect('success', 'Đã xóa người dùng!');
        } else {
            flashAndRedirect('error', 'Bạn không thể tự xóa chính mình!');
        }
    }
}

// --- LẤY DANH SÁCH ---
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = "Quản lý người dùng | Online Quiz";
$headerIcon = "fa-solid fa-users-gear";
$headerTitle = "Quản lý người dùng";
require __DIR__ . '/layouts/header.php';
?>

<div class="container">
    <?= $message ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="margin: 0; border: none; padding: 0;">Danh sách người dùng</h3>
            <button class="btn btn-primary" onclick="showModal('createModal')">
                <i class="fa-solid fa-plus"></i> Thêm người dùng
            </button>
        </div>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tài khoản</th>
                        <th>Họ và tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><b><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge" style="background: <?= $u['role'] === 'admin' ? '#fee2e2; color: #991b1b' : ($u['role'] === 'teacher' ? '#e0f2fe; color: #075985' : '#dcfce7; color: #166534') ?>">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $u['is_active'] ? '<span style="color: var(--success); font-weight: 600;">● Hoạt động</span>' : '<span style="color: var(--danger); font-weight: 600;">● Bị khóa</span>' ?>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-warning" title="Sửa" onclick='editUser(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Xác nhận đổi trạng thái?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $u['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>" title="<?= $u['is_active'] ? 'Khóa' : 'Mở khóa' ?>">
                                        <i class="fa-solid <?= $u['is_active'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                    </button>
                                </form>

                                <form method="POST" style="display:inline;" onsubmit="return confirm('Đặt lại mật khẩu về 123456?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-primary" title="Reset mật khẩu">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                </form>

                                <?php if ($u['id'] !== $userId): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger" title="Xóa">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm mới -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <h3>Thêm người dùng mới</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">
            
            <label>Tên đăng nhập</label>
            <input type="text" name="username" placeholder="Tên đăng nhập viết liền không dấu" required>
            
            <label>Email</label>
            <input type="email" name="email" placeholder="Địa chỉ email" required>
            
            <label>Họ và tên</label>
            <input type="text" name="full_name" placeholder="Họ và tên đầy đủ" required>
            
            <label>Mật khẩu</label>
            <input type="password" name="password" placeholder="Mật khẩu" required>
            
            <label>Vai trò</label>
            <select name="role">
                <option value="student">Học sinh</option>
                <option value="teacher">Giáo viên</option>
                <option value="admin">Quản trị viên</option>
            </select>
            
            <label>Số điện thoại</label>
            <input type="text" name="phone" placeholder="Số điện thoại (tùy chọn)">
            
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
        <h3>Chỉnh sửa người dùng</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id" id="edit_id">
            
            <label>Tên đăng nhập</label>
            <input type="text" id="edit_username" disabled style="background: #eee;">
            
            <label>Email</label>
            <input type="email" name="email" id="edit_email" placeholder="Email" required>
            
            <label>Họ và tên</label>
            <input type="text" name="full_name" id="edit_full_name" placeholder="Họ và tên" required>
            
            <label>Vai trò</label>
            <select name="role" id="edit_role">
                <option value="student">Học sinh</option>
                <option value="teacher">Giáo viên</option>
                <option value="admin">Quản trị viên</option>
            </select>
            
            <label>Số điện thoại</label>
            <input type="text" name="phone" id="edit_phone" placeholder="Số điện thoại">
            
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 11px;">Cập nhật</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 11px;" onclick="hideModal('editModal')">Hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_phone').value = user.phone || '';
    showModal('editModal');
}
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
