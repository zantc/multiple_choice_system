<?php
/**
 * QuestionController - Quản lý ngân hàng câu hỏi (Task 6)
 */

namespace App\Controllers;

use App\Models\Question;
use App\Helpers\Session;
use App\Helpers\Validator;

class QuestionController extends BaseController
{
    public function __construct()
    {
        $this->requireRole('teacher', 'admin');
    }

    /**
     * Danh sách câu hỏi (có lọc, tìm kiếm, phân trang)
     */
    public function index(): void
    {
        $filters = [
            'subject_id' => $this->get('subject_id'),
            'chapter_id' => $this->get('chapter_id'),
            'difficulty' => $this->get('difficulty'),
            'keyword' => $this->get('keyword'),
        ];

        // Giáo viên chỉ thấy câu hỏi của mình (admin thấy hết)
        if (Session::getUserRole() === 'teacher') {
            $filters['created_by'] = Session::getUserId();
        }

        $page = (int)($this->get('page', 1));
        $result = Question::getAll($filters, $page);
        $subjects = Question::getAllSubjects();

        // Lấy chương nếu đã chọn môn
        $chapters = [];
        if (!empty($filters['subject_id'])) {
            $chapters = Question::getChaptersBySubject((int)$filters['subject_id']);
        }

        $this->view('teacher.questions.index', [
            'pageTitle' => 'Ngân Hàng Câu Hỏi',
            'questions' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'subjects' => $subjects,
            'chapters' => $chapters,
        ]);
    }

    /**
     * Form thêm câu hỏi mới
     */
    public function create(): void
    {
        $subjects = Question::getAllSubjects();
        $this->view('teacher.questions.create', [
            'pageTitle' => 'Thêm Câu Hỏi Mới',
            'subjects' => $subjects,
        ]);
    }

    /**
     * Lưu câu hỏi mới
     */
    public function store(): void
    {
        if (!$this->isPost()) {
            $this->redirect('question');
        }

        $validator = new Validator();
        $validator
            ->required('subject_id', $this->post('subject_id'), 'Môn học')
            ->required('content', $this->post('content'), 'Nội dung câu hỏi')
            ->required('option_a', $this->post('option_a'), 'Đáp án A')
            ->required('option_b', $this->post('option_b'), 'Đáp án B')
            ->required('option_c', $this->post('option_c'), 'Đáp án C')
            ->required('option_d', $this->post('option_d'), 'Đáp án D')
            ->required('correct_answer', $this->post('correct_answer'), 'Đáp án đúng')
            ->inList('correct_answer', $this->post('correct_answer'), ['A', 'B', 'C', 'D'], 'Đáp án đúng')
            ->inList('difficulty', $this->post('difficulty'), ['easy', 'medium', 'hard'], 'Độ khó');

        if (!$validator->passes()) {
            Session::setFlash('error', $validator->getFirstError());
            $this->redirect('question/create');
            return;
        }

        $data = [
            'subject_id' => $this->post('subject_id'),
            'chapter_id' => $this->post('chapter_id'),
            'content' => $this->post('content'),
            'option_a' => $this->post('option_a'),
            'option_b' => $this->post('option_b'),
            'option_c' => $this->post('option_c'),
            'option_d' => $this->post('option_d'),
            'correct_answer' => $this->post('correct_answer'),
            'explanation' => $this->post('explanation'),
            'difficulty' => $this->post('difficulty'),
            'created_by' => Session::getUserId(),
        ];

        Question::create($data);
        Session::setFlash('success', 'Đã thêm câu hỏi thành công!');
        $this->redirect('question');
    }

    /**
     * Form sửa câu hỏi
     */
    public function edit(int $id): void
    {
        $question = Question::getById($id);
        if (!$question) {
            Session::setFlash('error', 'Không tìm thấy câu hỏi.');
            $this->redirect('question');
            return;
        }

        $subjects = Question::getAllSubjects();
        $chapters = Question::getChaptersBySubject((int)$question['subject_id']);

        $this->view('teacher.questions.edit', [
            'pageTitle' => 'Sửa Câu Hỏi',
            'question' => $question,
            'subjects' => $subjects,
            'chapters' => $chapters,
        ]);
    }

