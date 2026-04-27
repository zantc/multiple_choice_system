<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card animate-fade-in">
            <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Tạo Đề Thi Mới</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/exam/store">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Tên đề thi <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="VD: Đề thi giữa kỳ Toán - Lớp 10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Môn học <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">-- Chọn --</option>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Thời gian làm bài (phút) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_minutes" class="form-control" value="60" min="5" max="300" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Điểm tối đa</label>
                            <input type="number" name="max_score" class="form-control" value="10" min="1" step="0.5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Điểm đạt</label>
                            <input type="number" name="pass_score" class="form-control" value="5" min="0" step="0.5">
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3"><i class="bi bi-gear me-1"></i>Tùy chọn đề thi</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="shuffle_questions" value="1" id="shuffleQ">
                                <label class="form-check-label" for="shuffleQ">
                                    <i class="bi bi-shuffle me-1"></i>Trộn thứ tự câu hỏi
                                </label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="shuffle_answers" value="1" id="shuffleA">
                                <label class="form-check-label" for="shuffleA">
                                    <i class="bi bi-arrow-repeat me-1"></i>Trộn thứ tự đáp án A/B/C/D
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="show_result" value="1" id="showResult" checked>
                                <label class="form-check-label" for="showResult">
                                    <i class="bi bi-bar-chart me-1"></i>Hiện kết quả sau khi nộp bài
                                </label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="show_explanation" value="1" id="showExp">
                                <label class="form-check-label" for="showExp">
                                    <i class="bi bi-lightbulb me-1"></i>Hiện giải thích đáp án
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/exam" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right me-1"></i>Tiếp theo: Chọn câu hỏi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
