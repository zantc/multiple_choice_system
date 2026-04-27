<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card animate-fade-in">
            <div class="card-header">
                <i class="bi bi-file-earmark-excel me-2"></i>Import Câu Hỏi Từ File Excel
            </div>
            <div class="card-body">
                <!-- Hướng dẫn format -->
                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="bi bi-info-circle me-1"></i>Hướng dẫn format file Excel:</h6>
                    <p class="mb-2">File Excel cần có các cột theo thứ tự sau (dòng 1 là header):</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cột A</th><th>Cột B</th><th>Cột C</th><th>Cột D</th><th>Cột E</th><th>Cột F</th><th>Cột G</th><th>Cột H</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Nội dung câu hỏi</td>
                                    <td>Đáp án A</td>
                                    <td>Đáp án B</td>
                                    <td>Đáp án C</td>
                                    <td>Đáp án D</td>
                                    <td>Đáp án đúng (A/B/C/D)</td>
                                    <td>Độ khó (easy/medium/hard)</td>
                                    <td>Giải thích</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <form method="POST" action="<?= BASE_URL ?>/question/processImport" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Chọn môn học <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- Chọn môn --</option>
                            <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Chọn file Excel (.xlsx, .csv) <span class="text-danger">*</span></label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <small class="text-muted">Kích thước tối đa: 5MB</small>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/question" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload me-1"></i>Import Câu Hỏi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
