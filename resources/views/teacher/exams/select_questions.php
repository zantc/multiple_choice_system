<?php require VIEW_PATH . '/layouts/header.php'; ?>

<!-- Exam Info -->
<div class="alert alert-info d-flex align-items-center mb-3">
    <i class="bi bi-info-circle me-2 fs-5"></i>
    <div>
        <strong><?= htmlspecialchars($exam['title']) ?></strong> — 
        Môn: <?= htmlspecialchars($exam['subject_name'] ?? '') ?> |
        Đã chọn: <strong id="selectedCount"><?= count($selectedIds) ?></strong> câu hỏi
    </div>
</div>

<div class="row">
    <!-- Left: Selected Questions -->
    <div class="col-lg-4 mb-3">
        <div class="card" style="position:sticky;top:90px;">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-list-check me-1"></i>Câu Hỏi Đã Chọn (<span id="selectedCountSidebar"><?= count($examQuestions) ?></span>)
            </div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <?php if (!empty($examQuestions)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($examQuestions as $i => $eq): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                        <div>
                            <span class="question-number" style="width:24px;height:24px;font-size:0.7rem;"><?= $i + 1 ?></span>
                            <small><?= htmlspecialchars(mb_substr($eq['content'], 0, 50)) ?>...</small>
                        </div>
                        <span class="badge <?= 'badge-' . $eq['difficulty'] ?>"><?= $eq['difficulty'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;"></i>
                    Chưa chọn câu nào
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Question Bank -->
    <div class="col-lg-8">
        <form method="POST" action="<?= BASE_URL ?>/exam/saveQuestions/<?= $exam['id'] ?>">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-collection me-1"></i>Ngân Hàng Câu Hỏi — <?= htmlspecialchars($exam['subject_name'] ?? '') ?></span>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/exam/configure/<?= $exam['id'] ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-shuffle me-1"></i>Tạo ngẫu nhiên
                        </a>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Lưu đề thi
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px">
                                        <input type="checkbox" class="form-check-input" onclick="toggleAllCheckboxes(this, 'question-cb')">
                                    </th>
                                    <th>Nội dung</th>
                                    <th style="width:80px">Chương</th>
                                    <th style="width:60px">Đáp án</th>
                                    <th style="width:80px">Độ khó</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allQuestions as $q): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input question-cb" 
                                               name="question_ids[]" value="<?= $q['id'] ?>"
                                               <?= in_array($q['id'], $selectedIds) ? 'checked' : '' ?>
                                               onchange="updateSelectedCount('question-cb')">
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars(mb_substr($q['content'], 0, 80)) ?></div>
                                        <small class="text-muted">
                                            A: <?= htmlspecialchars(mb_substr($q['option_a'], 0, 15)) ?> |
                                            B: <?= htmlspecialchars(mb_substr($q['option_b'], 0, 15)) ?> |
                                            C: <?= htmlspecialchars(mb_substr($q['option_c'], 0, 15)) ?> |
                                            D: <?= htmlspecialchars(mb_substr($q['option_d'], 0, 15)) ?>
                                        </small>
                                    </td>
                                    <td><small><?= htmlspecialchars($q['chapter_name'] ?? '-') ?></small></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= $q['correct_answer'] ?></span></td>
                                    <td>
                                        <?php
                                        $db = match($q['difficulty']) { 'easy'=>'badge-easy','hard'=>'badge-hard',default=>'badge-medium' };
                                        $dt = match($q['difficulty']) { 'easy'=>'Dễ','hard'=>'Khó',default=>'TB' };
                                        ?>
                                        <span class="badge <?= $db ?>"><?= $dt ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