    /**
     * Cập nhật câu hỏi
     */
    public function update(int $id): void
    {
        if (!$this->isPost()) {
            $this->redirect('question');
        }

        $validator = new Validator();
        $validator
            ->required('subject_id', $this->post('subject_id'), 'Môn học')
            ->required('content', $this->post('content'), 'Nội dung câu hỏi')
            ->required('option_a', $this->post('option_a'), 'Đáp án A')
            ->required('option_b', $this->post('option_b'), 'Đáp án B')
            ->required('option_c', $this->post('option_c'), 'Đáp án C')
            ->required('option_d', $this->post('option_d'), 'Đáp án D')
            ->required('correct_answer', $this->post('correct_answer'), 'Đáp án đúng');

        if (!$validator->passes()) {
            Session::setFlash('error', $validator->getFirstError());
            $this->redirect("question/edit/{$id}");
            return;
        }

        $data = [
            'subject_id' => $this->post('subject_id'),
            'chapter_id' => $this->post('chapter_id'),
            'content' => $this->post('content'),
            'option_a' => $this->post('option_a'),
            'option_b' => $this->post('option_b'),
            'option_c' => $this->post('option_c'),
            'option_d' => $this->post('option_d'),
            'correct_answer' => $this->post('correct_answer'),
            'explanation' => $this->post('explanation'),
            'difficulty' => $this->post('difficulty'),
        ];

        Question::update($id, $data);
        Session::setFlash('success', 'Đã cập nhật câu hỏi!');
        $this->redirect('question');
    }

    /**
     * Xóa câu hỏi
     */
    public function delete(int $id): void
    {
        if (Question::isUsedInExam($id)) {
            Session::setFlash('error', 'Không thể xóa câu hỏi đang được dùng trong đề thi!');
        } else {
            Question::delete($id);
            Session::setFlash('success', 'Đã xóa câu hỏi!');
        }
        $this->redirect('question');
    }

    /**
     * Trang import câu hỏi từ Excel
     */
    public function import(): void
    {
        $subjects = Question::getAllSubjects();
        $this->view('teacher.questions.import', [
            'pageTitle' => 'Import Câu Hỏi Từ Excel',
            'subjects' => $subjects,
        ]);
    }

    /**
     * Xử lý upload file Excel
     */
    public function processImport(): void
    {
        if (!$this->isPost()) {
            $this->redirect('question/import');
        }

        $subjectId = (int)$this->post('subject_id');
        if (!$subjectId) {
            Session::setFlash('error', 'Vui lòng chọn môn học.');
            $this->redirect('question/import');
            return;
        }

        // Check file upload
        if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            Session::setFlash('error', 'Vui lòng chọn file Excel (.xlsx hoặc .csv).');
            $this->redirect('question/import');
            return;
        }

        $file = $_FILES['excel_file'];
        $allowedTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv'
        ];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            Session::setFlash('error', 'Chỉ chấp nhận file .xlsx, .xls hoặc .csv');
            $this->redirect('question/import');
            return;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = [];

            foreach ($worksheet->getRowIterator(2) as $row) { // Bỏ qua header (dòng 1)
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $cells = [];
                foreach ($cellIterator as $cell) {
                    $cells[] = $cell->getValue();
                }

                // Bỏ qua dòng trống
                if (empty($cells[0]) && empty($cells[1])) continue;

                $rows[] = [
                    'content' => $cells[0] ?? '',
                    'option_a' => $cells[1] ?? '',
                    'option_b' => $cells[2] ?? '',
                    'option_c' => $cells[3] ?? '',
                    'option_d' => $cells[4] ?? '',
                    'correct_answer' => strtoupper($cells[5] ?? 'A'),
                    'difficulty' => $cells[6] ?? 'medium',
                    'explanation' => $cells[7] ?? '',
                    'chapter_id' => null,
                ];
            }

            if (empty($rows)) {
                Session::setFlash('error', 'File Excel không có dữ liệu.');
                $this->redirect('question/import');
                return;
            }

            $count = Question::bulkInsert($rows, $subjectId, Session::getUserId());
            Session::setFlash('success', "Đã import thành công {$count} câu hỏi!");
            $this->redirect('question');

        } catch (\Exception $e) {
            Session::setFlash('error', 'Lỗi khi đọc file: ' . $e->getMessage());
            $this->redirect('question/import');
        }
    }

    /**
     * API: Lấy chương theo môn học (cho AJAX)
     */
    public function getChapters(int $subjectId): void
    {
        $chapters = Question::getChaptersBySubject($subjectId);
        $this->json(['chapters' => $chapters]);
    }
}
