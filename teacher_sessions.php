<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['teacher']);

$pdo = getDB();
$userId = (int) $currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_session') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $examId = (int) ($_POST['exam_id'] ?? 0);
            $startTime = normalizeDateTime($_POST['start_time'] ?? '');
            $endTime = normalizeDateTime($_POST['end_time'] ?? '');
            $mode = ($_POST['mode'] ?? 'official') === 'practice' ? 'practice' : 'official';
            $maxAttempts = max(1, min(99, (int) ($_POST['max_attempts'] ?? 1)));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $classIds = uniquePositiveIds($_POST['class_ids'] ?? []);

            if ($title === '') {
                throw new RuntimeException('Vui lòng nhập tên kỳ thi.');
            }

            if ($examId <= 0 || !validateExamForUser($pdo, $examId, $currentUser)) {
                throw new RuntimeException('Đề thi không hợp lệ hoặc bạn không có quyền sử dụng đề này.');
            }

            if (!$startTime || !$endTime) {
                throw new RuntimeException('Vui lòng nhập thời gian bắt đầu và kết thúc hợp lệ.');
            }

            if (strtotime($endTime) <= strtotime($startTime)) {
                throw new RuntimeException('Thời gian kết thúc phải sau thời gian bắt đầu.');
            }

            if (!$classIds) {
                throw new RuntimeException('Vui lòng chọn ít nhất một lớp hoặc nhóm học viên.');
            }

            $validClassIds = fetchValidIds($pdo, 'classes', $classIds, 'AND is_active = 1');
            $sortedClassIds = $classIds;
            sort($sortedClassIds);

            if ($validClassIds !== $sortedClassIds) {
                throw new RuntimeException('Danh sách lớp/nhóm không hợp lệ.');
            }

            $pdo->beginTransaction();

            if ($sessionId > 0) {
                $session = fetchManageableSession($pdo, $sessionId, $currentUser);
                if (!$session) {
                    throw new RuntimeException('Không tìm thấy kỳ thi hoặc bạn không có quyền sửa.');
                }

                $stmt = $pdo->prepare("
                    UPDATE exam_sessions
                    SET exam_id = ?, title = ?, start_time = ?, end_time = ?, mode = ?, max_attempts = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$examId, $title, $startTime, $endTime, $mode, $maxAttempts, $isActive, $sessionId]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO exam_sessions (exam_id, title, start_time, end_time, mode, max_attempts, is_active, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$examId, $title, $startTime, $endTime, $mode, $maxAttempts, $isActive, $userId]);
                $sessionId = (int) $pdo->lastInsertId();
            }

            saveSessionClasses($pdo, $sessionId, $classIds);
            $pdo->commit();

            flashAndRedirect('success', 'Đã lưu kỳ thi thành công.');
        }

        if ($action === 'toggle_session') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            $session = fetchManageableSession($pdo, $sessionId, $currentUser);
            if (!$session) {
                throw new RuntimeException('Không tìm thấy kỳ thi hoặc bạn không có quyền cập nhật.');
            }

            $nextStatus = (int) !$session['is_active'];
            $stmt = $pdo->prepare("UPDATE exam_sessions SET is_active = ? WHERE id = ?");
            $stmt->execute([$nextStatus, $sessionId]);

            flashAndRedirect('success', $nextStatus ? 'Đã bật kỳ thi.' : 'Đã tắt kỳ thi.');
        }

        if ($action === 'delete_session') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            $session = fetchManageableSession($pdo, $sessionId, $currentUser);
            if (!$session) {
                throw new RuntimeException('Không tìm thấy kỳ thi hoặc bạn không có quyền xóa.');
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM session_classes WHERE session_id = ?")->execute([$sessionId]);
            $pdo->prepare("DELETE FROM exam_sessions WHERE id = ?")->execute([$sessionId]);
            $pdo->commit();

            flashAndRedirect('success', 'Đã xóa kỳ thi thành công.');
        }

        if ($action === 'create_group') {
            $groupName = trim($_POST['group_name'] ?? '');
            $subjectId = (int) ($_POST['subject_id'] ?? 0);
            $schoolYear = trim($_POST['school_year'] ?? '');
            $studentIds = uniquePositiveIds($_POST['student_ids'] ?? []);

            if ($groupName === '') {
                throw new RuntimeException('Vui lòng nhập tên lớp hoặc nhóm.');
            }

            if (!$studentIds) {
                throw new RuntimeException('Vui lòng chọn ít nhất một học viên cho nhóm.');
            }

            if ($subjectId > 0) {
                $validSubjectIds = fetchValidIds($pdo, 'subjects', [$subjectId], 'AND is_active = 1');
                if (!$validSubjectIds) {
                    throw new RuntimeException('Môn học không hợp lệ.');
                }
            }

            $validStudentIds = fetchValidIds($pdo, 'users', $studentIds, "AND role = 'student' AND is_active = 1");
            $sortedStudentIds = $studentIds;
            sort($sortedStudentIds);

            if ($validStudentIds !== $sortedStudentIds) {
                throw new RuntimeException('Danh sách học viên không hợp lệ.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO classes (name, subject_id, school_year, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([
                $groupName,
                $subjectId > 0 ? $subjectId : null,
                $schoolYear !== '' ? $schoolYear : null,
            ]);
            $classId = (int) $pdo->lastInsertId();

            $insert = $pdo->prepare("INSERT INTO class_students (class_id, student_id) VALUES (?, ?)");
            foreach ($studentIds as $studentId) {
                $insert->execute([$classId, $studentId]);
            }
            $pdo->commit();

            flashAndRedirect('success', 'Đã tạo nhóm học viên thành công.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (($action ?? '') === 'delete_session') {
            flashAndRedirect('error', 'Không thể xóa kỳ thi đã có dữ liệu liên quan. Hãy tắt kỳ thi nếu cần ẩn khỏi học viên.');
        }

        flashAndRedirect('error', $e->getMessage());
    }
}

