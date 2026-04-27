<?php require VIEW_PATH . '/layouts/header.php'; ?>

<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <a href="<?= BASE_URL ?>/exam/create" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Tạo Đề Thi Mới
    </a>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="<?= BASE_URL ?>/exam" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Môn học</label>
            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Tất cả --</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($filters['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Trạng thái</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">-- Tất cả --</option>
                <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>📝 Nháp</option>
                <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>>✅ Đã xuất bản</option>
                <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>>📦 Lưu trữ</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Tìm kiếm</label>
            <input type="text" name="keyword" class="form-control" placeholder="Tên đề thi..." value="<?= htmlspecialchars($filters['keyword'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Lọc</button>
        </div>
    </form>
</div>

<!-- Exams Table -->
<div class="card animate-fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text me-2"></i>Danh Sách Đề Thi</span>
        <span class="badge bg-primary"><?= $pagination['total'] ?? 0 ?> đề thi</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($exams)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Tên đề thi</th>
                        <th>Môn học</th>
                        <th style="width:80px">Số câu</th>
                        <th style="width:90px">Thời gian</th>
                        <th style="width:80px">Điểm</th>
                        <th style="width:100px">Trạng thái</th>
                        <th style="width:200px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $i => $e): ?>
                    <tr>
                        <td class="text-muted"><?= ($pagination['page'] - 1) * $pagination['perPage'] + $i + 1 ?></td>
                        <td>
                            <div class="fw-medium"><?= htmlspecialchars($e['title']) ?></div>
                            <small class="text-muted">
                                <?= $e['shuffle_questions'] ? '🔀 Trộn câu' : '' ?>
                                <?= $e['shuffle_answers'] ? '🔄 Trộn đáp án' : '' ?>
                            </small>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= htmlspecialchars($e['subject_name'] ?? '') ?></span></td>
                        <td class="text-center"><strong><?= $e['question_count'] ?? $e['total_questions'] ?></strong></td>
                        <td><?= $e['duration_minutes'] ?> phút</td>
                        <td><?= $e['max_score'] ?></td>
                        <td>
                            <?php
                            $statusBadge = match($e['status']) {
                                'published' => 'badge-published',
                                'archived' => 'badge-archived',
                                default => 'badge-draft',
                            };
                            $statusText = match($e['status']) {
                                'published' => '✅ Xuất bản',
                                'archived' => '📦 Lưu trữ',
                                default => '📝 Nháp',
                            };
                            ?>
                            <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="<?= BASE_URL ?>/exam/preview/<?= $e['id'] ?>" class="btn btn-sm btn-outline-info btn-icon" title="Xem trước">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/exam/selectQuestions/<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Chọn câu hỏi">
                                    <i class="bi bi-list-check"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/exam/configure/<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning btn-icon" title="Tạo ngẫu nhiên">
                                    <i class="bi bi-shuffle"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/exam/edit/<?= $e['id'] ?>" class="btn btn-sm btn-outline-secondary btn-icon" title="Chỉnh sửa">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($e['status'] === 'draft'): ?>
                                <a href="<?= BASE_URL ?>/exam/publish/<?= $e['id'] ?>" class="btn btn-sm btn-success btn-icon" title="Xuất bản" onclick="return confirm('Xuất bản đề thi này?')">
                                    <i class="bi bi-check2-circle"></i>
                                </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/exam/delete/<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger btn-icon" title="Xóa" onclick="return confirmDelete('Bạn có chắc muốn xóa đề thi này?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['totalPages'] > 1): ?>
        <div class="d-flex justify-content-center p-3">
            <nav>
                <ul class="pagination mb-0">
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                    <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= BASE_URL ?>/exam?page=<?= $p ?>&status=<?= $filters['status'] ?? '' ?>&subject_id=<?= $filters['subject_id'] ?? '' ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-file-earmark-text"></i>
            <h5>Chưa có đề thi nào</h5>
            <p>Hãy tạo đề thi mới để bắt đầu.</p>
            <a href="<?= BASE_URL ?>/exam/create" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Tạo Đề Thi</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
