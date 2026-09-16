<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scales.php';
requireLogin();
startSession();

// consent.php 통해서 왔는지 확인
$consentData = $_SESSION['consent_data'] ?? null;
if (in_array($_GET['from'] ?? '', ['consent','battery'], true) && empty($consentData['patient_name'])) {
    header('Location: consent.php?step=1'); exit;
}

$scales  = getScales();
$success = '';
$error   = '';

// 연속검사(방문) 모드 데이터 추출
$battery      = (($_GET['from'] ?? '') === 'battery');
$queue        = $consentData['scale_queue'] ?? [];
$qpos         = (int)($consentData['qpos'] ?? 0);
$batteryId    = $consentData['battery_id'] ?? '';
$initPatient  = $consentData['patient_name'] ?? '';
$birthDate    = $consentData['birth_date'] ?? '';
$gender       = $consentData['gender'] ?? '';
$phone        = $consentData['phone'] ?? '';
$batteryTotal = count($queue);
$batteryStep  = $qpos + 1;
$isLastStep   = ($batteryStep >= $batteryTotal);

// 초기 척도 선택
$initScale = 'PHQ-9';
if ($battery && isset($queue[$qpos])) {
    $initScale = $queue[$qpos];
} elseif (isset($_GET['scale']) && array_key_exists($_GET['scale'], $scales)) {
    $initScale = $_GET['scale'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '잘못된 요청입니다.';
    } else {
        $patientName = trim($_POST['patient_name'] ?? '');
        $scaleType   = $_POST['scale_type'] ?? '';
        $answers     = $_POST['answers'] ?? [];
        $memo        = trim($_POST['memo'] ?? '');

        if (empty($patientName)) { $error = '환자 이름이 없습니다.'; }
        elseif (!array_key_exists($scaleType, $scales)) { $error = '척도 오류입니다.'; }
        elseif (count($answers) !== count($scales[$scaleType]['questions'])) { $error = '모든 문항에 응답해주세요.'; }
        else {
            $scored = calculateScore($scaleType, array_values($answers));
            $db     = getDB();
            require_once __DIR__ . '/patient_store.php';
            $patientId = upsertPatient($db, $patientName);

            $stmt = $db->prepare('INSERT INTO assessments (patient_id, scale_type, answers, total_score, result_label, memo, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$patientId, $scaleType, json_encode($answers, JSON_UNESCAPED_UNICODE), $scored['total'], $scored['label'], $memo, $_SESSION['admin_id']]);
            unset($_SESSION['consent_data']);
            $success = '검사 결과가 저장되었습니다.';
        }
    }
}

$csrf = getCsrfToken();

// 연속검사(방문) 모드
$battery    = (($_GET['from'] ?? '') === 'battery');
$forced     = $_GET['scale'] ?? '';
$queue      = $consentData['scale_queue'] ?? [];
$qpos       = (int)($consentData['qpos'] ?? 0);
$batteryId  = $consentData['battery_id'] ?? '';
$birthDate  = $consentData['birth_date'] ?? '';
$gender     = $consentData['gender'] ?? '';
$phone      = $consentData['phone'] ?? '';

// 방문 모드에서는 지정된 한 척도만 렌더 (탭이 하나로 접힘)
if ($battery && $forced && isset($scales[$forced])) {
    $scales = [$forced => $scales[$forced]];
}

$initScale   = ($battery && $forced && isset($scales[$forced])) ? $forced
             : ($consentData['scale_type'] ?? array_key_first($scales));
$initPatient = $consentData['patient_name'] ?? '';

// 연속검사 진행 표시 및 다음 단계 URL
$batteryTotal = count($queue);
$batteryStep  = $qpos + 1; // 1-indexed
$isLastStep   = ($batteryStep >= $batteryTotal);
$nextUrl      = 'run.php?next=1';
$isMale       = ($gender === '남');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?></title>
<script>
(function(){
  var saved = null;
  try { saved = localStorage.getItem('theme'); } catch(e) {}
  if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
})();
</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f8f9fa;--card:#fff;--surface:#f8fafd;--primary:#3b82f6;--primary-dark:#1d4ed8;
  --text:#111827;--muted:#6b7a99;--border:#dce3ef;--tint:#eef2fb;
  --radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);
  --severe:#4b5563;  /* 중한 결과: 붉은색 대신 검정 계열 */
}
html[data-theme="dark"]{
  --bg:#121212;--card:#1e1e24;--surface:#26262e;--primary:#3b82f6;--primary-dark:#60a5fa;
  --text:#f9fafb;--muted:#9ca3af;--border:#33333d;--tint:#1c2333;
  --shadow:0 4px 24px rgba(0,0,0,.35);--severe:#f9fafb;
}
html,body{height:100%;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
body{background:var(--bg);display:flex;flex-direction:column;min-height:100vh;transition:background .2s ease,color .2s ease;}
.header{background:var(--card);color:var(--text);padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;border-bottom:1px solid var(--border);}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:6px;align-items:center;}
.header-nav a{color:var(--muted);text-decoration:none;font-size:.82rem;padding:6px 12px;border-radius:8px;font-weight:500;transition:background .2s,color .2s;}
.header-nav a:hover{background:var(--surface);}
.header-nav a.active{background:var(--tint);color:var(--primary);font-weight:700;}
.admin-badge{font-size:.75rem;color:var(--muted);}
.theme-toggle{background:none;border:1.5px solid var(--border);color:var(--text);width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;font-family:inherit;}

