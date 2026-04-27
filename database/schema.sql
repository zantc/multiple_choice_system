-- ============================================
-- DATABASE: exam_system
-- Tạo database trong phpMyAdmin trước khi chạy
-- ============================================

CREATE DATABASE IF NOT EXISTS exam_system
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE exam_system;

-- ============================================
-- BẢNG 1: users (TV1 phụ trách, tạo sẵn để dùng)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    phone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 2: subjects - Môn học (TV2 phụ trách, tạo sẵn để dùng)
-- ============================================
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 3: classes - Lớp học (TV2 phụ trách, tạo sẵn để dùng)
-- ============================================
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject_id INT DEFAULT NULL,
    school_year VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 4: class_students (TV2)
-- ============================================
CREATE TABLE IF NOT EXISTS class_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_class_student (class_id, student_id)
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 5: chapters - Chương (TASK 6 - Bạn làm)
-- ============================================
CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    order_num INT DEFAULT 0,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 6: questions - Câu hỏi (TASK 6 - Bạn làm)
-- ============================================
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    chapter_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer CHAR(1) NOT NULL COMMENT 'A, B, C, or D',
    explanation TEXT DEFAULT NULL,
    difficulty ENUM('easy','medium','hard') DEFAULT 'medium',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_subject (subject_id),
    INDEX idx_chapter (chapter_id),
    INDEX idx_difficulty (difficulty),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 7: exams - Đề thi (TASK 7 - Bạn làm)
-- ============================================
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subject_id INT NOT NULL,
    created_by INT NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 60,
    total_questions INT NOT NULL DEFAULT 0,
    max_score DECIMAL(5,2) DEFAULT 10.00,
    pass_score DECIMAL(5,2) DEFAULT 5.00,
    shuffle_questions TINYINT(1) DEFAULT 0,
    shuffle_answers TINYINT(1) DEFAULT 0,
    show_result TINYINT(1) DEFAULT 1,
    show_explanation TINYINT(1) DEFAULT 0,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 8: exam_questions - Câu hỏi trong đề (TASK 7)
-- ============================================
CREATE TABLE IF NOT EXISTS exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    order_num INT DEFAULT 0,
    score DECIMAL(5,2) DEFAULT NULL,
    
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_question (exam_id, question_id)
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 9: exam_sessions - Kỳ thi (TASK 8 - Bạn làm)
-- ============================================
CREATE TABLE IF NOT EXISTS exam_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    mode ENUM('practice','official') DEFAULT 'official',
    max_attempts INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 10: session_classes - Kỳ thi gán cho lớp (TASK 8)
-- ============================================
CREATE TABLE IF NOT EXISTS session_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_class (session_id, class_id)
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 11: exam_results - Kết quả thi (TV5)
-- ============================================
CREATE TABLE IF NOT EXISTS exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    score DECIMAL(5,2) DEFAULT 0,
    correct_count INT DEFAULT 0,
    total_questions INT DEFAULT 0,
    time_spent_seconds INT DEFAULT 0,
    started_at DATETIME DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    attempt_number INT DEFAULT 1,
    
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id)
) ENGINE=InnoDB;

