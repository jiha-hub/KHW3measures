<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scales.php';
requireLogin();
startSession();

// consent.php 통해서 왔는지 확인
$consentData = $_SESSION['consent_data'] ?? null;
if (($_GET['from'] ?? '') === 'consent' && empty($consentData['patient_name'])) {
    header('Location: consent.php?step=1'); exit;
}

$scales  = getScales();
$success = '';
$error   = '';

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
            $stmt = $db->prepare('SELECT id FROM patients WHERE name = ?');
            $stmt->execute([$patientName]);
            $patient = $stmt->fetch();
            if (!$patient) {
                $stmt = $db->prepare('INSERT INTO patients (name) VALUES (?)');
                $stmt->execute([$patientName]);
                $patientId = $db->lastInsertId();
            } else { $patientId = $patient['id']; }

            $stmt = $db->prepare('INSERT INTO assessments (patient_id, scale_type, answers, total_score, result_label, memo, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$patientId, $scaleType, json_encode($answers, JSON_UNESCAPED_UNICODE), $scored['total'], $scored['label'], $memo, $_SESSION['admin_id']]);
            unset($_SESSION['consent_data']);
            $success = '검사 결과가 저장되었습니다.';
        }
    }
}

$csrf = getCsrfToken();
// 전달된 척도/환자 정보
$initScale   = $consentData['scale_type']   ?? 'PHQ-9';
$initPatient = $consentData['patient_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= APP_NAME ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;
  --text:#1a2236;--muted:#6b7a99;--border:#dce3ef;
  --radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);
}
html,body{height:100%;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
body{background:var(--bg);display:flex;flex-direction:column;min-height:100vh;}
.header{background:var(--primary);color:#fff;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.15);}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:10px;align-items:center;}
.header-nav a{color:rgba(255,255,255,.85);text-decoration:none;font-size:.82rem;padding:5px 10px;border-radius:6px;transition:background .2s;}
.header-nav a:hover,.header-nav a.active{background:rgba(255,255,255,.2);}
.admin-badge{font-size:.75rem;color:rgba(255,255,255,.65);}

/* 레이아웃: 화면 꽉 채우기 */
.main{flex:1;display:flex;flex-direction:column;padding:16px;max-width:700px;width:100%;margin:0 auto;}

/* 상단 정보 바 */
.info-bar{display:flex;align-items:center;justify-content:space-between;background:var(--card);border-radius:10px;padding:12px 18px;box-shadow:var(--shadow);margin-bottom:12px;flex-wrap:wrap;gap:8px;}
.info-patient{font-size:.95rem;font-weight:700;}
.info-meta{display:flex;gap:14px;font-size:.82rem;color:var(--muted);}
.info-badge{background:#eef2fb;color:var(--primary);padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;}

/* 카드 */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:12px;}

/* 척도 탭 */
.scale-tabs{display:flex;gap:8px;margin-bottom:16px;}
.scale-tab{flex:1;padding:10px;border:2px solid var(--border);border-radius:9px;background:#fafbfd;cursor:pointer;text-align:center;transition:all .2s;}
.scale-tab.active{border-color:var(--primary);background:#eef2fb;}
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
.instruction{background:#eef2fb;border-left:4px solid var(--primary);padding:10px 14px;border-radius:0 8px 8px 0;font-size:.85rem;color:var(--text);margin-bottom:14px;line-height:1.6;}
.q-card{background:#f8fafd;border:1.5px solid var(--border);border-radius:11px;padding:20px 18px;margin-bottom:12px;text-align:center;}
.q-number{font-size:.75rem;font-weight:700;color:var(--primary);margin-bottom:10px;letter-spacing:.05em;}
.q-text{font-size:1rem;font-weight:600;line-height:1.65;color:var(--text);margin-bottom:18px;}
.q-options{display:flex;flex-direction:column;gap:8px;}
.q-option-btn{width:100%;padding:11px 16px;border:2px solid var(--border);border-radius:9px;background:#fff;cursor:pointer;font-size:.9rem;font-weight:500;color:var(--text);transition:all .15s;text-align:left;font-family:inherit;}
.q-option-btn:hover{border-color:var(--primary);color:var(--primary);background:#f0f4ff;}
.q-option-btn.selected{border-color:var(--primary);background:var(--primary);color:#fff;}

/* 완료 화면 */
.complete-screen{display:none;text-align:center;padding:10px 0;}
.complete-screen.active{display:block;}
.complete-icon{font-size:2.5rem;margin-bottom:10px;}
.complete-score{font-size:2.8rem;font-weight:800;color:var(--primary);margin-bottom:4px;}
.complete-label{font-size:1rem;font-weight:700;color:var(--primary);margin-bottom:18px;}
textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem;color:var(--text);background:#fafbfd;outline:none;font-family:inherit;resize:vertical;min-height:70px;}
textarea:focus{border-color:var(--primary);}

/* 네비 버튼 */
.nav-btns{display:flex;gap:10px;margin-top:8px;}
.btn{padding:12px 0;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;flex:1;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.btn:disabled{opacity:.4;cursor:not-allowed;}
.save-status-box{min-height:28px;padding:6px 0;font-size:.875rem;margin-bottom:8px;}

/* 돋보기 / 글자 크기 */
.zoom-btn,.float-btn{position:fixed;right:20px;width:48px;height:48px;border-radius:50%;background:var(--primary);color:#fff;border:none;font-size:1.3rem;cursor:pointer;box-shadow:0 4px 12px rgba(59,108,183,.4);display:flex;align-items:center;justify-content:center;z-index:999;transition:all .2s;text-decoration:none;}
.zoom-btn{bottom:20px;}
.zoom-btn:hover,.float-btn:hover{background:var(--primary-dark);transform:scale(1.1);}
.zoom-tooltip{position:fixed;bottom:76px;right:14px;background:#1a2236;color:#fff;border-radius:8px;padding:8px 12px;font-size:.8rem;display:none;white-space:nowrap;z-index:999;}
body.font-lg .q-text{font-size:1.25rem !important;}
body.font-lg .q-option-btn{font-size:1.05rem !important;padding:14px 18px !important;}
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
  .q-options{flex-direction:row;flex-wrap:wrap;}
  .q-option-btn{flex:1;min-width:calc(50% - 4px);text-align:center;}
}
</style>
</head>
<body>
<header class="header">
  <h1><a href="consent.php" style="color:white;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php" class="active">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="graph.php">그래프</a>
    <a href="admin.php">설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">로그아웃</a>
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

<!-- 환자 정보 바 -->
<?php if ($initPatient): ?>
<div class="info-bar">
  <div>
    <div class="info-patient">👤 <?= htmlspecialchars($initPatient) ?></div>
    <div class="info-meta">
      <?php if ($consentData['gender'] ?? ''): ?><span><?= htmlspecialchars($consentData['gender']) ?></span><?php endif; ?>
      <?php if ($consentData['age'] ?? ''): ?><span><?= htmlspecialchars($consentData['age']) ?>세</span><?php endif; ?>
      <span id="dt_bar"></span>
    </div>
  </div>
  <span class="info-badge">✅ 동의 완료</span>
</div>
<?php endif; ?>

<div class="card">
  <!-- 척도 탭 -->
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

  <?php foreach ($scales as $key => $scale): ?>
  <div class="scale-section <?= $key === $initScale ? 'active' : '' ?>" id="section-<?= $key ?>">
    <div class="instruction"><?= htmlspecialchars($scale['instruction']) ?></div>

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
          <?php foreach ($scale['options'] as $oi => $optLabel): ?>
          <button type="button" class="q-option-btn"
                  data-scale="<?= $key ?>" data-qi="<?= $qi ?>" data-value="<?= $scale['option_values'][$oi] ?>"
                  onclick="selectAnswer(this)">
            <?= htmlspecialchars($optLabel) ?>
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
const state = {};
<?php foreach ($scales as $key => $scale): ?>
state['<?= $key ?>'] = {
  current:0, total:<?= count($scale['questions']) ?>,
  answers:{},
  scoring:<?= json_encode($scale['scoring']) ?>,
  reverseItems:<?= json_encode($scale['reverse_items'] ?? []) ?>,
  maxOptionVal:<?= max($scale['option_values']) ?>
};
<?php endforeach; ?>

// 시계
function tick(){
  const n=new Date(),p=v=>String(v).padStart(2,'0');
  const s=`${n.getFullYear()}년 ${p(n.getMonth()+1)}월 ${p(n.getDate())}일 ${p(n.getHours())}:${p(n.getMinutes())}`;
  const e=document.getElementById('dt_bar'); if(e) e.textContent=s;
}
tick(); setInterval(tick,1000);

function switchScale(key){
  document.querySelectorAll('.scale-section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.scale-tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('section-'+key).classList.add('active');
  document.querySelectorAll('.scale-tab').forEach(t=>{if(t.querySelector('input').value===key)t.classList.add('active');});
  state[key].current=0; state[key].answers={};
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
  document.querySelectorAll(`#slide-${key}-${qi} .q-option-btn`).forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
  state[key].answers[qi]=val;
  document.getElementById(`btn-next-${key}`).disabled=false;
  updateProg(key);
  setTimeout(()=>nextQ(key),280);
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
  document.getElementById(`cscore-${key}`).textContent=total+'점';
  document.getElementById(`clabel-${key}`).textContent=label;
  document.querySelectorAll(`#section-${key} .question-slide`).forEach(e=>e.classList.remove('active'));
  document.getElementById(`navbtns-${key}`).style.display='none';
  document.getElementById(`complete-${key}`).classList.add('active');

  // PHQ-9 9번 문항(자해/자살 사고) 주의 알림
  if(key === 'PHQ-9') {
    const q9score = s.answers[8] || 0; // 9번 문항 (0-index: 8)
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
      body: JSON.stringify({
        csrf_token:   csrf,
        patient_name: patient,
        scale_type:   key,
        answers:      answers,
        memo:         memo || '',
      })
    });

    // 응답 텍스트 먼저 받기 (JSON 파싱 오류 대비)
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
      const memoCard = document.getElementById(`memo-card-${key}`);
      if (memoCard) memoCard.style.display = 'block';
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
<!-- 새 검사 버튼 -->
<a href="consent.php?step=1" class="float-btn" style="bottom:76px;" title="새 검사 입력">✏️</a>

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
