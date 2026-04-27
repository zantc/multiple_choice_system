<?php require VIEW_PATH . '/layouts/header.php'; ?>

<!-- Session Info -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($session['title']) ?></h4>
                <span class="badge bg-<?= $session['status_class'] ?> me-2"><?= $session['status_text'] ?></span>
                <span class="badge <?= $session['mode'] === 'practice' ? 'bg-info' : 'bg-primary' ?>">
                    <?= $session['mode'] === 'practice' ? 'Thi thử' : 'Chính thức' ?>
                </span>
            </div>
            <div class="col-md-6 text-md-end">
                <div><i class="bi bi-calendar me-1"></i>Mở: <strong><?= date('d/m/Y H:i', strtotime($session['start_time'])) ?></strong></div>
                <div><i class="bi bi-calendar-check me-1"></i>Đóng: <strong><?= date('d/m/Y H:i', strtotime($session['end_time'])) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background:#dbeafe;color:#1e40af;">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $session['total_questions'] ?? 0 ?></div>
                    <div class="stat-label">Câu hỏi</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background:#dcfce7;color:#166534;">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="stat-value"><?= count($assignedClasses) ?></div>
                    <div class="stat-label">Lớp được gán</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background:#fef3c7;color:#92400e;">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <div>
                    <?php
                    $inProgress = count(array_filter($monitorData, fn($m) => $m['exam_status'] === 'in_progress'));
                    ?>
                    <div class="stat-value"><?= $inProgress ?></div>
                    <div class="stat-label">Đang làm bài</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background:#fecaca;color:#991b1b;">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <?php
                    $submitted = count(array_filter($monitorData, fn($m) => $m['exam_status'] === 'submitted'));
                    ?>
                    <div class="stat-value"><?= $submitted ?></div>
                    <div class="stat-label">Đã nộp bài</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assigned Classes -->
<?php if (!empty($assignedClasses)): ?>
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-people me-2"></i>Các Lớp Được Gán</div>
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($assignedClasses as $c): ?>
            <span class="badge bg-light text-dark fs-6 px-3 py-2"><?= htmlspecialchars($c['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Monitor Table -->
<div class="card animate-fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-display me-2"></i>Danh Sách Thí Sinh</span>
        <span class="badge bg-primary"><?= count($monitorData) ?> người</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($monitorData)): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Họ tên</th>
                        <th>Username</th>
                        <th>Bắt đầu lúc</th>
                        <th>Nộp bài lúc</th>
                        <th>Điểm</th>
                        <th>Đúng</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monitorData as $i => $m): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-medium"><?= htmlspecialchars($m['full_name']) ?></td>
                        <td><code><?= htmlspecialchars($m['username']) ?></code></td>
                        <td><small><?= $m['started_at'] ? date('H:i:s', strtotime($m['started_at'])) : '-' ?></small></td>
                        <td><small><?= $m['submitted_at'] ? date('H:i:s', strtotime($m['submitted_at'])) : '-' ?></small></td>
                        <td>
                            <?php if ($m['exam_status'] === 'submitted'): ?>
                            <strong class="<?= $m['score'] >= ($session['pass_score'] ?? 5) ? 'text-success' : 'text-danger' ?>">
                                <?= $m['score'] ?>
                            </strong>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?= $m['exam_status'] === 'submitted' ? $m['correct_count'] . '/' . $m['total_questions'] : '-' ?></td>
                        <td>
                            <?php
                            $statusBadge = match($m['exam_status']) {
                                'submitted' => 'bg-success',
                                'in_progress' => 'bg-warning',
                                default => 'bg-secondary',
                            };
                            $statusText = match($m['exam_status']) {
                                'submitted' => '✅ Đã nộp',
                                'in_progress' => '⏳ Đang làm',
                                default => '⬜ Chưa bắt đầu',
                            };
                            ?>
                            <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            <h5>Chưa có thí sinh nào tham gia</h5>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <a href="<?= BASE_URL ?>/session" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
