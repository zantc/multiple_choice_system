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

function selected($left, $right): string
{
    return (string) $left === (string) $right ? 'selected' : '';
}

function displayDateTime(?string $value): string
{
    if (!$value) {
        return 'Chưa có';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Không hợp lệ';
}

function displayNumber($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, $decimals);
}

function reportUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === '0') {
            unset($params[$key]);
        }
    }

    return 'system_reports.php' . ($params ? '?' . http_build_query($params) : '');
}

function getSessionStatus(array $session): array
{
    if (!(int) $session['is_active']) {
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

    return ['badge active', 'Đang diễn ra'];
}

function applySessionStatusFilter(string $alias, string $status, array &$where): void
{
    if ($status === 'ongoing') {
        $where[] = "{$alias}.is_active = 1 AND {$alias}.start_time <= NOW() AND {$alias}.end_time >= NOW()";
    } elseif ($status === 'ended') {
        $where[] = "{$alias}.end_time < NOW()";
    } elseif ($status === 'upcoming') {
        $where[] = "{$alias}.is_active = 1 AND {$alias}.start_time > NOW()";
    } elseif ($status === 'inactive') {
        $where[] = "{$alias}.is_active = 0";
    }
}

function buildResultWhere(array $currentUser, array $filters, array &$params, string $sessionAlias = 'es', string $examAlias = 'e', string $resultAlias = 'er'): array
{
    $where = [];

    if ($currentUser['role'] !== 'admin') {
        $where[] = "{$sessionAlias}.created_by = ?";
        $params[] = (int) $currentUser['id'];
    }

    applySessionStatusFilter($sessionAlias, $filters['status'], $where);

    if ($filters['subject_id'] > 0) {
        $where[] = "{$examAlias}.subject_id = ?";
        $params[] = $filters['subject_id'];
    }

    if ($filters['student_id'] > 0) {
        $where[] = "{$resultAlias}.student_id = ?";
        $params[] = $filters['student_id'];
    }

    if ($filters['class_id'] > 0) {
        $where[] = "EXISTS (
            SELECT 1
            FROM session_classes sc_filter
            JOIN class_students cs_filter ON cs_filter.class_id = sc_filter.class_id
            WHERE sc_filter.session_id = {$resultAlias}.session_id
              AND cs_filter.student_id = {$resultAlias}.student_id
              AND sc_filter.class_id = ?
        )";
        $params[] = $filters['class_id'];
    }

    return $where;
}

