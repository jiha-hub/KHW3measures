<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$db = getDB();

// 필터
$filterPatient = trim($_GET['patient'] ?? '');
$filterBirth   = trim($_GET['birth'] ?? '');
$filterScale   = $_GET['scale'] ?? '';
$filterDate    = $_GET['date'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;
$offset        = ($page - 1) * $perPage;

// 검색 조건
$where  = ['1=1'];
$params = [];

if ($filterPatient) {
    $where[]  = 'p.name LIKE ?';
    $params[] = '%' . $filterPatient . '%';
}
if ($filterBirth) {
    $where[]  = 'p.birth_date = ?';
    $params[] = $filterBirth;
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
    SELECT a.id, p.name AS patient_name, p.birth_date, p.phone, a.scale_type,
           a.total_score, a.result_label, a.memo, a.battery_id,
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
$detailEmrText = '';
if (isset($_GET['id'])) {
    $detailStmt = $db->prepare("
        SELECT a.*, p.name AS patient_name, p.birth_date, p.gender, ad.name AS admin_name
        FROM assessments a
        JOIN patients p ON a.patient_id = p.id
        JOIN admins ad  ON a.admin_id   = ad.id
        WHERE a.id = ?
    ");
    $detailStmt->execute([(int)$_GET['id']]);
    $detail = $detailStmt->fetch();
    if ($detail) {
        require_once __DIR__ . '/scales.php';
        require_once __DIR__ . '/psqi_scoring.php';
        require_once __DIR__ . '/patient_store.php';
        require_once __DIR__ . '/emr_text.php';

        $detailAnswersRaw = $detail['answers'];
        $detail['answers'] = json_decode($detailAnswersRaw, true);

        $scalesForDetail = getScales();
        $psqiMetaForDetail = getPsqiMeta();
        $rowForInterpret = $detail;
        $rowForInterpret['answers'] = $detailAnswersRaw;
        $itDetail = interpret($rowForInterpret, $scalesForDetail, $psqiMetaForDetail);
        $rowForInterpret['answers_decoded'] = is_array($detail['answers']) ? array_values($detail['answers']) : [];

        $ageDetail = ageFromBirth($detail['birth_date'] ?? null);
        $emrHeader = buildEmrHeader(
            '선별검사 결과',
            $detail['patient_name'],
            $detail['gender'] ?? null,
            $ageDetail,
            date('Y-m-d H:i', strtotime($detail['created_at'])),
            $detail['admin_name'] ?? null
        );
        $detailEmrText = $emrHeader . "\n\n" . buildEmrLine($rowForInterpret, $itDetail);
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
    '정상군' => 'green', '주의군' => 'yellow', '위험군' => 'red',
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
.header { background: #fff; color: #111827; padding: 0 24px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid #e5e7eb; }
.header h1 { font-size: .95rem; font-weight: 700; }
.header-nav { display: flex; gap: 6px; align-items: center; }
.header-nav a { color: #4b5563; text-decoration: none; font-size: .82rem; padding: 6px 12px; border-radius: 8px; font-weight: 500; transition: background 0.2s, color .2s; }
.header-nav a:hover { background: #f3f4f6; }
.header-nav a.active { background: #eff6ff; color: #2563eb; font-weight: 700; }
.admin-badge { font-size: .78rem; color: #9ca3af; }
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
.detail-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 18px; }
.emr-box { margin-top: 14px; background: #f8fafd; border: 1.5px solid var(--border); border-radius: 8px; padding: 12px 14px; display: none; }
.emr-box.open { display: block; }
.emr-box-label { font-size: 0.78rem; font-weight: 700; color: var(--muted); margin-bottom: 8px; }
.emr-box textarea { width: 100%; min-height: 140px; border: 1.5px solid var(--border); border-radius: 8px; padding: 10px; font-size: 0.82rem; line-height: 1.6; font-family: inherit; color: var(--text); background: #fff; resize: vertical; white-space: pre-wrap; }

@media print {
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body * { visibility: hidden; }
  #detailModal, #detailModal * { visibility: visible; }
  #detailModal { position: absolute; inset: 0; background: #fff; padding: 24px; }
  #detailModal .modal { max-width: none; max-height: none; box-shadow: none; width: 100%; }
  #detailModal .modal-header, #detailModal .modal-close, #detailModal .detail-actions, #detailModal .emr-box { display: none !important; }
}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
</head>
<body>

<header class="header">
  <h1><a href="consent.php" style="color:#111827;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
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
        <label>생년월일 <span style="font-weight:400;color:var(--muted);">(동명이인 구분)</span></label>
        <input type="date" name="birth" value="<?= htmlspecialchars($filterBirth) ?>">
      </div>
      <div class="filter-group">
        <label>척도</label>
        <select name="scale">
          <option value="">전체</option>
          <option value="PHQ-9"  <?= $filterScale === 'PHQ-9'  ? 'selected' : '' ?>>PHQ-9</option>
          <option value="GAD-7"  <?= $filterScale === 'GAD-7'  ? 'selected' : '' ?>>GAD-7</option>
          <option value="PSS-10" <?= $filterScale === 'PSS-10' ? 'selected' : '' ?>>PSS-10</option>
          <option value="PSQI-K" <?= $filterScale === 'PSQI-K' ? 'selected' : '' ?>>PSQI-K</option>
          <option value="CSEI-s" <?= $filterScale === 'CSEI-s' ? 'selected' : '' ?>>CSEI-s</option>
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
          <th>생년월일</th>
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
          <td><strong<?= $r['phone'] ? ' title="연락처: ' . htmlspecialchars($r['phone']) . '"' : '' ?>><?= htmlspecialchars($r['patient_name']) ?></strong></td>
          <td style="color:var(--muted);white-space:nowrap;"><?= htmlspecialchars($r['birth_date'] ?: '—') ?></td>
          <td><span class="scale-tag"><?= htmlspecialchars($r['scale_type']) ?></span></td>
          <td><strong><?= $r['scale_type']==='CSEI-s' ? 'T '.$r['total_score'] : $r['total_score'].'점' ?></strong></td>
          <td><span class="badge badge-<?= $colorKey ?>"><?= htmlspecialchars($r['result_label']) ?></span></td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)">
            <?= htmlspecialchars($r['memo'] ?: '—') ?>
          </td>
          <td><?= htmlspecialchars($r['admin_name']) ?></td>
          <td style="white-space:nowrap;">
            <a href="?id=<?= $r['id'] ?>&<?= http_build_query(['patient'=>$filterPatient,'birth'=>$filterBirth,'scale'=>$filterScale,'date'=>$filterDate,'page'=>$page]) ?>" class="btn btn-secondary btn-sm">상세</a>
            <?php if (!empty($r['battery_id'])): ?>
            <a href="summary.php?battery=<?= urlencode($r['battery_id']) ?>" class="btn btn-secondary btn-sm" title="이 방문 전체 요약 / PDF">🖨️ 방문</a>
            <?php endif; ?>
          </td>
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
    <a href="?page=<?= $i ?>&<?= http_build_query(['patient'=>$filterPatient,'birth'=>$filterBirth,'scale'=>$filterScale,'date'=>$filterDate]) ?>"><?= $i ?></a>
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
  require_once __DIR__ . '/psqi_scoring.php';
  $scales    = getScales();
  $isPsqi    = $detail['scale_type'] === 'PSQI-K';
  $isCsei    = $detail['scale_type'] === 'CSEI-s';
  $scale     = ($isPsqi || $isCsei) ? null : ($scales[$detail['scale_type']] ?? null);
  $psqiBreakdown = $isPsqi ? calculatePSQI($detail['answers'] ?? []) : null;
  $psqiMetaD = $isPsqi ? getPsqiMeta() : null;
  $cseiPayload = $isCsei ? (json_decode($detail['factor_scores'] ?? '', true) ?: []) : [];
  $cseiFactors = $cseiPayload['factors'] ?? [];
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
        <span>🎂 <?= htmlspecialchars($detail['birth_date'] ?: '생년월일 미상') ?><?= $ageDetail !== null ? " (만 {$ageDetail}세)" : '' ?></span>
        <?php if (!empty($detail['phone'])): ?><span>📱 <?= htmlspecialchars($detail['phone']) ?></span><?php endif; ?>
        <span>📅 <?= date('Y년 m월 d일 H:i', strtotime($detail['created_at'])) ?></span>
        <span>🏷 <?= htmlspecialchars($detail['scale_type']) ?></span>
        <span>👨‍⚕️ <?= htmlspecialchars($detail['admin_name']) ?></span>
      </div>

      <div class="detail-score-box" style="background:<?= $colorHex ?>18;border:2px solid <?= $colorHex ?>">
        <div class="detail-score" style="color:<?= $colorHex ?>"><?= $isCsei ? '종합 T '.$detail['total_score'] : $detail['total_score'].'점' ?></div>
        <div class="detail-label" style="color:<?= $colorHex ?>"><?= htmlspecialchars($detail['result_label']) ?></div>
      </div>

      <?php if ($isCsei && $cseiFactors): ?>
      <div style="margin-bottom:14px;">
        <div style="font-size:.85rem;font-weight:700;color:var(--muted);margin-bottom:8px;">7가지 감정 T점수</div>
        <?php foreach ($cseiFactors as $f):
          $w = min(100, round($f['tScore']));
          $bar = $f['group']==='risk' ? '#c0392b' : ($f['group']==='caution' ? '#e0a800' : '#27ae60');
        ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
          <span style="font-size:.82rem;width:64px;flex-shrink:0;"><?= htmlspecialchars($f['name']) ?></span>
          <span style="flex:1;height:9px;background:var(--border);border-radius:99px;overflow:hidden;">
            <span style="display:block;height:100%;width:<?= $w ?>%;background:<?= $bar ?>;border-radius:99px;"></span>
          </span>
          <span style="font-size:.8rem;font-weight:700;color:<?= $bar ?>;width:70px;text-align:right;">T<?= (int)$f['tScore'] ?> <?= htmlspecialchars($f['groupLabel']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
        <a href="csei_result.php?id=<?= (int)$detail['id'] ?>" class="btn btn-primary btn-sm">📊 결과 화면</a>
        <a href="csei_report.php?id=<?= (int)$detail['id'] ?>" class="btn btn-secondary btn-sm">🧠 심층 리포트 / PDF</a>
      </div>
      <?php elseif ($scale): ?>
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
      <?php elseif ($isPsqi && $psqiBreakdown): ?>
      <div style="margin-bottom:14px;">
        <div style="font-size:.85rem;font-weight:700;color:var(--muted);margin-bottom:8px;">구성요소별 점수 (각 0~3)</div>
        <?php foreach ($psqiBreakdown['components'] as $c):
          $w = round($c['score']/3*100);
          $bar = $c['score']>=2 ? '#c0392b' : ($c['score']==1 ? '#e0a800' : '#27ae60');
        ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
          <span style="font-size:.82rem;width:120px;flex-shrink:0;"><?= htmlspecialchars($c['name']) ?></span>
          <span style="flex:1;height:9px;background:var(--border);border-radius:99px;overflow:hidden;">
            <span style="display:block;height:100%;width:<?= $w ?>%;background:<?= $bar ?>;border-radius:99px;"></span>
          </span>
          <span style="font-size:.8rem;font-weight:700;color:var(--muted);width:32px;text-align:right;"><?= $c['score'] ?>/3</span>
        </div>
        <?php endforeach; ?>
        <?php if ($psqiBreakdown['efficiency'] !== null): ?>
        <div style="font-size:.8rem;color:var(--muted);margin-top:6px;">수면 효율 <?= $psqiBreakdown['efficiency'] ?>% · 침대에 누운 시간 <?= $psqiBreakdown['hours_in_bed'] ?>시간</div>
        <?php endif; ?>
      </div>
      <div class="answer-list">
        <?php foreach ($psqiMetaD['items'] as $qi => $item):
          $raw = $detail['answers'][$qi] ?? '';
          if ($item['type'] === 'likert') {
            $opts = $item['options'] ?? $psqiMetaD['freq_options'];
            $disp = ($opts[(int)$raw] ?? $raw) . " ({$raw}점)";
          } elseif ($item['type'] === 'time') {
            $disp = $raw !== '' ? $raw : '—';
          } else {
            $disp = $raw . ' ' . ($item['unit'] ?? '');
          }
        ?>
        <div class="answer-item">
          <span class="ans-num"><?= $qi+1 ?></span>
          <span style="font-size:.82rem;"><?= htmlspecialchars(mb_strimwidth($item['q'], 0, 60, '…')) ?></span>
          <span class="ans-score" style="min-width:70px;font-size:.8rem;"><?= htmlspecialchars($disp) ?></span>
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

      <div class="emr-box" id="emrBox">
        <div class="emr-box-label">EMR 붙여넣기용 텍스트</div>
        <textarea id="emrTextArea" readonly><?= htmlspecialchars($detailEmrText) ?></textarea>
      </div>

      <div class="detail-actions">
        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ PDF로 저장 / 인쇄</button>
        <button type="button" class="btn btn-secondary" onclick="toggleEmrBox()" id="btn-emr-toggle">📋 EMR 텍스트 생성</button>
        <button type="button" class="btn btn-secondary" onclick="copyEmrText()" id="btn-emr-copy" style="display:none;">📋 클립보드에 복사</button>
      </div>
    </div>
  </div>
</div>
<script>
function closeModal() {
    window.location.href = 'history.php?<?= http_build_query(['patient'=>$filterPatient,'birth'=>$filterBirth,'scale'=>$filterScale,'date'=>$filterDate,'page'=>$page]) ?>';
}
function toggleEmrBox() {
  const box = document.getElementById('emrBox');
  const copyBtn = document.getElementById('btn-emr-copy');
  const show = !box.classList.contains('open');
  box.classList.toggle('open', show);
  copyBtn.style.display = show ? 'inline-block' : 'none';
}
function copyEmrText() {
  const text = document.getElementById('emrTextArea').value;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.getElementById('btn-emr-copy');
    const original = btn.innerHTML;
    btn.innerHTML = '✅ 복사 완료';
    setTimeout(() => { btn.innerHTML = original; }, 2000);
  }).catch(err => {
    alert('복사에 실패했습니다. 권한을 확인해주세요.');
    console.error('Copy failed', err);
  });
}
</script>
<?php endif; ?>
<?php include __DIR__ . '/ui_settings.php'; ?>
</body>
</html>
