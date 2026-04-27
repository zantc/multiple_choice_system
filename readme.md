# 🎓 Hệ Thống Thi Trắc Nghiệm Online

Hệ thống quản lý thi trắc nghiệm trực tuyến xây dựng bằng PHP MVC + MySQL + XAMPP.

## 📋 Yêu cầu

- **XAMPP** (PHP 8.0+ & MySQL)
- **Composer** ([https://getcomposer.org](https://getcomposer.org))
- **Git**

## 🚀 Hướng dẫn cài đặt

### 1. Clone repository
```bash
git clone https://github.com/YOUR_USERNAME/multiple_choice_system.git
cd multiple_choice_system
```

### 2. Cài đặt Composer dependencies
```bash
composer install --ignore-platform-reqs
```

### 3. Tạo file .env
```bash
copy .env.example .env
```
Sau đó mở `.env` và chỉnh thông tin database:
```
DB_HOST=localhost
DB_DATABASE=exam_system
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Bật extension trong php.ini (XAMPP)
Mở `C:\xampp\php\php.ini`, tìm và bỏ dấu `;` trước:
```
extension=fileinfo
extension=gd
extension=mbstring
extension=pdo_mysql
```

### 5. Tạo database
- Mở **phpMyAdmin** → `http://localhost/phpmyadmin`
- Import file `database/schema.sql`
- Database sẽ được tạo tự động với dữ liệu mẫu

### 6. Chạy project
- Đảm bảo XAMPP Apache + MySQL đang chạy
- Truy cập: `http://localhost/multiple_choice_system/public`

### 7. Tài khoản mẫu
| Username | Password | Vai trò |
|----------|----------|---------|
| admin | password | Admin |
| teacher1 | password | Giáo viên |
| student1 | password | Học viên |

> ⚠️ Mật khẩu mẫu dùng hash bcrypt mặc định. Trong production cần đổi mật khẩu.

## 📁 Cấu trúc thư mục

```
├── app/
│   ├── Config/          # Cấu hình (database, routes)
│   ├── Controllers/     # Xử lý logic
│   ├── Helpers/         # Hàm tiện ích (Database, Session, Validator)
│   ├── Models/          # Tương tác database
│   └── Middleware/      # Kiểm tra quyền (Auth, Role)
├── database/
│   └── schema.sql       # Database schema + dữ liệu mẫu
├── public/              # Document root (Apache trỏ vào đây)
│   ├── assets/          # CSS, JS, images
│   ├── uploads/         # File upload
│   └── index.php        # Entry point
├── resources/
│   └── views/           # Giao diện (PHP templates)
├── storage/             # Logs, cache, exports
├── composer.json
├── .env.example
└── .gitignore
```

## 👥 Phân công nhóm

| Thành viên | Nhiệm vụ |
|------------|-----------|
| TV1 | Core & Authentication |
| TV2 | Admin Panel |
| TV3 (Thinh678) | Ngân hàng câu hỏi, Đề thi, Kỳ thi |
| TV4 | Giao diện thi & Kết quả |
| TV5 | Thống kê & Báo cáo |

## 🌿 Git Workflow

```bash
git checkout develop
git pull origin develop
git checkout -b feature/ten-chuc-nang
# ... code ...
git add .
git commit -m "feat: mô tả chức năng"
git push origin feature/ten-chuc-nang
# Tạo Pull Request trên GitHub
```
