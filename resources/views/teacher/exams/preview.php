<?php require VIEW_PATH . '/layouts/header.php'; ?>

<!-- Exam Info Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($exam['title']) ?></h4>
                <span class="badge bg-light text-dark me-2"><?= htmlspecialchars($exam['subject_name'] ?? '') ?></span>
                <span class="badge <?= $exam['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>">
                    <?= $exam['status'] === 'published' ? '✅ Đã xuất bản' : '📝 Nháp' ?>
                </span>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="me-3"><i class="bi bi-clock me-1"></i><?= $exam['duration_minutes'] ?> phút</span>
                <span class="me-3"><i class="bi bi-hash me-1"></i><?= count($questions) ?> câu</span>
                <span class="me-3"><i class="bi bi-trophy me-1"></i><?= $exam['max_score'] ?> điểm</span>
            </div>
        </div>
        <div class="mt-2">
            <?= $exam['shuffle_questions'] ? '<span class="badge bg-light text-dark me-1">🔀 Trộn câu</span>' : '' ?>
            <?= $exam['shuffle_answers'] ? '<span class="badge bg-light text-dark me-1">🔄 Trộn đáp án</span>' : '' ?>
            <?= $exam['show_result'] ? '<span class="badge bg-light text-dark me-1">📊 Hiện kết quả</span>' : '' ?>
            <?= $exam['show_explanation'] ? '<span class="badge bg-light text-dark me-1">💡 Hiện giải thích</span>' : '' ?>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="d-flex gap-2 mb-4">
    <a href="<?= BASE_URL ?>/exam/selectQuestions/<?= $exam['id'] ?>" class="btn btn-outline-primary">
        <i class="bi bi-pencil me-1"></i>Chỉnh sửa câu hỏi
    </a>
    <?php if ($exam['status'] === 'draft' && !empty($questions)): ?>
    <a href="<?= BASE_URL ?>/exam/publish/<?= $exam['id'] ?>" class="btn btn-success" onclick="return confirm('Xuất bản đề thi?')">
        <i class="bi bi-check2-circle me-1"></i>Xuất bản đề thi
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/exam" class="btn btn-outline-secondary ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
</div>

<!-- Questions Preview -->
<?php if (!empty($questions)): ?>
    <?php foreach ($questions as $i => $q): ?>
    <div class="question-card animate-fade-in">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="d-flex align-items-start">
                <span class="question-number"><?= $i + 1 ?></span>
                <div>
                    <p class="mb-0 fw-medium"><?= htmlspecialchars($q['content']) ?></p>
                    <small class="text-muted">
                        <?php
                        $db = match($q['difficulty']) { 'easy'=>'badge-easy','hard'=>'badge-hard',default=>'badge-medium' };
                        $dt = match($q['difficulty']) { 'easy'=>'Dễ','hard'=>'Khó',default=>'TB' };
                        ?>
                        <span class="badge <?= $db ?> me-1"><?= $dt ?></span>
                        <?= $q['chapter_name'] ? '<span class="badge bg-light text-dark">' . htmlspecialchars($q['chapter_name']) . '</span>' : '' ?>
                    </small>
                </div>
            </div>
        </div>

        <div class="ps-5">
            <?php foreach (['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']] as $letter => $text): ?>
            <div class="answer-option <?= $q['correct_answer'] === $letter ? 'correct' : '' ?> mb-2">
                <span class="answer-label"><?= $letter ?></span>
                <span><?= htmlspecialchars($text) ?></span>
                <?php if ($q['correct_answer'] === $letter): ?>
                <i class="bi bi-check-circle-fill text-success ms-auto"></i>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($q['explanation'])): ?>
            <div class="mt-2 p-2 rounded" style="background:var(--gray-100);">
                <small class="text-muted"><i class="bi bi-lightbulb me-1"></i><strong>Giải thích:</strong> <?= htmlspecialchars($q['explanation']) ?></small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <i class="bi bi-file-earmark-text"></i>
        <h5>Đề thi chưa có câu hỏi</h5>
        <a href="<?= BASE_URL ?>/exam/selectQuestions/<?= $exam['id'] ?>" class="btn btn-primary mt-2">
            <i class="bi bi-plus-circle me-1"></i>Thêm câu hỏi
        </a>
    </div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