$flash = getFlash();

$examSql = "
    SELECT e.id, e.title, e.status, e.duration_minutes, s.name AS subject_name
    FROM exams e
    LEFT JOIN subjects s ON s.id = e.subject_id
    WHERE e.status <> 'archived'
";
$examParams = [];
if ($currentUser['role'] !== 'admin') {
    $examSql .= " AND e.created_by = ?";
    $examParams[] = $userId;
}
$examSql .= " ORDER BY e.updated_at DESC, e.id DESC";
$examStmt = $pdo->prepare($examSql);
$examStmt->execute($examParams);
$exams = $examStmt->fetchAll();

$classes = $pdo->query("
    SELECT
        c.id,
        c.name,
        c.school_year,
        COALESCE(s.name, '') AS subject_name,
        COUNT(cs.student_id) AS student_count
    FROM classes c
    LEFT JOIN subjects s ON s.id = c.subject_id
    LEFT JOIN class_students cs ON cs.class_id = c.id
    WHERE c.is_active = 1
    GROUP BY c.id, c.name, c.school_year, s.name
    ORDER BY c.name ASC
")->fetchAll();

$students = $pdo->query("
    SELECT id, full_name, username, email
    FROM users
    WHERE role = 'student' AND is_active = 1
    ORDER BY full_name ASC, username ASC
")->fetchAll();

$subjects = $pdo->query("
    SELECT id, name
    FROM subjects
    WHERE is_active = 1
    ORDER BY name ASC
")->fetchAll();

$sessionSql = "
    SELECT
        es.*,
        e.title AS exam_title,
        e.status AS exam_status,
        e.duration_minutes,
        u.full_name AS creator_name,
        COALESCE(class_summary.class_names, '') AS class_names
    FROM exam_sessions es
    JOIN exams e ON e.id = es.exam_id
    JOIN users u ON u.id = es.created_by
    LEFT JOIN (
        SELECT
            sc.session_id,
            GROUP_CONCAT(
                CONCAT(c.name, ' (', COALESCE(student_counts.student_count, 0), ' HV)')
                ORDER BY c.name
                SEPARATOR ', '
            ) AS class_names
        FROM session_classes sc
        JOIN classes c ON c.id = sc.class_id
        LEFT JOIN (
            SELECT class_id, COUNT(*) AS student_count
            FROM class_students
            GROUP BY class_id
        ) student_counts ON student_counts.class_id = c.id
        GROUP BY sc.session_id
    ) class_summary ON class_summary.session_id = es.id
";
$sessionParams = [];
if ($currentUser['role'] !== 'admin') {
    $sessionSql .= " WHERE es.created_by = ?";
    $sessionParams[] = $userId;
}
$sessionSql .= " ORDER BY es.start_time DESC, es.id DESC";
$sessionStmt = $pdo->prepare($sessionSql);
$sessionStmt->execute($sessionParams);
$sessions = $sessionStmt->fetchAll();

$editingSession = null;
$editingClassIds = [];
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editingSession = fetchManageableSession($pdo, $editId, $currentUser);
    if ($editingSession) {
        $stmt = $pdo->prepare("SELECT class_id FROM session_classes WHERE session_id = ?");
        $stmt->execute([$editId]);
        $editingClassIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } else {
        $flash = [
            'type' => 'error',
            'message' => 'Không tìm thấy kỳ thi hoặc bạn không có quyền sửa.',
        ];
    }
}

$formSession = $editingSession ?: [
    'id' => 0,
    'title' => '',
    'exam_id' => '',
    'start_time' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    'end_time' => date('Y-m-d H:i:s', strtotime('+2 hours')),
    'mode' => 'official',
    'max_attempts' => 1,
    'is_active' => 1,
];



$pageTitle = "Tổ chức Kỳ thi & Ca thi | Portal Giáo viên";
$hideNavbar = true;
require __DIR__ . '/layouts/header.php';
?>

<?php require __DIR__ . '/layouts/teacher_sidebar.php'; ?>

<style>
.layout {
    display: grid;
    grid-template-columns: minmax(320px, 390px) 1fr;
    gap: 20px;
    align-items: start;
    margin-top: 10px;
}
.panel {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(24, 32, 51, .03);
}
.panel + .panel { margin-top: 18px; }
.panel h3 {
    margin: 0 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--line);
    font-size: 16px;
    color: var(--primary);
}
label {
    display: block;
    margin: 12px 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
}
.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.row > div { min-width: 0; }
.time-row { grid-template-columns: 1fr; }
.time-row input { max-width: 100%; }
.check-line {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    font-size: 13px;
    color: var(--text);
    font-weight: 600;
}
.check-line input {
    width: 16px;
    min-height: 16px;
    margin: 0;
}
.class-grid, .student-grid {
    display: grid;
    gap: 8px;
    max-height: 220px;
    overflow: auto;
    padding: 8px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #f8fafc;
}
.choice {
    display: grid;
    grid-template-columns: 20px 1fr;
    gap: 8px;
    align-items: start;
    padding: 8px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid var(--line);
    font-size: 13px;
    cursor: pointer;
}
.choice input {
    width: 15px;
    min-height: 15px;
    margin: 2px 0 0;
}
.choice strong {
    display: block;
    margin-bottom: 3px;
    color: var(--text);
}
.choice span {
    color: var(--muted);
    line-height: 1.35;
    font-size: 12px;
}
.form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
}
.empty {
    color: var(--muted);
    text-align: center;
    padding: 26px 12px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    font-size: 13px;
}
.subtle {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.45;
    margin-top: 4px;
}
.badge.mode { background: #ede9fe; color: #5b21b6; margin-top: 6px; display:inline-block; }
@media (max-width: 920px) {
    .layout { grid-template-columns: 1fr; }
    .row { grid-template-columns: 1fr; }
}
</style>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--line); padding-bottom: 15px; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0; color: var(--sidebar-bg); font-size: 24px;">
                <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i> Tổ chức Kỳ thi & Ca thi
            </h1>
            <p style="margin: 4px 0 0; color: var(--muted); font-size: 13px;">Tạo kỳ thi, gán đề, đặt thời gian mở/đóng và chỉ định lớp hoặc nhóm tham gia.</p>
        </div>
        <a href="teacher_analytics.php" class="btn btn-warning" style="font-size: 13px;">
            <i class="fa-solid fa-chart-line"></i> Báo cáo thống kê
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="alert <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="layout">
        <section>
            <div class="panel">
                <h3 style="margin-top:0;">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <?= $editingSession ? 'Sửa ca/kỳ thi' : 'Tạo ca/kỳ thi mới' ?>
                </h3>

                <form method="POST" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_session">
                    <input type="hidden" name="session_id" value="<?= h($formSession['id']) ?>">

                    <label for="title">Tên kỳ thi / Đợt thi</label>
                    <input id="title" type="text" name="title" value="<?= h($formSession['title']) ?>" placeholder="Ví dụ: Kiểm tra 15 phút Lần 1" required style="padding: 10px; margin-bottom:0;">

                    <label for="exam_id">Đề thi áp dụng</label>
                    <select id="exam_id" name="exam_id" required style="padding: 10px; margin-bottom:0;">
                        <option value="">-- Chọn đề thi --</option>
                        <?php foreach ($exams as $exam): ?>
                            <option
                                value="<?= h($exam['id']) ?>"
                                <?= (int) $formSession['exam_id'] === (int) $exam['id'] ? 'selected' : '' ?>
                            >
                                <?= h($exam['title']) ?>
                                · <?= h($exam['subject_name'] ?: 'Chưa có môn') ?>
                                · <?= h($exam['duration_minutes']) ?> phút
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="row time-row">
                        <div>
                            <label for="start_time">Thời gian Bắt đầu</label>
                            <input id="start_time" type="datetime-local" name="start_time" value="<?= h(toDateTimeLocal($formSession['start_time'])) ?>" required style="padding: 10px; margin-bottom:0;">
                        </div>
                        <div>
                            <label for="end_time">Thời gian Kết thúc</label>
                            <input id="end_time" type="datetime-local" name="end_time" value="<?= h(toDateTimeLocal($formSession['end_time'])) ?>" required style="padding: 10px; margin-bottom:0;">
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label for="mode">Hình thức</label>
                            <select id="mode" name="mode" style="padding: 10px; margin-bottom:0;">
                                <option value="official" <?= $formSession['mode'] === 'official' ? 'selected' : '' ?>>Chính thức (Tính điểm)</option>
                                <option value="practice" <?= $formSession['mode'] === 'practice' ? 'selected' : '' ?>>Luyện tập (Tự do)</option>
                            </select>
                        </div>
                        <div>
                            <label for="max_attempts">Số lần làm tối đa</label>
                            <input id="max_attempts" type="number" name="max_attempts" min="1" max="99" value="<?= h($formSession['max_attempts']) ?>" required style="padding: 10px; margin-bottom:0;">
                        </div>
                    </div>

                    <label class="check-line" style="margin-top: 15px;">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $formSession['is_active'] === 1 ? 'checked' : '' ?>>
                        Bật trạng thái khả dụng cho học viên
                    </label>

                    <label>Chỉ định lớp / Nhóm học viên tham gia</label>
                    <?php if ($classes): ?>
                        <div class="class-grid">
                            <?php foreach ($classes as $class): ?>
                                <label class="choice">
                                    <input
                                        type="checkbox"
                                        name="class_ids[]"
                                        value="<?= h($class['id']) ?>"
                                        <?= in_array((int) $class['id'], $editingClassIds, true) ? 'checked' : '' ?>
                                    >
                                    <span>
                                        <strong style="color:var(--primary);"><?= h($class['name']) ?></strong>
                                        <span>
                                            <?= h($class['subject_name'] ?: 'Không gắn môn') ?>
                                            · Sĩ số: <?= h($class['student_count']) ?>
                                            <?php if ($class['school_year']): ?>
                                                · <?= h($class['school_year']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty">Chưa có lớp học/nhóm nào. Hãy tạo ở form dưới.</div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" <?= (!$exams || !$classes) ? 'disabled' : '' ?> style="flex: 1; padding: 11px;">
                            <i class="fa-solid fa-floppy-disk"></i> Lưu lịch thi
                        </button>
                        <?php if ($editingSession): ?>
                            <a class="btn btn-danger" href="teacher_sessions.php" style="padding: 11px; text-align:center;">
                                Hủy
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h3 style="margin-top:0;"><i class="fa-solid fa-users-viewfinder"></i> Tạo nhanh nhóm học viên thi</h3>
                <form method="POST" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_group">

                    <label for="group_name" style="margin-top:0;">Tên lớp / Nhóm đặc biệt</label>
                    <input id="group_name" type="text" name="group_name" placeholder="Ví dụ: Nhóm thi lại Lần 2" required style="padding: 10px; margin-bottom:0;">

                    <div class="row">
                        <div>
                            <label for="subject_id">Môn học</label>
                            <select id="subject_id" name="subject_id" style="padding: 10px; margin-bottom:0;">
                                <option value="0">Không gắn môn</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= h($subject['id']) ?>"><?= h($subject['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="school_year">Năm học</label>
                            <input id="school_year" type="text" name="school_year" placeholder="2025-2026" style="padding: 10px; margin-bottom:0;">
                        </div>
                    </div>

                    <label>Học viên tham gia nhóm</label>
                    <?php if ($students): ?>
                        <div class="student-grid">
                            <?php foreach ($students as $student): ?>
                                <label class="choice">
                                    <input type="checkbox" name="student_ids[]" value="<?= h($student['id']) ?>">
                                    <span>
                                        <strong><?= h($student['full_name']) ?></strong>
                                        <span><?= h($student['username']) ?> · <?= h($student['email']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty">Chưa có tài khoản học viên nào.</div>
                    <?php endif; ?>

                    <button class="btn btn-warning" type="submit" <?= !$students ? 'disabled' : '' ?> style="width: 100%; padding: 11px; margin-top: 12px;">
                        <i class="fa-solid fa-user-plus"></i> Tạo nhóm ngay
                    </button>
                </form>
            </div>
        </section>

        <section class="panel" style="margin-top:0;">
            <h3 style="margin-top:0;"><i class="fa-solid fa-list-check"></i> Danh sách các đợt/Kỳ thi đã thiết lập</h3>

            <?php if ($sessions): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Kỳ thi</th>
                                <th>Đề sử dụng</th>
                                <th>Thời gian tổ chức</th>
                                <th>Lớp tham gia</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <?php [$badgeClass, $badgeText] = sessionStatus($session); ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--text); font-size:14px;"><?= h($session['title']) ?></strong>
                                        <div class="subtle"><i class="fa-solid fa-user-pen"></i> <?= h($session['creator_name']) ?></div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--primary);"><?= h($session['exam_title']) ?></span>
                                        <div class="subtle">
                                            <?= h($session['duration_minutes']) ?> phút · <?= h($session['exam_status']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 500; color: #1e293b;"><?= h(displayDateTime($session['start_time'])) ?></span>
                                        <div class="subtle">đến <b><?= h(displayDateTime($session['end_time'])) ?></b></div>
                                        <span class="badge mode" style="font-size: 11px; padding: 2px 6px;">
                                            <?= $session['mode'] === 'practice' ? 'Luyện tập' : 'Chính thức' ?>
                                            · <?= h($session['max_attempts']) ?> lần
                                        </span>
                                    </td>
                                    <td>
                                        <div style="max-width: 220px; line-height: 1.4; font-size:12px; color: #334155;">
                                            <?= h($session['class_names'] ?: 'Chưa gán lớp nào') ?>
                                        </div>
                                    </td>
                                    <td><span class="<?= h($badgeClass) ?>"><?= h($badgeText) ?></span></td>
                                    <td>
                                        <div class="actions">
                                            <a class="btn btn-warning" style="padding: 6px 10px;" href="teacher_sessions.php?edit=<?= h($session['id']) ?>" title="Sửa thông tin kỳ thi">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <form method="POST" style="display:inline; margin:0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_session">
                                                <input type="hidden" name="session_id" value="<?= h($session['id']) ?>">
                                                <button class="btn <?= $session['is_active'] ? 'btn-secondary' : 'btn-success' ?>" style="padding: 6px 10px;" type="submit" title="<?= $session['is_active'] ? 'Tắt đợt thi' : 'Bật đợt thi' ?>">
                                                    <i class="fa-solid <?= $session['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Xóa kỳ thi này hoàn toàn khỏi danh sách?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_session">
                                                <input type="hidden" name="session_id" value="<?= h($session['id']) ?>">
                                                <button class="btn btn-danger" style="padding: 6px 10px;" type="submit" title="Xóa đợt thi">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">Chưa có kỳ thi nào được thiết lập trên hệ thống.</div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
