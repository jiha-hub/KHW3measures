<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/patient_store.php';   // scaleOrder()
require_once __DIR__ . '/date_field.php';      // 숫자패드+달력 날짜 입력
requireLogin();

$db = getDB();

// 필터
$filterPatient = trim($_GET['patient'] ?? '');
$filterScale   = $_GET['scale'] ?? '';
$showDeleted   = ($_GET['show'] ?? '') === 'deleted';   // 숨김(삭제)된 기록 보기
$flashMsg      = $_GET['msg'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;   // 페이지당 "방문(검사일시)" 수
$offset        = ($page - 1) * $perPage;
$csrf          = getCsrfToken();

// 생년월일: 8자리 숫자 → YYYY-MM-DD
$birthRaw   = preg_replace('/[^0-9]/', '', (string)($_GET['birth'] ?? ''));
$filterBirth = '';
if (strlen($birthRaw) === 8) {
    $y=substr($birthRaw,0,4); $m=substr($birthRaw,4,2); $d=substr($birthRaw,6,2);
    if (checkdate((int)$m,(int)$d,(int)$y)) $filterBirth = "$y-$m-$d";
}
// 검사 기간 (from ~ to)
$filterFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$filterTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '')   ? $_GET['to']   : '';

// 검색 조건 — 기본은 숨김되지 않은 기록만
$where  = [$showDeleted ? 'a.deleted_at IS NOT NULL' : 'a.deleted_at IS NULL'];
$params = [];
if ($filterPatient) { $where[] = 'p.name LIKE ?'; $params[] = '%'.$filterPatient.'%'; }
if ($filterBirth)   { $where[] = 'p.birth_date = ?'; $params[] = $filterBirth; }
if ($filterScale)   { $where[] = 'a.scale_type = ?'; $params[] = $filterScale; }
if ($filterFrom)    { $where[] = 'DATE(a.created_at) >= ?'; $params[] = $filterFrom; }
if ($filterTo)      { $where[] = 'DATE(a.created_at) <= ?'; $params[] = $filterTo; }
$whereStr = implode(' AND ', $where);

// 검사일시(방문) 단위로 페이지네이션 — 방문키 = battery_id 또는 개별검사 'a'+id
$vkeyExpr = "COALESCE(a.battery_id, CONCAT('a', a.id))";

// 전체 방문 수
$countStmt = $db->prepare("SELECT COUNT(*) FROM (
    SELECT $vkeyExpr AS vkey FROM assessments a JOIN patients p ON a.patient_id=p.id
    WHERE $whereStr GROUP BY vkey) t");
$countStmt->execute($params);
$totalVisits = (int)$countStmt->fetchColumn();
$totalPage   = max(1, (int)ceil($totalVisits / $perPage));

