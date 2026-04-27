<?php require VIEW_PATH . '/layouts/header.php'; ?>

<!-- Filter Bar -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/session?status=scheduled" class="btn btn-sm <?= ($filters['status'] ?? '') === 'scheduled' ? 'btn-warning' : 'btn-outline-warning' ?>">
            ⏳ Sắp tới
        </a>
        <a href="<?= BASE_URL ?>/session?status=in_progress" class="btn btn-sm <?= ($filters['status'] ?? '') === 'in_progress' ? 'btn-success' : 'btn-outline-success' ?>">
            🟢 Đang diễn ra
        </a>
        <a href="<?= BASE_URL ?>/session?status=ended" class="btn btn-sm <?= ($filters['status'] ?? '') === 'ended' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
            ✅ Đã kết thúc
        </a>
        <a href="<?= BASE_URL ?>/session" class="btn btn-sm btn-outline-dark">Tất cả</a>
    </div>
    <a href="<?= BASE_URL ?>/session/create" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Tạo Kỳ Thi Mới
    </a>
</div>

<!-- Sessions Table -->
<div class="card animate-fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-event me-2"></i>Danh Sách Kỳ Thi</span>
        <span class="badge bg-primary"><?= $pagination['total'] ?? 0 ?> kỳ thi</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($sessions)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Tên kỳ thi</th>
                        <th>Đề thi</th>
                        <th style="width:130px">Bắt đầu</th>
                        <th style="width:130px">Kết thúc</th>
                        <th style="width:80px">Chế độ</th>
                        <th style="width:100px">Trạng thái</th>
                        <th style="width:60px">Lượt thi</th>
                        <th style="width:150px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $i => $s): ?>
                    <tr>
                        <td class="text-muted"><?= ($pagination['page'] - 1) * $pagination['perPage'] + $i + 1 ?></td>
                        <td>
                            <div class="fw-medium"><?= htmlspecialchars($s['title']) ?></div>
                            <small class="text-muted"><?= $s['class_count'] ?? 0 ?> lớp được gán</small>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($s['exam_title'] ?? '') ?></small>
                            <br><span class="badge bg-light text-dark"><?= $s['total_questions'] ?? 0 ?> câu / <?= $s['duration_minutes'] ?? 0 ?> phút</span>
                        </td>
                        <td><small><?= date('d/m/Y H:i', strtotime($s['start_time'])) ?></small></td>
                        <td><small><?= date('d/m/Y H:i', strtotime($s['end_time'])) ?></small></td>
                        <td>
                            <?php if ($s['mode'] === 'practice'): ?>
                            <span class="badge bg-info">Thi thử</span>
                            <?php else: ?>
                            <span class="badge bg-primary">Chính thức</span>
                            <?php endif; ?>
                            <br><small class="text-muted"><?= $s['max_attempts'] ?> lần</small>
                        </td>
                        <td>
                            <span class="badge bg-<?= $s['status_class'] ?>"><?= $s['status_text'] ?></span>
                        </td>
                        <td class="text-center"><?= $s['result_count'] ?? 0 ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= BASE_URL ?>/session/monitor/<?= $s['id'] ?>" class="btn btn-sm btn-outline-info btn-icon" title="Giám sát">
                                    <i class="bi bi-display"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/session/edit/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Sửa">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/session/delete/<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger btn-icon" title="Xóa" onclick="return confirmDelete('Xóa kỳ thi này?')">
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
                        <a class="page-link" href="<?= BASE_URL ?>/session?page=<?= $p ?>&status=<?= $filters['status'] ?? '' ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-calendar-event"></i>
            <h5>Chưa có kỳ thi nào</h5>
            <p>Hãy tạo kỳ thi mới và gán đề cho lớp học.</p>
            <a href="<?= BASE_URL ?>/session/create" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Tạo Kỳ Thi</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
