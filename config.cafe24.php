<?php
// =============================================================
// 카페24 웹호스팅용 DB 설정
//  1) 아래 4개 값(DB_NAME/DB_USER/DB_PASS, 필요 시 DB_HOST)을
//     카페24 "MySQL 관리"에서 확인한 값으로 바꾸세요.
//  2) 이 파일을 서버 웹 루트에 config.php 라는 이름으로 올리세요.
//     (저장소의 config.php 자리에 이 내용을 넣는다고 보면 됩니다)
//  3) 절대 GitHub 등에 실제 비밀번호를 올리지 마세요.
// =============================================================

define('DB_CHARSET', 'utf8mb4');

// 카페24 공유호스팅은 보통 아래 두 줄 그대로(localhost:3306)면 됩니다.
define('DB_HOST', 'localhost');   // 카페24 MySQL 정보에 별도 호스트가 있으면 그 값으로
define('DB_PORT', '3306');

define('DB_NAME', 'YOUR_CAFE24_DB_NAME');   // 예: hong123
define('DB_USER', 'YOUR_CAFE24_DB_USER');   // 보통 DB명과 동일하거나 계정 아이디
define('DB_PASS', 'YOUR_CAFE24_DB_PASSWORD');

// 세션 설정
define('SESSION_NAME', 'mental_health_session');
define('SESSION_LIFETIME', 3600);

// 앱 설정
define('APP_NAME', '정신건강 척도 검사');
define('APP_VERSION', '1.0.0');

// DB 연결 함수 (기존과 동일 — 다른 파일이 이 함수를 사용하므로 이름/시그니처 유지)
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
