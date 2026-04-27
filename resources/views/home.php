<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="text-center py-5">
    <div class="mb-4">
        <i class="bi bi-mortarboard-fill" style="font-size:5rem;color:var(--primary);"></i>
    </div>
    <h1 class="display-5 fw-bold mb-3" style="background:var(--gradient-primary);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
        <?= APP_NAME ?>
    </h1>
    <p class="lead text-muted mb-4">Hệ thống quản lý thi trắc nghiệm trực tuyến dành cho trường học.</p>
    
    <div class="row justify-content-center g-4 mt-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon mx-auto mb-3" style="background:#dbeafe;color:#1e40af;">
                    <i class="bi bi-collection"></i>
                </div>
                <h5 class="fw-bold">Ngân Hàng<br>Câu Hỏi</h5>
                <p class="text-muted small">Quản lý hàng ngàn câu hỏi trắc nghiệm theo môn học, chương, độ khó.</p>
                <a href="<?= BASE_URL ?>/question" class="btn btn-sm btn-outline-primary">Truy cập</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon mx-auto mb-3" style="background:#dcfce7;color:#166534;">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <h5 class="fw-bold">Tạo<br>Đề Thi</h5>
                <p class="text-muted small">Tạo đề thi thủ công hoặc ngẫu nhiên. Trộn câu hỏi & đáp án.</p>
                <a href="<?= BASE_URL ?>/exam" class="btn btn-sm btn-outline-success">Truy cập</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon mx-auto mb-3" style="background:#fef3c7;color:#92400e;">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <h5 class="fw-bold">Tổ Chức<br>Kỳ Thi</h5>
                <p class="text-muted small">Gán đề, lịch thi, chế độ thi. Giám sát thí sinh realtime.</p>
                <a href="<?= BASE_URL ?>/session" class="btn btn-sm btn-outline-warning">Truy cập</a>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
