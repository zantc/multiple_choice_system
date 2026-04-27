<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card animate-fade-in">
            <div class="card-header"><i class="bi bi-pencil me-2"></i>Chỉnh Sửa Kỳ Thi</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/session/update/<?= $session['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Tên kỳ thi <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($session['title']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Đề thi <span class="text-danger">*</span></label>
                        <select name="exam_id" class="form-select" required>
                            <?php foreach ($exams as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= $session['exam_id'] == $e['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['title']) ?> (<?= $e['subject_name'] ?? '' ?> - <?= $e['question_count'] ?? 0 ?> câu)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Thời gian mở đề</label>
                            <input type="datetime-local" name="start_time" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($session['start_time'])) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian đóng đề</label>
                            <input type="datetime-local" name="end_time" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($session['end_time'])) ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Chế độ thi</label>
                            <select name="mode" class="form-select">
                                <option value="official" <?= $session['mode'] === 'official' ? 'selected' : '' ?>>📋 Chính thức</option>
                                <option value="practice" <?= $session['mode'] === 'practice' ? 'selected' : '' ?>>📝 Thi thử</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Số lần được thi</label>
                            <input type="number" name="max_attempts" class="form-control" value="<?= $session['max_attempts'] ?>" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $session['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Kích hoạt</label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3"><i class="bi bi-people me-1"></i>Gán cho lớp học</h6>
                    <div class="row">
                        <?php foreach ($classes as $c): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" id="class_<?= $c['id'] ?>"
                                    <?= in_array($c['id'], $assignedClassIds) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="class_<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['name']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/session" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Cập Nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
