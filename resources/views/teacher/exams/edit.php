<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card animate-fade-in">
            <div class="card-header"><i class="bi bi-pencil me-2"></i>Chỉnh Sửa Đề Thi</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/exam/update/<?= $exam['id'] ?>">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Tên đề thi <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($exam['title']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Môn học <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $exam['subject_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Thời gian (phút)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="<?= $exam['duration_minutes'] ?>" min="5" max="300">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Điểm tối đa</label>
                            <input type="number" name="max_score" class="form-control" value="<?= $exam['max_score'] ?>" step="0.5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Điểm đạt</label>
                            <input type="number" name="pass_score" class="form-control" value="<?= $exam['pass_score'] ?>" step="0.5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= $exam['status'] === 'draft' ? 'selected' : '' ?>>📝 Nháp</option>
                                <option value="published" <?= $exam['status'] === 'published' ? 'selected' : '' ?>>✅ Xuất bản</option>
                                <option value="archived" <?= $exam['status'] === 'archived' ? 'selected' : '' ?>>📦 Lưu trữ</option>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="total_questions" value="<?= $exam['total_questions'] ?>">

                    <hr>
                    <h6 class="fw-bold mb-3"><i class="bi bi-gear me-1"></i>Tùy chọn</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="shuffle_questions" value="1" <?= $exam['shuffle_questions'] ? 'checked' : '' ?>>
                                <label class="form-check-label">🔀 Trộn thứ tự câu hỏi</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="shuffle_answers" value="1" <?= $exam['shuffle_answers'] ? 'checked' : '' ?>>
                                <label class="form-check-label">🔄 Trộn thứ tự đáp án</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="show_result" value="1" <?= $exam['show_result'] ? 'checked' : '' ?>>
                                <label class="form-check-label">📊 Hiện kết quả sau khi nộp</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="show_explanation" value="1" <?= $exam['show_explanation'] ? 'checked' : '' ?>>
                                <label class="form-check-label">💡 Hiện giải thích đáp án</label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/exam" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Cập Nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
