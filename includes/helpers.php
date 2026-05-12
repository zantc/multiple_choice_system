<?php
/**
 * Shared helper functions for the Online Quiz System.
 * Eliminates hundreds of lines of duplicate helper definitions across different files.
 */

/**
 * Escape HTML output securely.
 */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Return 'selected' if two values match.
 */
function selected($left, $right): string
{
    return (string) $left === (string) $right ? 'selected' : '';
}

/**
 * Format timestamp or string to readable datetime.
 */
function displayDateTime(?string $value): string
{
    if (!$value) {
        return 'Chưa có';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Không hợp lệ';
}

/**
 * Format numbers cleanly.
 */
function displayNumber($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, $decimals);
}

/**
 * Normalize submitted datetime string to standard MySQL DATETIME format.
 */
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

/**
 * Convert MySQL datetime string to HTML datetime-local input format.
 */
function toDateTimeLocal(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

/**
 * Get session status badge and text (exam_management version).
 */
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

/**
 * Get session status badge and text (reports/analytics version).
 */
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

/**
 * Get assigned session status badge and text (student dashboard version).
 */
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

/**
 * Filter unique positive integer IDs from an array.
 */
function uniquePositiveIds(array $ids): array
{
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, static fn ($id) => $id > 0);
    return array_values(array_unique($ids));
}

/**
 * Fetch list of valid integer IDs existing in a given database table.
 */
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

/**
 * Fetch a session ensuring current user has permission to manage it.
 */
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

/**
 * Validate if an exam exists and can be managed/used by the current user.
 */
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

/**
 * Update many-to-many relationship mapping between exam sessions and classes.
 */
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

/**
 * Build URL preserving active query parameters for reporting.
 */
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

/**
 * Apply session active/time filters to WHERE array.
 */
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

/**
 * Build report/analytics WHERE clause array and populate execution parameters.
 */
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

/**
 * Convert WHERE array into full string prepended with WHERE.
 */
function whereSql(array $where): string
{
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}
