<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = requireRole(['student']);

if (!isset($_GET['session_id'])) {
    flashAndRedirect("Kỳ thi không hợp lệ.", "student_exams.php", "danger");
}

$pdo = getDB();
$userId = (int) $currentUser['id'];
$sessionId = (int) $_GET['session_id'];

// Get session and exam details
$stmt = $pdo->prepare("
    SELECT es.*, e.title as exam_title, e.duration_minutes, e.total_questions, e.shuffle_questions, e.shuffle_answers
    FROM exam_sessions es
    JOIN exams e ON e.id = es.exam_id
    WHERE es.id = ? AND es.is_active = 1
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    flashAndRedirect("Kỳ thi không tồn tại hoặc đã bị đóng.", "student_exams.php", "danger");
}

// Verify access
$accessStmt = $pdo->prepare("
    SELECT 1 
    FROM session_classes sc 
    JOIN class_students cs ON cs.class_id = sc.class_id 
    WHERE sc.session_id = ? AND cs.student_id = ?
");
$accessStmt->execute([$sessionId, $userId]);
if (!$accessStmt->fetch()) {
    flashAndRedirect("Bạn không có quyền tham gia kỳ thi này.", "student_exams.php", "danger");
}

// Check time windows
$now = time();
$start = strtotime($session['start_time']);
$end = strtotime($session['end_time']);
if ($start && $now < $start) {
    flashAndRedirect("Kỳ thi chưa mở rộng.", "student_exams.php", "warning");
}
if ($end && $now > $end) {
    flashAndRedirect("Kỳ thi đã kết thúc.", "student_exams.php", "danger");
}

// Check attempts
$attemptStmt = $pdo->prepare("
    SELECT id, started_at, submitted_at 
    FROM exam_results 
    WHERE student_id = ? AND session_id = ? 
    ORDER BY started_at DESC
");
$attemptStmt->execute([$userId, $sessionId]);
$attempts = $attemptStmt->fetchAll();

$currentResultId = null;
$startedAt = null;
$isResume = false;

if ($attempts && is_null($attempts[0]['submitted_at'])) {
    // Resume unfinished attempt
    $currentResultId = $attempts[0]['id'];
    $startedAt = strtotime($attempts[0]['started_at']);
    $isResume = true;
} else {
    if ($session['max_attempts'] > 0 && count($attempts) >= $session['max_attempts']) {
        flashAndRedirect("Bạn đã hết số lượt làm bài cho kỳ thi này.", "student_exams.php", "warning");
    }
    // Create new attempt
    $attemptNum = count($attempts) + 1;
    $insertStmt = $pdo->prepare("
        INSERT INTO exam_results (student_id, session_id, exam_id, score, correct_count, total_questions, attempt_number, started_at) 
        VALUES (?, ?, ?, 0, 0, ?, ?, NOW())
    ");
    $insertStmt->execute([$userId, $sessionId, $session['exam_id'], $session['total_questions'], $attemptNum]);
    $currentResultId = $pdo->lastInsertId();
    $startedAt = time();
}

// Fetch Questions
$qStmt = $pdo->prepare("
    SELECT id, content, option_a, option_b, option_c, option_d 
    FROM questions 
    WHERE id IN (SELECT question_id FROM exam_questions WHERE exam_id = ?)
");
$qStmt->execute([$session['exam_id']]);
$questions = $qStmt->fetchAll();

if (empty($questions)) {
    flashAndRedirect("Kỳ thi hiện chưa có câu hỏi nào.", "student_exams.php", "warning");
}

if ($session['shuffle_questions']) {
    shuffle($questions);
}

// Get remaining time
$durationSeconds = $session['duration_minutes'] * 60;
$elapsedSeconds = $now - $startedAt;
$timeRemaining = max(0, $durationSeconds - $elapsedSeconds);

if ($timeRemaining <= 0) {
    header("Location: submit_exam.php?result_id=$currentResultId&auto=1");
    exit;
}

$pageTitle = h($session['title']) . " | Đang làm bài";
$hideNavbar = true;
require __DIR__ . '/layouts/header.php';
?>

<style>
body {
    background: #f1f5f9;
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    padding: 0;
    overflow: hidden;
    height: 100vh;
}
.exam-layout {
    display: flex;
    height: 100vh;
    width: 100%;
}
.exam-sidebar {
    width: 320px;
    background: #1e293b;
    color: #fff;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #334155;
}
.exam-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    overflow: hidden;
}
.exam-navbar {
    background: #fff;
    padding: 16px 30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.exam-timer {
    font-size: 22px;
    font-weight: 700;
    color: var(--danger);
    background: #fee2e2;
    border: 1px solid #fecaca;
    padding: 6px 16px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.exam-timer.warning {
    animation: pulse 1s infinite alternate;
}
@keyframes pulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.05); }
}
.q-map-container {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}
.q-map-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
}
.map-btn {
    aspect-ratio: 1;
    border: 1px solid #475569;
    border-radius: 6px;
    background: #334155;
    color: #cbd5e1;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.map-btn:hover {
    background: #475569;
    border-color: #64748b;
}
.map-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(81, 45, 168, 0.4);
}
.map-btn.answered {
    background: #10b981;
    color: #fff;
    border-color: #10b981;
}
.map-btn.flagged {
    border-bottom: 4px solid #f59e0b !important;
}
.sidebar-footer {
    padding: 20px;
    background: #0f172a;
    border-top: 1px solid #334155;
}
.btn-submit {
    width: 100%;
    padding: 14px;
    background: #10b981;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.2s;
}
.btn-submit:hover {
    background: #059669;
}
.question-viewport {
    flex: 1;
    padding: 40px;
    overflow-y: auto;
    display: flex;
    justify-content: center;
}
.question-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    width: 100%;
    max-width: 800px;
    display: none;
    height: fit-content;
}
.question-card.active {
    display: block;
}
.q-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 10px;
}
.q-content {
    font-size: 18px;
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 25px;
    line-height: 1.6;
}
.choice-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.choice-item {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 15px;
    font-weight: 500;
    color: #334155;
}
.choice-item:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.choice-item.selected {
    border-color: var(--primary);
    background: #eef2ff;
    color: var(--primary);
}
.choice-item input {
    margin-right: 15px;
    transform: scale(1.3);
    accent-color: var(--primary);
}
.exam-navigation {
    padding: 20px 40px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.nav-btn-wrap {
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
}
</style>

<div class="exam-layout">
    <div class="exam-sidebar">
        <div style="padding: 24px; border-bottom: 1px solid #334155; background: #0f172a;">
            <h3 style="margin: 0; font-size: 18px; line-height: 1.4; color: #fff;"><?= h($session['title']) ?></h3>
            <p style="margin: 6px 0 0; font-size: 13px; color: #94a3b8;"><i class="fa-solid fa-book"></i> Đề: <?= h($session['exam_title']) ?></p>
        </div>
        
        <div class="q-map-container">
            <div class="q-map-grid" id="qGrid">
                <?php foreach ($questions as $index => $q): ?>
                    <button class="map-btn" onclick="goToQuestion(<?= $index ?>)" id="qbtn-<?= $index ?>"><?= $index + 1 ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sidebar-footer">
            <button class="btn-submit" onclick="submitExam()"><i class="fa-solid fa-paper-plane"></i> NỘP BÀI THI</button>
        </div>
    </div>

    <div class="exam-main">
        <div class="exam-navbar">
            <div>
                <span style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase;">
                    <i class="fa-solid fa-user-graduate"></i> Thí sinh: <?= h($currentUser['full_name']) ?>
                </span>
            </div>
            <div class="exam-timer" id="timerContainer">
                <i class="fa-regular fa-clock"></i>
                <span id="timerDisplay">00:00</span>
            </div>
        </div>

        <form id="examForm" action="submit_exam.php" method="POST" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <input type="hidden" name="result_id" value="<?= (int)$currentResultId ?>">
            
            <div class="question-viewport">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="question-card" id="qcard-<?= $index ?>">
                        <div class="q-title">CÂU HỎI <?= $index + 1 ?></div>
                        <div class="q-content"><?= nl2br(h($q['content'])) ?></div>
                        
                        <div class="choice-container">
                            <?php 
                            $options = [
                                'A' => $q['option_a'],
                                'B' => $q['option_b'],
                                'C' => $q['option_c'],
                                'D' => $q['option_d']
                            ];
                            
                            // True option shuffling if enabled
                            if ($session['shuffle_answers']) {
                                $keys = array_keys($options);
                                shuffle($keys);
                                $shuffled = [];
                                foreach ($keys as $key) {
                                    $shuffled[$key] = $options[$key];
                                }
                                $options = $shuffled;
                            }

                            foreach ($options as $key => $val): ?>
                                <label class="choice-item" id="opt-<?= $index ?>-<?= $key ?>">
                                    <input type="radio" name="answers[<?= (int)$q['id'] ?>]" value="<?= h($key) ?>" onclick="markAnswered(<?= $index ?>)">
                                    <span><b><?= h($key) ?>.</b> <?= h($val) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="exam-navigation">
                <div class="nav-btn-wrap">
                    <button type="button" class="btn btn-secondary" onclick="prevQuestion()" id="btnPrev" style="min-height: 42px;"><i class="fa-solid fa-chevron-left"></i> Câu trước</button>
                    <button type="button" class="btn btn-warning" onclick="toggleFlag()" id="btnFlag" style="min-height: 42px;"><i class="fa-solid fa-flag"></i> Đánh dấu xem lại</button>
                    <button type="button" class="btn btn-secondary" onclick="nextQuestion()" id="btnNext" style="min-height: 42px;">Câu tiếp <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const totalQuestions = <?= count($questions) ?>;
let currentQ = 0;
let timeRemaining = <?= (int)$timeRemaining ?>;

// Timer countdown logic
const timerInterval = setInterval(() => {
    timeRemaining--;
    if (timeRemaining <= 0) {
        clearInterval(timerInterval);
        document.getElementById('examForm').submit();
    } else {
        let m = Math.floor(timeRemaining / 60).toString().padStart(2, '0');
        let s = (timeRemaining % 60).toString().padStart(2, '0');
        document.getElementById('timerDisplay').innerText = `${m}:${s}`;
        
        if (timeRemaining < 60) {
            document.getElementById('timerContainer').classList.add('warning');
        }
    }
}, 1000);

function goToQuestion(idx) {
    document.querySelectorAll('.question-card').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.map-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(`qcard-${idx}`).classList.add('active');
    document.getElementById(`qbtn-${idx}`).classList.add('active');
    
    currentQ = idx;
    document.getElementById('btnPrev').style.visibility = (idx === 0) ? 'hidden' : 'visible';
    document.getElementById('btnNext').style.visibility = (idx === totalQuestions - 1) ? 'hidden' : 'visible';
    
    // Flag marker state management
    const isFlagged = document.getElementById(`qbtn-${idx}`).classList.contains('flagged');
    const flagBtn = document.getElementById('btnFlag');
    if (isFlagged) {
        flagBtn.style.background = '#eab308';
        flagBtn.style.color = '#fff';
        flagBtn.innerHTML = '<i class="fa-solid fa-flag"></i> Bỏ đánh dấu';
    } else {
        flagBtn.style.background = '#fff';
        flagBtn.style.color = '#f59e0b';
        flagBtn.innerHTML = '<i class="fa-solid fa-flag"></i> Đánh dấu xem lại';
    }
}

function nextQuestion() {
    if (currentQ < totalQuestions - 1) goToQuestion(currentQ + 1);
}

function prevQuestion() {
    if (currentQ > 0) goToQuestion(currentQ - 1);
}

function markAnswered(idx) {
    document.getElementById(`qbtn-${idx}`).classList.add('answered');
    
    // Highlight and style checked option
    const card = document.getElementById(`qcard-${idx}`);
    card.querySelectorAll('.choice-item').forEach(el => el.classList.remove('selected'));
    
    const checkedInput = card.querySelector('input[type="radio"]:checked');
    if (checkedInput) {
        checkedInput.closest('.choice-item').classList.add('selected');
    }
}

function toggleFlag() {
    const btn = document.getElementById(`qbtn-${currentQ}`);
    btn.classList.toggle('flagged');
    goToQuestion(currentQ);
}

function submitExam() {
    const answered = document.querySelectorAll('.map-btn.answered').length;
    if (answered < totalQuestions) {
        if (!confirm(`Bạn mới làm được ${answered}/${totalQuestions} câu hỏi. Bạn có thực sự muốn nộp bài thi ngay bây giờ không?`)) {
            return;
        }
    } else {
        if (!confirm("Bạn có chắc chắn muốn nộp bài thi này để chấm điểm?")) {
            return;
        }
    }
    document.getElementById('examForm').submit();
}

// First load init
goToQuestion(0);
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