/* ===== 2단 레이아웃: 왼쪽 사이드바(검사 안내) + 오른쪽 문항 ===== */
.layout{flex:1;display:flex;gap:18px;max-width:1080px;width:100%;margin:0 auto;padding:16px;align-items:flex-start;}
.sidebar{width:300px;flex-shrink:0;position:sticky;top:72px;display:flex;flex-direction:column;gap:12px;}
.content{flex:1;min-width:0;display:flex;flex-direction:column;}

/* 사이드바: 검사 이름 + 응답 기간 + 문항박스(안내) */
.sb-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;}
.sb-scale-name{font-size:1.5rem;font-weight:800;color:var(--primary);line-height:1.2;}
.sb-scale-full{font-size:.78rem;color:var(--muted);margin-top:6px;line-height:1.5;}
.sb-period-box{margin-top:14px;background:var(--tint);border:1.5px solid var(--primary);border-radius:12px;padding:12px 14px;text-align:center;}
.sb-period-label{font-size:.72rem;color:var(--muted);font-weight:700;letter-spacing:.03em;}
.sb-period-value{font-size:1.15rem;font-weight:800;color:var(--primary);margin-top:2px;}
.sb-guide-title{font-size:.74rem;font-weight:800;color:var(--muted);margin:16px 0 6px;letter-spacing:.03em;display:flex;align-items:center;gap:6px;}
.sb-guide{background:var(--surface);border-left:4px solid var(--primary);border-radius:0 8px 8px 0;padding:11px 13px;font-size:.86rem;color:var(--text);line-height:1.65;}
.sb-progress{margin-top:14px;}
.sb-progress-label{display:flex;justify-content:space-between;font-size:.76rem;color:var(--muted);margin-bottom:6px;font-weight:600;}
.sb-progress-bar{height:8px;background:var(--border);border-radius:99px;overflow:hidden;}
.sb-progress-fill{height:100%;background:var(--primary);border-radius:99px;transition:width .3s;}

/* 사이드바 환자정보/취소 */
.sb-patient{font-size:.82rem;color:var(--muted);display:flex;flex-wrap:wrap;gap:4px 8px;align-items:center;}
.sb-patient b{color:var(--text);}
.sb-patient .ok{color:#16a34a;font-weight:700;}
.cancel-btn{width:100%;margin-top:4px;padding:11px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface);color:var(--muted);font-family:inherit;font-size:.86rem;font-weight:700;cursor:pointer;transition:all .2s;}
.cancel-btn:hover{border-color:var(--severe);color:var(--severe);}

/* 척도 선택 탭 (단일 검사·다중 선택 시) */
.scale-tabs{display:flex;flex-wrap:wrap;gap:6px;}
.scale-tab{flex:1 1 auto;min-width:70px;padding:8px 6px;border:2px solid var(--border);border-radius:9px;background:var(--surface);cursor:pointer;text-align:center;transition:all .2s;}
.scale-tab.active{border-color:var(--primary);background:var(--tint);}
.scale-tab input{display:none;}
.tab-name{font-weight:700;font-size:.82rem;color:var(--primary);}
.tab-desc{font-size:.68rem;color:var(--muted);margin-top:1px;}

/* 카드 */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:12px;}

/* 문항 */
.scale-section{display:none;}
.scale-section.active{display:block;}
.question-slide{display:none;}
.question-slide.active{display:block;}
.q-card{background:var(--surface);border:1.5px solid var(--border);border-radius:11px;padding:24px 18px;margin-bottom:12px;text-align:center;}
.q-number{font-size:.78rem;font-weight:700;color:var(--primary);margin-bottom:12px;letter-spacing:.05em;}
.q-text{font-size:1.2rem;font-weight:700;line-height:1.6;color:var(--text);margin-bottom:22px;}

/* 리커트 세그먼트 바 (짧은 보기: PHQ/GAD/PSS/PHQ-15/SSD-12) */
.q-options{display:flex;gap:6px;}
.q-option-btn{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:6px;padding:0;border:none;background:none;cursor:pointer;font-family:inherit;-webkit-tap-highlight-color:transparent;}
.seg-block{width:100%;min-height:72px;border-radius:16px;background:var(--seg-bg);display:flex;align-items:center;justify-content:center;transition:transform .15s ease,box-shadow .2s ease;}
.seg-check{color:var(--seg-text);font-size:1.5rem;font-weight:800;opacity:0;transform:scale(.5);transition:all .2s ease;}
.q-option-btn:hover .seg-block{transform:translateY(-2px);}
.q-option-btn.selected .seg-block{box-shadow:0 0 0 3px var(--card),0 0 0 5px var(--seg-bg),0 6px 16px rgba(0,0,0,.2);transform:scale(1.04);}
.q-option-btn.selected .seg-check{opacity:1;transform:scale(1);}
.seg-label{font-size:.88rem;font-weight:600;color:var(--muted);text-align:center;line-height:1.3;}
.q-option-btn.selected .seg-label{color:var(--text);font-weight:800;}

