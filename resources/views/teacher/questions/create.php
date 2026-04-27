<?php require VIEW_PATH . '/layouts/header.php'; ?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card animate-fade-in">
            <div class="card-header">
                <i class="bi bi-plus-circle me-2"></i>Thêm Câu Hỏi Trắc Nghiệm
            </div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/question/store">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Môn học <span class="text-danger">*</span></label>
                            <select name="subject_id" id="subject_id" class="form-select" required onchange="loadChapters(this.value, 'chapter_id')">
                                <option value="">-- Chọn môn --</option>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Chương</label>
                            <select name="chapter_id" id="chapter_id" class="form-select">
                                <option value="">-- Chọn chương --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Độ khó <span class="text-danger">*</span></label>
                            <select name="difficulty" class="form-select" required>
                                <option value="easy">🟢 Dễ</option>
                                <option value="medium" selected>🟡 Trung bình</option>
                                <option value="hard">🔴 Khó</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="3" required placeholder="Nhập nội dung câu hỏi..."></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <span class="answer-label d-inline-flex" style="width:24px;height:24px;font-size:0.75rem;">A</span>
                                Đáp án A <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="option_a" class="form-control" required placeholder="Nhập đáp án A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <span class="answer-label d-inline-flex" style="width:24px;height:24px;font-size:0.75rem;">B</span>
                                Đáp án B <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="option_b" class="form-control" required placeholder="Nhập đáp án B">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <span class="answer-label d-inline-flex" style="width:24px;height:24px;font-size:0.75rem;">C</span>
                                Đáp án C <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="option_c" class="form-control" required placeholder="Nhập đáp án C">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <span class="answer-label d-inline-flex" style="width:24px;height:24px;font-size:0.75rem;">D</span>
                                Đáp án D <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="option_d" class="form-control" required placeholder="Nhập đáp án D">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Đáp án đúng <span class="text-danger">*</span></label>
                            <select name="correct_answer" class="form-select" required>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Giải thích đáp án</label>
                        <textarea name="explanation" class="form-control" rows="2" placeholder="Giải thích tại sao đáp án đúng (tùy chọn)"></textarea>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/question" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Quay lại
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Lưu Câu Hỏi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
