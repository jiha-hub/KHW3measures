<?php
require_once __DIR__ . '/auth.php';

// 이미 로그인된 경우
if (isLoggedIn()) {
    header('Location: consent.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '잘못된 요청입니다.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (login($username, $password)) {
            header('Location: consent.php');
            exit;
        } else {
            $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
        }
    }
}

$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>로그인 — <?= APP_NAME ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #f0f4f8;
    --card: #ffffff;
    --primary: #3b6cb7;
    --primary-dark: #2d549a;
    --text: #1a2236;
    --muted: #6b7a99;
    --border: #dce3ef;
    --error: #c0392b;
    --radius: 12px;
    --shadow: 0 4px 24px rgba(59,108,183,0.10);
}
body {
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
    color: var(--text);
}
.login-wrap {
    width: 100%;
    max-width: 400px;
    padding: 24px;
}
.card {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 48px 40px 40px;
}
.logo {
    text-align: center;
    margin-bottom: 32px;
}
.logo-icon {
    width: 56px;
    height: 56px;
    background: var(--primary);
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}
.logo-icon svg { width: 32px; height: 32px; fill: white; }
.logo h1 { font-size: 1.25rem; font-weight: 700; color: var(--text); }
.logo p  { font-size: 0.85rem; color: var(--muted); margin-top: 4px; }

.form-group { margin-bottom: 20px; }
label { display: block; font-size: 0.875rem; font-weight: 600; color: var(--text); margin-bottom: 8px; }
input[type=text], input[type=password] {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    color: var(--text);
    background: #fafbfd;
    transition: border-color 0.2s;
    outline: none;
}
input:focus { border-color: var(--primary); background: #fff; }

.btn-login {
    width: 100%;
    padding: 14px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 8px;
}
.btn-login:hover { background: var(--primary-dark); }

.error-msg {
    background: #fdf0ef;
    border: 1px solid #e8b4b0;
    color: var(--error);
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 0.875rem;
    margin-bottom: 20px;
}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="card">
    <div class="logo">
      <div class="logo-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
      </div>
      <h1><?= APP_NAME ?></h1>
      <p>관리자 전용 시스템</p>
    </div>

    <?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-group">
        <label for="username">아이디</label>
        <input type="text" id="username" name="username" required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">비밀번호</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-login">로그인</button>
    </form>
  </div>
</div>
</body>
</html>