/* 세로 버튼 (긴 보기: BDI-9) */
.q-vert{display:flex;flex-direction:column;gap:10px;}
.vbtn{width:100%;padding:16px 18px;border:2px solid var(--border);border-radius:12px;background:var(--card);cursor:pointer;font-size:1rem;font-weight:600;color:var(--text);transition:all .15s;text-align:left;font-family:inherit;line-height:1.5;-webkit-tap-highlight-color:transparent;}
.vbtn:hover{border-color:var(--primary);background:var(--tint);}
.vbtn.selected{border-color:var(--primary);background:var(--primary);color:#fff;}

/* 예/아니오 큰 버튼 (S-GDpS, K-MDQ) */
.q-yesno{display:flex;gap:12px;}
.ynbtn{flex:1;min-height:96px;border:2.5px solid var(--border);border-radius:16px;background:var(--card);cursor:pointer;font-size:1.5rem;font-weight:800;color:var(--text);transition:all .15s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:10px;-webkit-tap-highlight-color:transparent;}
.ynbtn .yn-ico{font-size:1.7rem;}
.ynbtn:hover{border-color:var(--primary);background:var(--tint);}
.ynbtn.selected{border-color:var(--primary);background:var(--primary);color:#fff;}

/* 완료 화면 */
.complete-screen{display:none;text-align:center;padding:10px 0;}
.complete-screen.active{display:block;}
.complete-score{font-size:2.8rem;font-weight:800;color:var(--primary);margin-bottom:4px;}
.complete-label{font-size:1.1rem;font-weight:700;color:var(--primary);margin-bottom:14px;}
.flag-note{display:none;text-align:left;border-radius:10px;padding:14px 16px;margin-bottom:14px;font-size:.9rem;line-height:1.6;}
.flag-note.warn{background:#fff8e6;border:2px solid #e0a800;color:#7d5a00;}
.flag-note.urgent{background:#ececec;border:2px solid var(--severe);color:var(--severe);font-weight:700;}
textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.9rem;color:var(--text);background:var(--surface);outline:none;font-family:inherit;resize:vertical;min-height:70px;}
textarea:focus{border-color:var(--primary);}

/* 네비 버튼 */
.nav-btns{display:flex;gap:10px;margin-top:8px;}
.btn{padding:14px 0;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;flex:1;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--surface);color:var(--text);border:1.5px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.btn:disabled{opacity:.4;cursor:not-allowed;}
.save-status-box{min-height:28px;padding:6px 0;font-size:.9rem;margin-bottom:8px;}

/* 돋보기 / 글자 크기 */
.zoom-btn,.float-btn{position:fixed;right:20px;width:52px;height:52px;border-radius:50%;background:var(--primary);color:#fff;border:none;font-size:1.4rem;cursor:pointer;box-shadow:0 4px 12px rgba(59,108,183,.4);display:flex;align-items:center;justify-content:center;z-index:999;transition:all .2s;text-decoration:none;}
.zoom-btn{bottom:20px;}
.zoom-btn:hover,.float-btn:hover{background:var(--primary-dark);transform:scale(1.1);}
.zoom-tooltip{position:fixed;bottom:80px;right:14px;background:#1a2236;color:#fff;border-radius:8px;padding:8px 12px;font-size:.8rem;display:none;white-space:nowrap;z-index:999;}
body.font-lg .q-text{font-size:1.5rem !important;}
body.font-lg .seg-label{font-size:1.05rem !important;font-weight:700 !important;}
body.font-lg .seg-block{min-height:84px !important;}
body.font-lg .vbtn{font-size:1.15rem !important;padding:18px 20px !important;}
body.font-lg .ynbtn{font-size:1.7rem !important;min-height:110px !important;}
body.font-lg .sb-guide{font-size:1rem !important;}
body.font-lg .sb-period-value{font-size:1.3rem !important;}
body.font-lg .q-number{font-size:.9rem !important;}

/* 성공 */
.alert-success{padding:12px 16px;border-radius:8px;font-size:.875rem;background:#eafaf1;border:1px solid #a9dfbf;color:#1e8449;margin-bottom:12px;}
.alert-error{padding:12px 16px;border-radius:8px;font-size:.875rem;background:#fdedec;border:1px solid #f1948a;color:#c0392b;margin-bottom:12px;}
.result-actions{display:flex;gap:10px;flex-wrap:wrap;}
.result-actions a{flex:1;min-width:120px;padding:12px;border-radius:8px;text-align:center;text-decoration:none;font-weight:700;font-size:.875rem;}
.btn-new{background:var(--primary);color:#fff;}
.btn-hist{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}

/* 취소 확인 모달 */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--card);border-radius:16px;max-width:380px;width:100%;padding:24px;box-shadow:0 12px 48px rgba(0,0,0,.3);text-align:center;}
.modal-box h3{font-size:1.1rem;font-weight:800;margin-bottom:10px;}
.modal-box p{font-size:.9rem;color:var(--muted);line-height:1.6;margin-bottom:20px;}
.modal-btns{display:flex;gap:10px;}

/* ===== 반응형: 태블릿·모바일에서 사이드바를 상단 카드로 ===== */
@media(max-width:860px){
  .layout{flex-direction:column;gap:12px;padding:12px;}
  .sidebar{width:100%;position:static;top:auto;}
  .sb-scale-name{font-size:1.3rem;}
}
@media(min-width:861px){
  .q-options{gap:8px;}
  .seg-block{min-height:88px;}
}
@media(max-width:380px){
  .q-options{gap:4px;}
  .seg-block{min-height:64px;border-radius:12px;}
  .seg-label{font-size:.76rem;}
  .q-yesno{gap:8px;}
  .ynbtn{min-height:80px;font-size:1.3rem;}
}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
</head>
    <?php include __DIR__ . '/ui_settings.php'; ?>
<body>
<header class="header">
  <h1><a href="consent.php" style="color:var(--text);text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php" class="active">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="graph.php">그래프</a>
    <a href="admin.php">설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">로그아웃</a>
    <button type="button" class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="화면 테마 전환">🌙</button>
  </nav>
</header>

<?php if ($success): ?>
<div class="layout"><div style="flex:1;">
<div class="alert-success"><?= htmlspecialchars($success) ?> — <a href="history.php" style="color:#1e8449;font-weight:700;">이력 보기 →</a></div>
<div class="result-actions">
  <a href="consent.php?step=1" class="btn-new result-actions">새 검사 입력</a>
  <a href="history.php" class="btn-hist result-actions">이력 조회</a>
  <a href="graph.php" class="btn-hist result-actions">그래프 보기</a>
</div>
</div></div>

<?php elseif ($error): ?>
<div class="layout"><div style="flex:1;"><div class="alert-error"><?= htmlspecialchars($error) ?></div>
<a href="consent.php?step=1" class="btn-new result-actions" style="display:inline-block;padding:12px 20px;border-radius:8px;text-decoration:none;">← 검사 다시 시작</a></div></div>

<?php else: ?>

<div class="layout">
  <!-- ===== 왼쪽 사이드바: 검사 이름 + 응답 기간 + 문항박스(안내) ===== -->
  <aside class="sidebar">
    <?php if (!$battery && count($scales) > 1): ?>
    <div class="sb-card" style="padding:12px;">
      <div class="sb-guide-title">📋 검사 선택</div>
      <div class="scale-tabs">
        <?php foreach ($scales as $key => $scale): ?>
        <label class="scale-tab <?= $key === $initScale ? 'active' : '' ?>">
          <input type="radio" name="scale_type_ui" value="<?= $key ?>"
                 <?= $key === $initScale ? 'checked' : '' ?> onchange="switchScale('<?= $key ?>')">
          <div class="tab-name"><?= $key ?></div>
          <div class="tab-desc"><?= htmlspecialchars($scale['category'] ?? '') ?></div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="sb-card">
      <div class="sb-scale-name" id="sb-name"><?= htmlspecialchars($initScale) ?></div>
      <div class="sb-scale-full" id="sb-full"></div>
      <div class="sb-period-box">
        <div class="sb-period-label">응답 기간</div>
        <div class="sb-period-value" id="sb-period">—</div>
      </div>
      <div class="sb-guide-title">📝 문항 안내</div>
      <div class="sb-guide" id="sb-guide">—</div>
      <div class="sb-progress">
        <div class="sb-progress-label">
          <span id="sb-ptxt">1 / 1</span>
          <span id="sb-ppct">0%</span>
        </div>
        <div class="sb-progress-bar"><div class="sb-progress-fill" id="sb-pfill" style="width:0%"></div></div>
      </div>
    </div>

    <div class="sb-card">
      <?php if ($initPatient):
        require_once __DIR__ . '/patient_store.php';
        $agev = ageFromBirth($birthDate ?? null);
      ?>
      <div class="sb-patient">
        <b><?= htmlspecialchars($initPatient) ?></b>
        <?php if (($gender ?? '') || $agev !== null): ?>
          <span><?= htmlspecialchars($gender ?? '') ?><?= $agev !== null ? ' ' . $agev . '세' : '' ?></span>
        <?php endif; ?>
        <?php if ($battery && $batteryTotal > 1): ?>
          <span>· 검사 <?= $batteryStep ?>/<?= $batteryTotal ?></span>
        <?php endif; ?>
        <span class="ok">✓ 동의</span>
      </div>
      <?php endif; ?>
      <button type="button" class="cancel-btn" id="cancelBtn" onclick="openCancel()">
        <?= $battery ? ($isLastStep ? '이 검사 취소하고 결과 보기 →' : '이 검사 취소하고 다음으로 →') : '검사 취소' ?>
      </button>
    </div>
  </aside>

  <!-- ===== 오른쪽: 문항 ===== -->
  <div class="content">
  <?php foreach ($scales as $key => $scale):
    $layout   = $scale['layout'] ?? 'segment';
    $opts     = $scale['options'];
    $optCount = count($opts);
    // 세그먼트 색상 그라데이션 (연한 파랑 → 진한 네이비) — 응답 강도 표현(불안 유발 X)
    if ($optCount >= 5) {
        $segColors = ['#E0F2FE','#BAE6FD','#3B82F6','#1D4ED8','#0F172A'];
        $segTextDark = [true,true,true,false,false];
    } elseif ($optCount === 4) {
        $segColors = ['#DBEAFE','#93C5FD','#3B82F6','#1D4ED8'];
        $segTextDark = [true,true,false,false];
    } else {
        $segColors = ['#DBEAFE','#60A5FA','#1D4ED8'];
        $segTextDark = [true,false,false];
    }
  ?>
  <div class="scale-section <?= $key === $initScale ? 'active' : '' ?>" id="section-<?= $key ?>">
    <div class="card">
    <?php foreach ($scale['questions'] as $qi => $question):
      $isFemaleOnly = in_array($qi, $scale['female_only_items'] ?? [], true);
      $qOpts = $scale['question_options'][$qi] ?? $opts;
    ?>
    <div class="question-slide <?= $qi===0?'active':'' ?>" id="slide-<?= $key ?>-<?= $qi ?>"
         data-female="<?= $isFemaleOnly ? '1' : '0' ?>">
      <div class="q-card">
        <div class="q-number">문항 <?= $qi+1 ?> / <?= count($scale['questions']) ?></div>
        <div class="q-text"><?= htmlspecialchars($question) ?></div>

        <?php if ($layout === 'yesno'): ?>
          <div class="q-yesno">
            <?php foreach ($qOpts as $oi => $optLabel): ?>
            <button type="button" class="ynbtn" data-scale="<?= $key ?>" data-qi="<?= $qi ?>" data-idx="<?= $oi ?>"
                    onclick="selectAnswer(this)">
              <span class="yn-ico"><?= $oi === 0 ? '⭕' : '❌' ?></span><span><?= htmlspecialchars($optLabel) ?></span>
            </button>
            <?php endforeach; ?>
          </div>

        <?php elseif ($layout === 'vertical'): ?>
          <div class="q-vert">
            <?php foreach ($qOpts as $oi => $optLabel): ?>
            <button type="button" class="vbtn" data-scale="<?= $key ?>" data-qi="<?= $qi ?>" data-idx="<?= $oi ?>"
                    onclick="selectAnswer(this)"><?= htmlspecialchars($optLabel) ?></button>
            <?php endforeach; ?>
          </div>

        <?php else: /* segment */ ?>
          <div class="q-options">
            <?php foreach ($qOpts as $oi => $optLabel):
              $segBg   = $segColors[$oi] ?? '#3B82F6';
              $segText = ($segTextDark[$oi] ?? false) ? '#0f172a' : '#fff';
            ?>
            <button type="button" class="q-option-btn"
                    style="--seg-bg:<?= $segBg ?>;--seg-text:<?= $segText ?>;"
                    data-scale="<?= $key ?>" data-qi="<?= $qi ?>" data-idx="<?= $oi ?>"
                    onclick="selectAnswer(this)">
              <span class="seg-block"><span class="seg-check">✓</span></span>
              <span class="seg-label"><?= htmlspecialchars($optLabel) ?></span>
            </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- 완료 -->
    <div class="complete-screen" id="complete-<?= $key ?>">
      <div class="flag-note" id="flag-<?= $key ?>"></div>
      <div class="complete-score" id="cscore-<?= $key ?>">—</div>
      <div class="complete-label" id="clabel-<?= $key ?>">—</div>
      <div id="save-status-<?= $key ?>" class="save-status-box"></div>

      <div class="card" style="text-align:left;margin-bottom:10px;display:none;" id="memo-card-<?= $key ?>">
        <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:6px;">메모</label>
        <textarea id="memo-<?= $key ?>" placeholder="임상 소견, 특이사항 등"></textarea>
        <button type="button" class="btn btn-secondary" style="margin-top:8px;width:100%;font-size:.85rem;"
                onclick="saveWithMemo('<?= $key ?>')">메모 포함하여 다시 저장</button>
      </div>
      <div id="battery-next-<?= $key ?>" style="margin-top:6px;"></div>
    </div>

    <!-- 이전/다음 -->
    <div class="nav-btns" id="navbtns-<?= $key ?>">
      <button type="button" class="btn btn-secondary" id="btn-prev-<?= $key ?>" onclick="prevQ('<?= $key ?>')" disabled>← 이전</button>
      <button type="button" class="btn btn-primary"   id="btn-next-<?= $key ?>" onclick="nextQ('<?= $key ?>')" disabled>다음 →</button>
    </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- 숨겨진 제출 폼 -->
  <form method="post" action="index.php" id="submitForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="patient_name" id="f_patient" value="<?= htmlspecialchars($initPatient) ?>">
    <input type="hidden" name="scale_type"   id="f_scale">
    <input type="hidden" name="memo"         id="f_memo">
    <div id="f_answers"></div>
  </form>
  </div>
</div>

<!-- 취소 확인 모달 -->
<div class="modal-overlay" id="cancelModal" onclick="if(event.target===this)closeCancel()">
  <div class="modal-box">
    <h3 id="cancelTitle">검사를 취소할까요?</h3>
    <p id="cancelMsg">현재 검사의 응답은 저장되지 않습니다.</p>
    <div class="modal-btns">
      <button type="button" class="btn btn-secondary" onclick="closeCancel()">계속 검사</button>
      <button type="button" class="btn btn-primary" id="cancelConfirm">취소하고 진행</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const BATTERY     = <?= $battery ? 'true' : 'false' ?>;
const BATTERY_LAST= <?= $isLastStep ? 'true' : 'false' ?>;
const NEXT_URL    = <?= json_encode($nextUrl) ?>;
const QUEUE       = <?= json_encode($queue) ?>;
const QPOS        = <?= $qpos ?>;
const NEXT_SCALE  = QUEUE[QPOS + 1] || '';
const IS_MALE     = <?= $isMale ? 'true' : 'false' ?>;
const PT = {
  birth:   <?= json_encode($birthDate) ?>,
  gender:  <?= json_encode($gender) ?>,
  phone:   <?= json_encode($phone) ?>,
  battery: <?= json_encode($batteryId) ?>
};
const COLOR = {
  green:{solid:'#27ae60',text:'#1b7a43'}, yellow:{solid:'#e0a800',text:'#8a6100'},
  orange:{solid:'#e67e22',text:'#9a4a12'}, black:{solid:'#4b5563',text:'var(--severe)'},
  red:{solid:'#4b5563',text:'var(--severe)'}, darkred:{solid:'#4b5563',text:'var(--severe)'}
};

const SCALEMETA = {};
const state = {};
<?php foreach ($scales as $key => $scale):
  // 남성 환자면 여성전용 문항 제외한 표시 순서
  $order = [];
  foreach ($scale['questions'] as $qi => $q) {
      if ($isMale && in_array($qi, $scale['female_only_items'] ?? [], true)) continue;
      $order[] = $qi;
  }
?>
SCALEMETA['<?= $key ?>'] = {
  name: '<?= $key ?>',
  full: <?= json_encode($scale['full_name'] ?? '') ?>,
  period: <?= json_encode($scale['period'] ?? '') ?>,
  instruction: <?= json_encode($scale['instruction'] ?? '') ?>,
  layout: <?= json_encode($scale['layout'] ?? 'segment') ?>,
  qCount: <?= count($scale['questions']) ?>,
  order: <?= json_encode($order) ?>,
  femaleOnly: <?= json_encode(array_values($scale['female_only_items'] ?? [])) ?>,
  optionValues: <?= json_encode($scale['option_values'] ?? array_map('intval', array_keys($scale['options']))) ?>,
  itemScores: <?= json_encode($scale['item_scores'] ?? null) ?>,
  reverseItems: <?= json_encode($scale['reverse_items'] ?? []) ?>,
  scoring: <?= json_encode($scale['scoring']) ?>,
  flag: <?= json_encode(isset($scale['flag_item']) ? ['item'=>$scale['flag_item'],'warn'=>$scale['flag_warn'] ?? 999,'urgent'=>$scale['flag_urgent'] ?? 999,'text'=>$scale['flag_text'] ?? ''] : null) ?>,
  phq9: <?= $key === 'PHQ-9' ? 'true' : 'false' ?>
};
state['<?= $key ?>'] = { pos:0, answers:{}, advancing:false, completed:false, savedId:null };
<?php endforeach; ?>

// 시계 (사이드바 환자정보엔 표시 안 함; 유지 목적)
function toggleTheme(){
  const html = document.documentElement, btn = document.getElementById('themeToggle');
  const dark = html.getAttribute('data-theme') === 'dark';
  if (dark) { html.removeAttribute('data-theme'); if(btn) btn.textContent='🌙'; }
  else { html.setAttribute('data-theme','dark'); if(btn) btn.textContent='☀️'; }
  try { localStorage.setItem('theme', dark?'light':'dark'); } catch(e){}
}
if (document.getElementById('themeToggle') && document.documentElement.getAttribute('data-theme')==='dark')
  document.getElementById('themeToggle').textContent='☀️';

let activeScale = '<?= $initScale ?>';

function switchScale(key){
  activeScale = key;
  document.querySelectorAll('.scale-section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.scale-tab').forEach(t=>t.classList.remove('active'));
  const sec = document.getElementById('section-'+key);
  if (sec) sec.classList.add('active');
  document.querySelectorAll('.scale-tab').forEach(t=>{ if(t.querySelector('input') && t.querySelector('input').value===key) t.classList.add('active'); });
  // 사이드바 갱신
  const m = SCALEMETA[key];
  document.getElementById('sb-name').textContent = m.name;
  document.getElementById('sb-full').textContent = m.full;
  document.getElementById('sb-period').textContent = m.period || '—';
  document.getElementById('sb-guide').textContent = m.instruction || '—';
  // 상태 초기화
  const s = state[key];
  s.pos=0; s.answers={}; s.completed=false; s.advancing=false;
  showSlide(key,0); updateProg(key);
}

function currentQi(key){ return SCALEMETA[key].order[state[key].pos]; }

function showSlide(key,pos){
  const m = SCALEMETA[key], s = state[key];
  s.pos = pos;
  document.querySelectorAll(`#section-${key} .question-slide`).forEach(e=>e.classList.remove('active'));
  document.getElementById(`complete-${key}`).classList.remove('active');
  document.getElementById(`navbtns-${key}`).style.display='flex';
  const qi = m.order[pos];
  const slide = document.getElementById(`slide-${key}-${qi}`);
  if (slide){
    slide.classList.add('active');
    if (s.answers[qi]!==undefined){
      slide.querySelectorAll('[data-idx]').forEach(b=>b.classList.toggle('selected', parseInt(b.dataset.idx)===s.answers[qi]));
    }
  }
  const pv=document.getElementById(`btn-prev-${key}`), nv=document.getElementById(`btn-next-${key}`);
  pv.disabled = pos===0;
  nv.disabled = s.answers[qi]===undefined;
  nv.textContent = pos===m.order.length-1 ? '완료 →' : '다음 →';
  updateProg(key);
}

function selectAnswer(btn){
  const key=btn.dataset.scale, qi=parseInt(btn.dataset.qi), idx=parseInt(btn.dataset.idx);
  const s=state[key];
  if (s.advancing) return;
  s.advancing = true;
  document.querySelectorAll(`#slide-${key}-${qi} [data-idx]`).forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
  s.answers[qi]=idx;
  document.getElementById(`btn-next-${key}`).disabled=false;
  updateProg(key);
  setTimeout(()=>{ s.advancing=false; nextQ(key); },260);
}

function prevQ(key){ const s=state[key]; if(s.pos>0) showSlide(key,s.pos-1); }
function nextQ(key){
  const m=SCALEMETA[key], s=state[key];
  if (s.answers[m.order[s.pos]]===undefined) return;
  if (s.pos < m.order.length-1) showSlide(key,s.pos+1);
  else showComplete(key);
}
function updateProg(key){
  const m=SCALEMETA[key], s=state[key];
  const answered=Object.keys(s.answers).length, tot=m.order.length;
  const pct=Math.round(answered/tot*100);
  document.getElementById('sb-pfill').style.width=pct+'%';
  document.getElementById('sb-ptxt').textContent=`${s.pos+1} / ${tot}`;
  document.getElementById('sb-ppct').textContent=pct+'%';
}

// 점수 계산 (PHP calculateScore 와 동일 규칙)
function scoreItem(m, qi, idx){
  if (m.itemScores && m.itemScores[qi]) return (m.itemScores[qi][idx] ?? 0);
  const val = m.optionValues[idx] !== undefined ? m.optionValues[idx] : idx;
  if (m.reverseItems && m.reverseItems.includes(qi)){
    const mx = Math.max.apply(null, m.optionValues);
    return mx - val;
  }
  return val;
}

function buildFullAnswers(key){
  // 전체 문항 길이의 배열(선택 인덱스). 남성 여성전용 문항은 0.
  const m=SCALEMETA[key], s=state[key];
  const a=[];
  for(let qi=0; qi<m.qCount; qi++){
    if (s.answers[qi]!==undefined) a.push(s.answers[qi]);
    else a.push(0); // 미표시(여성전용) 또는 미응답 → 0 인덱스
  }
  return a;
}

function showComplete(key){
  const m=SCALEMETA[key], s=state[key];
  if (s.completed) return;
  s.completed = true;
  let total=0;
  for (const qi of m.order){
    const idx = s.answers[qi]; if (idx===undefined) continue;
    total += scoreItem(m, qi, idx);
  }
  let label='', color='green';
  for(const r of m.scoring){ if(total>=r.min&&total<=r.max){ label=r.label; color=r.color; break; } }
  const c = COLOR[color] || {solid:'#3b82f6',text:'#3b82f6'};

  const scoreEl=document.getElementById(`cscore-${key}`), labelEl=document.getElementById(`clabel-${key}`);
  if (typeof tossCountUp === 'function') tossCountUp(scoreEl, total, {suffix:'점'});
  else scoreEl.textContent = total+'점';
  labelEl.textContent = BATTERY ? '검사가 완료되었습니다' : label;
  // 색상: 연속검사(환자 직접 응답) 시엔 중립(파랑), 단독검사 시 밴드색(중한 결과는 검정)
  scoreEl.style.color = BATTERY ? 'var(--primary)' : c.text;
  labelEl.style.color = BATTERY ? 'var(--primary)' : c.text;

  // 안전 플래그 (BDI-9 등) + PHQ-9 9번 문항 — 연속검사 중엔 환자에게 표시하지 않음
  const flagEl=document.getElementById(`flag-${key}`);
  flagEl.style.display='none';
  if (!BATTERY){
    let fl=null;
    if (m.flag){
      const fidx = s.answers[m.flag.item];
      if (fidx!==undefined){
        if (fidx>=m.flag.urgent) fl={level:'urgent', text:`${m.flag.text}에서 높은 응답(${fidx}점) — 즉각적인 안전 평가가 필요합니다.`};
        else if (fidx>=m.flag.warn) fl={level:'warn', text:`${m.flag.text}에 응답이 있었습니다(${fidx}점) — 추가적인 임상 판단이 필요합니다.`};
      }
    }
    if (m.phq9){
      const q9 = s.answers[8] || 0;
      if (q9>=2) fl={level:'urgent', text:`9번 문항(자해·자살 사고)에서 높은 점수(${q9}점)가 나왔습니다. 즉각적인 임상적 평가와 안전 확인이 필요합니다.`};
      else if (q9>=1) fl={level:'warn', text:`9번 문항(자해·자살 사고)에 응답이 있었습니다(${q9}점). 추가적인 임상적 판단이 필요합니다.`};
    }
    if (fl){
      flagEl.style.display='block';
      flagEl.className = 'flag-note '+fl.level;
      flagEl.innerHTML = (fl.level==='urgent'?'⚠️ <strong>즉각적 주의 필요</strong><br>':'⚠️ <strong>주의</strong><br>') + fl.text;
    }
  }

  document.querySelectorAll(`#section-${key} .question-slide`).forEach(e=>e.classList.remove('active'));
  document.getElementById(`navbtns-${key}`).style.display='none';
  document.getElementById(`complete-${key}`).classList.add('active');
  setTimeout(()=>autoSave(key), 900);
}

// ===== 저장 (기존과 동일) =====
async function doSave(key, memo){
  const patient=document.getElementById('f_patient').value.trim();
  if(!patient){ window.location='consent.php?step=1'; return; }
  const answers = buildFullAnswers(key);
  const statusEl=document.getElementById(`save-status-${key}`);
  statusEl.innerHTML='<span style="color:var(--muted)">💾 저장 중...</span>';
  const csrfEl=document.querySelector('input[name="csrf_token"]');
  const csrf=csrfEl?csrfEl.value:'';
  try{
    const res=await fetch('save_assessment.php',{
      method:'POST', headers:{'Content-Type':'application/json; charset=utf-8'},
      body:JSON.stringify(Object.assign({csrf_token:csrf, patient_name:patient, scale_type:key, answers:answers, memo:memo||'', assessment_id:state[key].savedId||null}, BATTERY?{birth_date:PT.birth,gender:PT.gender,phone:PT.phone,battery_id:PT.battery}:{}))
    });
    const text=await res.text(); let data;
    try{ data=JSON.parse(text); }catch(e){
      statusEl.innerHTML=`<span style="color:var(--severe);font-weight:700;">❌ 서버 응답 오류</span> <button onclick="autoSave('${key}')" style="border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
      console.error('Response:',text); return;
    }
    if(data.success){
      statusEl.innerHTML='<span style="color:#27ae60;font-weight:700;">✅ 저장 완료</span>';
      if(data.id) state[key].savedId=data.id;
      if(BATTERY){
        const nx=document.getElementById(`battery-next-${key}`);
        if(nx) nx.innerHTML=`<a href="${NEXT_URL}" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;padding:14px;">${BATTERY_LAST?'검사 마치고 결과 보기 →':('다음 검사 진행 ('+NEXT_SCALE+') →')}</a>`;
      } else {
        const mc=document.getElementById(`memo-card-${key}`); if(mc) mc.style.display='block';
      }
    } else {
      statusEl.innerHTML=`<span style="color:var(--severe);font-weight:700;">❌ 저장 실패: ${data.message}</span> <button onclick="autoSave('${key}')" style="border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
    }
  }catch(e){
    statusEl.innerHTML=`<span style="color:var(--severe);font-weight:700;">❌ 네트워크 오류 — 연결을 확인해주세요</span> <button onclick="autoSave('${key}')" style="border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
    console.error(e);
  }
}
function autoSave(key){ doSave(key,''); }
function saveWithMemo(key){ doSave(key, document.getElementById(`memo-${key}`).value); }

// ===== 검사 취소/건너뛰기 =====
function openCancel(){
  const modal=document.getElementById('cancelModal');
  const title=document.getElementById('cancelTitle'), msg=document.getElementById('cancelMsg'), btn=document.getElementById('cancelConfirm');
  if(BATTERY){
    title.textContent = BATTERY_LAST ? '이 검사를 취소할까요?' : '이 검사를 건너뛸까요?';
    msg.textContent = BATTERY_LAST ? '현재 검사는 저장되지 않고 결과 화면으로 이동합니다.' : ('현재 검사는 저장되지 않고 다음 검사'+(NEXT_SCALE?(' ('+NEXT_SCALE+')'):'')+'로 넘어갑니다.');
    btn.textContent = BATTERY_LAST ? '취소하고 결과 보기' : '건너뛰고 다음으로';
    btn.onclick = ()=>{ window.location = NEXT_URL; };
  } else {
    title.textContent = '검사를 취소할까요?';
    msg.textContent = '현재 검사의 응답은 저장되지 않습니다.';
    btn.textContent = '취소하고 나가기';
    btn.onclick = ()=>{ window.location = 'consent.php'; };
  }
  modal.classList.add('open');
}
function closeCancel(){ document.getElementById('cancelModal').classList.remove('open'); }

// 초기 척도
switchScale('<?= $initScale ?>');
</script>

<?php if (!$success && !$error): ?>
<div class="zoom-tooltip" id="zoomTooltip">글자 크게/작게</div>
<button class="zoom-btn" id="zoomBtn" onclick="toggleZoom()" title="글자 크기 조절">🔍</button>
<script>
function toggleZoom(){
  const body=document.body, btn=document.getElementById('zoomBtn'), tip=document.getElementById('zoomTooltip');
  if(body.classList.contains('font-lg')){ body.classList.remove('font-lg'); btn.textContent='🔍'; tip.textContent='글자 크게'; }
  else{ body.classList.add('font-lg'); btn.textContent='🔎'; tip.textContent='글자 작게'; }
  tip.style.display='block'; setTimeout(()=>tip.style.display='none',1500);
}
</script>
<?php endif; ?>
</body>
</html>
