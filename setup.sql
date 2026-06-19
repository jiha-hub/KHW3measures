-- 정신건강 척도 앱 DB 설정
-- MariaDB 10.x / UTF-8

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 관리자 테이블
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 기본 관리자 계정 (비밀번호: admin1234 → 실제 사용 전 반드시 변경)
-- 비밀번호는 PHP에서 password_hash()로 생성된 값
INSERT INTO admins (username, password, name) VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '관리자'
);

-- 환자 테이블
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 검사 결과 테이블
CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    scale_type ENUM('PHQ-9', 'GAD-7', 'PSS-10') NOT NULL,
    answers JSON NOT NULL,
    total_score INT NOT NULL,
    result_label VARCHAR(50) NOT NULL,
    memo TEXT,
    admin_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (admin_id) REFERENCES admins(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 인덱스
CREATE INDEX idx_assessments_patient ON assessments(patient_id);
CREATE INDEX idx_assessments_created ON assessments(created_at);
CREATE INDEX idx_assessments_scale ON assessments(scale_type);
