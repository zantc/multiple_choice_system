# Hệ Thống Thi Trắc Nghiệm Trực Tuyến (Online Quiz System)

Chào mừng bạn đến với **Online Quiz System** — một ứng dụng web thi trắc nghiệm trực tuyến hoàn chỉnh, bảo mật và được hiện đại hóa toàn diện về cả cấu trúc mã nguồn (PHP) lẫn giao diện người dùng (UI/UX) theo chuẩn 2026.

---

## ✨ Điểm Nổi Bật Của Dự Án

### 🎨 Giao Diện UI/UX Cao Cấp & Trực Quan (Aesthetic Design)
*   **Trang Đăng Ký / Đăng Nhập:** Thừa hưởng thiết kế chuyển động trượt 3D mượt mà từ mẫu gốc hiện đại, loại bỏ hoàn toàn các biểu mẫu truyền thống thô cứng.
*   **Student Portal (Portal Học Viên):**
    *   Trang Dashboard được tích hợp biểu ngữ chào mừng cá nhân hóa đổi màu chuyển sắc (gradient) bắt mắt.
    *   Bảng thống kê học lực hiển thị số bài thi đã làm, điểm số trung bình tích lũy và số ca thi đã đạt yêu cầu trực quan.
    *   Tính năng **Tải ảnh đại diện cá nhân (Avatar)** có chế độ xem trước ảnh cục bộ cực nhanh và tự động dọn dẹp bộ nhớ máy chủ.
    *   Bố cục lưới hai cột hiện đại tương thích 100% với các thiết bị di động (Responsive).
*   **Trung Tâm Làm Bài Trắc Nghiệm:** Giao diện thi toàn màn hình (Fullscreen mode), đồng hồ đếm ngược trực quan, bộ chuyển câu hỏi nhanh, thẻ đánh dấu cờ câu hỏi chưa chắc chắn, và tính năng tự động nộp bài an toàn khi hết giờ.
*   **Xem Kết Quả & In Chứng Chỉ:** Biểu đồ điểm số trực quan (Đạt/Không đạt), phân tích chi tiết từng đáp án kèm giải thích cụ thể của giáo viên. Hỗ trợ xuất/in chứng chỉ học viên ra file PDF trực quan, sạch đẹp thông qua lệnh in hệ thống.

### 🛡️ Bảo Mật & Kiến Trúc Hiện Đại
*   **Ngăn Chặn Lỗ Hổng Web:** Phòng chống tuyệt đối các cuộc tấn công CSRF thông qua mã thông báo (CSRF Tokens) tự động trên mọi biểu mẫu gửi lên, phòng chống tấn công chèn lệnh (SQL Injection) bằng PDO Prepared Statements, và bảo mật Session chống rò rỉ.
*   **Bảo Mật Mật Khẩu:** Toàn bộ mật khẩu trong cơ sở dữ liệu được mã hóa một chiều bằng thuật toán Bcrypt mạnh mẽ nhất.
*   **Quản Lý Cấu Hình Sạch:** Tách biệt hoàn toàn thông tin nhạy cảm (mật khẩu cơ sở dữ liệu, SMTP Email...) sang tệp `.env.php` nằm ngoài Git để phòng chống lộ lọt thông tin.

---

## 🛠️ Yêu Cầu Hệ Thống (Requirements)
*   **PHP:** Phiên bản 8.0 trở lên.
*   **Cơ Sở Dữ Liệu:** MySQL / MariaDB.
*   **Web Server:** Apache (thích hợp nhất là sử dụng XAMPP) hoặc Nginx.
*   **Công cụ gửi thư (Tùy chọn):** SMTP Email (như Gmail App Password) để gửi email xác thực.

---

## 🚀 Hướng Dẫn Cài Đặt Chi Tiết

### Bước 1: Sao chép mã nguồn
Tải dự án về máy tính của bạn và giải nén hoặc sao chép thư mục `quiz` vào thư mục gốc của máy chủ web (ví dụ: `C:\xampp\htdocs\quiz`).

### Bước 2: Cài đặt Cơ sở dữ liệu
1.  Mở công cụ quản trị cơ sở dữ liệu của bạn (ví dụ: `phpMyAdmin`).
2.  Tạo một database mới có tên là `exam_system` với bảng mã `utf8mb4_unicode_ci` để hỗ trợ hiển thị tiếng Việt chính xác nhất.
3.  Nhập (Import) tệp cơ sở dữ liệu kết xuất gốc của dự án (`exam_system.sql`) vào database `exam_system` vừa tạo.

### Bước 3: Cấu hình hệ thống (Môi trường)
1.  Truy cập vào thư mục `quiz/config/`.
2.  Sao chép tệp mẫu cấu hình **`.env.example.php`** và đổi tên thành **`.env.php`**.
3.  Mở tệp `.env.php` lên và điền thông tin tài khoản kết nối Database cũng như tài khoản SMTP gửi mail của bạn:
    ```php
    // --- Database ---
    define('DB_HOST',   'localhost');
    define('DB_NAME',   'exam_system');
    define('DB_USER',   'root');
    define('DB_PASS',   ''); // Mật khẩu DB của bạn

    // --- SMTP ---
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 465);
    define('SMTP_USER', 'email_cua_ban@gmail.com');
    define('SMTP_PASS', 'mat_khau_ung_dung_gmail');
    ```

### Bước 4: Khởi chạy và Trải nghiệm
*   Khởi động module Apache và MySQL trong bảng điều khiển XAMPP Control Panel.
*   Mở trình duyệt web của bạn và truy cập theo đường dẫn: `http://localhost/quiz`.
*   Tài khoản quản trị viên (Admin) mặc định:
    *   **Tên đăng nhập:** `admin` hoặc `phuthinh`
    *   **Mật khẩu đăng nhập:** `admin123`

---

## 👥 Vai Trò & Phân Quyền Trong Hệ Thống

| Vai Trò (Role) | Chức năng chính |
| :--- | :--- |
| **Quản Trị Viên (Admin)** | Quản lý người dùng, lớp học, môn học, kỳ thi và báo cáo tổng quan toàn hệ thống. |
| **Giáo Viên (Teacher)** | Quản lý kho câu hỏi (tạo mới, sửa, xóa, import từ excel), cấu hình đợt thi, theo dõi tiến độ và chấm điểm thi của học sinh. |
| **Học Viên (Student)** | Đăng ký, đăng nhập tài khoản, cập nhật thông tin cá nhân/avatar, theo dõi ca thi được giao, tham gia làm bài thi trực tuyến và xem lịch sử điểm số. |

---

## 📄 Giấy Phép & Bản Quyền
Dự án được phân phối phi thương mại, phục vụ mục đích học tập và ôn thi trực tuyến. Vui lòng ghi rõ nguồn khi chia sẻ hoặc phát triển thêm.

Chúc các bạn có những trải nghiệm tuyệt vời cùng **Online Quiz System**! 🎓
