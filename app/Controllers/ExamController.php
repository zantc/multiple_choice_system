<?php
/**
 * ExamController - Quản lý đề thi (Task 7)
 */

namespace App\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Helpers\Session;
use App\Helpers\Validator;

class ExamController extends BaseController
{
    public function __construct()
    {
        $this->requireRole('teacher', 'admin');
    }

    /**
     * Danh sách đề thi
     */
    public function index(): void
    {
        $filters = [
            'subject_id' => $this->get('subject_id'),
            'status' => $this->get('status'),
            'keyword' => $this->get('keyword'),
        ];

        if (Session::getUserRole() === 'teacher') {
            $filters['created_by'] = Session::getUserId();
        }

        $page = (int)($this->get('page', 1));
        $result = Exam::getAll($filters, $page);
        $subjects = Question::getAllSubjects();

        $this->view('teacher.exams.index', [
            'pageTitle' => 'Quản Lý Đề Thi',
            'exams' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Form tạo đề thi mới
     */
    public function create(): void
    {
        $subjects = Question::getAllSubjects();
        $this->view('teacher.exams.create', [
            'pageTitle' => 'Tạo Đề Thi Mới',
            'subjects' => $subjects,
        ]);
    }

    /**
     * Lưu đề thi mới
     */
    public function store(): void
    {
        if (!$this->isPost()) {
            $this->redirect('exam');
        }

        $validator = new Validator();
        $validator
            ->required('title', $this->post('title'), 'Tên đề thi')
            ->required('subject_id', $this->post('subject_id'), 'Môn học')
            ->required('duration_minutes', $this->post('duration_minutes'), 'Thời gian')
            ->numeric('duration_minutes', $this->post('duration_minutes'), 'Thời gian');

        if (!$validator->passes()) {
            Session::setFlash('error', $validator->getFirstError());
            $this->redirect('exam/create');
            return;
        }

        $data = [
            'title' => $this->post('title'),
            'subject_id' => $this->post('subject_id'),
            'created_by' => Session::getUserId(),
            'duration_minutes' => (int)$this->post('duration_minutes'),
            'total_questions' => 0,
            'max_score' => (float)($this->post('max_score') ?? 10),
            'pass_score' => (float)($this->post('pass_score') ?? 5),
            'shuffle_questions' => $this->post('shuffle_questions') ? 1 : 0,
            'shuffle_answers' => $this->post('shuffle_answers') ? 1 : 0,
            'show_result' => $this->post('show_result') ? 1 : 0,
            'show_explanation' => $this->post('show_explanation') ? 1 : 0,
            'status' => 'draft',
        ];

        $examId = Exam::create($data);
        Session::setFlash('success', 'Đã tạo đề thi! Bây giờ hãy thêm câu hỏi vào đề.');
        $this->redirect("exam/selectQuestions/{$examId}");
    }

    /**
     * Form sửa đề thi
     */
    public function edit(int $id): void
    {
        $exam = Exam::getById($id);
        if (!$exam) {
            Session::setFlash('error', 'Không tìm thấy đề thi.');
            $this->redirect('exam');
            return;
        }

        $subjects = Question::getAllSubjects();
        $this->view('teacher.exams.edit', [
            'pageTitle' => 'Chỉnh Sửa Đề Thi',
            'exam' => $exam,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Cập nhật đề thi
     */
    public function update(int $id): void
    {
        if (!$this->isPost()) {
            $this->redirect('exam');
        }

        $data = [
            'title' => $this->post('title'),
            'subject_id' => $this->post('subject_id'),
            'duration_minutes' => (int)$this->post('duration_minutes'),
            'total_questions' => (int)$this->post('total_questions'),
            'max_score' => (float)($this->post('max_score') ?? 10),
            'pass_score' => (float)($this->post('pass_score') ?? 5),
            'shuffle_questions' => $this->post('shuffle_questions') ? 1 : 0,
            'shuffle_answers' => $this->post('shuffle_answers') ? 1 : 0,
            'show_result' => $this->post('show_result') ? 1 : 0,
            'show_explanation' => $this->post('show_explanation') ? 1 : 0,
            'status' => $this->post('status') ?? 'draft',
        ];

        Exam::update($id, $data);
        Session::setFlash('success', 'Đã cập nhật đề thi!');
        $this->redirect('exam');
    }

    /**
     * Xóa đề thi
     */
    public function delete(int $id): void
    {
        if (Exam::isUsedInSession($id)) {
            Session::setFlash('error', 'Không thể xóa đề thi đang được sử dụng trong kỳ thi!');
        } else {
            Exam::delete($id);
            Session::setFlash('success', 'Đã xóa đề thi!');
        }
        $this->redirect('exam');
    }

    /**
     * Chọn câu hỏi thủ công cho đề thi
     */
    public function selectQuestions(int $examId): void
    {
        $exam = Exam::getById($examId);
        if (!$exam) {
            Session::setFlash('error', 'Không tìm thấy đề thi.');
            $this->redirect('exam');
            return;
        }

        // Lấy câu hỏi đã có trong đề
        $examQuestions = Exam::getQuestions($examId);
        $selectedIds = array_column($examQuestions, 'question_id');

        // Lấy tất cả câu hỏi của môn thi (để chọn)
        $filters = ['subject_id' => $exam['subject_id']];
        $page = (int)($this->get('page', 1));
        $allQuestions = Question::getAll($filters, $page, 50);

        $chapters = Question::getChaptersBySubject((int)$exam['subject_id']);

        $this->view('teacher.exams.select_questions', [
            'pageTitle' => 'Chọn Câu Hỏi - ' . $exam['title'],
            'exam' => $exam,
            'examQuestions' => $examQuestions,
            'selectedIds' => $selectedIds,
            'allQuestions' => $allQuestions['data'],
            'pagination' => $allQuestions,
            'chapters' => $chapters,
        ]);
    }

    /**
     * Lưu danh sách câu hỏi đã chọn
     */
    public function saveQuestions(int $examId): void
    {
        if (!$this->isPost()) {
            $this->redirect("exam/selectQuestions/{$examId}");
        }

        $questionIds = $this->post('question_ids') ?? [];

        // Xóa hết câu cũ rồi thêm lại
        Exam::clearQuestions($examId);

        if (!empty($questionIds)) {
            Exam::addQuestions($examId, $questionIds);
        }

        Session::setFlash('success', 'Đã cập nhật danh sách câu hỏi cho đề thi! (' . count($questionIds) . ' câu)');
        $this->redirect("exam/preview/{$examId}");
    }

    /**
     * Cấu hình tạo đề ngẫu nhiên
     */
    public function configure(int $examId): void
    {
        $exam = Exam::getById($examId);
        if (!$exam) {
            Session::setFlash('error', 'Không tìm thấy đề thi.');
            $this->redirect('exam');
            return;
        }

        $chapters = Question::getChaptersBySubject((int)$exam['subject_id']);

        // Đếm câu hỏi khả dụng theo độ khó
        $counts = [
            'easy' => Question::countByFilter((int)$exam['subject_id'], 'easy'),
            'medium' => Question::countByFilter((int)$exam['subject_id'], 'medium'),
            'hard' => Question::countByFilter((int)$exam['subject_id'], 'hard'),
        ];

        $this->view('teacher.exams.configure', [
            'pageTitle' => 'Cấu Hình Tạo Đề Ngẫu Nhiên',
            'exam' => $exam,
            'chapters' => $chapters,
            'questionCounts' => $counts,
        ]);
    }

    /**
     * Thực hiện tạo đề ngẫu nhiên
     */
    public function generateRandom(int $examId): void
    {
        if (!$this->isPost()) {
            $this->redirect("exam/configure/{$examId}");
        }

        $exam = Exam::getById($examId);
        if (!$exam) {
            Session::setFlash('error', 'Không tìm thấy đề thi.');
            $this->redirect('exam');
            return;
        }

        $easyCount = (int)$this->post('easy_count', 0);
        $mediumCount = (int)$this->post('medium_count', 0);
        $hardCount = (int)$this->post('hard_count', 0);
        $chapterId = $this->post('chapter_id') ?: null;

        $totalNeeded = $easyCount + $mediumCount + $hardCount;
        if ($totalNeeded === 0) {
            Session::setFlash('error', 'Vui lòng nhập số lượng câu hỏi.');
            $this->redirect("exam/configure/{$examId}");
            return;
        }

        $subjectId = (int)$exam['subject_id'];
        $allQuestions = [];

        // Random câu dễ
        if ($easyCount > 0) {
            $easy = Question::getRandomByFilter($subjectId, $easyCount, 'easy', $chapterId);
            if (count($easy) < $easyCount) {
                Session::setFlash('error', "Không đủ câu hỏi dễ! Cần {$easyCount}, chỉ có " . count($easy));
                $this->redirect("exam/configure/{$examId}");
                return;
            }
            $allQuestions = array_merge($allQuestions, $easy);
        }

        // Random câu trung bình
        if ($mediumCount > 0) {
            $medium = Question::getRandomByFilter($subjectId, $mediumCount, 'medium', $chapterId);
            if (count($medium) < $mediumCount) {
                Session::setFlash('error', "Không đủ câu hỏi trung bình! Cần {$mediumCount}, chỉ có " . count($medium));
                $this->redirect("exam/configure/{$examId}");
                return;
            }
            $allQuestions = array_merge($allQuestions, $medium);
        }

        // Random câu khó
        if ($hardCount > 0) {
            $hard = Question::getRandomByFilter($subjectId, $hardCount, 'hard', $chapterId);
            if (count($hard) < $hardCount) {
                Session::setFlash('error', "Không đủ câu hỏi khó! Cần {$hardCount}, chỉ có " . count($hard));
                $this->redirect("exam/configure/{$examId}");
                return;
            }
            $allQuestions = array_merge($allQuestions, $hard);
        }

        // Xóa câu cũ, thêm câu mới
        Exam::clearQuestions($examId);
        $questionIds = array_column($allQuestions, 'id');
        shuffle($questionIds); // Xáo trộn thứ tự
        Exam::addQuestions($examId, $questionIds);

        Session::setFlash('success', "Đã tạo đề ngẫu nhiên với {$totalNeeded} câu hỏi!");
        $this->redirect("exam/preview/{$examId}");
    }

    /**
     * Preview đề thi
     */
    public function preview(int $examId): void
    {
        $exam = Exam::getById($examId);
        if (!$exam) {
            Session::setFlash('error', 'Không tìm thấy đề thi.');
            $this->redirect('exam');
            return;
        }

        $questions = Exam::getQuestions($examId);

        $this->view('teacher.exams.preview', [
            'pageTitle' => 'Preview Đề Thi - ' . $exam['title'],
            'exam' => $exam,
            'questions' => $questions,
        ]);
    }

    /**
     * Publish đề thi (chuyển từ draft → published)
     */
    public function publish(int $id): void
    {
        $exam = Exam::getById($id);
        if (!$exam) {
            Session::setFlash('error', 'Không tìm thấy đề thi.');
            $this->redirect('exam');
            return;
        }

        $questions = Exam::getQuestions($id);
        if (empty($questions)) {
            Session::setFlash('error', 'Đề thi chưa có câu hỏi nào! Hãy thêm câu hỏi trước.');
            $this->redirect("exam/selectQuestions/{$id}");
            return;
        }

        Exam::updateStatus($id, 'published');
        Session::setFlash('success', 'Đề thi đã được xuất bản!');
        $this->redirect('exam');
    }
}
