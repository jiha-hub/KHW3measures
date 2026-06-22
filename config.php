<?php
// =============================================
// DB 연결 설정
// Railway 배포 시: 환경변수 자동 사용
// 로컬(XAMPP) 사용 시: 아래 값 직접 수정
// =============================================

define('DB_CHARSET', 'utf8mb4');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'YOUR_DB_NAME');
define('DB_USER', getenv('DB_USER') ?: 'YOUR_DB_USER');
define('DB_PASS', getenv('DB_PASS') ?: 'YOUR_DB_PASS');

// 세션 설정
define('SESSION_NAME', 'mental_health_session');
define('SESSION_LIFETIME', 3600);

// 앱 설정
define('APP_NAME', '정신건강 척도 검사');
define('APP_VERSION', '1.0.0');

// DB 연결 함수
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('DB 연결 실패: ' . $e->getMessage());
        }
    }
    return $pdo;
}
