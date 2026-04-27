<?php
/**
 * Question Model - Quản lý ngân hàng câu hỏi (Task 6)
 */

namespace App\Models;

use App\Helpers\Database;

class Question
{
    /**
     * Lấy tất cả câu hỏi (có lọc & phân trang)
     */
    public static function getAll(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $where = ['q.is_active = 1'];
        $params = [];

        // Lọc theo môn học
        if (!empty($filters['subject_id'])) {
            $where[] = 'q.subject_id = ?';
            $params[] = $filters['subject_id'];
        }

        // Lọc theo chương
        if (!empty($filters['chapter_id'])) {
            $where[] = 'q.chapter_id = ?';
            $params[] = $filters['chapter_id'];
        }

        // Lọc theo độ khó
        if (!empty($filters['difficulty'])) {
            $where[] = 'q.difficulty = ?';
            $params[] = $filters['difficulty'];
        }

        // Lọc theo giáo viên tạo
        if (!empty($filters['created_by'])) {
            $where[] = 'q.created_by = ?';
            $params[] = $filters['created_by'];
        }

        // Tìm kiếm từ khóa
        if (!empty($filters['keyword'])) {
            $where[] = '(q.content LIKE ? OR q.option_a LIKE ? OR q.option_b LIKE ? OR q.option_c LIKE ? OR q.option_d LIKE ?)';
            $keyword = '%' . $filters['keyword'] . '%';
            $params = array_merge($params, [$keyword, $keyword, $keyword, $keyword, $keyword]);
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Đếm tổng
        $countSql = "SELECT COUNT(*) as total FROM questions q WHERE {$whereClause}";
        $total = Database::queryOne($countSql, $params)['total'];

        // Lấy dữ liệu
        $sql = "SELECT q.*, s.name as subject_name, c.name as chapter_name, u.full_name as creator_name
                FROM questions q
                LEFT JOIN subjects s ON q.subject_id = s.id
                LEFT JOIN chapters c ON q.chapter_id = c.id
                LEFT JOIN users u ON q.created_by = u.id
                WHERE {$whereClause}
                ORDER BY q.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $questions = Database::query($sql, $params);

        return [
            'data' => $questions,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage),
        ];
    }

    /**
     * Lấy 1 câu hỏi theo ID
     */
    public static function getById(int $id): ?array
    {
        $sql = "SELECT q.*, s.name as subject_name, c.name as chapter_name
                FROM questions q
                LEFT JOIN subjects s ON q.subject_id = s.id
                LEFT JOIN chapters c ON q.chapter_id = c.id
                WHERE q.id = ?";
        return Database::queryOne($sql, [$id]);
    }

    /**
     * Thêm câu hỏi mới
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO questions (subject_id, chapter_id, content, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        Database::execute($sql, [
            $data['subject_id'],
            $data['chapter_id'] ?: null,
            $data['content'],
            $data['option_a'],
            $data['option_b'],
            $data['option_c'],
            $data['option_d'],
            $data['correct_answer'],
            $data['explanation'] ?? null,
            $data['difficulty'] ?? 'medium',
            $data['created_by'],
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Cập nhật câu hỏi
     */
    public static function update(int $id, array $data): bool
    {
        $sql = "UPDATE questions SET 
                subject_id = ?, chapter_id = ?, content = ?, 
                option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                correct_answer = ?, explanation = ?, difficulty = ?
                WHERE id = ?";
        
        return Database::execute($sql, [
            $data['subject_id'],
            $data['chapter_id'] ?: null,
            $data['content'],
            $data['option_a'],
            $data['option_b'],
            $data['option_c'],
            $data['option_d'],
            $data['correct_answer'],
            $data['explanation'] ?? null,
            $data['difficulty'] ?? 'medium',
            $id,
        ]) > 0;
    }

    /**
     * Xóa mềm câu hỏi
     */
    public static function delete(int $id): bool
    {
        return Database::execute("UPDATE questions SET is_active = 0 WHERE id = ?", [$id]) > 0;
    }

    /**
     * Kiểm tra câu hỏi đã được dùng trong đề thi chưa
     */
    public static function isUsedInExam(int $id): bool
    {
        $result = Database::queryOne(
            "SELECT COUNT(*) as count FROM exam_questions WHERE question_id = ?", [$id]
        );
        return $result['count'] > 0;
    }

    /**
     * Đếm câu hỏi theo bộ lọc (dùng cho tạo đề ngẫu nhiên)
     */
    public static function countByFilter(int $subjectId, ?string $difficulty = null, ?int $chapterId = null): int
    {
        $where = ['subject_id = ?', 'is_active = 1'];
        $params = [$subjectId];

        if ($difficulty) {
            $where[] = 'difficulty = ?';
            $params[] = $difficulty;
        }
        if ($chapterId) {
            $where[] = 'chapter_id = ?';
            $params[] = $chapterId;
        }

        $sql = "SELECT COUNT(*) as total FROM questions WHERE " . implode(' AND ', $where);
        return Database::queryOne($sql, $params)['total'];
    }

    /**
     * Lấy ngẫu nhiên câu hỏi theo điều kiện (dùng cho tạo đề)
     */
    public static function getRandomByFilter(int $subjectId, int $limit, ?string $difficulty = null, ?int $chapterId = null): array
    {
        $where = ['subject_id = ?', 'is_active = 1'];
        $params = [$subjectId];

        if ($difficulty) {
            $where[] = 'difficulty = ?';
            $params[] = $difficulty;
        }
        if ($chapterId) {
            $where[] = 'chapter_id = ?';
            $params[] = $chapterId;
        }

        $sql = "SELECT * FROM questions WHERE " . implode(' AND ', $where) . " ORDER BY RAND() LIMIT {$limit}";
        return Database::query($sql, $params);
    }

    /**
     * Import nhiều câu hỏi cùng lúc
     */
    public static function bulkInsert(array $rows, int $subjectId, int $createdBy): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $row['subject_id'] = $subjectId;
            $row['created_by'] = $createdBy;
            self::create($row);
            $count++;
        }
        return $count;
    }

    /**
     * Lấy tất cả môn học (helper)
     */
    public static function getAllSubjects(): array
    {
        return Database::query("SELECT * FROM subjects WHERE is_active = 1 ORDER BY name");
    }

    /**
     * Lấy chương theo môn học
     */
    public static function getChaptersBySubject(int $subjectId): array
    {
        return Database::query(
            "SELECT * FROM chapters WHERE subject_id = ? ORDER BY order_num",
            [$subjectId]
        );
    }
}
