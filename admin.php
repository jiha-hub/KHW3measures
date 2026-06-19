<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$db      = getDB();
$success = '';
$error   = '';

// 관리자 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '잘못된 요청입니다.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if (empty($username) || empty($name) || empty($password)) {
            $error = '모든 항목을 입력해주세요.';
        } elseif ($password !== $confirm) {
            $error = '비밀번호가 일치하지 않습니다.';
        } elseif (strlen($password) < 8) {
            $error = '비밀번호는 8자 이상이어야 합니다.';
        } else {
            try {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt   = $db->prepare('INSERT INTO admins (username, password, name) VALUES (?, ?, ?)');
                $stmt->execute([$username, $hashed, $name]);
                $success = "관리자 '{$name}'이 추가되었습니다.";
            } catch (\PDOException $e) {
                $error = '이미 사용 중인 아이디입니다.';
            }
        }
    }
}

// 비밀번호 변경
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_pw') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '잘못된 요청입니다.';
    } else {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        $stmt  = $db->prepare('SELECT password FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!password_verify($currentPw, $admin['password'])) {
            $error = '현재 비밀번호가 올바르지 않습니다.';
        } elseif ($newPw !== $confirm) {
            $error = '새 비밀번호가 일치하지 않습니다.';
        } elseif (strlen($newPw) < 8) {
            $error = '비밀번호는 8자 이상이어야 합니다.';
        } else {
            $hashed = password_hash($newPw, PASSWORD_BCRYPT);
            $stmt   = $db->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $stmt->execute([$hashed, $_SESSION['admin_id']]);
            $success = '비밀번호가 변경되었습니다.';
        }
    }
}

// 관리자 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '잘못된 요청입니다.';
    } else {
        $delId = (int)($_POST['admin_id'] ?? 0);
        if ($delId === (int)$_SESSION['admin_id']) {
            $error = '자기 자신은 삭제할 수 없습니다.';
        } else {
            $stmt = $db->prepare('DELETE FROM admins WHERE id = ?');
            $stmt->execute([$delId]);
            $success = '관리자가 삭제되었습니다.';
        }
    }
}

$admins = $db->query('SELECT id, username, name, created_at FROM admins ORDER BY created_at')->fetchAll();
$csrf   = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>관리자 설정 — <?= APP_NAME ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --bg: #f0f4f8; --card: #fff; --primary: #3b6cb7; --primary-dark: #2d549a; --text: #1a2236; --muted: #6b7a99; --border: #dce3ef; --radius: 12px; --shadow: 0 4px 24px rgba(59,108,183,0.10); --red: #c0392b; }
body { background: var(--bg); font-family: 'Apple SD Gothic Neo','Noto Sans KR',sans-serif; color: var(--text); }
.header { background: var(--primary); color: white; padding: 0 24px; height: 60px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.header h1 { font-size: 1rem; font-weight: 700; }
.header-nav { display: flex; gap: 16px; align-items: center; }
.header-nav a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.875rem; padding: 6px 12px; border-radius: 6px; transition: background 0.2s; }
.header-nav a:hover, .header-nav a.active { background: rgba(255,255,255,0.2); color: white; }
.admin-badge { font-size: 0.8rem; color: rgba(255,255,255,0.7); }
.container { max-width: 800px; margin: 0 auto; padding: 32px 24px; }
.card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 28px 32px; margin-bottom: 24px; }
.card-title { font-size: 1rem; font-weight: 700; margin-bottom: 24px; padding-bottom: 14px; border-bottom: 2px solid var(--bg); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; }
input[type=text], input[type=password] { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.9rem; color: var(--text); background: #fafbfd; outline: none; font-family: inherit; }
input:focus { border-color: var(--primary); background: #fff; }
.btn { padding: 10px 22px; border-radius: 8px; font-size: 0.875rem; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); }
.btn-danger { background: #fdedec; color: var(--red); border: 1px solid #f1948a; padding: 5px 12px; font-size: 0.8rem; }
.btn-danger:hover { background: var(--red); color: white; }
.alert { padding: 12px 16px; border-radius: 8px; font-size: 0.875rem; margin-bottom: 20px; }
.alert-success { background: #eafaf1; border: 1px solid #a9dfbf; color: #1e8449; }
.alert-error   { background: #fdedec; border: 1px solid #f1948a; color: var(--red); }
table { width: 100%; border-collapse: collapse; }
th { background: #f5f7fc; padding: 10px 14px; font-size: 0.8rem; font-weight: 700; color: var(--muted); text-align: left; border-bottom: 2px solid var(--border); }
td { padding: 12px 14px; font-size: 0.875rem; border-bottom: 1px solid var(--border); }
.you-badge { display: inline-block; padding: 2px 8px; background: #eef2fb; color: var(--primary); border-radius: 4px; font-size: 0.75rem; font-weight: 700; margin-left: 6px; }
</style>
</head>
<body>

<header class="header">
  <h1><a href="consent.php" style="color:white;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="admin.php" class="active">관리자 설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">로그아웃</a>
  </nav>
</header>

<div class="container">

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- 관리자 목록 -->
<div class="card">
  <div class="card-title">👥 관리자 계정 목록</div>
  <table>
    <thead>
      <tr><th>이름</th><th>아이디</th><th>등록일</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($admins as $a): ?>
      <tr>
        <td>
          <?= htmlspecialchars($a['name']) ?>
          <?php if ($a['id'] === (int)$_SESSION['admin_id']): ?>
          <span class="you-badge">나</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($a['username']) ?></td>
        <td><?= date('Y.m.d', strtotime($a['created_at'])) ?></td>
        <td>
          <?php if ($a['id'] !== (int)$_SESSION['admin_id']): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
            <button type="submit" class="btn btn-danger">삭제</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- 관리자 추가 -->
<div class="card">
  <div class="card-title">➕ 관리자 추가</div>
  <form method="post" action="admin.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group">
        <label>이름</label>
        <input type="text" name="name" placeholder="실명 입력" required>
      </div>
      <div class="form-group">
        <label>아이디</label>
        <input type="text" name="username" placeholder="로그인 아이디" required>
      </div>
      <div class="form-group">
        <label>비밀번호 (8자 이상)</label>
        <input type="password" name="password" required>
      </div>
      <div class="form-group">
        <label>비밀번호 확인</label>
        <input type="password" name="confirm" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">관리자 추가</button>
  </form>
</div>

<!-- 비밀번호 변경 -->
<div class="card">
  <div class="card-title">🔑 내 비밀번호 변경</div>
  <form method="post" action="admin.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="change_pw">
    <div class="form-group">
      <label>현재 비밀번호</label>
      <input type="password" name="current_password" required style="max-width:360px">
    </div>
    <div class="form-row" style="max-width:740px">
      <div class="form-group">
        <label>새 비밀번호 (8자 이상)</label>
        <input type="password" name="new_password" required>
      </div>
      <div class="form-group">
        <label>새 비밀번호 확인</label>
        <input type="password" name="confirm_password" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">비밀번호 변경</button>
  </form>
</div>

</div>
</body>
</html>
