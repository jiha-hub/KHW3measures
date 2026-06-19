<?php
// =============================================
// DB 연결 설정 - 이 파일의 값을 본인 환경에 맞게 수정하세요
// =============================================

define('DB_CHARSET', 'utf8mb4');
define('DB_HOST', 'localhost');       // 대부분 localhost
define('DB_NAME', 'mental_health');
define('DB_USER', 'root');
define('DB_PASS', '');

// 세션 설정
define('SESSION_NAME', 'mental_health_session');
define('SESSION_LIFETIME', 3600); // 1시간

// 앱 설정
define('APP_NAME', '정신건강 척도 검사');
define('APP_VERSION', '1.0.0');

// DB 연결 함수
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
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
