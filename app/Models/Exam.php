<?php
/**
 * Exam Model - Quản lý đề thi (Task 7)
 */

namespace App\Models;

use App\Helpers\Database;

class Exam
{
    /**
     * Lấy tất cả đề thi (có lọc & phân trang)
     */
    public static function getAll(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['subject_id'])) {
            $where[] = 'e.subject_id = ?';
            $params[] = $filters['subject_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['created_by'])) {
            $where[] = 'e.created_by = ?';
            $params[] = $filters['created_by'];
        }

        if (!empty($filters['keyword'])) {
            $where[] = 'e.title LIKE ?';
            $params[] = '%' . $filters['keyword'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = Database::queryOne(
            "SELECT COUNT(*) as total FROM exams e WHERE {$whereClause}", $params
        )['total'];

        $sql = "SELECT e.*, s.name as subject_name, u.full_name as creator_name,
                       (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count
                FROM exams e
                LEFT JOIN subjects s ON e.subject_id = s.id
                LEFT JOIN users u ON e.created_by = u.id
                WHERE {$whereClause}
                ORDER BY e.created_at DESC
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
     * Lấy 1 đề thi theo ID (kèm câu hỏi)
     */
    public static function getById(int $id): ?array
    {
        $sql = "SELECT e.*, s.name as subject_name, u.full_name as creator_name
                FROM exams e
                LEFT JOIN subjects s ON e.subject_id = s.id
                LEFT JOIN users u ON e.created_by = u.id
                WHERE e.id = ?";
        return Database::queryOne($sql, [$id]);
    }

    /**
     * Lấy câu hỏi của đề thi
     */
    public static function getQuestions(int $examId): array
    {
        $sql = "SELECT eq.*, q.content, q.option_a, q.option_b, q.option_c, q.option_d,
                       q.correct_answer, q.explanation, q.difficulty, 
                       c.name as chapter_name
                FROM exam_questions eq
                JOIN questions q ON eq.question_id = q.id
                LEFT JOIN chapters c ON q.chapter_id = c.id
                WHERE eq.exam_id = ?
                ORDER BY eq.order_num ASC";
        return Database::query($sql, [$examId]);
    }

    /**
     * Tạo đề thi mới
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO exams (title, subject_id, created_by, duration_minutes, total_questions, max_score, pass_score, shuffle_questions, shuffle_answers, show_result, show_explanation, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        Database::execute($sql, [
            $data['title'],
            $data['subject_id'],
            $data['created_by'],
            $data['duration_minutes'] ?? 60,
            $data['total_questions'] ?? 0,
            $data['max_score'] ?? 10.00,
            $data['pass_score'] ?? 5.00,
            $data['shuffle_questions'] ?? 0,
            $data['shuffle_answers'] ?? 0,
            $data['show_result'] ?? 1,
            $data['show_explanation'] ?? 0,
            $data['status'] ?? 'draft',
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Cập nhật đề thi
     */
    public static function update(int $id, array $data): bool
    {
        $sql = "UPDATE exams SET 
                title = ?, subject_id = ?, duration_minutes = ?, total_questions = ?,
                max_score = ?, pass_score = ?, shuffle_questions = ?, shuffle_answers = ?,
                show_result = ?, show_explanation = ?, status = ?
                WHERE id = ?";

        return Database::execute($sql, [
            $data['title'],
            $data['subject_id'],
            $data['duration_minutes'] ?? 60,
            $data['total_questions'] ?? 0,
            $data['max_score'] ?? 10.00,
            $data['pass_score'] ?? 5.00,
            $data['shuffle_questions'] ?? 0,
            $data['shuffle_answers'] ?? 0,
            $data['show_result'] ?? 1,
            $data['show_explanation'] ?? 0,
            $data['status'] ?? 'draft',
            $id,
        ]) > 0;
    }

    /**
     * Xóa đề thi
     */
    public static function delete(int $id): bool
    {
        return Database::execute("DELETE FROM exams WHERE id = ?", [$id]) > 0;
    }

    /**
     * Thêm câu hỏi vào đề thi
     */
    public static function addQuestion(int $examId, int $questionId, int $orderNum = 0, ?float $score = null): bool
    {
        $sql = "INSERT IGNORE INTO exam_questions (exam_id, question_id, order_num, score) VALUES (?, ?, ?, ?)";
        return Database::execute($sql, [$examId, $questionId, $orderNum, $score]) > 0;
    }

    /**
     * Xóa câu hỏi khỏi đề thi
     */
    public static function removeQuestion(int $examId, int $questionId): bool
    {
        return Database::execute(
            "DELETE FROM exam_questions WHERE exam_id = ? AND question_id = ?",
            [$examId, $questionId]
        ) > 0;
    }

    /**
     * Xóa tất cả câu hỏi của đề thi
     */
    public static function clearQuestions(int $examId): bool
    {
        Database::execute("DELETE FROM exam_questions WHERE exam_id = ?", [$examId]);
        return true;
    }

    /**
     * Thêm câu hỏi hàng loạt vào đề
     */
    public static function addQuestions(int $examId, array $questionIds): int
    {
        $count = 0;
        foreach ($questionIds as $order => $qId) {
            if (self::addQuestion($examId, $qId, $order + 1)) {
                $count++;
            }
        }
        // Cập nhật tổng câu hỏi
        Database::execute("UPDATE exams SET total_questions = ? WHERE id = ?", [$count, $examId]);
        return $count;
    }

    /**
     * Cập nhật trạng thái đề thi
     */
    public static function updateStatus(int $id, string $status): bool
    {
        return Database::execute("UPDATE exams SET status = ? WHERE id = ?", [$status, $id]) > 0;
    }

    /**
     * Lấy đề thi đã published (dùng cho tạo kỳ thi)
     */
    public static function getPublished(?int $subjectId = null): array
    {
        $where = "e.status = 'published'";
        $params = [];

        if ($subjectId) {
            $where .= " AND e.subject_id = ?";
            $params[] = $subjectId;
        }

        $sql = "SELECT e.*, s.name as subject_name, 
                       (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count
                FROM exams e 
                LEFT JOIN subjects s ON e.subject_id = s.id
                WHERE {$where}
                ORDER BY e.created_at DESC";
        return Database::query($sql, $params);
    }

    /**
     * Kiểm tra đề thi có đang được dùng trong kỳ thi nào không
     */
    public static function isUsedInSession(int $id): bool
    {
        $result = Database::queryOne(
            "SELECT COUNT(*) as count FROM exam_sessions WHERE exam_id = ?", [$id]
        );
        return $result['count'] > 0;
    }
}
