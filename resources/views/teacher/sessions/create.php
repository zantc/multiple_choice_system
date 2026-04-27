<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card animate-fade-in">
            <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Tạo Kỳ Thi Mới</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/session/store">
                    <div class="mb-3">
                        <label class="form-label">Tên kỳ thi <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="VD: Thi giữa kỳ Toán - Lớp 10A1 - Đợt 1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chọn đề thi <span class="text-danger">*</span></label>
                        <select name="exam_id" class="form-select" required>
                            <option value="">-- Chọn đề thi (chỉ hiện đề đã xuất bản) --</option>
                            <?php foreach ($exams as $e): ?>
                            <option value="<?= $e['id'] ?>">
                                <?= htmlspecialchars($e['title']) ?> (<?= $e['subject_name'] ?? '' ?> - <?= $e['question_count'] ?? 0 ?> câu)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($exams)): ?>
                        <small class="text-danger">⚠️ Chưa có đề thi nào được xuất bản. Hãy tạo và xuất bản đề thi trước.</small>
                        <?php endif; ?>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Thời gian mở đề <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian đóng đề <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Chế độ thi</label>
                            <select name="mode" class="form-select">
                                <option value="official">📋 Thi chính thức</option>
                                <option value="practice">📝 Thi thử</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số lần được thi</label>
                            <input type="number" name="max_attempts" class="form-control" value="1" min="1" max="99">
                            <small class="text-muted">1 = Chỉ được thi 1 lần</small>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3"><i class="bi bi-people me-1"></i>Gán cho lớp học</h6>

                    <?php if (!empty($classes)): ?>
                    <div class="row">
                        <?php foreach ($classes as $c): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" id="class_<?= $c['id'] ?>">
                                <label class="form-check-label" for="class_<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['name']) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($c['subject_name'] ?? '') ?></small>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Chưa có lớp học nào. Hãy nhờ Admin tạo lớp trước.</p>
                    <?php endif; ?>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/session" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Tạo Kỳ Thi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