-- ============================================
-- BẢNG 12: student_answers - Câu trả lời (TV5)
-- ============================================
CREATE TABLE IF NOT EXISTS student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer CHAR(1) DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    is_marked TINYINT(1) DEFAULT 0,
    
    FOREIGN KEY (result_id) REFERENCES exam_results(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB;

-- ============================================
-- DỮ LIỆU MẪU
-- ============================================

-- Admin mặc định (password: admin123)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@exam.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản Trị Viên', 'admin');

-- Giáo viên mẫu (password: teacher123)
INSERT INTO users (username, email, password, full_name, role) VALUES
('teacher1', 'teacher1@exam.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nguyễn Văn A', 'teacher'),
('teacher2', 'teacher2@exam.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Trần Thị B', 'teacher');

-- Học viên mẫu (password: student123)
INSERT INTO users (username, email, password, full_name, role) VALUES
('student1', 'student1@exam.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lê Văn C', 'student'),
('student2', 'student2@exam.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Phạm Thị D', 'student');

-- Môn học mẫu
INSERT INTO subjects (name, code, description) VALUES
('Toán học', 'MATH', 'Toán học đại cương'),
('Tiếng Anh', 'ENG', 'Tiếng Anh cơ bản'),
('Tin học', 'IT', 'Tin học văn phòng và lập trình'),
('Vật lý', 'PHY', 'Vật lý đại cương');

-- Chương mẫu
INSERT INTO chapters (subject_id, name, order_num) VALUES
(1, 'Đại số', 1),
(1, 'Hình học', 2),
(1, 'Giải tích', 3),
(2, 'Grammar', 1),
(2, 'Vocabulary', 2),
(2, 'Reading', 3),
(3, 'Mạng máy tính', 1),
(3, 'Lập trình cơ bản', 2),
(3, 'Cơ sở dữ liệu', 3);

-- Lớp học mẫu
INSERT INTO classes (name, subject_id, school_year) VALUES
('Lớp Toán A1', 1, '2025-2026'),
('Lớp Toán A2', 1, '2025-2026'),
('Lớp Anh B1', 2, '2025-2026'),
('Lớp Tin C1', 3, '2025-2026');

-- Học viên vào lớp
INSERT INTO class_students (class_id, student_id) VALUES
(1, 4), (1, 5), (2, 4), (3, 5);

-- Câu hỏi mẫu (Toán - Đại số)
INSERT INTO questions (subject_id, chapter_id, content, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, created_by) VALUES
(1, 1, 'Giải phương trình: 2x + 4 = 10', 'x = 2', 'x = 3', 'x = 4', 'x = 5', 'B', '2x = 10 - 4 = 6, suy ra x = 3', 'easy', 2),
(1, 1, 'Tính giá trị biểu thức: 3² + 4²', '7', '12', '25', '49', 'C', '9 + 16 = 25', 'easy', 2),
(1, 1, 'Phương trình x² - 5x + 6 = 0 có nghiệm là:', 'x=1, x=6', 'x=2, x=3', 'x=-2, x=-3', 'x=1, x=5', 'B', 'Phân tích: (x-2)(x-3)=0', 'medium', 2),
(1, 2, 'Diện tích hình tròn bán kính r=5 là:', '25π', '10π', '50π', '5π', 'A', 'S = πr² = π×25 = 25π', 'easy', 2),
(1, 2, 'Chu vi hình chữ nhật có chiều dài 8, chiều rộng 5 là:', '13', '26', '40', '80', 'B', 'C = 2×(8+5) = 26', 'easy', 2);

-- Câu hỏi mẫu (Tiếng Anh - Grammar)
INSERT INTO questions (subject_id, chapter_id, content, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, created_by) VALUES
(2, 4, 'She _____ to school every day.', 'go', 'goes', 'going', 'gone', 'B', 'Chủ ngữ số ít (She) + động từ thêm -es', 'easy', 3),
(2, 4, 'I have been living here _____ 2010.', 'for', 'since', 'in', 'at', 'B', 'Since + mốc thời gian cụ thể', 'medium', 3),
(2, 5, 'The word "enormous" means:', 'tiny', 'very big', 'beautiful', 'expensive', 'B', 'Enormous = rất lớn, khổng lồ', 'easy', 3);

-- Câu hỏi mẫu (Tin học)
INSERT INTO questions (subject_id, chapter_id, content, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, created_by) VALUES
(3, 7, 'HTTP là viết tắt của?', 'Hyper Text Transfer Protocol', 'High Tech Transfer Protocol', 'Hyper Text Transport Protocol', 'Home Tool Transfer Protocol', 'A', 'HTTP = Hyper Text Transfer Protocol', 'easy', 2),
(3, 8, 'Trong PHP, biến được khai báo bằng ký tự nào?', '#', '@', '$', '&', 'C', 'PHP sử dụng ký tự $ để khai báo biến', 'easy', 2),
(3, 9, 'SQL là viết tắt của?', 'Structured Query Language', 'Simple Query Language', 'Standard Query Language', 'Server Query Language', 'A', 'SQL = Structured Query Language', 'easy', 2);
