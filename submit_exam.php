<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['auto'])) {
    flashAndRedirect("Yêu cầu không hợp lệ.", "student_exams.php", "danger");
}

$pdo = getDB();
$userId = (int) $currentUser['id'];
$resultId = isset($_POST['result_id']) ? (int)$_POST['result_id'] : (isset($_GET['result_id']) ? (int)$_GET['result_id'] : null);
$answers = $_POST['answers'] ?? []; // Format: [question_id => selected_option]

if (!$resultId) {
    flashAndRedirect("Thiếu mã lượt thi.", "student_exams.php", "danger");
}

// Verify result belongs to user and is not submitted
$stmt = $pdo->prepare("
    SELECT er.*, e.max_score 
    FROM exam_results er 
    JOIN exams e ON e.id = er.exam_id 
    WHERE er.id = ? AND er.student_id = ?
");
$stmt->execute([$resultId, $userId]);
$resultRow = $stmt->fetch();

if (!$resultRow) {
    flashAndRedirect("Yêu cầu nộp bài không hợp lệ.", "student_exams.php", "danger");
}

if (!is_null($resultRow['submitted_at'])) {
    // Already submitted
    header("Location: view_result.php?id=$resultId");
    exit;
}

// Fetch all correct answers for this exam
$qStmt = $pdo->prepare("
    SELECT id, correct_answer 
    FROM questions 
    WHERE id IN (SELECT question_id FROM exam_questions WHERE exam_id = ?)
");
$qStmt->execute([$resultRow['exam_id']]);
$correctAnswers = [];
while ($row = $qStmt->fetch()) {
    $correctAnswers[(int)$row['id']] = $row['correct_answer'];
}

$correctCount = 0;
$totalQuestions = (int) $resultRow['total_questions'];

// Insert student answers using a single transaction to ensure maximum speed and safety
$pdo->beginTransaction();
try {
    $insertAnswerStmt = $pdo->prepare("
        INSERT INTO student_answers (result_id, question_id, selected_option, is_correct) 
        VALUES (?, ?, ?, ?)
    ");

    foreach ($correctAnswers as $qId => $correctOpt) {
        $selected = isset($answers[$qId]) ? trim((string)$answers[$qId]) : null;
        if ($selected === '') {
            $selected = null;
        }
        $isCorrect = ($selected === $correctOpt) ? 1 : 0;
        if ($isCorrect) {
            $correctCount++;
        }
        
        $insertAnswerStmt->execute([$resultId, $qId, $selected, $isCorrect]);
    }

    // Calculate score (out of 10 or max_score)
    $maxScore = $resultRow['max_score'] > 0 ? (float)$resultRow['max_score'] : 10.0;
    $score = ($totalQuestions > 0) ? ($correctCount / $totalQuestions) * $maxScore : 0.0;
    $score = round($score, 2);

    $startedAt = strtotime($resultRow['started_at']);
    $timeSpent = time() - $startedAt;

    // Update exam_results
    $updateStmt = $pdo->prepare("
        UPDATE exam_results 
        SET score = ?, correct_count = ?, time_spent_seconds = ?, submitted_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$score, $correctCount, $timeSpent, $resultId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    flashAndRedirect("Lỗi hệ thống khi lưu kết quả bài thi: " . $e->getMessage(), "student_exams.php", "danger");
}

// Redirect to view result
header("Location: view_result.php?id=$resultId");
exit;
