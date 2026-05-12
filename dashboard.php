<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Require active login
$currentUser = requireLogin();
$pdo = getDB();
$user_id = (int) $currentUser['id'];
$message = '';

// Check flash messages
$flash = getFlash();
if ($flash) {
    $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $msg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    $message = "<div class='alert {$type}'>{$msg}</div>";
}

// --- XỬ LÝ FORM CẬP NHẬT THÔNG TIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        $avatarPath = null;
        $uploadError = '';
        
        // Handle avatar upload if provided
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileSize = $_FILES['avatar']['size'];
            $fileType = $_FILES['avatar']['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedfileExtensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            if (in_array($fileExtension, $allowedfileExtensions, true)) {
                if ($fileSize <= 2 * 1024 * 1024) { // limit to 2MB
                    $uploadFileDir = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        $avatarPath = 'uploads/avatars/' . $newFileName;
                        
                        // Delete old avatar if exists
                        $oldAvatarStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                        $oldAvatarStmt->execute([$user_id]);
                        $oldAvatar = $oldAvatarStmt->fetchColumn();
                        if (!empty($oldAvatar) && file_exists(__DIR__ . '/' . $oldAvatar)) {
                            unlink(__DIR__ . '/' . $oldAvatar);
                        }
                    } else {
                        $uploadError = 'Không thể di chuyển tệp tải lên vào thư mục lưu trữ!';
                    }
                } else {
                    $uploadError = 'Tệp quá lớn! Kích thước tối đa là 2MB.';
                }
            } else {
                $uploadError = 'Định dạng tệp không được hỗ trợ! Chỉ chấp nhận JPG, JPEG, PNG, GIF, WEBP.';
            }
        }
        
        if ($uploadError !== '') {
            $message = "<div class='alert error'>{$uploadError}</div>";
        } else {
            if ($avatarPath !== null) {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, avatar = ? WHERE id = ?");
                $success = $stmt->execute([$full_name, $phone, $avatarPath, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                $success = $stmt->execute([$full_name, $phone, $user_id]);
            }
            
            if ($success) {
                $_SESSION['full_name'] = $full_name; // Cập nhật lại session
                flashAndRedirect('success', 'Cập nhật thông tin thành công!');
            } else {
                $message = "<div class='alert error'>Có lỗi xảy ra, vui lòng thử lại!</div>";
            }
        }
    }

    // --- XỬ LÝ FORM ĐỔI MẬT KHẨU ---
    if ($action === 'change_password') {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        // Lấy pass cũ từ DB ra check
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($old_password, $user['password'])) {
            if (strlen($new_password) < 6) {
                $message = "<div class='alert error'>Mật khẩu mới phải từ 6 ký tự!</div>";
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $user_id]);
                flashAndRedirect('success', 'Đổi mật khẩu thành công!');
            }
        } else {
            $message = "<div class='alert error'>Mật khẩu cũ không chính xác!</div>";
        }
    }
}

