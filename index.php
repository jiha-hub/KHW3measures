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
  // 기본값은 항상 라이트 모드 — 사용자가 토글로 명시적으로 다크를 선택했을 때만 전환
  if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
})();
</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f8f9fa;--card:#fff;--surface:#f8fafd;--primary:#3b82f6;--primary-dark:#1d4ed8;
  --text:#111827;--muted:#6b7a99;--border:#dce3ef;--tint:#eef2fb;
  --radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);
}
html[data-theme="dark"]{
  --bg:#121212;--card:#1e1e24;--surface:#26262e;--primary:#3b82f6;--primary-dark:#60a5fa;
  --text:#f9fafb;--muted:#9ca3af;--border:#33333d;--tint:#1c2333;
  --shadow:0 4px 24px rgba(0,0,0,.35);
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

/* 레이아웃: 화면 꽉 채우기 */
.main{flex:1;display:flex;flex-direction:column;padding:16px;max-width:700px;width:100%;margin:0 auto;}

/* 상단 정보 바: 한 줄 인라인 배지 */
.info-bar{font-size:.78rem;color:var(--muted);padding:8px 2px 14px;display:flex;flex-wrap:wrap;gap:4px 8px;align-items:center;}
.info-bar b{color:var(--text);font-weight:700;}
.info-bar .sep{color:var(--border);}
.info-bar .ok{color:#16a34a;font-weight:700;}

/* 카드 */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:12px;}

/* 척도 탭 */
.scale-tabs{display:flex;gap:8px;margin-bottom:16px;}
.scale-tab{flex:1;padding:10px;border:2px solid var(--border);border-radius:9px;background:var(--surface);cursor:pointer;text-align:center;transition:all .2s;}
.scale-tab.active{border-color:var(--primary);background:var(--tint);}
.scale-tab input{display:none;}
.tab-name{font-weight:700;font-size:.95rem;color:var(--primary);}
.tab-desc{font-size:.72rem;color:var(--muted);margin-top:2px;}

/* 진행 바 */
.progress-wrap{margin-bottom:16px;}
.progress-label{display:flex;justify-content:space-between;font-size:.78rem;color:var(--muted);margin-bottom:6px;}
.progress-bar{height:5px;background:var(--border);border-radius:99px;overflow:hidden;}
.progress-fill{height:100%;background:var(--primary);border-radius:99px;transition:width .3s;}

/* 문항 */
.scale-section{display:none;}
.scale-section.active{display:block;}
.question-slide{display:none;}
.question-slide.active{display:block;}
.instruction{background:var(--tint);border-left:4px solid var(--primary);padding:10px 14px;border-radius:0 8px 8px 0;font-size:.85rem;color:var(--text);margin-bottom:14px;line-height:1.6;}
.q-card{background:var(--surface);border:1.5px solid var(--border);border-radius:11px;padding:20px 18px;margin-bottom:12px;text-align:center;}
.q-number{font-size:.75rem;font-weight:700;color:var(--primary);margin-bottom:10px;letter-spacing:.05em;}
.q-text{font-size:1.15rem;font-weight:700;line-height:1.6;color:var(--text);margin-bottom:20px;}

/* 리커트 세그먼트 바 */
.q-options{display:flex;gap:6px;}
.q-option-btn{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:6px;padding:0;border:none;background:none;cursor:pointer;font-family:inherit;-webkit-tap-highlight-color:transparent;}
.seg-block{width:100%;min-height:72px;border-radius:16px;background:var(--seg-bg);display:flex;align-items:center;justify-content:center;transition:transform .15s ease,box-shadow .2s ease;}
.seg-check{color:var(--seg-text);font-size:1.5rem;font-weight:800;opacity:0;transform:scale(.5);transition:all .2s ease;}
.q-option-btn:hover .seg-block{transform:translateY(-2px);}
.q-option-btn.selected .seg-block{box-shadow:0 0 0 3px var(--card),0 0 0 5px var(--seg-bg),0 6px 16px rgba(0,0,0,.2);transform:scale(1.04);}
.q-option-btn.selected .seg-check{opacity:1;transform:scale(1);}
.seg-label{font-size:.88rem;font-weight:600;color:var(--muted);text-align:center;line-height:1.3;}
.q-option-btn.selected .seg-label{color:var(--text);font-weight:800;}

/* 완료 화면 */
.complete-screen{display:none;text-align:center;padding:10px 0;}
.complete-screen.active{display:block;}
.complete-icon{font-size:2.5rem;margin-bottom:10px;}
.complete-score{font-size:2.8rem;font-weight:800;color:var(--primary);margin-bottom:4px;}
.complete-label{font-size:1rem;font-weight:700;color:var(--primary);margin-bottom:18px;}
textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem;color:var(--text);background:var(--surface);outline:none;font-family:inherit;resize:vertical;min-height:70px;}
textarea:focus{border-color:var(--primary);}

