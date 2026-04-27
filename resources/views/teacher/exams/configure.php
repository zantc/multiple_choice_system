<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Exam Info -->
        <div class="alert alert-info mb-3">
            <strong><i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($exam['title']) ?></strong> — 
            Môn: <?= htmlspecialchars($exam['subject_name'] ?? '') ?>
        </div>

        <!-- Question Count Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto mb-2" style="background:#dcfce7;color:#166534;">
                        <i class="bi bi-emoji-smile"></i>
                    </div>
                    <div class="stat-value text-success"><?= $questionCounts['easy'] ?></div>
                    <div class="stat-label">Câu hỏi Dễ</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto mb-2" style="background:#fef3c7;color:#92400e;">
                        <i class="bi bi-emoji-neutral"></i>
                    </div>
                    <div class="stat-value text-warning"><?= $questionCounts['medium'] ?></div>
                    <div class="stat-label">Câu hỏi TB</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto mb-2" style="background:#fecaca;color:#991b1b;">
                        <i class="bi bi-emoji-frown"></i>
                    </div>
                    <div class="stat-value text-danger"><?= $questionCounts['hard'] ?></div>
                    <div class="stat-label">Câu hỏi Khó</div>
                </div>
            </div>
        </div>

        <!-- Configure Form -->
        <div class="card animate-fade-in">
            <div class="card-header"><i class="bi bi-shuffle me-2"></i>Cấu Hình Tạo Đề Ngẫu Nhiên</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/exam/generateRandom/<?= $exam['id'] ?>">
                    
                    <?php if (!empty($chapters)): ?>
                    <div class="mb-3">
                        <label class="form-label">Lọc theo chương (tùy chọn)</label>
                        <select name="chapter_id" class="form-select">
                            <option value="">-- Tất cả chương --</option>
                            <?php foreach ($chapters as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <h6 class="fw-bold mb-3">Số lượng câu hỏi theo độ khó:</h6>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">🟢 Số câu Dễ</label>
                            <input type="number" name="easy_count" class="form-control" value="5" min="0" max="<?= $questionCounts['easy'] ?>">
                            <small class="text-muted">Tối đa: <?= $questionCounts['easy'] ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">🟡 Số câu Trung bình</label>
                            <input type="number" name="medium_count" class="form-control" value="3" min="0" max="<?= $questionCounts['medium'] ?>">
                            <small class="text-muted">Tối đa: <?= $questionCounts['medium'] ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">🔴 Số câu Khó</label>
                            <input type="number" name="hard_count" class="form-control" value="2" min="0" max="<?= $questionCounts['hard'] ?>">
                            <small class="text-muted">Tối đa: <?= $questionCounts['hard'] ?></small>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Lưu ý:</strong> Tạo ngẫu nhiên sẽ <strong>xóa tất cả câu hỏi cũ</strong> trong đề và thay bằng câu hỏi mới.
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/exam/selectQuestions/<?= $exam['id'] ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Chọn thủ công
                        </a>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Tạo đề ngẫu nhiên sẽ thay thế tất cả câu hỏi cũ. Tiếp tục?')">
                            <i class="bi bi-shuffle me-1"></i>Tạo Đề Ngẫu Nhiên
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
