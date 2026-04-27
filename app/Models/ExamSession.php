<?php
/**
 * ExamSession Model - Quản lý kỳ thi (Task 8)
 */

namespace App\Models;

use App\Helpers\Database;

class ExamSession
{
    /**
     * Lấy tất cả kỳ thi (có lọc & phân trang)
     */
    public static function getAll(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['created_by'])) {
            $where[] = 'es.created_by = ?';
            $params[] = $filters['created_by'];
        }

        if (!empty($filters['status'])) {
            $now = date('Y-m-d H:i:s');
            switch ($filters['status']) {
                case 'scheduled':
                    $where[] = 'es.start_time > ?';
                    $params[] = $now;
                    break;
                case 'in_progress':
                    $where[] = 'es.start_time <= ? AND es.end_time >= ?';
                    $params[] = $now;
                    $params[] = $now;
                    break;
                case 'ended':
                    $where[] = 'es.end_time < ?';
                    $params[] = $now;
                    break;
            }
        }

        if (!empty($filters['mode'])) {
            $where[] = 'es.mode = ?';
            $params[] = $filters['mode'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = Database::queryOne(
            "SELECT COUNT(*) as total FROM exam_sessions es WHERE {$whereClause}", $params
        )['total'];

        $sql = "SELECT es.*, e.title as exam_title, e.duration_minutes, e.total_questions,
                       s.name as subject_name, u.full_name as creator_name,
                       (SELECT COUNT(*) FROM session_classes sc WHERE sc.session_id = es.id) as class_count,
                       (SELECT COUNT(*) FROM exam_results er WHERE er.session_id = es.id) as result_count
                FROM exam_sessions es
                LEFT JOIN exams e ON es.exam_id = e.id
                LEFT JOIN subjects s ON e.subject_id = s.id
                LEFT JOIN users u ON es.created_by = u.id
                WHERE {$whereClause}
                ORDER BY es.start_time DESC
                LIMIT {$perPage} OFFSET {$offset}";

        return [
            'data' => Database::query($sql, $params),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage),
        ];
    }

    /**
     * Lấy 1 kỳ thi theo ID
     */
    public static function getById(int $id): ?array
    {
        $sql = "SELECT es.*, e.title as exam_title, e.duration_minutes, e.total_questions,
                       e.subject_id, s.name as subject_name, u.full_name as creator_name
                FROM exam_sessions es
                LEFT JOIN exams e ON es.exam_id = e.id
                LEFT JOIN subjects s ON e.subject_id = s.id
                LEFT JOIN users u ON es.created_by = u.id
                WHERE es.id = ?";
        return Database::queryOne($sql, [$id]);
    }

    /**
     * Tạo kỳ thi mới
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO exam_sessions (exam_id, title, start_time, end_time, mode, max_attempts, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        Database::execute($sql, [
            $data['exam_id'],
            $data['title'],
            $data['start_time'],
            $data['end_time'],
            $data['mode'] ?? 'official',
            $data['max_attempts'] ?? 1,
            $data['is_active'] ?? 1,
            $data['created_by'],
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Cập nhật kỳ thi
     */
    public static function update(int $id, array $data): bool
    {
        $sql = "UPDATE exam_sessions SET 
                exam_id = ?, title = ?, start_time = ?, end_time = ?,
                mode = ?, max_attempts = ?, is_active = ?
                WHERE id = ?";

        return Database::execute($sql, [
            $data['exam_id'],
            $data['title'],
            $data['start_time'],
            $data['end_time'],
            $data['mode'] ?? 'official',
            $data['max_attempts'] ?? 1,
            $data['is_active'] ?? 1,
            $id,
        ]) > 0;
    }

    /**
     * Xóa kỳ thi
     */
    public static function delete(int $id): bool
    {
        return Database::execute("DELETE FROM exam_sessions WHERE id = ?", [$id]) > 0;
    }

    /**
     * Gán lớp cho kỳ thi
     */
    public static function assignClasses(int $sessionId, array $classIds): int
    {
        // Xóa gán cũ
        Database::execute("DELETE FROM session_classes WHERE session_id = ?", [$sessionId]);

        // Gán mới
        $count = 0;
        foreach ($classIds as $classId) {
            Database::execute(
                "INSERT INTO session_classes (session_id, class_id) VALUES (?, ?)",
                [$sessionId, $classId]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Lấy danh sách lớp đã gán cho kỳ thi
     */
    public static function getAssignedClasses(int $sessionId): array
    {
        $sql = "SELECT c.*, sc.id as assignment_id
                FROM session_classes sc
                JOIN classes c ON sc.class_id = c.id
                WHERE sc.session_id = ?
                ORDER BY c.name";
        return Database::query($sql, [$sessionId]);
    }

    /**
     * Lấy ID các lớp đã gán
     */
    public static function getAssignedClassIds(int $sessionId): array
    {
        $rows = Database::query(
            "SELECT class_id FROM session_classes WHERE session_id = ?", [$sessionId]
        );
        return array_column($rows, 'class_id');
    }

    /**
     * Tính trạng thái kỳ thi (realtime)
     */
    public static function getStatus(array $session): string
    {
        $now = new \DateTime();
        $start = new \DateTime($session['start_time']);
        $end = new \DateTime($session['end_time']);

        if ($now < $start) return 'scheduled';
        if ($now >= $start && $now <= $end) return 'in_progress';
        return 'ended';
    }

    /**
     * Lấy nhãn trạng thái (tiếng Việt)
     */
    public static function getStatusLabel(string $status): array
    {
        return match ($status) {
            'scheduled' => ['text' => 'Sắp diễn ra', 'class' => 'warning'],
            'in_progress' => ['text' => 'Đang diễn ra', 'class' => 'success'],
            'ended' => ['text' => 'Đã kết thúc', 'class' => 'secondary'],
            default => ['text' => 'Không rõ', 'class' => 'dark'],
        };
    }

    /**
     * Lấy danh sách tất cả lớp (helper)
     */
    public static function getAllClasses(): array
    {
        return Database::query(
            "SELECT c.*, s.name as subject_name FROM classes c LEFT JOIN subjects s ON c.subject_id = s.id WHERE c.is_active = 1 ORDER BY c.name"
        );
    }

    /**
     * Giám sát kỳ thi - xem ai đang thi
     */
    public static function getMonitorData(int $sessionId): array
    {
        $sql = "SELECT er.*, u.full_name, u.username,
                       CASE 
                           WHEN er.submitted_at IS NOT NULL THEN 'submitted'
                           WHEN er.started_at IS NOT NULL THEN 'in_progress'
                           ELSE 'not_started'
                       END as exam_status
                FROM exam_results er
                JOIN users u ON er.student_id = u.id
                WHERE er.session_id = ?
                ORDER BY er.started_at DESC";
        return Database::query($sql, [$sessionId]);
    }
}
