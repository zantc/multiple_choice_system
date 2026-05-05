<?php
session_start();
require 'config.php';

date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$userId = (int) $_SESSION['user_id'];

$userStmt = $pdo->prepare("SELECT id, full_name, email, role FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch();

if (!$currentUser || !in_array($currentUser['role'], ['admin', 'teacher'], true)) {
    http_response_code(403);
    echo '<h2 style="font-family:Arial,sans-serif;color:#b91c1c;">Bạn không có quyền truy cập trang này.</h2>';
    echo '<p><a href="dashboard.php">Quay lại dashboard</a></p>';
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flashAndRedirect(string $type, string $message, string $suffix = ''): void
{
    $_SESSION['exam_management_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
    header('Location: exam_management.php' . $suffix);
    exit;
}

function normalizeDateTime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function toDateTimeLocal(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function displayDateTime(?string $value): string
{
    if (!$value) {
        return 'Chưa có';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Không hợp lệ';
}

function uniquePositiveIds(array $ids): array
{
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, static fn ($id) => $id > 0);
    return array_values(array_unique($ids));
}

function fetchValidIds(PDO $pdo, string $table, array $ids, string $extraWhere = ''): array
{
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id FROM {$table} WHERE id IN ({$placeholders}) {$extraWhere}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($validIds);
    return $validIds;
}

function fetchManageableSession(PDO $pdo, int $sessionId, array $currentUser): ?array
{
    $sql = "SELECT * FROM exam_sessions WHERE id = ?";
    $params = [$sessionId];

    if ($currentUser['role'] !== 'admin') {
        $sql .= " AND created_by = ?";
        $params[] = (int) $currentUser['id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $session = $stmt->fetch();

    return $session ?: null;
}

function validateExamForUser(PDO $pdo, int $examId, array $currentUser): bool
{
    $sql = "SELECT id FROM exams WHERE id = ? AND status <> 'archived'";
    $params = [$examId];

    if ($currentUser['role'] !== 'admin') {
        $sql .= " AND created_by = ?";
        $params[] = (int) $currentUser['id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function saveSessionClasses(PDO $pdo, int $sessionId, array $classIds): void
{
    $pdo->prepare("DELETE FROM session_classes WHERE session_id = ?")->execute([$sessionId]);

    if (!$classIds) {
        return;
    }

    $insert = $pdo->prepare("INSERT INTO session_classes (session_id, class_id) VALUES (?, ?)");
    foreach ($classIds as $classId) {
        $insert->execute([$sessionId, $classId]);
    }
}

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

            flashAndRedirect('success', 'Đã xóa kỳ thi.');
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

            flashAndRedirect('success', 'Đã tạo nhóm học viên.');
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

$flash = $_SESSION['exam_management_flash'] ?? null;
unset($_SESSION['exam_management_flash']);

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

function sessionStatus(array $session): array
{
    if (!$session['is_active']) {
        return ['badge muted', 'Đã tắt'];
    }

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
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý kỳ thi | Online Quiz System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap');
        :root {
            --primary: #512da8;
            --primary-dark: #3f237f;
            --accent: #0f766e;
            --danger: #b91c1c;
            --warning: #b45309;
            --text: #182033;
            --muted: #657184;
            --line: #d9dee8;
            --surface: #ffffff;
            --page: #f4f6fb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Montserrat', sans-serif;
            background: var(--page);
            color: var(--text);
        }
        .navbar {
            background: var(--primary);
            color: #fff;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .navbar h2 {
            margin: 0;
            font-size: 20px;
        }
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .nav-link {
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            background: rgba(255,255,255,.18);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
        }
        .nav-link:hover { background: rgba(255,255,255,.3); }
        .page {
            max-width: 1180px;
            margin: 26px auto 42px;
            padding: 0 20px;
        }
        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .page-head h1 {
            margin: 0 0 6px;
            font-size: 28px;
            letter-spacing: 0;
        }
        .page-head p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        .layout {
            display: grid;
            grid-template-columns: minmax(320px, 390px) 1fr;
            gap: 20px;
            align-items: start;
        }
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 8px 18px rgba(24, 32, 51, .05);
        }
        .panel + .panel { margin-top: 18px; }
        .panel h3 {
            margin: 0 0 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line);
            font-size: 18px;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 700;
        }
        .alert.success {
            background: #e7f7ee;
            color: #166534;
            border: 1px solid #b7e4c7;
        }
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        label {
            display: block;
            margin: 12px 0 6px;
            font-size: 13px;
            font-weight: 700;
            color: #354055;
        }
        input, select {
            width: 100%;
            min-height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 9px 11px;
            font-family: inherit;
            font-size: 14px;
            background: #fff;
            color: var(--text);
        }
        input:focus, select:focus {
            outline: 2px solid rgba(81, 45, 168, .18);
            border-color: var(--primary);
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .row > div {
            min-width: 0;
        }
        .time-row {
            grid-template-columns: 1fr;
        }
        .time-row input {
            max-width: 100%;
        }
        .check-line {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-size: 14px;
            color: #354055;
            font-weight: 700;
        }
        .check-line input {
            width: 18px;
            min-height: 18px;
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
            border: 1px solid #edf1f7;
            font-size: 13px;
        }
        .choice input {
            width: 16px;
            min-height: 16px;
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
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 6px;
            min-height: 40px;
            padding: 9px 14px;
            color: #fff;
            background: var(--primary);
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn:hover { background: var(--primary-dark); }
        .btn.secondary {
            background: #475569;
        }
        .btn.secondary:hover { background: #334155; }
        .btn.warning {
            background: var(--warning);
        }
        .btn.warning:hover { background: #92400e; }
        .btn.danger {
            background: var(--danger);
        }
        .btn.danger:hover { background: #7f1d1d; }
        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
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
        }
        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }
        th, td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 10px;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }
        th {
            color: #475569;
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
        }
        .subtle {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            margin-top: 4px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.waiting { background: #e0f2fe; color: #075985; }
        .badge.closed { background: #fef3c7; color: #92400e; }
        .badge.muted { background: #e5e7eb; color: #4b5563; }
        .badge.mode { background: #ede9fe; color: #5b21b6; margin-top: 6px; }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .actions form { margin: 0; }
        .actions .btn {
            min-height: 34px;
            padding: 7px 9px;
            font-size: 12px;
        }
        @media (max-width: 920px) {
            .layout { grid-template-columns: 1fr; }
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><i class="fa-solid fa-calendar-days"></i> Quản lý kỳ thi</h2>
        <div class="nav-actions">
            <span><?= h($currentUser['full_name']) ?> (<?= h($currentUser['role']) ?>)</span>
            <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
            <a class="nav-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Thoát</a>
        </div>
    </div>

    <main class="page">
        <div class="page-head">
            <div>
                <h1>Kỳ thi</h1>
                <p>Tạo kỳ thi, chọn đề, đặt thời gian và gán cho lớp/nhóm học viên.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>

        <div class="layout">
            <section>
                <div class="panel">
                    <h3>
                        <i class="fa-solid fa-pen-to-square"></i>
                        <?= $editingSession ? 'Sửa kỳ thi' : 'Tạo kỳ thi' ?>
                    </h3>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_session">
                        <input type="hidden" name="session_id" value="<?= h($formSession['id']) ?>">

                        <label for="title">Tên kỳ thi</label>
                        <input id="title" type="text" name="title" value="<?= h($formSession['title']) ?>" required>

                        <label for="exam_id">Đề thi</label>
                        <select id="exam_id" name="exam_id" required>
                            <option value="">-- Chọn đề --</option>
                            <?php foreach ($exams as $exam): ?>
                                <option
                                    value="<?= h($exam['id']) ?>"
                                    <?= (int) $formSession['exam_id'] === (int) $exam['id'] ? 'selected' : '' ?>
                                >
                                    <?= h($exam['title']) ?>
                                    · <?= h($exam['subject_name'] ?: 'Chưa có môn') ?>
                                    · <?= h($exam['duration_minutes']) ?> phút
                                    · <?= h($exam['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="row time-row">
                            <div>
                                <label for="start_time">Bắt đầu</label>
                                <input id="start_time" type="datetime-local" name="start_time" value="<?= h(toDateTimeLocal($formSession['start_time'])) ?>" required>
                            </div>
                            <div>
                                <label for="end_time">Kết thúc</label>
                                <input id="end_time" type="datetime-local" name="end_time" value="<?= h(toDateTimeLocal($formSession['end_time'])) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div>
                                <label for="mode">Chế độ</label>
                                <select id="mode" name="mode">
                                    <option value="official" <?= $formSession['mode'] === 'official' ? 'selected' : '' ?>>Chính thức</option>
                                    <option value="practice" <?= $formSession['mode'] === 'practice' ? 'selected' : '' ?>>Luyện tập</option>
                                </select>
                            </div>
                            <div>
                                <label for="max_attempts">Số lần làm tối đa</label>
                                <input id="max_attempts" type="number" name="max_attempts" min="1" max="99" value="<?= h($formSession['max_attempts']) ?>" required>
                            </div>
                        </div>

                        <label class="check-line">
                            <input type="checkbox" name="is_active" value="1" <?= (int) $formSession['is_active'] === 1 ? 'checked' : '' ?>>
                            Bật kỳ thi
                        </label>

                        <label>Lớp / nhóm học viên</label>
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
                                            <strong><?= h($class['name']) ?></strong>
                                            <span>
                                                <?= h($class['subject_name'] ?: 'Không gắn môn') ?>
                                                · <?= h($class['student_count']) ?> học viên
                                                <?php if ($class['school_year']): ?>
                                                    · <?= h($class['school_year']) ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty">Chưa có lớp hoặc nhóm học viên.</div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button class="btn" type="submit" <?= (!$exams || !$classes) ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-floppy-disk"></i> Lưu kỳ thi
                            </button>
                            <?php if ($editingSession): ?>
                                <a class="btn secondary" href="exam_management.php">
                                    <i class="fa-solid fa-xmark"></i> Hủy sửa
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="panel">
                    <h3><i class="fa-solid fa-users"></i> Tạo nhóm học viên</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_group">

                        <label for="group_name">Tên lớp / nhóm</label>
                        <input id="group_name" type="text" name="group_name" required>

                        <div class="row">
                            <div>
                                <label for="subject_id">Môn học</label>
                                <select id="subject_id" name="subject_id">
                                    <option value="0">Không gắn môn</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= h($subject['id']) ?>"><?= h($subject['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="school_year">Năm học</label>
                                <input id="school_year" type="text" name="school_year" placeholder="2025-2026">
                            </div>
                        </div>

                        <label>Học viên</label>
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
                            <div class="empty">Chưa có học viên để tạo nhóm.</div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button class="btn secondary" type="submit" <?= !$students ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-user-plus"></i> Tạo nhóm
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <h3><i class="fa-solid fa-list-check"></i> Danh sách kỳ thi</h3>

                <?php if ($sessions): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kỳ thi</th>
                                    <th>Đề</th>
                                    <th>Thời gian</th>
                                    <th>Lớp / nhóm</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <?php [$badgeClass, $badgeText] = sessionStatus($session); ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($session['title']) ?></strong>
                                            <div class="subtle">Người tạo: <?= h($session['creator_name']) ?></div>
                                        </td>
                                        <td>
                                            <?= h($session['exam_title']) ?>
                                            <div class="subtle">
                                                <?= h($session['duration_minutes']) ?> phút · <?= h($session['exam_status']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= h(displayDateTime($session['start_time'])) ?>
                                            <div class="subtle">đến <?= h(displayDateTime($session['end_time'])) ?></div>
                                            <span class="badge mode">
                                                <?= $session['mode'] === 'practice' ? 'Luyện tập' : 'Chính thức' ?>
                                                · <?= h($session['max_attempts']) ?> lần
                                            </span>
                                        </td>
                                        <td><?= h($session['class_names'] ?: 'Chưa gán') ?></td>
                                        <td><span class="<?= h($badgeClass) ?>"><?= h($badgeText) ?></span></td>
                                        <td>
                                            <div class="actions">
                                                <a class="btn secondary" href="exam_management.php?edit=<?= h($session['id']) ?>" title="Sửa">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="toggle_session">
                                                    <input type="hidden" name="session_id" value="<?= h($session['id']) ?>">
                                                    <button class="btn warning" type="submit" title="<?= $session['is_active'] ? 'Tắt' : 'Bật' ?>">
                                                        <i class="fa-solid <?= $session['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Xóa kỳ thi này?');">
                                                    <input type="hidden" name="action" value="delete_session">
                                                    <input type="hidden" name="session_id" value="<?= h($session['id']) ?>">
                                                    <button class="btn danger" type="submit" title="Xóa">
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
                    <div class="empty">Chưa có kỳ thi nào.</div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
