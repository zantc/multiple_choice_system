<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['admin', 'teacher']);

$pdo = getDB();
$userId = (int) $currentUser['id'];

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

$pageTitle = "Giám sát & Thống kê | Online Quiz System";
$headerIcon = "fa-solid fa-chart-line";
$headerTitle = "Giám sát & Thống kê";
$backUrl = ($currentUser['role'] === 'teacher') ? 'teacher_dashboard.php' : 'dashboard.php';
require __DIR__ . '/layouts/header.php';
?>

<style>
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
    font-weight: 600;
    color: var(--text);
}
select {
    width: 100%;
    min-height: 42px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 9px 11px;
    font-family: inherit;
    font-size: 13px;
    background: #fff;
    color: var(--text);
}
.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.metric {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.metric span {
    display: block;
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 8px;
    font-weight: 500;
}
.metric strong {
    font-size: 26px;
    color: var(--primary);
    font-weight: 700;
}
.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-top: 20px;
}
.panel {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(24, 32, 51, .03);
    margin-bottom: 20px;
}
.panel h3 {
    margin: 0 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--line);
    font-size: 16px;
    color: var(--primary);
}
.subtle {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.45;
    margin-top: 4px;
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
@media (max-width: 980px) {
    .filters, .cards, .grid-2 { grid-template-columns: 1fr; }
}
</style>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0 0 4px; font-size: 22px;">Báo cáo giám sát toàn diện</h2>
            <p style="margin: 0; color: var(--muted); font-size: 13px;">Thống kê theo thời gian thực tiến độ làm bài, phổ điểm và hiệu suất của học viên theo các tiêu chí.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a class="btn btn-success" style="padding: 10px 15px; font-size:13px;" href="<?= h(reportUrl(['export' => 'excel'])) ?>"><i class="fa-solid fa-file-excel"></i> Xuất file Excel</a>
            <a class="btn btn-danger" style="padding: 10px 15px; font-size:13px;" href="<?= h(reportUrl(['export' => 'pdf'])) ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Xuất bản in / PDF</a>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-top: 0; border-bottom: 1px solid var(--line); padding-bottom: 10px; font-size: 15px; color: var(--primary);"><i class="fa-solid fa-filter"></i> Bộ lọc & Truy vấn Báo cáo</h3>
        <form method="GET" class="filters" style="margin: 0;">
            <div>
                <label for="status">Trạng thái kỳ thi</label>
                <select id="status" name="status">
                    <option value="all" <?= selected($filters['status'], 'all') ?>>Tất cả trạng thái</option>
                    <option value="ongoing" <?= selected($filters['status'], 'ongoing') ?>>Đang diễn ra</option>
                    <option value="ended" <?= selected($filters['status'], 'ended') ?>>Đã kết thúc</option>
                    <option value="upcoming" <?= selected($filters['status'], 'upcoming') ?>>Sắp mở</option>
                    <option value="inactive" <?= selected($filters['status'], 'inactive') ?>>Đã tắt</option>
                </select>
            </div>
            <div>
                <label for="subject_id">Môn học</label>
                <select id="subject_id" name="subject_id">
                    <option value="0">Tất cả môn học</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= h($subject['id']) ?>" <?= selected($filters['subject_id'], $subject['id']) ?>>
                            <?= h($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="class_id">Lớp / Nhóm</label>
                <select id="class_id" name="class_id">
                    <option value="0">Tất cả các lớp</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= h($class['id']) ?>" <?= selected($filters['class_id'], $class['id']) ?>>
                            <?= h($class['name']) ?><?= $class['school_year'] ? ' · ' . h($class['school_year']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="student_id">Học viên tham gia</label>
                <select id="student_id" name="student_id">
                    <option value="0">Tất cả học viên</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= h($student['id']) ?>" <?= selected($filters['student_id'], $student['id']) ?>>
                            <?= h($student['full_name']) ?> · <?= h($student['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-primary" type="submit" style="flex: 1; padding: 10px; font-size:13px;"><i class="fa-solid fa-magnifying-glass"></i> Lọc</button>
                <a class="btn btn-secondary" href="system_reports.php" style="padding: 10px; font-size:13px;" title="Đặt lại"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

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
            <span>Tổng lượt nộp bài</span>
            <strong><?= h($totalAttempts) ?></strong>
        </div>
        <div class="metric">
            <span>Điểm TB Toàn hệ thống</span>
            <strong><?= h($avgScore === null ? '-' : displayNumber($avgScore)) ?></strong>
        </div>
    </section>

    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-top:0; border-bottom: 1px solid var(--line); padding-bottom: 10px; font-size:16px; color:var(--primary);"><i class="fa-solid fa-calendar-check"></i> Thống kê tổng quan các Kỳ thi</h3>
        <?php if ($sessions): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kỳ thi</th>
                            <th>Đề / Môn</th>
                            <th>Khoảng thời gian</th>
                            <th>Lớp / Nhóm tham gia</th>
                            <th>Sĩ số gán</th>
                            <th>Lượt nộp</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <?php [$badgeClass, $statusText] = getSessionStatus($session); ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--text); font-size:13px;"><?= h($session['title']) ?></strong>
                                    <div class="subtle">Tạo bởi: <?= h($session['creator_name']) ?></div>
                                </td>
                                <td>
                                    <span style="font-weight:600; color:var(--primary);"><?= h($session['exam_title']) ?></span>
                                    <div class="subtle"><?= h($session['subject_name']) ?></div>
                                </td>
                                <td>
                                    <span style="font-weight:500; color:#1e293b;"><?= h(displayDateTime($session['start_time'])) ?></span>
                                    <div class="subtle">đến <b><?= h(displayDateTime($session['end_time'])) ?></b></div>
                                </td>
                                <td><div style="max-width: 180px; font-size:12px; line-height:1.4;"><?= h($session['class_names'] ?: 'Chưa gán') ?></div></td>
                                <td><span class="badge" style="background:#f1f5f9; color:#334155; font-weight:600;"><?= h($session['assigned_student_count']) ?> HV</span></td>
                                <td>
                                    <span style="font-weight:600; color:var(--success);"><?= h($session['result_count']) ?> lượt</span>
                                    <div class="subtle">Điểm TB: <?= h(displayNumber($session['avg_score'])) ?></div>
                                </td>
                                <td><span class="<?= h($badgeClass) ?>"><?= h($statusText) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">Không tìm thấy kỳ thi nào phù hợp với điều kiện lọc.</div>
        <?php endif; ?>
    </div>

    <section class="grid-2">
        <div class="card" style="margin:0;">
            <h3 style="margin-top:0; border-bottom: 1px solid var(--line); padding-bottom: 10px; font-size:15px; color:var(--primary);"><i class="fa-solid fa-layer-group"></i> Hiệu suất theo Môn học</h3>
            <?php if ($subjectStats): ?>
                <div class="table-wrap"><?php renderStatsTable($subjectStats, 'Môn học'); ?></div>
            <?php else: ?>
                <div class="empty">Chưa có dữ liệu thống kê theo môn học.</div>
            <?php endif; ?>
        </div>
        <div class="card" style="margin:0;">
            <h3 style="margin-top:0; border-bottom: 1px solid var(--line); padding-bottom: 10px; font-size:15px; color:var(--primary);"><i class="fa-solid fa-users"></i> Hiệu suất theo Lớp / Nhóm</h3>
            <?php if ($classStats): ?>
                <div class="table-wrap"><?php renderStatsTable($classStats, 'Lớp/nhóm'); ?></div>
            <?php else: ?>
                <div class="empty">Chưa có dữ liệu thống kê theo lớp/nhóm.</div>
            <?php endif; ?>
        </div>
    </section>

    <div class="card" style="margin-top: 20px;">
        <h3 style="margin-top:0; border-bottom: 1px solid var(--line); padding-bottom: 10px; font-size:16px; color:var(--primary);"><i class="fa-solid fa-user-graduate"></i> Tổng hợp kết quả theo từng Học viên</h3>
        <?php if ($studentStats): ?>
            <div class="table-wrap"><?php renderStudentStatsTable($studentStats); ?></div>
        <?php else: ?>
            <div class="empty">Chưa có dữ liệu thống kê chi tiết theo học viên.</div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 style="margin-top:0; border-bottom: 1px solid var(--line); padding-bottom: 10px; font-size:16px; color:var(--primary);"><i class="fa-solid fa-file-invoice"></i> Danh sách chi tiết các Lượt nộp bài</h3>
        <?php if ($resultRows): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Học viên</th>
                            <th>Lớp / Nhóm</th>
                            <th>Môn học</th>
                            <th>Kỳ thi tham gia</th>
                            <th>Điểm số</th>
                            <th>Tỷ lệ đúng</th>
                            <th>Lần thi</th>
                            <th>Thời gian nộp bài</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultRows as $row): ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--text); font-size:13px;"><?= h($row['student_name']) ?></strong>
                                    <div class="subtle"><?= h($row['username']) ?></div>
                                </td>
                                <td><span style="font-size:12px; color:#475569;"><?= h($row['class_names'] ?: '-') ?></span></td>
                                <td><span class="badge" style="background:#f1f5f9; color:#334155; font-weight:500;"><?= h($row['subject_name']) ?></span></td>
                                <td>
                                    <span style="font-weight:500; color:var(--primary);"><?= h($row['session_title']) ?></span>
                                    <div class="subtle"><?= h($row['exam_title']) ?></div>
                                </td>
                                <td><strong style="color:var(--success); font-size:15px;"><?= h(displayNumber($row['score'])) ?></strong></td>
                                <td><span class="badge" style="background:#e0e7ff; color:#3730a3;"><?= h((int) $row['correct_count'] . ' / ' . (int) $row['total_questions']) ?></span></td>
                                <td style="text-align:center;"><b><?= h($row['attempt_number']) ?></b></td>
                                <td><span style="font-size:12px; color:#475569;"><?= h(displayDateTime($row['submitted_at'] ?: $row['started_at'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">Chưa có lượt nộp bài nào phù hợp với bộ lọc hiện tại.</div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