/* 네비 버튼 */
.nav-btns{display:flex;gap:10px;margin-top:8px;}
.btn{padding:12px 0;border-radius:12px;font-size:.9rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;flex:1;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--surface);color:var(--text);border:1.5px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.btn:disabled{opacity:.4;cursor:not-allowed;}
.save-status-box{min-height:28px;padding:6px 0;font-size:.875rem;margin-bottom:8px;}

/* 돋보기 / 글자 크기 */
.zoom-btn,.float-btn{position:fixed;right:20px;width:48px;height:48px;border-radius:50%;background:var(--primary);color:#fff;border:none;font-size:1.3rem;cursor:pointer;box-shadow:0 4px 12px rgba(59,108,183,.4);display:flex;align-items:center;justify-content:center;z-index:999;transition:all .2s;text-decoration:none;}
.zoom-btn{bottom:20px;}
.zoom-btn:hover,.float-btn:hover{background:var(--primary-dark);transform:scale(1.1);}
.zoom-tooltip{position:fixed;bottom:76px;right:14px;background:#1a2236;color:#fff;border-radius:8px;padding:8px 12px;font-size:.8rem;display:none;white-space:nowrap;z-index:999;}
body.font-lg .q-text{font-size:1.4rem !important;}
body.font-lg .seg-label{font-size:1.05rem !important;font-weight:700 !important;}
body.font-lg .seg-block{min-height:84px !important;}
body.font-lg .instruction{font-size:1rem !important;}
body.font-lg .q-number{font-size:.9rem !important;}

/* 성공 */
.alert-success{padding:12px 16px;border-radius:8px;font-size:.875rem;background:#eafaf1;border:1px solid #a9dfbf;color:#1e8449;margin-bottom:12px;}
.alert-error{padding:12px 16px;border-radius:8px;font-size:.875rem;background:#fdedec;border:1px solid #f1948a;color:#c0392b;margin-bottom:12px;}
.result-actions{display:flex;gap:10px;flex-wrap:wrap;}
.result-actions a{flex:1;min-width:120px;padding:12px;border-radius:8px;text-align:center;text-decoration:none;font-weight:700;font-size:.875rem;}
.btn-new{background:var(--primary);color:#fff;}
.btn-hist{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}

@media(min-width:768px){
  .main{padding:20px 24px;}
  .seg-block{min-height:88px;}
  .seg-label{font-size:.95rem;}
}
@media(max-width:380px){
  .q-options{gap:4px;}
  .seg-block{min-height:64px;border-radius:12px;}
  .seg-label{font-size:.76rem;}
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

<div class="main">

<?php if ($error): ?>
<div class="alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-success"><?= htmlspecialchars($success) ?> — <a href="history.php" style="color:#1e8449;font-weight:700;">이력 보기 →</a></div>
<div class="result-actions">
  <a href="consent.php?step=1" class="btn-new result-actions">새 검사 입력</a>
  <a href="history.php" class="btn-hist result-actions">이력 조회</a>
  <a href="graph.php" class="btn-hist result-actions">그래프 보기</a>
</div>

<?php else: ?>

<!-- 환자 정보 바 (한 줄 인라인 배지) -->
<?php if ($initPatient):
  require_once __DIR__ . '/patient_store.php';
  $agev = ageFromBirth($birthDate ?? null);
?>
<div class="info-bar">
  <b><?= htmlspecialchars($initPatient) ?></b>
  <?php if (($consentData['gender'] ?? '') || $agev !== null): ?>
    <span class="sep">|</span>
    <span><?= htmlspecialchars($consentData['gender'] ?? '') ?><?= $agev !== null ? ' ' . $agev . '세' : '' ?></span>
  <?php endif; ?>
  <?php if ($battery && $batteryTotal > 1): ?>
    <span class="sep">|</span>
    <span>검사 <?= $batteryStep ?> / <?= $batteryTotal ?><?php if (!$isLastStep && isset($queue[$qpos+1])): ?> (다음: <?= htmlspecialchars($queue[$qpos+1]) ?>)<?php endif; ?></span>
  <?php endif; ?>
  <span class="sep">|</span>
  <span id="dt_bar"></span>
  <span class="sep">|</span>
  <span class="ok">동의 완료</span>
</div>
<?php endif; ?>

<div class="card">
  <!-- 척도 탭 (단일 검사일 때만; 연속검사에서는 숨김) -->
  <?php if (!$battery): ?>
  <div class="scale-tabs">
    <?php foreach ($scales as $key => $scale): ?>
    <label class="scale-tab <?= $key === $initScale ? 'active' : '' ?>">
      <input type="radio" name="scale_type_ui" value="<?= $key ?>"
             <?= $key === $initScale ? 'checked' : '' ?> onchange="switchScale('<?= $key ?>')">
      <div class="tab-name"><?= $key ?></div>
      <div class="tab-desc"><?= $key==='PHQ-9'?'우울':($key==='GAD-7'?'불안':'스트레스') ?></div>
    </label>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($scales as $key => $scale): ?>
  <div class="scale-section <?= $key === $initScale ? 'active' : '' ?>" id="section-<?= $key ?>">
    <div class="instruction"><?= htmlspecialchars($scale['instruction']) ?></div>

    <?php
    // 리커트 옵션 개수에 따른 색상 그라데이션 (연한 파랑 → 진한 네이비)
    $optCount = count($scale['options']);
    if ($optCount >= 5) {
        $segColors = ['#E0F2FE','#BAE6FD','#3B82F6','#1D4ED8','#0F172A'];
        $segTextDark = [true,true,true,false,false];
    } else {
        $segColors = ['#DBEAFE','#60A5FA','#2563EB','#0F172A'];
        $segTextDark = [true,true,false,false];
    }
    ?>

    <!-- 진행 바 -->
    <div class="progress-wrap">
      <div class="progress-label">
        <span id="ptxt-<?= $key ?>">1 / <?= count($scale['questions']) ?></span>
        <span id="ppct-<?= $key ?>">0%</span>
      </div>
      <div class="progress-bar"><div class="progress-fill" id="pfill-<?= $key ?>" style="width:0%"></div></div>
    </div>

    <?php foreach ($scale['questions'] as $qi => $question): ?>
    <div class="question-slide <?= $qi===0?'active':'' ?>" id="slide-<?= $key ?>-<?= $qi ?>">
      <div class="q-card">
        <div class="q-number">문항 <?= $qi+1 ?> / <?= count($scale['questions']) ?></div>
        <div class="q-text"><?= htmlspecialchars($question) ?></div>
        <div class="q-options">
          <?php foreach ($scale['options'] as $oi => $optLabel):
            $segBg   = $segColors[$oi] ?? '#3B82F6';
            $segText = ($segTextDark[$oi] ?? false) ? '#0f172a' : '#fff';
          ?>
          <button type="button" class="q-option-btn"
                  style="--seg-bg:<?= $segBg ?>;--seg-text:<?= $segText ?>;"
                  data-scale="<?= $key ?>" data-qi="<?= $qi ?>" data-value="<?= $scale['option_values'][$oi] ?>"
                  onclick="selectAnswer(this)">
            <span class="seg-block"><span class="seg-check">✓</span></span>
            <span class="seg-label"><?= htmlspecialchars($optLabel) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- 완료 -->
    <div class="complete-screen" id="complete-<?= $key ?>">
      <?php if($key === 'PHQ-9'): ?>
      <div id="phq9-warning" style="display:none;background:#fff3cd;border:2px solid #f0ad4e;border-radius:10px;padding:14px 16px;margin-bottom:14px;font-size:.875rem;color:#7d5a00;text-align:left;line-height:1.6;"></div>
      <?php endif; ?>
      <div class="complete-score" id="cscore-<?= $key ?>">—</div>
      <div class="complete-label" id="clabel-<?= $key ?>">—</div>
      <!-- 저장 상태 -->
      <div id="save-status-<?= $key ?>" class="save-status-box"></div>

      <!-- 메모 (저장 후에도 추가 가능) -->
      <div class="card" style="text-align:left;margin-bottom:10px;display:none;" id="memo-card-<?= $key ?>">
        <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:6px;">메모</label>
        <textarea id="memo-<?= $key ?>" placeholder="임상 소견, 특이사항 등"></textarea>
        <button type="button" class="btn btn-secondary" style="margin-top:8px;width:100%;font-size:.85rem;"
                onclick="saveWithMemo('<?= $key ?>')">메모 포함하여 다시 저장</button>
      </div>
      <!-- 연속검사: 다음 검사로 -->
      <div id="battery-next-<?= $key ?>" style="margin-top:6px;"></div>
    </div>

    <!-- 이전/다음 -->
    <div class="nav-btns" id="navbtns-<?= $key ?>">
      <button type="button" class="btn btn-secondary" id="btn-prev-<?= $key ?>" onclick="prevQ('<?= $key ?>')" disabled>← 이전</button>
      <button type="button" class="btn btn-primary"   id="btn-next-<?= $key ?>" onclick="nextQ('<?= $key ?>')" disabled>다음 →</button>
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
<?php endif; ?>
</div>

<script>
const BATTERY     = <?= $battery ? 'true' : 'false' ?>;
const BATTERY_LAST= <?= $isLastStep ? 'true' : 'false' ?>;
const NEXT_URL    = <?= json_encode($nextUrl) ?>;
const QUEUE       = <?= json_encode($queue) ?>;
const QPOS        = <?= $qpos ?>;
const NEXT_SCALE  = QUEUE[QPOS + 1] || '';
const PT = {
  birth:   <?= json_encode($birthDate) ?>,
  gender:  <?= json_encode($gender) ?>,
  phone:   <?= json_encode($phone) ?>,
  battery: <?= json_encode($batteryId) ?>
};
const state = {};
<?php foreach ($scales as $key => $scale): ?>
state['<?= $key ?>'] = {
  current:0, total:<?= count($scale['questions']) ?>,
  answers:{},
  scoring:<?= json_encode($scale['scoring']) ?>,
  reverseItems:<?= json_encode($scale['reverse_items'] ?? []) ?>,
  maxOptionVal:<?= max($scale['option_values']) ?>,
  advancing:false, completed:false, savedId:null
};
<?php endforeach; ?>

// 시계
function tick(){
  const n=new Date(),p=v=>String(v).padStart(2,'0');
  const s=`${n.getFullYear()}.${p(n.getMonth()+1)}.${p(n.getDate())} ${p(n.getHours())}:${p(n.getMinutes())}`;
  const e=document.getElementById('dt_bar'); if(e) e.textContent=s;
}
tick(); setInterval(tick,1000);

function toggleTheme(){
  const html = document.documentElement;
  const btn  = document.getElementById('themeToggle');
  const dark = html.getAttribute('data-theme') === 'dark';
  if (dark) { html.removeAttribute('data-theme'); if(btn) btn.textContent = '🌙'; }
  else      { html.setAttribute('data-theme', 'dark'); if(btn) btn.textContent = '☀️'; }
  try { localStorage.setItem('theme', dark ? 'light' : 'dark'); } catch(e) {}
}
if (document.getElementById('themeToggle') && document.documentElement.getAttribute('data-theme') === 'dark') {
  document.getElementById('themeToggle').textContent = '☀️';
}

function switchScale(key){
  document.querySelectorAll('.scale-section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.scale-tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('section-'+key).classList.add('active');
  document.querySelectorAll('.scale-tab').forEach(t=>{if(t.querySelector('input').value===key)t.classList.add('active');});
  state[key].current=0; state[key].answers={}; state[key].completed=false; state[key].advancing=false;
  showSlide(key,0); updateProg(key);
}

function showSlide(key,idx){
  const s=state[key];
  document.querySelectorAll(`#section-${key} .question-slide`).forEach(e=>e.classList.remove('active'));
  document.getElementById(`complete-${key}`).classList.remove('active');
  document.getElementById(`navbtns-${key}`).style.display='flex';
  const slide=document.getElementById(`slide-${key}-${idx}`);
  if(slide){
    slide.classList.add('active');
    if(s.answers[idx]!==undefined){
      slide.querySelectorAll('.q-option-btn').forEach(b=>{
        b.classList.toggle('selected',parseInt(b.dataset.value)===s.answers[idx]);
      });
    }
  }
  const pv=document.getElementById(`btn-prev-${key}`);
  const nv=document.getElementById(`btn-next-${key}`);
  pv.disabled=idx===0;
  nv.disabled=s.answers[idx]===undefined;
  nv.textContent=idx===s.total-1?'완료 →':'다음 →';
}

function selectAnswer(btn){
  const key=btn.dataset.scale, qi=parseInt(btn.dataset.qi), val=parseInt(btn.dataset.value);
  const s=state[key];
  if (s.advancing) return; // 연타/이중 클릭으로 인한 중복 진행·중복 저장 방지
  s.advancing = true;
  document.querySelectorAll(`#slide-${key}-${qi} .q-option-btn`).forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
  s.answers[qi]=val;
  document.getElementById(`btn-next-${key}`).disabled=false;
  updateProg(key);
  setTimeout(()=>{ s.advancing = false; nextQ(key); },280);
}

function prevQ(key){const s=state[key];if(s.current>0){s.current--;showSlide(key,s.current);updateProg(key);}}
function nextQ(key){
  const s=state[key];
  if(s.answers[s.current]===undefined)return;
  if(s.current<s.total-1){s.current++;showSlide(key,s.current);updateProg(key);}
  else showComplete(key);
}
function updateProg(key){
  const s=state[key], answered=Object.keys(s.answers).length;
  const pct=Math.round(answered/s.total*100);
  document.getElementById(`pfill-${key}`).style.width=pct+'%';
  document.getElementById(`ptxt-${key}`).textContent=`${s.current+1} / ${s.total}`;
  document.getElementById(`ppct-${key}`).textContent=pct+'%';
}
function showComplete(key){
  const s=state[key];
  if (s.completed) return; // 중복 호출로 인한 중복 자동저장 방지
  s.completed = true;
  let total=0;
  for(let i=0;i<s.total;i++){
    let v = s.answers[i]||0;
    if(s.reverseItems && s.reverseItems.includes(i)){
      v = s.maxOptionVal - v;
    }
    total += v;
  }
  let label='';
  for(const r of s.scoring){if(total>=r.min&&total<=r.max){label=r.label;break;}}
  if (typeof tossCountUp === 'function') tossCountUp(document.getElementById(`cscore-${key}`), total, {suffix:'점'});
  else document.getElementById(`cscore-${key}`).textContent=total+'점';
  document.getElementById(`clabel-${key}`).textContent = BATTERY ? '검사가 완료되었습니다' : label;
  document.querySelectorAll(`#section-${key} .question-slide`).forEach(e=>e.classList.remove('active'));
  document.getElementById(`navbtns-${key}`).style.display='none';
  document.getElementById(`complete-${key}`).classList.add('active');

  // PHQ-9 9번 문항(자해/자살 사고) 주의 알림
  if(!BATTERY && key === 'PHQ-9') {
    const q9score = s.answers[8] || 0;
    const warningEl = document.getElementById('phq9-warning');
    if(q9score >= 1) {
      warningEl.style.display = 'block';
      warningEl.innerHTML = q9score >= 2
        ? '⚠️ <strong>즉각적 주의 필요</strong>: 9번 문항(자해·자살 사고)에서 높은 점수('+q9score+'점)가 나왔습니다. 즉각적인 임상적 평가와 안전 확인이 필요합니다.'
        : '⚠️ <strong>주의</strong>: 9번 문항(자해·자살 사고)에 응답이 있었습니다('+q9score+'점). 추가적인 임상적 판단이 필요합니다.';
    } else {
      warningEl.style.display = 'none';
    }
  }

  // 자동 저장 (1초 후)
  setTimeout(() => autoSave(key), 1000);
}
// AJAX 저장 공통 함수
async function doSave(key, memo) {
  const patient = document.getElementById('f_patient').value.trim();
  if (!patient) { window.location = 'consent.php?step=1'; return; }

  const s = state[key];
  const answers = [];
  for (let i = 0; i < s.total; i++) answers.push(s.answers[i] !== undefined ? s.answers[i] : 0);

  const statusEl = document.getElementById(`save-status-${key}`);
  statusEl.innerHTML = '<span style="color:var(--muted)">💾 저장 중...</span>';

  // CSRF 토큰 가져오기
  const csrfEl = document.querySelector('input[name="csrf_token"]');
  const csrf = csrfEl ? csrfEl.value : '';

  try {
    const res = await fetch('save_assessment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify(Object.assign({
        csrf_token:     csrf,
        patient_name:   patient,
        scale_type:     key,
        answers:        answers,
        memo:           memo || '',
        assessment_id:  s.savedId || null,
      }, BATTERY ? { birth_date: PT.birth, gender: PT.gender, phone: PT.phone, battery_id: PT.battery } : {}))
    });

    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch(parseErr) {
      statusEl.innerHTML = `<span style="color:#c0392b;">❌ 서버 응답 오류</span>
        <button onclick="autoSave('${key}')" style="margin-left:8px;font-size:.82rem;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
      console.error('Response:', text);
      return;
    }

    if (data.success) {
      statusEl.innerHTML = `<span style="color:#27ae60;font-weight:700;">✅ 저장 완료</span>`;
      if (data.id) s.savedId = data.id;
      if (BATTERY) {
        const nx = document.getElementById(`battery-next-${key}`);
        if (nx) nx.innerHTML = `<a href="${NEXT_URL}" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;padding:14px;">${BATTERY_LAST ? '검사 마치고 결과 보기 →' : ('다음 검사 진행 (' + NEXT_SCALE + ') →')}</a>`;
      } else {
        const memoCard = document.getElementById(`memo-card-${key}`);
        if (memoCard) memoCard.style.display = 'block';
      }
    } else {
      statusEl.innerHTML = `<span style="color:#c0392b;">❌ 저장 실패: ${data.message}</span>
        <button onclick="autoSave('${key}')" style="margin-left:8px;font-size:.82rem;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
    }
  } catch(e) {
    statusEl.innerHTML = `<span style="color:#c0392b;">❌ 네트워크 오류 — 와이파이 연결을 확인해주세요</span>
      <button onclick="autoSave('${key}')" style="margin-left:8px;font-size:.82rem;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
    console.error(e);
  }
}

// 자동 저장 (메모 없이)
function autoSave(key) {
  doSave(key, '');
}

// 메모 포함 재저장
function saveWithMemo(key) {
  const memo = document.getElementById(`memo-${key}`).value;
  doSave(key, memo);
}

// 하위 호환용
function submitResult(key){ autoSave(key); }

// 초기 척도
switchScale('<?= $initScale ?>');
</script>
<!-- 새 검사 버튼 (연속검사 중에는 숨김) -->
<?php if (!$battery): ?>
<a href="consent.php?step=1" class="float-btn" style="bottom:76px;" title="새 검사 입력">✏️</a>
<?php endif; ?>

<!-- 돋보기 버튼 -->
<div class="zoom-tooltip" id="zoomTooltip">글자 크게/작게</div>
<button class="zoom-btn" id="zoomBtn" onclick="toggleZoom()" title="글자 크기 조절">🔍</button>

<script>
function toggleZoom() {
  const body = document.body;
  const btn  = document.getElementById('zoomBtn');
  const tip  = document.getElementById('zoomTooltip');
  if (body.classList.contains('font-lg')) {
    body.classList.remove('font-lg');
    btn.textContent = '🔍';
    tip.textContent = '글자 크게';
  } else {
    body.classList.add('font-lg');
    btn.textContent = '🔎';
    tip.textContent = '글자 작게';
  }
  // 툴팁 잠깐 표시
  tip.style.display = 'block';
  setTimeout(() => tip.style.display = 'none', 1500);
}
</script>
</body>
</html>