// --- LẤY DỮ LIỆU HIỂN THỊ ---
// 1. Lấy thông tin user mới nhất
$stmt = $pdo->prepare("SELECT full_name, email, phone, role, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUserData = $stmt->fetch() ?: $currentUser;

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
if ($currentUserData['role'] === 'student') {
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

// Calculate student statistics
$totalExams = count($examHistory);
$averageScore = 0;
$passedExams = 0;
if ($totalExams > 0) {
    $sumScores = 0;
    foreach ($examHistory as $row) {
        $sumScores += (float) $row['score'];
        if ((float) $row['score'] >= 5.0) {
            $passedExams++;
        }
    }
    $averageScore = $sumScores / $totalExams;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Online Quiz System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 12px rgba(24, 32, 51, .03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(24, 32, 51, .06);
        }
        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .metric-icon.blue { background: #e0f2fe; color: #0369a1; }
        .metric-icon.green { background: #dcfce7; color: #15803d; }
        .metric-icon.purple { background: #f3e8ff; color: #7e22ce; }

        .metric-info h4 {
            margin: 0 0 4px;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
        }
        .metric-info span {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }
        .avatar-container:hover .avatar-overlay {
            opacity: 1 !important;
        }
        .avatar-container:hover {
            transform: scale(1.03);
            border-color: var(--primary) !important;
        }
    </style>
</head>
<body>

    <?php if ($currentUserData['role'] === 'student'): ?>
        <?php include __DIR__ . '/layouts/student_sidebar.php'; ?>
        <div class="main-content">
            
            <!-- Welcome Banner -->
            <div class="welcome-banner" style="background: linear-gradient(135deg, var(--primary) 0%, #311b92 100%); color: white; padding: 24px 28px; border-radius: 16px; margin-bottom: 24px; box-shadow: 0 8px 24px rgba(81, 45, 168, 0.15); display: flex; align-items: center; justify-content: space-between; position: relative; overflow: hidden;">
                <div style="z-index: 2;">
                    <h1 style="margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; color: white; border: none; padding: 0;">Chào mừng quay trở lại, <?= htmlspecialchars($currentUserData['full_name'], ENT_QUOTES, 'UTF-8') ?>! 👋</h1>
                    <p style="margin: 8px 0 0; font-size: 13px; opacity: 0.9; line-height: 1.4;">Hôm nay là một ngày tuyệt vời để ôn luyện kiến thức. Chúc bạn đạt kết quả thật cao!</p>
                </div>
                <div class="banner-illustration" style="font-size: 56px; opacity: 0.2; position: absolute; right: 20px; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 1;">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
            </div>

            <!-- Metrics Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon blue">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <div class="metric-info">
                        <h4>Bài thi đã làm</h4>
                        <span><?= $totalExams ?></span>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon green">
                        <i class="fa-solid fa-award"></i>
                    </div>
                    <div class="metric-info">
                        <h4>Điểm trung bình</h4>
                        <span><?= displayNumber($averageScore, 2) ?></span>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon purple">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div class="metric-info">
                        <h4>Kỳ thi đã đạt</h4>
                        <span><?= $passedExams ?> <span style="font-size: 12px; font-weight: normal; color: var(--muted);">/ <?= $totalExams ?></span></span>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
    <?php else: ?>
        <div class="navbar">
            <h2><i class="fa-solid fa-graduation-cap"></i> Online Quiz System</h2>
            <div class="nav-actions">
                <span>Xin chào, <b><?= htmlspecialchars($currentUserData['full_name'], ENT_QUOTES, 'UTF-8') ?></b>!</span>
                <?php if ($currentUserData['role'] === 'admin'): ?>
                    <a href="admin_users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> Người dùng</a>
                    <a href="admin_subjects.php" class="nav-link"><i class="fa-solid fa-book"></i> Môn học</a>
                    <a href="admin_classes.php" class="nav-link"><i class="fa-solid fa-chalkboard-user"></i> Lớp học</a>
                    <a href="exam_management.php" class="nav-link"><i class="fa-solid fa-calendar-days"></i> Kỳ thi</a>
                    <a href="system_reports.php" class="nav-link"><i class="fa-solid fa-chart-line"></i> Thống kê</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Thoát</a>
            </div>
        </div>
        <div class="page" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <?php endif; ?>
    
        <div class="dash-left-panel">
            <?= $message ?>
            
            <div class="card" style="margin-bottom: 20px;">
                <h3><i class="fa-solid fa-user"></i> Thông tin cá nhân</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <!-- Avatar Upload Picker -->
                    <div style="text-align: center; margin-bottom: 24px; position: relative;">
                        <div class="avatar-container" style="display: inline-block; position: relative; width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 3px solid var(--line); cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.08); transition: all 0.2s;">
                            <?php if (!empty($currentUserData['avatar']) && file_exists(__DIR__ . '/' . $currentUserData['avatar'])): ?>
                                <img id="avatar-preview" src="<?= htmlspecialchars($currentUserData['avatar'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div id="avatar-placeholder" style="width: 100%; height: 100%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 38px;">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <img id="avatar-preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <?php endif; ?>
                            
                            <div class="avatar-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; opacity: 0; transition: opacity 0.25s; font-size: 11px; font-weight: 600;">
                                <i class="fa-solid fa-camera" style="font-size: 18px; margin-bottom: 4px;"></i>
                                <span>Đổi ảnh</span>
                            </div>
                        </div>
                        <input type="file" name="avatar" id="avatar-input" accept="image/*" style="display: none;">
                        <div style="margin-top: 8px; font-size: 11px; color: var(--muted);">Click vào vòng tròn để đổi ảnh cá nhân</div>
                    </div>

                    <label>Email (Tài khoản)</label>
                    <input type="email" value="<?= htmlspecialchars($currentUserData['email'], ENT_QUOTES, 'UTF-8') ?>" disabled style="background:#eee;">
                    
                    <label>Họ và tên</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($currentUserData['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    
                    <label>Số điện thoại</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($currentUserData['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Chưa cập nhật">
                    
                    <button type="submit" class="btn btn-primary" style="width:100%; padding:11px;">Lưu thay đổi</button>
                </form>
            </div>

            <script>
                document.querySelector('.avatar-container').addEventListener('click', function() {
                    document.getElementById('avatar-input').click();
                });
                
                document.getElementById('avatar-input').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            const preview = document.getElementById('avatar-preview');
                            const placeholder = document.getElementById('avatar-placeholder');
                            preview.src = event.target.result;
                            preview.style.display = 'block';
                            if (placeholder) {
                                placeholder.style.display = 'none';
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            </script>

            <div class="card">
                <h3><i class="fa-solid fa-lock"></i> Đổi mật khẩu</h3>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">
                    <label>Mật khẩu hiện tại</label>
                    <input type="password" name="old_password" placeholder="Mật khẩu hiện tại" required>
                    <label>Mật khẩu mới</label>
                    <input type="password" name="new_password" placeholder="Mật khẩu mới (ít nhất 6 ký tự)" required>
                    <button type="submit" class="btn btn-danger" style="width:100%; padding:11px;">Đổi mật khẩu</button>
                </form>
            </div>
        </div>

        <div class="dash-right-panel">
            <div class="card">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Lịch sử thi của bạn</h3>
                <?php if (count($examHistory) > 0): ?>
                    <div class="table-wrap">
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
                                        <td><b><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                        <td style="color: <?= $row['score'] >= 5 ? 'var(--success)' : 'var(--danger)' ?>; font-weight: bold;">
                                            <?= displayNumber($row['score'], 2) ?>
                                        </td>
                                        <td><?= $row['total_correct'] ?> / <?= $row['total_questions'] ?></td>
                                        <td><?= displayDateTime($row['completed_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty">Bạn chưa tham gia kỳ thi nào.</div>
                <?php endif; ?>
            </div>

            <?php if ($currentUserData['role'] === 'student'): ?>
                <div class="card wide-card" style="margin-top: 20px;">
                    <h3><i class="fa-solid fa-calendar-check"></i> Kỳ thi được giao</h3>
                    <?php if (count($assignedSessions) > 0): ?>
                        <div class="table-wrap">
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
                                                <b><?= htmlspecialchars($session['title'], ENT_QUOTES, 'UTF-8') ?></b>
                                                <div class="subtle">
                                                    <?= $session['mode'] === 'practice' ? 'Luyện tập' : 'Chính thức' ?>
                                                    · <?= htmlspecialchars($session['max_attempts'], ENT_QUOTES, 'UTF-8') ?> lần
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($session['exam_title'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?= displayDateTime($session['start_time']) ?>
                                                <div class="subtle">đến <?= displayDateTime($session['end_time']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($session['class_names'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty">Bạn chưa được gán kỳ thi nào.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <?php if ($currentUserData['role'] === 'student'): ?>
        </div> <!-- Closes .main-content -->
    <?php endif; ?>

<script src="assets/js/app.js"></script>
</body>
</html>
