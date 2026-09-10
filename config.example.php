<?php
// =============================================================
// 설정 예시 파일 (config.example.php)
//  ▶ install.bat 이 이 파일을 복사해 config.php 를 자동 생성합니다.
//  ▶ 수동으로 만들 때는 이 파일을 config.php 로 복사한 뒤 값만 바꾸세요.
//  ▶ config.php 는 저장소에 올라가지 않습니다(.gitignore).
// =============================================================
define('DB_CHARSET', 'utf8mb4');
define('DB_HOST', 'localhost');   // 로컬 XAMPP
define('DB_PORT', '3306');
define('DB_NAME', 'khw3');        // install.bat 이 만드는 DB 이름과 동일
define('DB_USER', 'root');        // XAMPP 기본 아이디
define('DB_PASS', '');            // XAMPP 기본은 비번 없음

define('SESSION_NAME', 'mental_health_session');
define('SESSION_LIFETIME', 3600);
define('APP_NAME', '정신건강 척도 검사');
define('APP_VERSION', '1.0.0');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
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
