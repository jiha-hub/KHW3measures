<?php
// =============================================================
// InfinityFree(무료 호스팅)용 DB 설정 — 데모 전용
//  ▶ 아래 4개 값을 InfinityFree 제어판 > "MySQL Databases"에서
//    보이는 값으로 그대로 복사해 넣으세요.
//    (InfinityFree는 DB 주소가 localhost가 아니라 sqlXXX... 형태입니다)
//  ▶ 파일명을 config.php 로 바꿔 htdocs 폴더에 올리세요.
//  ▶ 실제 환자정보는 넣지 말고 가짜 데모 데이터만 사용하세요.
// =============================================================

define('DB_CHARSET', 'utf8mb4');

// 예: sql123.infinityfree.com  (제어판의 "MySQL Hostname" 값)
define('DB_HOST', 'sqlXXX.infinityfree.com');
define('DB_PORT', '3306');

// 예: epiz_12345678_khw3   (제어판의 "MySQL Database Name")
define('DB_NAME', 'epiz_XXXXXXXX_XXXX');
// 예: epiz_12345678         (제어판의 "MySQL Username")
define('DB_USER', 'epiz_XXXXXXXX');
// InfinityFree 계정(제어판) 비밀번호
define('DB_PASS', '여기에_DB_비밀번호_입력');

// 세션 설정
define('SESSION_NAME', 'mental_health_session');
define('SESSION_LIFETIME', 3600);

// 앱 설정
define('APP_NAME', '정신건강 척도 검사 (데모)');
define('APP_VERSION', '1.0.0');

// DB 연결 함수 (기존과 동일 — 다른 파일이 이 함수를 사용하므로 유지)
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
