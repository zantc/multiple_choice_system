<?php
/**
 * SessionController - Quản lý kỳ thi (Task 8)
 */

namespace App\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Helpers\Session;
use App\Helpers\Validator;

class SessionController extends BaseController
{
    public function __construct()
    {
        $this->requireRole('teacher', 'admin');
    }

    /**
     * Danh sách kỳ thi
     */
    public function index(): void
    {
        $filters = [
            'status' => $this->get('status'),
            'mode' => $this->get('mode'),
        ];

        if (Session::getUserRole() === 'teacher') {
            $filters['created_by'] = Session::getUserId();
        }

        $page = (int)($this->get('page', 1));
        $result = ExamSession::getAll($filters, $page);

        // Tính trạng thái realtime cho mỗi kỳ thi
        foreach ($result['data'] as &$session) {
            $status = ExamSession::getStatus($session);
            $statusLabel = ExamSession::getStatusLabel($status);
            $session['computed_status'] = $status;
            $session['status_text'] = $statusLabel['text'];
            $session['status_class'] = $statusLabel['class'];
        }

        $this->view('teacher.sessions.index', [
            'pageTitle' => 'Quản Lý Kỳ Thi',
            'sessions' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
        ]);
    }

    /**
     * Form tạo kỳ thi mới
     */
    public function create(): void
    {
        $exams = Exam::getPublished();
        $classes = ExamSession::getAllClasses();

        $this->view('teacher.sessions.create', [
            'pageTitle' => 'Tạo Kỳ Thi Mới',
            'exams' => $exams,
            'classes' => $classes,
        ]);
    }

    /**
     * Lưu kỳ thi mới
     */
    public function store(): void
    {
        if (!$this->isPost()) {
            $this->redirect('session');
        }

        $validator = new Validator();
        $validator
            ->required('exam_id', $this->post('exam_id'), 'Đề thi')
            ->required('title', $this->post('title'), 'Tên kỳ thi')
            ->required('start_time', $this->post('start_time'), 'Thời gian bắt đầu')
            ->required('end_time', $this->post('end_time'), 'Thời gian kết thúc');

        if (!$validator->passes()) {
            Session::setFlash('error', $validator->getFirstError());
            $this->redirect('session/create');
            return;
        }

        // Validate thời gian
        $startTime = $this->post('start_time');
        $endTime = $this->post('end_time');
        if (strtotime($endTime) <= strtotime($startTime)) {
            Session::setFlash('error', 'Thời gian kết thúc phải sau thời gian bắt đầu!');
            $this->redirect('session/create');
            return;
        }

        $data = [
            'exam_id' => $this->post('exam_id'),
            'title' => $this->post('title'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'mode' => $this->post('mode') ?? 'official',
            'max_attempts' => (int)($this->post('max_attempts') ?? 1),
            'is_active' => 1,
            'created_by' => Session::getUserId(),
        ];

        $sessionId = ExamSession::create($data);

        // Gán lớp
        $classIds = $this->post('class_ids') ?? [];
        if (!empty($classIds)) {
            ExamSession::assignClasses($sessionId, $classIds);
        }

        Session::setFlash('success', 'Đã tạo kỳ thi thành công!');
        $this->redirect('session');
    }

    /**
     * Form sửa kỳ thi
     */
    public function edit(int $id): void
    {
        $session = ExamSession::getById($id);
        if (!$session) {
            Session::setFlash('error', 'Không tìm thấy kỳ thi.');
            $this->redirect('session');
            return;
        }

        $exams = Exam::getPublished();
        $classes = ExamSession::getAllClasses();
        $assignedClassIds = ExamSession::getAssignedClassIds($id);

        $this->view('teacher.sessions.edit', [
            'pageTitle' => 'Chỉnh Sửa Kỳ Thi',
            'session' => $session,
            'exams' => $exams,
            'classes' => $classes,
            'assignedClassIds' => $assignedClassIds,
        ]);
    }

    /**
     * Cập nhật kỳ thi
     */
    public function update(int $id): void
    {
        if (!$this->isPost()) {
            $this->redirect('session');
        }

        $data = [
            'exam_id' => $this->post('exam_id'),
            'title' => $this->post('title'),
            'start_time' => $this->post('start_time'),
            'end_time' => $this->post('end_time'),
            'mode' => $this->post('mode') ?? 'official',
            'max_attempts' => (int)($this->post('max_attempts') ?? 1),
            'is_active' => $this->post('is_active') ? 1 : 0,
        ];

        ExamSession::update($id, $data);

        // Cập nhật gán lớp
        $classIds = $this->post('class_ids') ?? [];
        ExamSession::assignClasses($id, $classIds);

        Session::setFlash('success', 'Đã cập nhật kỳ thi!');
        $this->redirect('session');
    }

    /**
     * Xóa kỳ thi
     */
    public function delete(int $id): void
    {
        ExamSession::delete($id);
        Session::setFlash('success', 'Đã xóa kỳ thi!');
        $this->redirect('session');
    }

    /**
     * Giám sát kỳ thi
     */
    public function monitor(int $id): void
    {
        $session = ExamSession::getById($id);
        if (!$session) {
            Session::setFlash('error', 'Không tìm thấy kỳ thi.');
            $this->redirect('session');
            return;
        }

        $status = ExamSession::getStatus($session);
        $statusLabel = ExamSession::getStatusLabel($status);
        $session['computed_status'] = $status;
        $session['status_text'] = $statusLabel['text'];
        $session['status_class'] = $statusLabel['class'];

        $monitorData = ExamSession::getMonitorData($id);
        $assignedClasses = ExamSession::getAssignedClasses($id);

        $this->view('teacher.sessions.monitor', [
            'pageTitle' => 'Giám Sát Kỳ Thi - ' . $session['title'],
            'session' => $session,
            'monitorData' => $monitorData,
            'assignedClasses' => $assignedClasses,
        ]);
    }
}
