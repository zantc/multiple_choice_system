<?php require VIEW_PATH . '/layouts/header.php'; ?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/question/import" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-excel me-1"></i>Import Excel
        </a>
        <a href="<?= BASE_URL ?>/question/create" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Thêm Câu Hỏi
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="<?= BASE_URL ?>/question" class="row g-2 align-items-end">
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
        <div class="col-md-2">
            <label class="form-label">Chương</label>
            <select name="chapter_id" class="form-select">
                <option value="">-- Tất cả --</option>
                <?php foreach ($chapters as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($filters['chapter_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Độ khó</label>
            <select name="difficulty" class="form-select">
                <option value="">-- Tất cả --</option>
                <option value="easy" <?= ($filters['difficulty'] ?? '') === 'easy' ? 'selected' : '' ?>>🟢 Dễ</option>
                <option value="medium" <?= ($filters['difficulty'] ?? '') === 'medium' ? 'selected' : '' ?>>🟡 Trung bình</option>
                <option value="hard" <?= ($filters['difficulty'] ?? '') === 'hard' ? 'selected' : '' ?>>🔴 Khó</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Tìm kiếm</label>
            <input type="text" name="keyword" class="form-control" placeholder="Nhập từ khóa..." value="<?= htmlspecialchars($filters['keyword'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search me-1"></i>Lọc
            </button>
        </div>
    </form>
</div>

<!-- Questions Table -->
<div class="card animate-fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-collection me-2"></i>Danh Sách Câu Hỏi</span>
        <span class="badge bg-primary"><?= $pagination['total'] ?? 0 ?> câu hỏi</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($questions)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nội dung câu hỏi</th>
                        <th style="width:100px">Môn</th>
                        <th style="width:100px">Chương</th>
                        <th style="width:80px">Đáp án</th>
                        <th style="width:100px">Độ khó</th>
                        <th style="width:130px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $i => $q): ?>
                    <tr>
                        <td class="text-muted"><?= ($pagination['page'] - 1) * $pagination['perPage'] + $i + 1 ?></td>
                        <td>
                            <div class="fw-medium"><?= htmlspecialchars(mb_substr($q['content'], 0, 80)) ?><?= mb_strlen($q['content']) > 80 ? '...' : '' ?></div>
                            <small class="text-muted">
                                A: <?= htmlspecialchars(mb_substr($q['option_a'], 0, 20)) ?> |
                                B: <?= htmlspecialchars(mb_substr($q['option_b'], 0, 20)) ?> |
                                C: <?= htmlspecialchars(mb_substr($q['option_c'], 0, 20)) ?> |
                                D: <?= htmlspecialchars(mb_substr($q['option_d'], 0, 20)) ?>
                            </small>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= htmlspecialchars($q['subject_name'] ?? '') ?></span></td>
                        <td><small><?= htmlspecialchars($q['chapter_name'] ?? '-') ?></small></td>
                        <td><span class="badge bg-primary"><?= $q['correct_answer'] ?></span></td>
                        <td>
                            <?php
                            $diffBadge = match($q['difficulty']) {
                                'easy' => 'badge-easy',
                                'hard' => 'badge-hard',
                                default => 'badge-medium',
                            };
                            $diffText = match($q['difficulty']) {
                                'easy' => 'Dễ',
                                'hard' => 'Khó',
                                default => 'TB',
                            };
                            ?>
                            <span class="badge <?= $diffBadge ?>"><?= $diffText ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= BASE_URL ?>/question/edit/<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Sửa">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/question/delete/<?= $q['id'] ?>" class="btn btn-sm btn-outline-danger btn-icon" title="Xóa" onclick="return confirmDelete('Bạn có chắc muốn xóa câu hỏi này?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['totalPages'] > 1): ?>
        <div class="d-flex justify-content-center p-3">
            <nav>
                <ul class="pagination mb-0">
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                    <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= BASE_URL ?>/question?page=<?= $p ?>&subject_id=<?= $filters['subject_id'] ?? '' ?>&difficulty=<?= $filters['difficulty'] ?? '' ?>&keyword=<?= $filters['keyword'] ?? '' ?>">
                            <?= $p ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h5>Chưa có câu hỏi nào</h5>
            <p>Hãy thêm câu hỏi mới hoặc import từ file Excel.</p>
            <a href="<?= BASE_URL ?>/question/create" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Thêm Câu Hỏi
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