function whereSql(array $where): string
{
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

$filters = [
    'status' => $_GET['status'] ?? 'all',
    'subject_id' => max(0, (int) ($_GET['subject_id'] ?? 0)),
    'class_id' => max(0, (int) ($_GET['class_id'] ?? 0)),
    'student_id' => max(0, (int) ($_GET['student_id'] ?? 0)),
];

if (!in_array($filters['status'], ['all', 'ongoing', 'ended', 'upcoming', 'inactive'], true)) {
    $filters['status'] = 'all';
}

$subjects = $pdo->query("SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name")->fetchAll();
$classes = $pdo->query("SELECT id, name, school_year FROM classes WHERE is_active = 1 ORDER BY name")->fetchAll();
$students = $pdo->query("
    SELECT id, full_name, username, email
    FROM users
    WHERE role = 'student' AND is_active = 1
    ORDER BY full_name, username
")->fetchAll();

$sessionWhere = [];
$sessionParams = [];
if ($currentUser['role'] !== 'admin') {
    $sessionWhere[] = "es.created_by = ?";
    $sessionParams[] = $userId;
}
applySessionStatusFilter('es', $filters['status'], $sessionWhere);

if ($filters['subject_id'] > 0) {
    $sessionWhere[] = "e.subject_id = ?";
    $sessionParams[] = $filters['subject_id'];
}

if ($filters['class_id'] > 0) {
    $sessionWhere[] = "EXISTS (SELECT 1 FROM session_classes sc_filter WHERE sc_filter.session_id = es.id AND sc_filter.class_id = ?)";
    $sessionParams[] = $filters['class_id'];
}

if ($filters['student_id'] > 0) {
    $sessionWhere[] = "EXISTS (
        SELECT 1
        FROM session_classes sc_filter
        JOIN class_students cs_filter ON cs_filter.class_id = sc_filter.class_id
        WHERE sc_filter.session_id = es.id
          AND cs_filter.student_id = ?
    )";
    $sessionParams[] = $filters['student_id'];
}

$sessionStmt = $pdo->prepare("
    SELECT
        es.id,
        es.title,
        es.start_time,
        es.end_time,
        es.mode,
        es.max_attempts,
        es.is_active,
        e.title AS exam_title,
        sub.name AS subject_name,
        u.full_name AS creator_name,
        COUNT(DISTINCT sc.class_id) AS class_count,
        COUNT(DISTINCT cs.student_id) AS assigned_student_count,
        COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '), '') AS class_names,
        COUNT(DISTINCT er.id) AS result_count,
        AVG(er.score) AS avg_score
    FROM exam_sessions es
    JOIN exams e ON e.id = es.exam_id
    JOIN subjects sub ON sub.id = e.subject_id
    JOIN users u ON u.id = es.created_by
    LEFT JOIN session_classes sc ON sc.session_id = es.id
    LEFT JOIN classes c ON c.id = sc.class_id
    LEFT JOIN class_students cs ON cs.class_id = c.id
    LEFT JOIN exam_results er ON er.session_id = es.id
    " . whereSql($sessionWhere) . "
    GROUP BY es.id, es.title, es.start_time, es.end_time, es.mode, es.max_attempts, es.is_active,
             e.title, sub.name, u.full_name
    ORDER BY es.start_time DESC, es.id DESC
");
$sessionStmt->execute($sessionParams);
$sessions = $sessionStmt->fetchAll();

$resultParams = [];
$resultWhere = buildResultWhere($currentUser, $filters, $resultParams);

$detailStmt = $pdo->prepare("
    SELECT
        er.id,
        sub.name AS subject_name,
        es.title AS session_title,
        e.title AS exam_title,
        stu.full_name AS student_name,
        stu.username,
        (
            SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
            FROM session_classes sc
            JOIN classes c ON c.id = sc.class_id
            JOIN class_students cs ON cs.class_id = c.id
            WHERE sc.session_id = er.session_id
              AND cs.student_id = er.student_id
        ) AS class_names,
        er.score,
        er.correct_count,
        er.total_questions,
        er.time_spent_seconds,
        er.attempt_number,
        er.started_at,
        er.submitted_at
    FROM exam_results er
    JOIN exam_sessions es ON es.id = er.session_id
    JOIN exams e ON e.id = er.exam_id
    JOIN subjects sub ON sub.id = e.subject_id
    JOIN users stu ON stu.id = er.student_id
    " . whereSql($resultWhere) . "
    ORDER BY COALESCE(er.submitted_at, er.started_at) DESC, er.id DESC
");
$detailStmt->execute($resultParams);
$resultRows = $detailStmt->fetchAll();

$subjectParams = [];
$subjectWhere = buildResultWhere($currentUser, $filters, $subjectParams);
$subjectStmt = $pdo->prepare("
    SELECT
        sub.name AS name,
        COUNT(er.id) AS attempt_count,
        COUNT(DISTINCT er.student_id) AS student_count,
        AVG(er.score) AS avg_score,
        MIN(er.score) AS min_score,
        MAX(er.score) AS max_score,
        SUM(er.correct_count) AS correct_sum,
        SUM(er.total_questions) AS question_sum
    FROM exam_results er
    JOIN exam_sessions es ON es.id = er.session_id
    JOIN exams e ON e.id = er.exam_id
    JOIN subjects sub ON sub.id = e.subject_id
    " . whereSql($subjectWhere) . "
    GROUP BY sub.id, sub.name
    ORDER BY sub.name
");
$subjectStmt->execute($subjectParams);
$subjectStats = $subjectStmt->fetchAll();

$classParams = [];
$classWhere = [];
if ($currentUser['role'] !== 'admin') {
    $classWhere[] = "es.created_by = ?";
    $classParams[] = $userId;
}
applySessionStatusFilter('es', $filters['status'], $classWhere);
if ($filters['subject_id'] > 0) {
    $classWhere[] = "e.subject_id = ?";
    $classParams[] = $filters['subject_id'];
}
if ($filters['class_id'] > 0) {
    $classWhere[] = "c.id = ?";
    $classParams[] = $filters['class_id'];
}
if ($filters['student_id'] > 0) {
    $classWhere[] = "er.student_id = ?";
    $classParams[] = $filters['student_id'];
}

$classStmt = $pdo->prepare("
    SELECT
        c.name AS name,
        COUNT(DISTINCT er.id) AS attempt_count,
        COUNT(DISTINCT er.student_id) AS student_count,
        AVG(er.score) AS avg_score,
        MIN(er.score) AS min_score,
        MAX(er.score) AS max_score,
        SUM(er.correct_count) AS correct_sum,
        SUM(er.total_questions) AS question_sum
    FROM exam_results er
    JOIN exam_sessions es ON es.id = er.session_id
    JOIN exams e ON e.id = er.exam_id
    JOIN session_classes sc ON sc.session_id = er.session_id
    JOIN classes c ON c.id = sc.class_id
    JOIN class_students cs ON cs.class_id = c.id AND cs.student_id = er.student_id
    " . whereSql($classWhere) . "
    GROUP BY c.id, c.name
    ORDER BY c.name
");
$classStmt->execute($classParams);
$classStats = $classStmt->fetchAll();

$studentParams = [];
$studentWhere = buildResultWhere($currentUser, $filters, $studentParams);
$studentStmt = $pdo->prepare("
    SELECT
        stu.full_name AS name,
        stu.username,
        COUNT(er.id) AS attempt_count,
        COUNT(DISTINCT er.session_id) AS session_count,
        AVG(er.score) AS avg_score,
        MIN(er.score) AS min_score,
        MAX(er.score) AS max_score,
        SUM(er.correct_count) AS correct_sum,
        SUM(er.total_questions) AS question_sum
    FROM exam_results er
    JOIN exam_sessions es ON es.id = er.session_id
    JOIN exams e ON e.id = er.exam_id
    JOIN users stu ON stu.id = er.student_id
    " . whereSql($studentWhere) . "
    GROUP BY stu.id, stu.full_name, stu.username
    ORDER BY avg_score DESC, stu.full_name
");
$studentStmt->execute($studentParams);
$studentStats = $studentStmt->fetchAll();

$totalAttempts = count($resultRows);
$totalStudents = count(array_unique(array_column($resultRows, 'username')));
$avgScore = null;
if ($totalAttempts > 0) {
    $avgScore = array_sum(array_map(static fn ($row) => (float) $row['score'], $resultRows)) / $totalAttempts;
}

$ongoingCount = 0;
$endedCount = 0;
foreach ($sessions as $session) {
    [, $statusText] = getSessionStatus($session);
    if ($statusText === 'Đang diễn ra') {
        $ongoingCount++;
    } elseif ($statusText === 'Đã kết thúc') {
        $endedCount++;
    }
}

$export = $_GET['export'] ?? '';

function renderExportTables(array $sessions, array $subjectStats, array $classStats, array $studentStats, array $resultRows): void
{
    ?>
    <h2>Danh sách kỳ thi</h2>
    <table>
        <thead>
            <tr>
                <th>Kỳ thi</th>
                <th>Đề thi</th>
                <th>Môn học</th>
                <th>Bắt đầu</th>
                <th>Kết thúc</th>
                <th>Lớp/nhóm</th>
                <th>Học viên được gán</th>
                <th>Lượt nộp</th>
                <th>Điểm TB</th>
                <th>Trạng thái</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sessions as $session): ?>
                <?php [, $statusText] = getSessionStatus($session); ?>
                <tr>
                    <td><?= h($session['title']) ?></td>
                    <td><?= h($session['exam_title']) ?></td>
                    <td><?= h($session['subject_name']) ?></td>
                    <td><?= h(displayDateTime($session['start_time'])) ?></td>
                    <td><?= h(displayDateTime($session['end_time'])) ?></td>
                    <td><?= h($session['class_names']) ?></td>
                    <td><?= h($session['assigned_student_count']) ?></td>
                    <td><?= h($session['result_count']) ?></td>
                    <td><?= h(displayNumber($session['avg_score'])) ?></td>
                    <td><?= h($statusText) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Thống kê theo môn học</h2>
    <?php renderStatsTable($subjectStats, 'Môn học'); ?>

    <h2>Thống kê theo lớp/nhóm</h2>
    <?php renderStatsTable($classStats, 'Lớp/nhóm'); ?>

    <h2>Thống kê theo học viên</h2>
    <?php renderStudentStatsTable($studentStats); ?>

    <h2>Chi tiết kết quả</h2>
    <table>
        <thead>
            <tr>
                <th>Học viên</th>
                <th>Tài khoản</th>
                <th>Lớp/nhóm</th>
                <th>Môn học</th>
                <th>Kỳ thi</th>
                <th>Điểm</th>
                <th>Câu đúng</th>
                <th>Lần làm</th>
                <th>Thời gian nộp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultRows as $row): ?>
                <tr>
                    <td><?= h($row['student_name']) ?></td>
                    <td><?= h($row['username']) ?></td>
                    <td><?= h($row['class_names'] ?: '-') ?></td>
                    <td><?= h($row['subject_name']) ?></td>
                    <td><?= h($row['session_title']) ?></td>
                    <td><?= h(displayNumber($row['score'])) ?></td>
                    <td><?= h((int) $row['correct_count'] . '/' . (int) $row['total_questions']) ?></td>
                    <td><?= h($row['attempt_number']) ?></td>
                    <td><?= h(displayDateTime($row['submitted_at'] ?: $row['started_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function renderStatsTable(array $rows, string $label): void
{
    ?>
    <table>
        <thead>
            <tr>
                <th><?= h($label) ?></th>
                <th>Lượt làm</th>
                <th>Học viên</th>
                <th>Điểm TB</th>
                <th>Thấp nhất</th>
                <th>Cao nhất</th>
                <th>Tỷ lệ đúng</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $accuracy = (int) $row['question_sum'] > 0 ? ((int) $row['correct_sum'] / (int) $row['question_sum']) * 100 : null; ?>
                <tr>
                    <td><?= h($row['name']) ?></td>
                    <td><?= h($row['attempt_count']) ?></td>
                    <td><?= h($row['student_count']) ?></td>
                    <td><?= h(displayNumber($row['avg_score'])) ?></td>
                    <td><?= h(displayNumber($row['min_score'])) ?></td>
                    <td><?= h(displayNumber($row['max_score'])) ?></td>
                    <td><?= h($accuracy === null ? '-' : displayNumber($accuracy) . '%') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function renderStudentStatsTable(array $rows): void
{
    ?>
    <table>
        <thead>
            <tr>
                <th>Học viên</th>
                <th>Tài khoản</th>
                <th>Kỳ thi đã làm</th>
                <th>Lượt làm</th>
                <th>Điểm TB</th>
                <th>Thấp nhất</th>
                <th>Cao nhất</th>
                <th>Tỷ lệ đúng</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $accuracy = (int) $row['question_sum'] > 0 ? ((int) $row['correct_sum'] / (int) $row['question_sum']) * 100 : null; ?>
                <tr>
                    <td><?= h($row['name']) ?></td>
                    <td><?= h($row['username']) ?></td>
                    <td><?= h($row['session_count']) ?></td>
                    <td><?= h($row['attempt_count']) ?></td>
                    <td><?= h(displayNumber($row['avg_score'])) ?></td>
                    <td><?= h(displayNumber($row['min_score'])) ?></td>
                    <td><?= h(displayNumber($row['max_score'])) ?></td>
                    <td><?= h($accuracy === null ? '-' : displayNumber($accuracy) . '%') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

if ($export === 'excel') {
    $filename = 'bao_cao_he_thong_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; margin-bottom: 24px; }
            th, td { border: 1px solid #999; padding: 6px; }
            th { background: #ececec; }
        </style>
    </head>
    <body>
        <h1>Báo cáo giám sát và thống kê hệ thống</h1>
        <p>Xuất lúc: <?= h(date('d/m/Y H:i')) ?></p>
        <?php renderExportTables($sessions, $subjectStats, $classStats, $studentStats, $resultRows); ?>
    </body>
    </html>
    <?php
    exit;
}

if ($export === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>Báo cáo giám sát và thống kê hệ thống</title>
        <style>
            body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
            .toolbar { display: flex; gap: 10px; margin-bottom: 18px; }
            .toolbar button, .toolbar a { border: 0; border-radius: 6px; padding: 9px 14px; background: #512da8; color: #fff; text-decoration: none; font-weight: 700; cursor: pointer; }
            h1 { font-size: 24px; margin: 0 0 6px; }
            h2 { font-size: 17px; margin-top: 24px; border-bottom: 1px solid #d1d5db; padding-bottom: 6px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 16px; page-break-inside: auto; }
            th, td { border: 1px solid #d1d5db; padding: 6px; font-size: 11px; vertical-align: top; }
            th { background: #f3f4f6; }
            tr { page-break-inside: avoid; }
            @page { size: A4 landscape; margin: 12mm; }
            @media print {
                body { margin: 0; }
                .toolbar { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <button onclick="window.print()">In / Lưu PDF</button>
            <a href="system_reports.php">Quay lại</a>
        </div>
        <h1>Báo cáo giám sát và thống kê hệ thống</h1>
        <p>Xuất lúc: <?= h(date('d/m/Y H:i')) ?></p>
        <?php renderExportTables($sessions, $subjectStats, $classStats, $studentStats, $resultRows); ?>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giám sát & thống kê | Online Quiz System</title>
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
        .navbar h2 { margin: 0; font-size: 20px; }
        .nav-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
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
            max-width: 1220px;
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
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        .btn.secondary { background: #475569; }
        .btn.secondary:hover { background: #334155; }
        .btn.export { background: var(--accent); }
        .btn.export:hover { background: #115e59; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 8px 18px rgba(24, 32, 51, .05);
            margin-bottom: 18px;
        }
        .panel h3 {
            margin: 0 0 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line);
            font-size: 18px;
        }
        .filters {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 12px;
            align-items: end;
        }
        label {
            display: block;
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 700;
            color: #354055;
        }
        select {
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
        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .metric {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
        }
        .metric span {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
        }
        .metric strong {
            font-size: 26px;
            color: var(--text);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
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
        .empty {
            color: var(--muted);
            text-align: center;
            padding: 26px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
        }
        @media (max-width: 980px) {
            .filters, .cards, .grid-2 { grid-template-columns: 1fr; }
            .page-head { align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><i class="fa-solid fa-chart-line"></i> Giám sát & thống kê</h2>
        <div class="nav-actions">
            <span><?= h($currentUser['full_name']) ?> (<?= h($currentUser['role']) ?>)</span>
            <a class="nav-link" href="exam_management.php"><i class="fa-solid fa-calendar-days"></i> Quản lý kỳ thi</a>
            <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
            <a class="nav-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Thoát</a>
        </div>
    </div>

    <main class="page">
        <div class="page-head">
            <div>
                <h1>Giám sát và thống kê hệ thống</h1>
                <p>Theo dõi trạng thái kỳ thi, điểm số theo môn học, lớp/nhóm và học viên.</p>
            </div>
            <div class="actions">
                <a class="btn export" href="<?= h(reportUrl(['export' => 'excel'])) ?>"><i class="fa-solid fa-file-excel"></i> Xuất Excel</a>
                <a class="btn export" href="<?= h(reportUrl(['export' => 'pdf'])) ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Xuất PDF</a>
            </div>
        </div>

        <section class="panel">
            <h3><i class="fa-solid fa-filter"></i> Bộ lọc báo cáo</h3>
            <form method="GET" class="filters">
                <div>
                    <label for="status">Trạng thái kỳ thi</label>
                    <select id="status" name="status">
                        <option value="all" <?= selected($filters['status'], 'all') ?>>Tất cả</option>
                        <option value="ongoing" <?= selected($filters['status'], 'ongoing') ?>>Đang diễn ra</option>
                        <option value="ended" <?= selected($filters['status'], 'ended') ?>>Đã kết thúc</option>
                        <option value="upcoming" <?= selected($filters['status'], 'upcoming') ?>>Sắp mở</option>
                        <option value="inactive" <?= selected($filters['status'], 'inactive') ?>>Đã tắt</option>
                    </select>
                </div>
                <div>
                    <label for="subject_id">Môn học</label>
                    <select id="subject_id" name="subject_id">
                        <option value="0">Tất cả môn</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= h($subject['id']) ?>" <?= selected($filters['subject_id'], $subject['id']) ?>>
                                <?= h($subject['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="class_id">Lớp / nhóm</label>
                    <select id="class_id" name="class_id">
                        <option value="0">Tất cả lớp</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= h($class['id']) ?>" <?= selected($filters['class_id'], $class['id']) ?>>
                                <?= h($class['name']) ?><?= $class['school_year'] ? ' · ' . h($class['school_year']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="student_id">Học viên</label>
                    <select id="student_id" name="student_id">
                        <option value="0">Tất cả học viên</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= h($student['id']) ?>" <?= selected($filters['student_id'], $student['id']) ?>>
                                <?= h($student['full_name']) ?> · <?= h($student['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="actions">
                    <button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Lọc</button>
                    <a class="btn secondary" href="system_reports.php"><i class="fa-solid fa-rotate-left"></i> Xóa lọc</a>
                </div>
            </form>
        </section>

        <section class="cards">
            <div class="metric">
                <span>Kỳ thi đang diễn ra</span>
                <strong><?= h($ongoingCount) ?></strong>
            </div>
            <div class="metric">
                <span>Kỳ thi đã kết thúc</span>
                <strong><?= h($endedCount) ?></strong>
            </div>
            <div class="metric">
                <span>Lượt nộp bài</span>
                <strong><?= h($totalAttempts) ?></strong>
            </div>
            <div class="metric">
                <span>Điểm trung bình</span>
                <strong><?= h($avgScore === null ? '-' : displayNumber($avgScore)) ?></strong>
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
                                <th>Đề / môn</th>
                                <th>Thời gian</th>
                                <th>Lớp / nhóm</th>
                                <th>Học viên</th>
                                <th>Kết quả</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <?php [$badgeClass, $statusText] = getSessionStatus($session); ?>
                                <tr>
                                    <td>
                                        <strong><?= h($session['title']) ?></strong>
                                        <div class="subtle">Người tạo: <?= h($session['creator_name']) ?></div>
                                    </td>
                                    <td>
                                        <?= h($session['exam_title']) ?>
                                        <div class="subtle"><?= h($session['subject_name']) ?></div>
                                    </td>
                                    <td>
                                        <?= h(displayDateTime($session['start_time'])) ?>
                                        <div class="subtle">đến <?= h(displayDateTime($session['end_time'])) ?></div>
                                    </td>
                                    <td><?= h($session['class_names'] ?: 'Chưa gán') ?></td>
                                    <td><?= h($session['assigned_student_count']) ?> học viên</td>
                                    <td>
                                        <?= h($session['result_count']) ?> lượt
                                        <div class="subtle">Điểm TB: <?= h(displayNumber($session['avg_score'])) ?></div>
                                    </td>
                                    <td><span class="<?= h($badgeClass) ?>"><?= h($statusText) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">Không có kỳ thi phù hợp với bộ lọc.</div>
            <?php endif; ?>
        </section>

        <section class="grid-2">
            <div class="panel">
                <h3><i class="fa-solid fa-book"></i> Điểm theo môn học</h3>
                <?php if ($subjectStats): ?>
                    <div class="table-wrap"><?php renderStatsTable($subjectStats, 'Môn học'); ?></div>
                <?php else: ?>
                    <div class="empty">Chưa có dữ liệu điểm theo môn học.</div>
                <?php endif; ?>
            </div>
            <div class="panel">
                <h3><i class="fa-solid fa-users"></i> Điểm theo lớp / nhóm</h3>
                <?php if ($classStats): ?>
                    <div class="table-wrap"><?php renderStatsTable($classStats, 'Lớp/nhóm'); ?></div>
                <?php else: ?>
                    <div class="empty">Chưa có dữ liệu điểm theo lớp/nhóm.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <h3><i class="fa-solid fa-user-graduate"></i> Điểm theo học viên</h3>
            <?php if ($studentStats): ?>
                <div class="table-wrap"><?php renderStudentStatsTable($studentStats); ?></div>
            <?php else: ?>
                <div class="empty">Chưa có dữ liệu điểm theo học viên.</div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h3><i class="fa-solid fa-table"></i> Chi tiết kết quả</h3>
            <?php if ($resultRows): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Học viên</th>
                                <th>Lớp / nhóm</th>
                                <th>Môn học</th>
                                <th>Kỳ thi</th>
                                <th>Điểm</th>
                                <th>Câu đúng</th>
                                <th>Lần làm</th>
                                <th>Thời gian nộp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultRows as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($row['student_name']) ?></strong>
                                        <div class="subtle"><?= h($row['username']) ?></div>
                                    </td>
                                    <td><?= h($row['class_names'] ?: '-') ?></td>
                                    <td><?= h($row['subject_name']) ?></td>
                                    <td>
                                        <?= h($row['session_title']) ?>
                                        <div class="subtle"><?= h($row['exam_title']) ?></div>
                                    </td>
                                    <td><strong><?= h(displayNumber($row['score'])) ?></strong></td>
                                    <td><?= h((int) $row['correct_count'] . '/' . (int) $row['total_questions']) ?></td>
                                    <td><?= h($row['attempt_number']) ?></td>
                                    <td><?= h(displayDateTime($row['submitted_at'] ?: $row['started_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">Chưa có kết quả thi phù hợp với bộ lọc.</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