// 이 페이지의 방문 목록 (검사일시 최신순)
$visitStmt = $db->prepare("
    SELECT $vkeyExpr AS vkey, MAX(a.created_at) AS vtime, p.name AS patient_name,
           p.birth_date, p.phone, a.battery_id
    FROM assessments a JOIN patients p ON a.patient_id=p.id
    WHERE $whereStr
    GROUP BY vkey, p.name, p.birth_date, p.phone, a.battery_id
    ORDER BY vtime DESC
    LIMIT $perPage OFFSET $offset");
$visitStmt->execute($params);
$visitRows = $visitStmt->fetchAll();

// 각 방문의 검사행 로드 → 방문별로 묶고 척도순 정렬
$scaleOrd = array_flip(scaleOrder());
$visits = [];
$vkeys  = [];
foreach ($visitRows as $v) { $vkeys[] = $v['vkey']; $v['rows']=[]; $visits[$v['vkey']]=$v; }
if ($vkeys) {
    $ph = implode(',', array_fill(0, count($vkeys), '?'));
    $rowStmt = $db->prepare("
        SELECT a.id, $vkeyExpr AS vkey, a.scale_type, a.total_score, a.result_label,
               a.memo, a.battery_id, a.created_at, ad.name AS admin_name
        FROM assessments a JOIN admins ad ON a.admin_id=ad.id
        WHERE " . ($showDeleted ? 'a.deleted_at IS NOT NULL' : 'a.deleted_at IS NULL') . "
          AND $vkeyExpr IN ($ph)");
    $rowStmt->execute($vkeys);
    foreach ($rowStmt->fetchAll() as $r) {
        if (isset($visits[$r['vkey']])) $visits[$r['vkey']]['rows'][] = $r;
    }
    foreach ($visits as $k => &$v) {
        usort($v['rows'], fn($x,$y)=>($scaleOrd[$x['scale_type']]??99)<=>($scaleOrd[$y['scale_type']]??99));
    }
    unset($v);
}
$total = $totalVisits;

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
    'red' => '#4b5563', 'darkred' => '#4b5563', 'black' => '#4b5563',
];
// 중한 결과는 붉은색 대신 검정(black)으로 표기 — 환자 불안 완화
$labelColorMap = [
    '우울아님' => 'green', '가벼운 우울' => 'yellow', '중간정도 우울' => 'orange',
    '중한 우울' => 'black',  '심한 우울' => 'black',
    '불안아님' => 'green', '가벼운 불안' => 'yellow', '중간 불안' => 'orange', '심한 불안' => 'black',
    '낮은 스트레스' => 'green', '중간 스트레스' => 'yellow', '높은 스트레스' => 'black',
    '정상군' => 'green', '주의군' => 'yellow', '위험군' => 'black',
    // 신규 척도
    '최소 신체증상' => 'green', '경도 신체증상' => 'yellow', '중등도 신체증상' => 'orange', '고도 신체증상' => 'black',
    '자살사고 없음' => 'green', '자살사고 – 경도' => 'yellow', '자살사고 – 뚜렷' => 'black',
    '정상' => 'green', '경도 우울 경향' => 'yellow', '우울 의심' => 'black',
    '선별 음성' => 'green', '선별 양성' => 'black',
    '임상적 위험군' => 'black',
    // PSQI-K
    '양호한 수면' => 'green', '경도 수면문제' => 'yellow', '수면의 질 저하' => 'black',
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
.badge-red    { background: #4b5563; color: white; }
.badge-darkred { background: #4b5563; color: white; }
.badge-black  { background: #4b5563; color: white; }
.scale-tag { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.78rem; font-weight: 700; background: #eef2fb; color: var(--primary); }

/* 페이지네이션 */
.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 20px; }
.pagination a, .pagination span {
    padding: 7px 13px; border-radius: 6px; font-size: 0.875rem;
    text-decoration: none; border: 1.5px solid var(--border);
}
.pagination a { color: var(--text); } .pagination a:hover { border-color: var(--primary); color: var(--primary); }
.pagination .current { background: var(--primary); color: white; border-color: var(--primary); }

/* ===== 2단 레이아웃: 왼쪽 필터 사이드바(고정) + 오른쪽 결과 ===== */
.layout{display:grid;grid-template-columns:290px 1fr;gap:20px;align-items:start;}
@media(max-width:820px){.layout{grid-template-columns:1fr;}}
.side{position:sticky;top:72px;}
@media(max-width:820px){.side{position:static;top:auto;}}
.side .card{margin-bottom:0;}
.side .filter-group{margin-bottom:14px;}
.side .filter-group:last-of-type{margin-bottom:0;}
.side label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);margin-bottom:5px;}
.side input[type=text],.side input[type=date],.side select{width:100%;}
.date-range{display:flex;align-items:center;gap:6px;}
.date-range input{flex:1;min-width:0;}
.date-range .datefield{flex:1;min-width:0;}
.date-range span{color:var(--muted);font-size:.8rem;}
.side .actions{display:flex;gap:8px;margin-top:6px;}
.side .actions .btn{flex:1;text-align:center;}
.birth-hint{font-size:.72rem;color:var(--muted);margin-top:4px;}

/* 방문(검사일시) 그룹 */
.visit{border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:14px;background:var(--card);}
.visit-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:11px 16px;background:#f5f7fc;border-bottom:1px solid var(--border);}
.visit-head .v-date{font-weight:800;font-size:.92rem;color:var(--primary);font-variant-numeric:tabular-nums;}
.visit-head .v-name{font-weight:700;font-size:.95rem;}
.visit-head .v-birth{font-size:.8rem;color:var(--muted);}
.visit-head .v-count{font-size:.75rem;color:var(--muted);margin-left:auto;}
.visit-table{width:100%;border-collapse:collapse;}
.visit-table td{padding:11px 14px;font-size:.875rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.visit-table tr:last-child td{border-bottom:none;}
.visit-table tr:hover td{background:#f8fafd;}
.chk{width:18px;height:18px;accent-color:var(--primary);cursor:pointer;vertical-align:middle;}
.col-chk{width:36px;text-align:center;}
.col-act{white-space:nowrap;text-align:right;}

/* 일괄 선택/삭제 바 */
.bulkbar{position:sticky;top:56px;z-index:50;display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  background:var(--card);border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:14px;box-shadow:var(--shadow);}
.bulkbar .selall{display:flex;align-items:center;gap:7px;font-size:.85rem;font-weight:600;cursor:pointer;}
.bulkbar .count{font-size:.83rem;color:var(--muted);}
.bulkbar .spacer{margin-left:auto;}
.btn-danger{background:#4b5563;color:#fff;}
.btn-danger:hover{background:#3a434f;}
.btn-danger:disabled{opacity:.45;cursor:not-allowed;}
.btn-restore{background:#1e8449;color:#fff;}

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
  <?php dateFieldAssets(); ?>
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

<?php $qbase = ['patient'=>$filterPatient,'birth'=>$birthRaw,'scale'=>$filterScale,'from'=>$filterFrom,'to'=>$filterTo] + ($showDeleted ? ['show'=>'deleted'] : []); ?>
<div class="container">
<div class="layout">

  <!-- 왼쪽 필터 사이드바(고정) -->
  <aside class="side">
    <div class="card">
      <div class="card-title" style="margin-bottom:16px;">🔍 검색 필터</div>
      <form method="get" action="history.php" id="filterForm">
        <div class="filter-group">
          <label>환자 이름</label>
          <input type="text" name="patient" value="<?= htmlspecialchars($filterPatient) ?>" placeholder="이름 검색">
        </div>
        <div class="filter-group">
          <label>생년월일 <span style="font-weight:400;">(동명이인 구분)</span></label>
          <input type="text" name="birth" id="birthInput" value="<?= htmlspecialchars($filterBirth) ?>"
                 inputmode="numeric" maxlength="10" autocomplete="off" placeholder="예: 19800315">
          <div class="birth-hint" id="birthHint">8자리 숫자 입력</div>
        </div>
        <div class="filter-group">
          <label>척도</label>
          <select name="scale">
            <option value="">전체</option>
            <?php foreach (['CSEI-s','PHQ-9','GAD-7','PSS-10','PHQ-15','BDI-9','S-GDpS','K-MDQ','SSD-12','PSQI-K'] as $sc): ?>
            <option value="<?= $sc ?>" <?= $filterScale === $sc ? 'selected' : '' ?>><?= $sc ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>검사 기간</label>
          <div class="date-range">
            <?php renderDateField('from', $filterFrom, 'fromInput', '시작일'); ?>
            <span>~</span>
            <?php renderDateField('to', $filterTo, 'toInput', '종료일'); ?>
          </div>
        </div>
        <?php if ($showDeleted): ?><input type="hidden" name="show" value="deleted"><?php endif; ?>
        <div class="actions">
          <button type="submit" class="btn btn-primary">검색</button>
          <a href="history.php<?= $showDeleted ? '?show=deleted' : '' ?>" class="btn btn-secondary">초기화</a>
        </div>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
          <?php if ($showDeleted): ?>
            <a href="history.php?<?= http_build_query(array_diff_key($qbase,['show'=>1])) ?>" class="btn btn-secondary btn-sm" style="width:100%;text-align:center;">← 일반 이력으로</a>
          <?php else: ?>
            <a href="history.php?<?= http_build_query($qbase + ['show'=>'deleted']) ?>" class="btn btn-secondary btn-sm" style="width:100%;text-align:center;">🗑️ 숨김 기록 보기</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </aside>

  <!-- 오른쪽 결과 -->
  <div class="results">
  <?php if ($flashMsg): ?>
  <div style="margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:.85rem;<?= str_starts_with($flashMsg,'err:') ? 'background:#ececec;border:1px solid #4b5563;color:#4b5563;' : 'background:#eafaf1;border:1px solid #a9dfbf;color:#1e8449;' ?>">
    <?= htmlspecialchars(str_starts_with($flashMsg,'err:') ? ('오류: '.substr($flashMsg,4)) : $flashMsg) ?>
  </div>
  <?php endif; ?>
  <div class="card-title" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;border:none;padding:0;margin-bottom:14px;">
    <span><?= $showDeleted ? '🗑️ 숨김(삭제)된 검사 이력' : '📋 검사 이력' ?> — 총 <?= $total ?>건(검사일시)</span>
  </div>
  <?php $retq = 'history.php?' . http_build_query($qbase + ['page'=>$page]); ?>
  <?php if (empty($visits)): ?>
  <div class="empty card">검색 결과가 없습니다.</div>
  <?php else: ?>
  <form method="post" action="delete_assessment.php" id="bulkForm"
        onsubmit="return confirm('<?= $showDeleted ? '선택한 기록을 복구할까요?' : '선택한 기록을 숨김 처리할까요? (기록은 보존되며 복구할 수 있습니다)' ?>');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="<?= $showDeleted ? 'restore' : 'delete' ?>">
    <input type="hidden" name="return" value="<?= htmlspecialchars($retq) ?>">
    <div class="bulkbar">
      <label class="selall"><input type="checkbox" id="selAll" class="chk" onclick="toggleAll(this)"> 전체 선택</label>
      <span class="count" id="selCount">0개 선택</span>
      <span class="spacer"></span>
      <button type="submit" class="btn btn-sm <?= $showDeleted ? 'btn-restore' : 'btn-danger' ?>" id="bulkBtn" disabled>
        <?= $showDeleted ? '↩ 선택 복구' : '🗑 선택 삭제' ?>
      </button>
    </div>

    <?php foreach ($visits as $v):
      $age = ageFromBirth($v['birth_date'] ?? null);
    ?>
    <div class="visit">
      <div class="visit-head">
        <span class="v-date"><?= date('Y.m.d (D) H:i', strtotime($v['vtime'])) ?></span>
        <span class="v-name"<?= $v['phone'] ? ' title="연락처: '.htmlspecialchars($v['phone']).'"' : '' ?>>👤 <?= htmlspecialchars($v['patient_name']) ?></span>
        <span class="v-birth"><?= htmlspecialchars($v['birth_date'] ?: '생년월일 미상') ?><?= $age!==null ? " (만 {$age}세)" : '' ?></span>
        <?php if (!empty($v['battery_id']) && !$showDeleted): ?>
        <a class="btn btn-secondary btn-sm" href="summary.php?battery=<?= urlencode($v['battery_id']) ?>" title="이 방문 전체 요약 / PDF">🖨️ 방문요약</a>
        <?php endif; ?>
        <span class="v-count"><?= count($v['rows']) ?>개 검사</span>
      </div>
      <table class="visit-table"><tbody>
        <?php foreach ($v['rows'] as $r):
          $colorKey = $labelColorMap[$r['result_label']] ?? 'green';
          $detailQ  = http_build_query($qbase + ['page'=>$page, 'id'=>$r['id']]);
        ?>
        <tr>
          <td class="col-chk"><input type="checkbox" class="chk rowchk" name="ids[]" value="<?= $r['id'] ?>" onclick="updateSel()"></td>
          <td style="width:92px;"><span class="scale-tag"><?= htmlspecialchars($r['scale_type']) ?></span></td>
          <td style="width:70px;"><strong><?= $r['scale_type']==='CSEI-s' ? 'T '.$r['total_score'] : $r['total_score'].'점' ?></strong></td>
          <td><span class="badge badge-<?= $colorKey ?>"><?= htmlspecialchars($r['result_label']) ?></span></td>
          <td style="color:var(--muted);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['memo'] ?: '—') ?></td>
          <td class="col-act"><a href="?<?= $detailQ ?>" class="btn btn-secondary btn-sm">상세</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php endforeach; ?>
  </form>

  <!-- 페이지네이션 -->
  <?php if ($totalPage > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPage; $i++): ?>
    <?php if ($i === $page): ?>
    <span class="current"><?= $i ?></span>
    <?php else: ?>
    <a href="?<?= http_build_query($qbase + ['page'=>$i]) ?>"><?= $i ?></a>
    <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  </div><!-- results -->
</div><!-- layout -->
</div><!-- container -->

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
  // 신규 일반 척도: 하위영역/플래그 계산 (SSD-12 등)
  $genRes = ($scale && is_array($detail['answers'])) ? calculateScore($detail['scale_type'], array_values($detail['answers'])) : null;
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
          $bar = $f['group']==='risk' ? '#4b5563' : ($f['group']==='caution' ? '#e0a800' : '#27ae60');
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
      <?php if ($genRes && !empty($genRes['subscales'])): ?>
      <div style="margin-bottom:14px;">
        <div style="font-size:.85rem;font-weight:700;color:var(--muted);margin-bottom:8px;">하위영역 점수</div>
        <?php foreach ($genRes['subscales'] as $su):
          $mx = count($su['items']) * max($scale['option_values']);
          $w  = $mx > 0 ? round($su['score']/$mx*100) : 0;
          $bar = $w >= 60 ? '#4b5563' : ($w >= 35 ? '#e0a800' : '#27ae60');
        ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
          <span style="font-size:.82rem;width:64px;flex-shrink:0;"><?= htmlspecialchars($su['name']) ?></span>
          <span style="flex:1;height:9px;background:var(--border);border-radius:99px;overflow:hidden;">
            <span style="display:block;height:100%;width:<?= $w ?>%;background:<?= $bar ?>;border-radius:99px;"></span>
          </span>
          <span style="font-size:.8rem;font-weight:700;color:var(--muted);width:52px;text-align:right;"><?= $su['score'] ?>/<?= $mx ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($genRes && !empty($genRes['flag'])): ?>
      <div style="margin-bottom:14px;padding:12px 14px;border-radius:8px;font-size:.85rem;line-height:1.5;<?= $genRes['flag']['level']==='urgent' ? 'background:#ececec;border:2px solid #4b5563;color:#4b5563;font-weight:700;' : 'background:#fff8e6;border:1px solid #e0a800;color:#7d5a00;' ?>">
        ⚠️ <?= htmlspecialchars($genRes['flag']['text']) ?>
      </div>
      <?php endif; ?>
      <div class="answer-list">
        <?php
        $opts    = $scale['options'] ?? [];
        $isYesNo = ($scale['layout'] ?? '') === 'yesno';
        foreach ($scale['questions'] as $qi => $question):
          $ansIdx = (int)($detail['answers'][$qi] ?? 0);
          $optLabel = $opts[$ansIdx] ?? $ansIdx;
          // 문항별 실제 점수(item_scores/역채점 반영)
          $one = calculateScore($detail['scale_type'], [$qi => $ansIdx]);
          $itemScore = $one['total'];
        ?>
        <div class="answer-item">
          <span class="ans-num"><?= $qi+1 ?></span>
          <span><?= htmlspecialchars($question) ?></span>
          <span class="ans-score" style="min-width:auto;white-space:nowrap;"><?= htmlspecialchars((string)$optLabel) ?> <span style="opacity:.7;">(<?= $itemScore ?>점)</span></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php elseif ($isPsqi && $psqiBreakdown): ?>
      <div style="margin-bottom:14px;">
        <div style="font-size:.85rem;font-weight:700;color:var(--muted);margin-bottom:8px;">구성요소별 점수 (각 0~3)</div>
        <?php foreach ($psqiBreakdown['components'] as $c):
          $w = round($c['score']/3*100);
          $bar = $c['score']>=2 ? '#4b5563' : ($c['score']==1 ? '#e0a800' : '#27ae60');
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
          $unscored = !empty($item['unscored']);
          if ($item['type'] === 'likert') {
            $opts = $item['options'] ?? $psqiMetaD['freq_options'];
            $disp = ($opts[(int)$raw] ?? $raw) . ($unscored ? '' : " ({$raw}점)");
          } elseif ($item['type'] === 'choice') {
            $opts = $item['options'] ?? [];
            $disp = ($raw === '' || $raw === null) ? '—' : ($opts[(int)$raw] ?? $raw);
          } elseif ($item['type'] === 'text') {
            $disp = ($raw !== '' && $raw !== null) ? $raw : '—';
          } elseif ($item['type'] === 'time') {
            $disp = $raw !== '' ? $raw : '—';
          } else {
            $disp = $raw . ' ' . ($item['unit'] ?? '');
          }
        ?>
        <div class="answer-item">
          <span class="ans-num"><?= $qi+1 ?></span>
          <span style="font-size:.82rem;"><?= htmlspecialchars(mb_strimwidth($item['q'], 0, 60, '…')) ?></span>
          <span class="ans-score" style="min-width:70px;font-size:.8rem;<?= $unscored ? 'background:var(--muted);' : '' ?>"><?= htmlspecialchars($disp) ?></span>
        </div>
        <?php endforeach; ?>
        <?php
        // 5-j 그 밖의 이유(주관식) — 응답 배열의 마지막 요소에 저장됨
        $otherReason = '';
        if (is_array($detail['answers'])) {
          $allAns = array_values($detail['answers']);
          if (count($allAns) > count($psqiMetaD['items'])) $otherReason = (string)end($allAns);
        }
        if (trim($otherReason) !== ''):
        ?>
        <div class="answer-item" style="background:#fff8e6;">
          <span class="ans-num">✎</span>
          <span style="font-size:.82rem;">수면을 방해한 그 밖의 이유 (주관식)</span>
          <span style="font-size:.82rem;font-weight:600;"><?= htmlspecialchars($otherReason) ?></span>
        </div>
        <?php endif; ?>
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
        <?php $isHidden = !empty($detail['deleted_at']); $retq = 'history.php?' . http_build_query($qbase + ['show'=>$showDeleted?'deleted':'']); ?>
        <form method="post" action="delete_assessment.php" style="display:inline;margin-left:auto;" onsubmit="return confirm('<?= $isHidden ? '이 검사 기록을 복구할까요?' : '이 검사 기록을 숨김 처리할까요? (기록은 보존되며 복구할 수 있습니다)' ?>');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
          <input type="hidden" name="action" value="<?= $isHidden ? 'restore' : 'delete' ?>">
          <input type="hidden" name="return" value="<?= htmlspecialchars($retq) ?>">
          <button type="submit" class="btn btn-secondary" style="<?= $isHidden ? 'color:#1e8449;border-color:#1e8449;' : 'color:#4b5563;border-color:#4b5563;' ?>"><?= $isHidden ? '↩ 기록 복구' : '🗑 기록 삭제(숨김)' ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
function closeModal() {
    window.location.href = 'history.php?<?= http_build_query($qbase + ['page'=>$page]) ?>';
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
<script>
// ===== 일괄 선택/삭제 =====
function rowChecks(){ return Array.prototype.slice.call(document.querySelectorAll('.rowchk')); }
function updateSel(){
  const chks = rowChecks();
  const sel = chks.filter(c=>c.checked).length;
  const cnt = document.getElementById('selCount'); if(cnt) cnt.textContent = sel + '개 선택';
  const btn = document.getElementById('bulkBtn'); if(btn) btn.disabled = sel === 0;
  const all = document.getElementById('selAll');
  if(all) all.checked = sel > 0 && sel === chks.length;
}
function toggleAll(box){ rowChecks().forEach(c=>c.checked = box.checked); updateSel(); }

// ===== 생년월일 숫자 키패드 입력 (자동 하이픈) =====
(function(){
  const inp = document.getElementById('birthInput'), hint = document.getElementById('birthHint');
  if(!inp) return;
  function fmt(){
    let d = inp.value.replace(/[^0-9]/g,'').slice(0,8);
    let f = d;
    if(d.length>=5) f = d.slice(0,4)+'-'+d.slice(4);
    if(d.length>=7) f = d.slice(0,4)+'-'+d.slice(4,6)+'-'+d.slice(6);
    inp.value = f;
    if(hint){
      if(d.length===8){
        const y=+d.slice(0,4),m=+d.slice(4,6),dd=+d.slice(6,8),dt=new Date(y,m-1,dd);
        if(dt.getFullYear()===y&&dt.getMonth()===m-1&&dt.getDate()===dd){ hint.textContent=`✅ ${y}년 ${m}월 ${dd}일`; hint.style.color='#1e8449'; }
        else { hint.textContent='❌ 유효하지 않은 날짜'; hint.style.color='#c0392b'; }
      } else if(d.length>0){ hint.textContent=`${d.length}/8자리`; hint.style.color='var(--muted)'; }
      else { hint.textContent='8자리 숫자 입력'; hint.style.color='var(--muted)'; }
    }
  }
  inp.addEventListener('input', fmt); fmt();
})();
updateSel();
</script>
<?php include __DIR__ . '/ui_settings.php'; ?>
</body>
</html>
