<?php
session_start();
require 'config.php';

date_default_timezone_set('Asia/Bangkok');

// Kiểm tra xem đã đăng nhập chưa, nếu chưa thì đuổi về index.php
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$message = '';

// --- XỬ LÝ FORM CẬP NHẬT THÔNG TIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$full_name, $phone, $user_id])) {
            $_SESSION['full_name'] = $full_name; // Cập nhật lại session
            $message = "<div class='alert success'>Cập nhật thông tin thành công!</div>";
        } else {
            $message = "<div class='alert error'>Có lỗi xảy ra, vui lòng thử lại!</div>";
        }
    }

    // --- XỬ LÝ FORM ĐỔI MẬT KHẨU ---
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];

        // Lấy pass cũ từ DB ra check
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (password_verify($old_password, $user['password'])) {
            if (strlen($new_password) < 6) {
                $message = "<div class='alert error'>Mật khẩu mới phải từ 6 ký tự!</div>";
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $user_id]);
                $message = "<div class='alert success'>Đổi mật khẩu thành công!</div>";
            }
        } else {
            $message = "<div class='alert error'>Mật khẩu cũ không chính xác!</div>";
        }
    }
}

// --- LẤY DỮ LIỆU HIỂN THỊ ---
// 1. Lấy thông tin user
$stmt = $pdo->prepare("SELECT full_name, email, phone, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();

// 2. Lấy lịch sử thi (JOIN 2 bảng exam_results và exams)
$historyStmt = $pdo->prepare("
    SELECT
        e.title,
        er.score,
        er.correct_count AS total_correct,
        er.total_questions,
        COALESCE(er.submitted_at, er.started_at) AS completed_at
    FROM exam_results er 
    JOIN exams e ON er.exam_id = e.id 
    WHERE er.student_id = ? 
    ORDER BY completed_at DESC
");
$historyStmt->execute([$user_id]);
$examHistory = $historyStmt->fetchAll();

$assignedSessions = [];
if (($currentUser['role'] ?? '') === 'student') {
    $assignedStmt = $pdo->prepare("
        SELECT DISTINCT
            es.id,
            es.title,
            e.title AS exam_title,
            es.start_time,
            es.end_time,
            es.mode,
            es.max_attempts,
            COALESCE(class_summary.class_names, '') AS class_names
        FROM exam_sessions es
        JOIN exams e ON e.id = es.exam_id
        JOIN session_classes sc ON sc.session_id = es.id
        JOIN class_students cs ON cs.class_id = sc.class_id
        LEFT JOIN (
            SELECT sc_inner.session_id, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS class_names
            FROM session_classes sc_inner
            JOIN classes c ON c.id = sc_inner.class_id
            GROUP BY sc_inner.session_id
        ) class_summary ON class_summary.session_id = es.id
        WHERE cs.student_id = ?
          AND es.is_active = 1
        ORDER BY es.start_time ASC
    ");
    $assignedStmt->execute([$user_id]);
    $assignedSessions = $assignedStmt->fetchAll();
}

function getAssignedSessionStatus(array $session): array
{
    $now = time();
    $start = strtotime($session['start_time']);
    $end = strtotime($session['end_time']);

    if ($start && $now < $start) {
        return ['badge waiting', 'Sắp mở'];
    }

    if ($end && $now > $end) {
        return ['badge closed', 'Đã kết thúc'];
    }

    return ['badge active', 'Đang mở'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Online Quiz System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #f4f5f7; margin: 0; padding: 0; }
        .navbar { background-color: #512da8; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 20px; }
        .nav-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .nav-link, .logout-btn { color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 5px; }
        .nav-link:hover, .logout-btn:hover { background: rgba(255,255,255,0.4); }
        .container-dash { max-width: 1000px; margin: 30px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .card h3 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; color: #333; }
        input { width: 100%; padding: 10px; margin: 8px 0 15px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-family: inherit; }
        button { background-color: #512da8; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; }
        button:hover { background-color: #4527a0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px;}
        table th { background-color: #f2f2f2; color: #333; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; font-size: 14px; font-weight: bold;}
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge { display: inline-block; border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 700; }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.waiting { background: #e0f2fe; color: #075985; }
        .badge.closed { background: #fef3c7; color: #92400e; }
        .subtle { color: #777; font-size: 12px; margin-top: 4px; }
        .wide-card { grid-column: 1 / -1; }
        @media(max-width: 768px) { .container-dash { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <div class="navbar">
        <h2><i class="fa-solid fa-graduation-cap"></i> Online Quiz System</h2>
        <div class="nav-actions">
            <span>Xin chào, <b><?= htmlspecialchars($currentUser['full_name']) ?></b>!</span>
            <?php if (in_array($currentUser['role'], ['admin', 'teacher'], true)): ?>
                <a href="exam_management.php" class="nav-link"><i class="fa-solid fa-calendar-days"></i> Quản lý kỳ thi</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Thoát</a>
        </div>
    </div>

    <div class="container-dash">
        <div>
            <?= $message ?>
            
            <div class="card" style="margin-bottom: 20px;">
                <h3><i class="fa-solid fa-user"></i> Thông tin cá nhân</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <label>Email (Tài khoản)</label>
                    <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled style="background:#eee;">
                    
                    <label>Họ và tên</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
                    
                    <label>Số điện thoại</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="Chưa cập nhật">
                    
                    <button type="submit">Lưu thay đổi</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fa-solid fa-lock"></i> Đổi mật khẩu</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <input type="password" name="old_password" placeholder="Mật khẩu hiện tại" required>
                    <input type="password" name="new_password" placeholder="Mật khẩu mới (ít nhất 6 ký tự)" required>
                    <button type="submit" style="background:#d32f2f;">Đổi mật khẩu</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Lịch sử thi của bạn</h3>
            <?php if (count($examHistory) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tên kỳ thi</th>
                            <th>Điểm số</th>
                            <th>Câu đúng</th>
                            <th>Ngày hoàn thành</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($examHistory as $row): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($row['title']) ?></b></td>
                                <td style="color: <?= $row['score'] >= 5 ? 'green' : 'red' ?>; font-weight: bold;">
                                    <?= number_format($row['score'], 2) ?>
                                </td>
                                <td><?= $row['total_correct'] ?> / <?= $row['total_questions'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($row['completed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #777; text-align: center; margin-top: 30px;">Bạn chưa tham gia kỳ thi nào.</p>
            <?php endif; ?>
        </div>

        <?php if (($currentUser['role'] ?? '') === 'student'): ?>
            <div class="card wide-card">
                <h3><i class="fa-solid fa-calendar-check"></i> Kỳ thi được giao</h3>
                <?php if (count($assignedSessions) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kỳ thi</th>
                                <th>Đề thi</th>
                                <th>Thời gian</th>
                                <th>Lớp / nhóm</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedSessions as $session): ?>
                                <?php [$badgeClass, $badgeText] = getAssignedSessionStatus($session); ?>
                                <tr>
                                    <td>
                                        <b><?= htmlspecialchars($session['title']) ?></b>
                                        <div class="subtle">
                                            <?= $session['mode'] === 'practice' ? 'Luyện tập' : 'Chính thức' ?>
                                            · <?= htmlspecialchars($session['max_attempts']) ?> lần
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($session['exam_title']) ?></td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($session['start_time'])) ?>
                                        <div class="subtle">đến <?= date('d/m/Y H:i', strtotime($session['end_time'])) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($session['class_names']) ?></td>
                                    <td><span class="<?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($badgeText) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #777; text-align: center; margin-top: 30px;">Bạn chưa được gán kỳ thi nào.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
