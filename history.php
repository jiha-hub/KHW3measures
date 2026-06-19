<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$db = getDB();

// 필터
$filterPatient = trim($_GET['patient'] ?? '');
$filterScale   = $_GET['scale'] ?? '';
$filterDate    = $_GET['date'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;
$offset        = ($page - 1) * $perPage;

// 환자 목록 (검색용)
$patients = $db->query('SELECT id, name FROM patients ORDER BY name')->fetchAll();

// 검색 조건
$where  = ['1=1'];
$params = [];

if ($filterPatient) {
    $where[]  = 'p.name LIKE ?';
    $params[] = '%' . $filterPatient . '%';
}
if ($filterScale) {
    $where[]  = 'a.scale_type = ?';
    $params[] = $filterScale;
}
if ($filterDate) {
    $where[]  = 'DATE(a.created_at) = ?';
    $params[] = $filterDate;
}

$whereStr = implode(' AND ', $where);

// 전체 수
$countStmt = $db->prepare("SELECT COUNT(*) FROM assessments a JOIN patients p ON a.patient_id = p.id WHERE $whereStr");
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$totalPage = (int)ceil($total / $perPage);

// 목록 조회
$listStmt = $db->prepare("
    SELECT a.id, p.name AS patient_name, a.scale_type,
           a.total_score, a.result_label, a.memo,
           a.created_at, ad.name AS admin_name
    FROM assessments a
    JOIN patients p  ON a.patient_id = p.id
    JOIN admins ad   ON a.admin_id   = ad.id
    WHERE $whereStr
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$records = $listStmt->fetchAll();

// 상세 조회
$detail = null;
if (isset($_GET['id'])) {
    $detailStmt = $db->prepare("
        SELECT a.*, p.name AS patient_name, ad.name AS admin_name
        FROM assessments a
        JOIN patients p ON a.patient_id = p.id
        JOIN admins ad  ON a.admin_id   = ad.id
        WHERE a.id = ?
    ");
    $detailStmt->execute([(int)$_GET['id']]);
    $detail = $detailStmt->fetch();
    if ($detail) {
        $detail['answers'] = json_decode($detail['answers'], true);
    }
}

// 색상 매핑
$colorMap = [
    'green' => '#27ae60', 'yellow' => '#f39c12', 'orange' => '#e67e22',
    'red' => '#c0392b', 'darkred' => '#922b21',
];
$labelColorMap = [
    '우울아님' => 'green', '가벼운 우울' => 'yellow', '중간정도 우울' => 'orange',
    '중한 우울' => 'red',  '심한 우울' => 'darkred',
    '불안아님' => 'green', '가벼운 불안' => 'yellow', '중간 불안' => 'orange', '심한 불안' => 'red',
    '낮은 스트레스' => 'green', '중간 스트레스' => 'yellow', '높은 스트레스' => 'red',
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>이력 조회 — <?= APP_NAME ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #f0f4f8; --card: #fff; --primary: #3b6cb7; --primary-dark: #2d549a;
    --text: #1a2236; --muted: #6b7a99; --border: #dce3ef;
    --radius: 12px; --shadow: 0 4px 24px rgba(59,108,183,0.10);
    --green: #27ae60; --yellow: #f39c12; --orange: #e67e22; --red: #c0392b;
}
body { background: var(--bg); font-family: 'Apple SD Gothic Neo','Noto Sans KR',sans-serif; color: var(--text); }
.header { background: var(--primary); color: white; padding: 0 24px; height: 60px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.header h1 { font-size: 1rem; font-weight: 700; }
.header-nav { display: flex; gap: 16px; align-items: center; }
.header-nav a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.875rem; padding: 6px 12px; border-radius: 6px; transition: background 0.2s; }
.header-nav a:hover, .header-nav a.active { background: rgba(255,255,255,0.2); color: white; }
.admin-badge { font-size: 0.8rem; color: rgba(255,255,255,0.7); }
.container { max-width: 1000px; margin: 0 auto; padding: 32px 24px; }
.card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 24px; margin-bottom: 20px; }
.card-title { font-size: 1rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid var(--bg); }

/* 필터 */
.filter-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 0.8rem; font-weight: 600; color: var(--muted); }
input[type=text], input[type=date], select {
    padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 8px;
    font-size: 0.875rem; color: var(--text); background: #fafbfd; outline: none;
    font-family: inherit;
}
input:focus, select:focus { border-color: var(--primary); }

.btn { padding: 9px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); }
.btn-secondary { background: var(--bg); color: var(--text); border: 1.5px solid var(--border); }
.btn-sm { padding: 5px 12px; font-size: 0.8rem; }

