<?php
require_once __DIR__ . '/config.php';

// 세션 시작
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// 로그인 여부 확인
function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// 로그인 필수 (미로그인시 login.php로 이동)
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// 로그인 처리
function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, password, name FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        return true;
    }
    return false;
}

// 로그아웃
function logout(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

// CSRF 토큰 생성
function getCsrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF 토큰 검증
function verifyCsrfToken(string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