/* 테이블 */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th { background: #f5f7fc; padding: 12px 14px; font-size: 0.8rem; font-weight: 700; color: var(--muted); text-align: left; border-bottom: 2px solid var(--border); }
td { padding: 13px 14px; font-size: 0.875rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
tr:hover td { background: #f8fafd; }
.badge {
    display: inline-block; padding: 4px 10px; border-radius: 20px;
    font-size: 0.78rem; font-weight: 700; color: white;
}
.badge-green  { background: var(--green); }
.badge-yellow { background: var(--yellow); }
.badge-orange { background: var(--orange); }
.badge-red    { background: var(--red); color: white; }
.badge-darkred { background: #1a237e; color: white; }
.scale-tag { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.78rem; font-weight: 700; background: #eef2fb; color: var(--primary); }

/* 페이지네이션 */
.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 20px; }
.pagination a, .pagination span {
    padding: 7px 13px; border-radius: 6px; font-size: 0.875rem;
    text-decoration: none; border: 1.5px solid var(--border);
}
.pagination a { color: var(--text); } .pagination a:hover { border-color: var(--primary); color: var(--primary); }
.pagination .current { background: var(--primary); color: white; border-color: var(--primary); }

/* 상세 모달 */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; padding: 24px; }
.modal-overlay.open { display: flex; }
.modal { background: white; border-radius: var(--radius); width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 40px rgba(0,0,0,0.2); }
.modal-header { padding: 24px 28px 0; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { font-size: 1.1rem; font-weight: 700; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--muted); line-height: 1; }
.modal-body { padding: 20px 28px 28px; }
.detail-meta { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; font-size: 0.85rem; color: var(--muted); }
.detail-score-box { text-align: center; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
.detail-score { font-size: 3rem; font-weight: 800; }
.detail-label { font-size: 1.1rem; font-weight: 700; margin-top: 4px; }
.answer-list { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.answer-item { display: grid; grid-template-columns: 28px 1fr auto; gap: 12px; padding: 10px 14px; align-items: start; font-size: 0.85rem; border-bottom: 1px solid var(--border); }
.answer-item:last-child { border-bottom: none; }
.answer-item:nth-child(even) { background: #fafbfd; }
.ans-num { font-weight: 700; color: var(--primary); }
.ans-score { font-weight: 700; background: var(--primary); color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; }
.memo-box { background: #f8f9fb; border-radius: 8px; padding: 14px; font-size: 0.875rem; margin-top: 16px; line-height: 1.6; }
.memo-box strong { display: block; margin-bottom: 6px; color: var(--muted); font-size: 0.8rem; }
.empty { text-align: center; padding: 60px 20px; color: var(--muted); font-size: 0.9rem; }
</style>
</head>
<body>

<header class="header">
  <h1><a href="consent.php" style="color:white;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php">검사 입력</a>
    <a href="history.php" class="active">이력 조회</a>
    <a href="graph.php">그래프</a>
    <a href="admin.php">관리자 설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">로그아웃</a>
  </nav>
</header>

<div class="container">

<!-- 필터 -->
<div class="card">
  <div class="card-title">🔍 검색 필터</div>
  <form method="get" action="history.php">
    <div class="filter-row">
      <div class="filter-group">
        <label>환자 이름</label>
        <input type="text" name="patient" value="<?= htmlspecialchars($filterPatient) ?>" placeholder="이름 검색">
      </div>
      <div class="filter-group">
        <label>척도</label>
        <select name="scale">
          <option value="">전체</option>
          <option value="PHQ-9"  <?= $filterScale === 'PHQ-9'  ? 'selected' : '' ?>>PHQ-9</option>
          <option value="GAD-7"  <?= $filterScale === 'GAD-7'  ? 'selected' : '' ?>>GAD-7</option>
          <option value="PSS-10" <?= $filterScale === 'PSS-10' ? 'selected' : '' ?>>PSS-10</option>
        </select>
      </div>
      <div class="filter-group">
        <label>검사 날짜</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
      </div>
      <button type="submit" class="btn btn-primary">검색</button>
      <a href="history.php" class="btn btn-secondary">초기화</a>
    </div>
  </form>
</div>

<!-- 결과 목록 -->
<div class="card">
  <div class="card-title">📋 검사 이력 (총 <?= $total ?>건)</div>
  <?php if (empty($records)): ?>
  <div class="empty">검색 결과가 없습니다.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>검사일시</th>
          <th>환자명</th>
          <th>척도</th>
          <th>점수</th>
          <th>결과</th>
          <th>메모</th>
          <th>입력자</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): 
          $colorKey = $labelColorMap[$r['result_label']] ?? 'green';
        ?>
        <tr>
          <td><?= date('Y.m.d H:i', strtotime($r['created_at'])) ?></td>
          <td><strong><?= htmlspecialchars($r['patient_name']) ?></strong></td>
          <td><span class="scale-tag"><?= htmlspecialchars($r['scale_type']) ?></span></td>
          <td><strong><?= $r['total_score'] ?>점</strong></td>
          <td><span class="badge badge-<?= $colorKey ?>"><?= htmlspecialchars($r['result_label']) ?></span></td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)">
            <?= htmlspecialchars($r['memo'] ?: '—') ?>
          </td>
          <td><?= htmlspecialchars($r['admin_name']) ?></td>
          <td><a href="?id=<?= $r['id'] ?>&<?= http_build_query(['patient'=>$filterPatient,'scale'=>$filterScale,'date'=>$filterDate,'page'=>$page]) ?>" class="btn btn-secondary btn-sm">상세</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- 페이지네이션 -->
  <?php if ($totalPage > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPage; $i++): ?>
    <?php if ($i === $page): ?>
    <span class="current"><?= $i ?></span>
    <?php else: ?>
    <a href="?page=<?= $i ?>&<?= http_build_query(['patient'=>$filterPatient,'scale'=>$filterScale,'date'=>$filterDate]) ?>"><?= $i ?></a>
    <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

</div>

<!-- 상세 모달 -->
<?php if ($detail): 
  require_once __DIR__ . '/scales.php';
  $scales    = getScales();
  $scale     = $scales[$detail['scale_type']] ?? null;
  $colorKey  = $labelColorMap[$detail['result_label']] ?? 'green';
  $colorHex  = $colorMap[$colorKey] ?? '#27ae60';
?>
<div class="modal-overlay open" id="detailModal" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-header">
      <h2>검사 상세 결과</h2>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="detail-meta">
        <span>👤 <?= htmlspecialchars($detail['patient_name']) ?></span>
        <span>📅 <?= date('Y년 m월 d일 H:i', strtotime($detail['created_at'])) ?></span>
        <span>🏷 <?= htmlspecialchars($detail['scale_type']) ?></span>
        <span>👨‍⚕️ <?= htmlspecialchars($detail['admin_name']) ?></span>
      </div>

      <div class="detail-score-box" style="background:<?= $colorHex ?>18;border:2px solid <?= $colorHex ?>">
        <div class="detail-score" style="color:<?= $colorHex ?>"><?= $detail['total_score'] ?>점</div>
        <div class="detail-label" style="color:<?= $colorHex ?>"><?= htmlspecialchars($detail['result_label']) ?></div>
      </div>

      <?php if ($scale): ?>
      <div class="answer-list">
        <?php foreach ($scale['questions'] as $qi => $question): 
          $ansVal = $detail['answers'][$qi] ?? 0;
        ?>
        <div class="answer-item">
          <span class="ans-num"><?= $qi+1 ?></span>
          <span><?= htmlspecialchars($question) ?></span>
          <span class="ans-score"><?= $ansVal ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($detail['memo']): ?>
      <div class="memo-box">
        <strong>메모</strong>
        <?= nl2br(htmlspecialchars($detail['memo'])) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
function closeModal() {
    window.location.href = 'history.php?<?= http_build_query(['patient'=>$filterPatient,'scale'=>$filterScale,'date'=>$filterDate,'page'=>$page]) ?>';
}
</script>
<?php endif; ?>

</body>
</html>
