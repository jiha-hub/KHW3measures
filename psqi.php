<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/psqi_scoring.php';
requireLogin();
startSession();

$consentData = $_SESSION['consent_data'] ?? null;
// consent/연속검사 흐름 밖에서 직접 들어온 경우 가드
if (in_array($_GET['from'] ?? '', ['consent','battery'], true) && empty($consentData['patient_name'])) {
    header('Location: consent.php?step=1'); exit;
}

$meta = getPsqiMeta();
$csrf = getCsrfToken();
$initPatient = $consentData['patient_name'] ?? '';
$freqOptions = $meta['freq_options'];

// 연속검사(방문) 모드
$battery      = (($_GET['from'] ?? '') === 'battery');
$queue        = $consentData['scale_queue'] ?? [];
$qpos         = (int)($consentData['qpos'] ?? 0);
$batteryId    = $consentData['battery_id'] ?? '';
$birthDate    = $consentData['birth_date'] ?? '';
$gender       = $consentData['gender'] ?? '';
$phone        = $consentData['phone'] ?? '';
$batteryTotal = count($queue);
$batteryStep  = $qpos + 1;
$isLastStep   = ($batteryStep >= $batteryTotal);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PSQI-K 수면의 질 검사 — <?= APP_NAME ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;
  --text:#1a2236;--muted:#6b7a99;--border:#dce3ef;
  --radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);
}
html,body{height:100%;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
body{background:var(--bg);display:flex;flex-direction:column;min-height:100vh;}
.header{background:#fff;color:#111827;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;border-bottom:1px solid #e5e7eb;}
.header h1{font-size:.95rem;font-weight:700;}
.header h1 a{color:#111827;text-decoration:none;}
.header-nav{display:flex;gap:6px;align-items:center;}
.header-nav a{color:#4b5563;text-decoration:none;font-size:.82rem;padding:6px 12px;border-radius:8px;font-weight:500;transition:background .2s,color .2s;}
.header-nav a:hover{background:#f3f4f6;}
.header-nav a.active{background:#eff6ff;color:#2563eb;font-weight:700;}
.admin-badge{font-size:.78rem;color:#9ca3af;}

.main{flex:1;display:flex;flex-direction:column;padding:16px;max-width:700px;width:100%;margin:0 auto;}

.info-bar{display:flex;align-items:center;justify-content:space-between;background:var(--card);border-radius:10px;padding:12px 18px;box-shadow:var(--shadow);margin-bottom:12px;flex-wrap:wrap;gap:8px;}
.info-patient{font-size:.95rem;font-weight:700;}
.info-meta{display:flex;gap:14px;font-size:.82rem;color:var(--muted);}
.info-badge{background:#eef2fb;color:var(--primary);padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;}

.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:12px;}
.scale-head{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.scale-head .ico{width:44px;height:44px;border-radius:10px;background:#eef4ff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;}
.scale-head strong{display:block;font-size:1.05rem;color:var(--primary);}
.scale-head span{font-size:.78rem;color:var(--muted);}

.instruction{background:#eef2fb;border-left:4px solid var(--primary);padding:10px 14px;border-radius:0 8px 8px 0;font-size:.85rem;color:var(--text);margin-bottom:14px;line-height:1.6;}

.progress-wrap{margin-bottom:16px;}
.progress-label{display:flex;justify-content:space-between;font-size:.78rem;color:var(--muted);margin-bottom:6px;}
.progress-bar{height:5px;background:var(--border);border-radius:99px;overflow:hidden;}
.progress-fill{height:100%;background:var(--primary);border-radius:99px;transition:width .3s;}

.question-slide{display:none;}
.question-slide.active{display:block;}
.q-card{background:#f8fafd;border:1.5px solid var(--border);border-radius:11px;padding:22px 18px;margin-bottom:12px;text-align:center;}
.q-number{font-size:.75rem;font-weight:700;color:var(--primary);margin-bottom:10px;letter-spacing:.05em;}
.q-text{font-size:1.05rem;font-weight:600;line-height:1.65;color:var(--text);margin-bottom:20px;}

/* 리커트 버튼 */
.q-options{display:flex;flex-direction:column;gap:8px;}
.q-option-btn{width:100%;padding:13px 16px;border:2px solid var(--border);border-radius:9px;background:#fff;cursor:pointer;font-size:.95rem;font-weight:500;color:var(--text);transition:all .15s;text-align:left;font-family:inherit;}
.q-option-btn:hover{border-color:var(--primary);color:var(--primary);background:#f0f4ff;}
.q-option-btn.selected{border-color:var(--primary);background:var(--primary);color:#fff;}

/* 시간 입력 */
.time-input{font-size:2rem;font-weight:700;padding:14px 18px;border:2px solid var(--border);border-radius:12px;font-family:inherit;color:var(--text);background:#fff;text-align:center;width:100%;max-width:220px;outline:none;}
.time-input:focus{border-color:var(--primary);}
.time-hint{font-size:.8rem;color:var(--muted);margin-top:10px;}

/* 숫자 입력 (분 단위 터치) */
.num-manual{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:6px;}
.num-step{min-width:56px;height:56px;padding:0 10px;border-radius:28px;border:2px solid var(--border);background:#fff;font-size:1.5rem;font-weight:700;color:var(--primary);cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit;line-height:1;transition:all .15s;}
.num-step:active{background:var(--primary);color:#fff;border-color:var(--primary);}
.num-field{font-size:2rem;font-weight:800;color:var(--text);width:110px;min-height:56px;text-align:center;border:2px solid var(--primary);border-radius:12px;padding:12px;background:#f8fafd;font-family:inherit;}
.num-field:focus{border-color:var(--primary-dark);outline:none;background:#fff;}
.num-unit{font-size:1.1rem;color:var(--text);font-weight:700;}
.num-format-hint{font-size:.8rem;color:var(--muted);margin-top:12px;}

/* 커스텀 아날로그 시간 선택기 (시계 UI) */
.time-picker-custom{display:flex;flex-direction:column;align-items:center;gap:14px;}
.ampm-toggle{display:flex;background:#eef2fb;border-radius:12px;padding:4px;width:200px;}
.ampm-btn{flex:1;padding:10px 0;text-align:center;border-radius:8px;font-weight:700;font-size:1rem;color:var(--muted);cursor:pointer;transition:all .2s;}
.ampm-btn.active{background:#fff;color:var(--primary);box-shadow:0 2px 8px rgba(0,0,0,.08);}

.clock-readout{display:flex;align-items:center;justify-content:center;gap:4px;}
.clock-ro-seg{font-size:2.6rem;font-weight:800;color:var(--muted);cursor:pointer;padding:2px 12px;border-radius:10px;font-variant-numeric:tabular-nums;transition:all .15s;font-family:inherit;line-height:1;}
.clock-ro-seg.active{color:var(--primary);background:#eef2fb;}
.clock-ro-colon{font-size:2.6rem;font-weight:800;color:var(--muted);}
.clock-ro-hint{font-size:.72rem;color:var(--muted);margin-top:-8px;}

.clock-face-wrap{display:flex;justify-content:center;}
.clock-face{width:250px;height:250px;touch-action:none;-webkit-user-select:none;user-select:none;}
.clock-bg{fill:#f4f7fb;stroke:var(--border);stroke-width:2;}
.clock-center{fill:var(--primary);}
.clock-hand{stroke:var(--primary);stroke-width:4;stroke-linecap:round;}
.clock-num-dot{fill:var(--primary);}
.clock-num{font-size:17px;font-weight:700;fill:var(--text);font-family:inherit;pointer-events:none;}
.clock-num.selected{fill:#fff;}


/* 완료 화면 */
.complete-screen{display:none;text-align:center;padding:6px 0;}
.complete-screen.active{display:block;}
.complete-score{font-size:2.8rem;font-weight:800;color:var(--primary);margin-bottom:2px;}
.complete-label{font-size:1.1rem;font-weight:700;margin-bottom:8px;}
.cutoff-note{display:inline-block;font-size:.85rem;font-weight:700;padding:6px 14px;border-radius:20px;margin-bottom:16px;}
.cutoff-good{background:#eafaf1;color:#1e8449;border:1px solid #a9dfbf;}
.cutoff-poor{background:#fdedec;color:#c0392b;border:1px solid #f1948a;}

/* 구성요소 막대 */
.comp-list{text-align:left;margin:6px 0 4px;}
.comp-row{display:flex;align-items:center;gap:10px;margin-bottom:9px;}
.comp-name{font-size:.82rem;color:var(--text);width:112px;flex-shrink:0;}
.comp-bar{flex:1;height:10px;background:var(--border);border-radius:99px;overflow:hidden;}
.comp-fill{height:100%;border-radius:99px;transition:width .4s;}
.comp-val{font-size:.8rem;font-weight:700;color:var(--muted);width:34px;text-align:right;flex-shrink:0;}
.eff-note{font-size:.82rem;color:var(--muted);margin:10px 0 4px;}

textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.9rem;color:var(--text);background:#fafbfd;outline:none;font-family:inherit;resize:vertical;min-height:70px;}
textarea:focus{border-color:var(--primary);}

.nav-btns{display:flex;gap:10px;margin-top:8px;}
.btn{padding:13px 0;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;flex:1;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.btn:disabled{opacity:.4;cursor:not-allowed;}
.save-status-box{min-height:28px;padding:6px 0;font-size:.9rem;margin-bottom:8px;}

.result-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;}
.result-actions a{flex:1;min-width:120px;padding:12px;border-radius:8px;text-align:center;text-decoration:none;font-weight:700;font-size:.875rem;}
.btn-new{background:var(--primary);color:#fff;}
.btn-hist{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}

.src-note{font-size:.72rem;color:var(--muted);margin-top:14px;line-height:1.5;text-align:left;}

/* 유니버설 디자인: 글자 크게 모드 대응 */
body.font-lg .q-text{font-size:1.3rem !important;}
body.font-lg .q-option-btn{font-size:1.1rem !important;padding:16px 18px !important;}
body.font-lg .instruction{font-size:1rem !important;}
body.font-lg .num-preset{font-size:1.15rem !important;}
body.font-lg .comp-name{font-size:.95rem !important;width:130px !important;}

@media(min-width:768px){
  .main{padding:20px 24px;}
  .q-options{flex-direction:row;flex-wrap:wrap;}
  .q-option-btn{flex:1;min-width:calc(50% - 4px);text-align:center;}
}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
</head>
<?php include __DIR__ . '/ui_settings.php'; ?>
<body>
<header class="header">
  <h1><a href="consent.php">🌙 PSQI-K 수면의 질 검사</a></h1>
  <nav class="header-nav">
    <a href="consent.php">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="graph.php?scale=PSQI-K">그래프</a>
    <a href="admin.php">설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name'] ?? '') ?></span>
    <a href="logout.php">로그아웃</a>
  </nav>
</header>

<div class="main">

<?php if ($initPatient): ?>
<div class="info-bar">
  <div>
    <div class="info-patient">👤 <?= htmlspecialchars($initPatient) ?></div>
    <div class="info-meta">
      <?php if ($consentData['gender'] ?? ''): ?><span><?= htmlspecialchars($consentData['gender']) ?></span><?php endif; ?>
      <?php if ($consentData['age'] ?? ''): ?><span><?= htmlspecialchars($consentData['age']) ?>세</span><?php endif; ?>
      <?php if ($battery && $batteryTotal > 1): ?>
        <span style="color:var(--primary);font-weight:700;">검사 <?= $batteryStep ?> / <?= $batteryTotal ?>
          <?php if (!$isLastStep && isset($queue[$qpos+1])): ?> <span style="font-weight:400;color:var(--muted);">(다음: <?= htmlspecialchars($queue[$qpos+1]) ?>)</span><?php endif; ?>
        </span>
      <?php endif; ?>
      <span id="dt_bar"></span>
    </div>
  </div>
  <span class="info-badge">✅ 동의 완료</span>
</div>
<?php endif; ?>

<div class="card">
  <div class="scale-head">
    <div class="ico">🌙</div>
    <div>
      <strong>PSQI-K</strong>
      <span>한국판 피츠버그 수면의 질 지수 · 지난 한 달 기준</span>
    </div>
  </div>

  <div class="instruction">지난 한 달간의 <strong>평소</strong> 수면 습관에 대한 질문입니다. 특정 하루가 아니라 <strong>대부분의 날</strong>에 해당하는 답을 골라 주세요.</div>

  <div class="progress-wrap">
    <div class="progress-label">
      <span id="ptxt">1 / <?= $meta['answer_count'] ?></span>
      <span id="ppct">0%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" id="pfill" style="width:0%"></div></div>
  </div>

  <?php foreach ($meta['items'] as $qi => $item): ?>
  <div class="question-slide <?= $qi === 0 ? 'active' : '' ?>" id="slide-<?= $qi ?>">
    <div class="q-card">
      <div class="q-number">문항 <?= $qi + 1 ?> / <?= $meta['answer_count'] ?></div>
      <div class="q-text"><?= htmlspecialchars($item['q']) ?></div>

      <?php if ($item['type'] === 'time'): ?>
        <div class="time-picker-custom" id="tp-<?= $qi ?>">
          <div class="ampm-toggle">
            <div class="ampm-btn active" onclick="setAmpm(<?= $qi ?>,'AM')">오전</div>
            <div class="ampm-btn" onclick="setAmpm(<?= $qi ?>,'PM')">오후</div>
          </div>
          <div class="clock-readout">
            <span class="clock-ro-seg active" id="ro-h-<?= $qi ?>" onclick="setClockMode(<?= $qi ?>,'h')">12</span>
            <span class="clock-ro-colon">:</span>
            <span class="clock-ro-seg" id="ro-m-<?= $qi ?>" onclick="setClockMode(<?= $qi ?>,'m')">00</span>
          </div>
          <div class="clock-ro-hint" id="ro-hint-<?= $qi ?>">시계에서 시(時)를 선택하세요</div>
          <div class="clock-face-wrap">
            <svg class="clock-face" id="clock-<?= $qi ?>" viewBox="0 0 260 260">
              <circle cx="130" cy="130" r="120" class="clock-bg"></circle>
              <line id="clock-hand-<?= $qi ?>" class="clock-hand" x1="130" y1="130" x2="130" y2="40"></line>
              <circle cx="130" cy="130" r="5" class="clock-center"></circle>
              <g id="clock-nums-<?= $qi ?>"></g>
            </svg>
          </div>
          <button type="button" class="btn btn-secondary" style="width:220px;margin-top:2px;" onclick="confirmTime(<?= $qi ?>)">시간 입력 완료</button>
        </div>
        <input type="hidden" id="in-<?= $qi ?>" value="">

      <?php elseif ($item['type'] === 'number'): ?>
        <?php $unit = $item['unit'] ?? ''; $step = $item['step'] ?? 1; $mx = $item['max'] ?? 999; ?>
        <?php if ($qi === 1): /* 잠들기까지 걸린 시간 (문항2) 특별 터치 UI */ ?>
          <div class="num-manual">
            <button type="button" class="num-step" onclick="onStep(<?= $qi ?>, -5, <?= $mx ?>)">-5</button>
            <input type="number" class="num-field" id="in-<?= $qi ?>" min="0" max="<?= $mx ?>" step="5"
                   inputmode="numeric" placeholder="00" oninput="onNum(<?= $qi ?>, this.value, <?= $mx ?>)"
                   onblur="padTwoDigits(<?= $qi ?>)">
            <button type="button" class="num-step" onclick="onStep(<?= $qi ?>, 5, <?= $mx ?>)">+5</button>
            <span class="num-unit"><?= htmlspecialchars($unit) ?></span>
          </div>
          <div class="num-format-hint">숫자를 터치하여 직접 입력하거나 버튼으로 조절하세요. <strong>00분</strong> 형식으로 입력하세요 (예: 15분, 30분).</div>
        <?php else: ?>
          <div class="num-manual">
            <button type="button" class="num-step" onclick="onStep(<?= $qi ?>, <?= -$step ?>, <?= $mx ?>)">−</button>
            <input type="number" class="num-field" id="in-<?= $qi ?>" min="0" max="<?= $mx ?>" step="<?= $step ?>"
                   inputmode="decimal" placeholder="0" oninput="onNum(<?= $qi ?>, this.value, <?= $mx ?>)">
            <button type="button" class="num-step" onclick="onStep(<?= $qi ?>, <?= $step ?>, <?= $mx ?>)">+</button>
            <span class="num-unit"><?= htmlspecialchars($unit) ?></span>
          </div>
        <?php endif; ?>

      <?php else: /* likert */ ?>
        <?php $opts = $item['options'] ?? $freqOptions; ?>
        <div class="q-options">
          <?php foreach ($opts as $oi => $optLabel): ?>
          <button type="button" class="q-option-btn" data-qi="<?= $qi ?>" data-val="<?= $oi ?>"
                  onclick="onLikert(<?= $qi ?>, <?= $oi ?>, this)"><?= htmlspecialchars($optLabel) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- 완료 -->
  <div class="complete-screen" id="complete">
    <div class="complete-score" id="cscore">—</div>
    <div class="complete-label" id="clabel">—</div>
    <div id="cutoffNote" class="cutoff-note"></div>

    <div class="comp-list" id="compList"></div>
    <div class="eff-note" id="effNote"></div>

    <div id="save-status" class="save-status-box"></div>

    <div class="card" style="text-align:left;margin-bottom:0;display:none;" id="memo-card">
      <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:6px;">메모</label>
      <textarea id="memo" placeholder="임상 소견, 특이사항 등"></textarea>
      <button type="button" class="btn btn-secondary" style="margin-top:8px;width:100%;font-size:.85rem;"
              onclick="saveWithMemo()">메모 포함하여 다시 저장</button>
    </div>

    <div id="battery-next" style="margin:8px 0;"></div>

    <div class="result-actions">
      <a href="consent.php?step=1" class="btn-new">새 검사 입력</a>
      <a href="history.php" class="btn-hist">이력 조회</a>
      <a href="graph.php?scale=PSQI-K" class="btn-hist">그래프 보기</a>
    </div>

    <div class="src-note">
      ⚠️ 문항 문안은 표준 검증판을 참고한 것입니다. 임상 사용 전 공식 문서와 대조·검수하세요.<br>
      <?= htmlspecialchars($meta['source']) ?>
    </div>
  </div>

  <div class="nav-btns" id="navbtns">
    <button type="button" class="btn btn-secondary" id="btn-prev" onclick="prevQ()" disabled>← 이전</button>
    <button type="button" class="btn btn-primary"   id="btn-next" onclick="nextQ()" disabled>다음 →</button>
  </div>

  <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" id="f_patient" value="<?= htmlspecialchars($initPatient) ?>">
</div>
</div>

<script>
const ITEM_TYPES = <?= json_encode(array_map(fn($it) => $it['type'], $meta['items'])) ?>;
const TOTAL = <?= $meta['answer_count'] ?>;
const CUTOFF = <?= $meta['cutoff'] ?>;
const SCORING = <?= json_encode($meta['scoring']) ?>;
const COLOR_HEX = {green:'#27ae60', yellow:'#e0a800', orange:'#e67e22', red:'#c0392b', darkred:'#922b21'};

const state = { current: 0, answers: {}, advancing: false, completed: false, savedId: null };  // answers[idx] = 값(시간문자열/숫자/리커트정수)

// 시계
function tick(){
  const n=new Date(),p=v=>String(v).padStart(2,'0');
  const s=`${n.getFullYear()}년 ${p(n.getMonth()+1)}월 ${p(n.getDate())}일 ${p(n.getHours())}:${p(n.getMinutes())}`;
  const e=document.getElementById('dt_bar'); if(e) e.textContent=s;
}
tick(); setInterval(tick,1000);

function isAnswered(i){
  const v = state.answers[i];
  if (v === undefined || v === null || v === '') return false;
  return true;
}

function showSlide(idx){
  document.querySelectorAll('.question-slide').forEach(e=>e.classList.remove('active'));
  document.getElementById('complete').classList.remove('active');
  document.getElementById('navbtns').style.display='flex';
  const slide = document.getElementById('slide-'+idx);
  if (slide) slide.classList.add('active');
  state.current = idx;

  // 이전 응답이 있으면 선택 표시 복원
  const v = state.answers[idx];
  if (v !== undefined && v !== '') {
    slide.querySelectorAll('.q-option-btn').forEach(b=>b.classList.toggle('selected', parseInt(b.dataset.val)===v));
    slide.querySelectorAll('.num-preset').forEach(b=>b.classList.toggle('selected', parseFloat(b.dataset.val)===v));
    const field = slide.querySelector('.num-field, .time-input');
    if (field && field.value === '') field.value = v;
  }
  if (ITEM_TYPES[idx] === 'time') {
    initTimeState(idx);
    if (typeof v === 'string') {
      const mm = /^(\d{1,2}):(\d{2})$/.exec(v);
      if (mm) {
        const h24 = parseInt(mm[1], 10), min = parseInt(mm[2], 10);
        let h12 = h24 % 12; if (h12 === 0) h12 = 12;
        timeStates[idx] = { ampm: h24 < 12 ? 'AM' : 'PM', h: h12, m: min, mode: 'h' };
      }
    }
    renderClockFace(idx);
  }

  const prev = document.getElementById('btn-prev');
  const next = document.getElementById('btn-next');
  prev.disabled = idx === 0;
  next.disabled = !isAnswered(idx);
  next.textContent = idx === TOTAL-1 ? '완료 →' : '다음 →';
  updateProg();
}

function updateProg(){
  let answered = 0;
  for (let i=0;i<TOTAL;i++) if (isAnswered(i)) answered++;
  const pct = Math.round(answered/TOTAL*100);
  document.getElementById('pfill').style.width = pct+'%';
  document.getElementById('ptxt').textContent = `${state.current+1} / ${TOTAL}`;
  document.getElementById('ppct').textContent = pct+'%';
}

// --- 입력 핸들러 ---
// --- 시간 입력 로직 (아날로그 시계 UI) ---
const SVG_NS = 'http://www.w3.org/2000/svg';
const timeStates = {}; // qi -> { ampm, h, m, mode:'h'|'m' }
const clockDragging = {}; // qi -> bool

function initTimeState(qi) {
  if(!timeStates[qi]) timeStates[qi] = { ampm:'AM', h:12, m:0, mode:'h' };
}
function setAmpm(qi, val) { initTimeState(qi); timeStates[qi].ampm = val; renderClockFace(qi); }
function setClockMode(qi, mode) { initTimeState(qi); timeStates[qi].mode = mode; renderClockFace(qi); }

function clockPolar(cx, cy, r, angleDeg) {
  const rad = angleDeg * Math.PI / 180;
  return { x: cx + r * Math.sin(rad), y: cy - r * Math.cos(rad) };
}

function renderClockFace(qi) {
  initTimeState(qi);
  const ts = timeStates[qi];
  const cx = 130, cy = 130, numR = 92;
  const isHour = ts.mode === 'h';
  const g = document.getElementById(`clock-nums-${qi}`);
  g.innerHTML = '';
  let selIdx = -1;
  for (let i = 0; i < 12; i++) {
    const val = isHour ? (i === 0 ? 12 : i) : i * 5;
    const isSel = isHour ? (ts.h === val) : (ts.m === val);
    if (isSel) selIdx = i;
    const { x, y } = clockPolar(cx, cy, numR, i * 30);
    if (isSel) {
      const dot = document.createElementNS(SVG_NS, 'circle');
      dot.setAttribute('cx', x); dot.setAttribute('cy', y); dot.setAttribute('r', 16);
      dot.setAttribute('class', 'clock-num-dot');
      g.appendChild(dot);
    }
    const t = document.createElementNS(SVG_NS, 'text');
    t.setAttribute('x', x); t.setAttribute('y', y);
    t.setAttribute('text-anchor', 'middle');
    t.setAttribute('dominant-baseline', 'central');
    t.setAttribute('class', 'clock-num' + (isSel ? ' selected' : ''));
    t.textContent = isHour ? String(val) : String(val).padStart(2, '0');
    g.appendChild(t);
  }
  const handAngle = selIdx >= 0 ? selIdx * 30 : 0;
  const handLen = isHour ? 66 : 88;
  const hp = clockPolar(cx, cy, handLen, handAngle);
  const hand = document.getElementById(`clock-hand-${qi}`);
  hand.setAttribute('x2', hp.x); hand.setAttribute('y2', hp.y);

  document.getElementById(`ro-h-${qi}`).textContent = ts.h;
  document.getElementById(`ro-m-${qi}`).textContent = String(ts.m).padStart(2, '0');
  document.getElementById(`ro-h-${qi}`).classList.toggle('active', isHour);
  document.getElementById(`ro-m-${qi}`).classList.toggle('active', !isHour);
  const hint = document.getElementById(`ro-hint-${qi}`);
  if (hint) hint.textContent = isHour ? '시계에서 시(時)를 선택하세요' : '시계에서 분(分)을 선택하세요';

  const btns = document.querySelectorAll(`#tp-${qi} .ampm-btn`);
  btns[0].classList.toggle('active', ts.ampm === 'AM');
  btns[1].classList.toggle('active', ts.ampm === 'PM');
}

function clockEventPoint(evt) {
  return evt.touches && evt.touches[0] ? evt.touches[0] : evt;
}
function pickFromClockEvent(qi, evt) {
  initTimeState(qi);
  const svg = document.getElementById(`clock-${qi}`);
  const rect = svg.getBoundingClientRect();
  const pt = clockEventPoint(evt);
  const cx = rect.left + rect.width / 2, cy = rect.top + rect.height / 2;
  const dx = pt.clientX - cx, dy = pt.clientY - cy;
  const dist = Math.hypot(dx, dy);
  if (dist < 18 * (rect.width / 260)) return; // 중심 근처 탭은 무시
  let angle = Math.atan2(dx, -dy) * 180 / Math.PI;
  if (angle < 0) angle += 360;
  const idx = Math.round(angle / 30) % 12;
  const ts = timeStates[qi];
  if (ts.mode === 'h') ts.h = (idx === 0 ? 12 : idx);
  else ts.m = idx * 5;
  renderClockFace(qi);
}
function clockDown(qi, evt) {
  evt.preventDefault();
  clockDragging[qi] = true;
  pickFromClockEvent(qi, evt);
}
function clockMove(qi, evt) {
  if (!clockDragging[qi]) return;
  evt.preventDefault();
  pickFromClockEvent(qi, evt);
}
function clockUp(qi) {
  if (!clockDragging[qi]) return;
  clockDragging[qi] = false;
  const ts = timeStates[qi];
  if (ts.mode === 'h') { ts.mode = 'm'; renderClockFace(qi); } // 시 선택 후 자동으로 분 선택으로 전환
}
function setupClockFace(qi) {
  const svg = document.getElementById(`clock-${qi}`);
  if (!svg) return;
  svg.addEventListener('pointerdown', e => clockDown(qi, e));
  svg.addEventListener('pointermove', e => clockMove(qi, e));
  window.addEventListener('pointerup', () => clockUp(qi));
  svg.addEventListener('touchstart', e => clockDown(qi, e), { passive: false });
  svg.addEventListener('touchmove', e => clockMove(qi, e), { passive: false });
  window.addEventListener('touchend', () => clockUp(qi));
  renderClockFace(qi);
}

function confirmTime(qi) {
  initTimeState(qi);
  const ts = timeStates[qi];
  let h24 = ts.h;
  if (ts.ampm==='PM' && h24<12) h24 += 12;
  if (ts.ampm==='AM' && h24===12) h24 = 0;
  const val = String(h24).padStart(2,'0') + ':' + String(ts.m).padStart(2,'0');
  document.getElementById('in-'+qi).value = val;
  state.answers[qi] = val;
  document.getElementById('btn-next').disabled = false;
  updateProg();
  nextQ();
}

function onLikert(i, val, btn){
  if (state.advancing) return; // 연타/이중 클릭으로 인한 중복 진행·중복 저장 방지
  state.advancing = true;
  document.querySelectorAll(`#slide-${i} .q-option-btn`).forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
  state.answers[i] = val;
  document.getElementById('btn-next').disabled = false;
  updateProg();
  setTimeout(()=>{ state.advancing = false; nextQ(); }, 260);   // 리커트는 자동 진행
}
function onNum(i, raw, mx){
  let v = parseFloat(raw);
  if (isNaN(v)) { state.answers[i] = ''; }
  else { if (v<0) v=0; if (v>mx) v=mx; state.answers[i] = v; }
  document.getElementById('btn-next').disabled = !isAnswered(i);
  updateProg();
}
function onStep(i, delta, mx){
  const f = document.getElementById('in-'+i);
  let v = parseFloat(f.value); if (isNaN(v)) v = 0;
  v = Math.round((v+delta)*10)/10;
  if (v<0) v=0; if (v>mx) v=mx;
  f.value = v;
  onNum(i, v, mx);
}
function padTwoDigits(i){
  const f = document.getElementById('in-'+i);
  if (f.value === '') return;
  const v = parseInt(f.value, 10);
  if (!isNaN(v)) f.value = String(v).padStart(2, '0');
}

function prevQ(){ if (state.current>0) showSlide(state.current-1); }
function nextQ(){
  if (!isAnswered(state.current)) return;
  if (state.current < TOTAL-1) showSlide(state.current+1);
  else showComplete();
}

// --- 채점 (PHP calculatePSQI 와 동일 규칙) ---
function parseTimeMin(s){
  const m = /^(\d{1,2}):(\d{2})$/.exec((s||'').trim());
  if (!m) return null;
  const h=+m[1], mi=+m[2];
  if (h>23||mi>59) return null;
  return h*60+mi;
}
function psqiScore(a){
  const lik = i => Math.max(0, Math.min(3, parseInt(a[i]||0)));

  const c1 = lik(14);

  const latMin = Math.max(0, parseFloat(a[1]||0));
  let latScore = latMin<=15?0 : latMin<=30?1 : latMin<=60?2 : 3;
  const c2sum = latScore + lik(4);
  const c2 = c2sum===0?0 : c2sum<=2?1 : c2sum<=4?2 : 3;

  const hours = Math.max(0, parseFloat(a[3]||0));
  const c3 = hours>7?0 : hours>=6?1 : hours>=5?2 : 3;

  const bed = parseTimeMin(a[0]), wake = parseTimeMin(a[2]);
  let inBed = null, eff = null;
  if (bed!==null && wake!==null){
    let diff = wake-bed; if (diff<=0) diff += 24*60;
    inBed = diff/60;
    if (inBed>0){ eff = hours/inBed*100; if (eff>100) eff=100; }
  }
  const c4 = eff===null?0 : eff>=85?0 : eff>=75?1 : eff>=65?2 : 3;

  let distSum=0; for (let i=5;i<=13;i++) distSum += lik(i);
  const c5 = distSum===0?0 : distSum<=9?1 : distSum<=18?2 : 3;

  const c6 = lik(15);

  const ddSum = lik(16)+lik(17);
  const c7 = ddSum===0?0 : ddSum<=2?1 : ddSum<=4?2 : 3;

  const comps = [
    {name:'주관적 수면의 질', v:c1}, {name:'수면 잠복기', v:c2},
    {name:'수면 시간', v:c3}, {name:'수면 효율', v:c4},
    {name:'수면 방해', v:c5}, {name:'수면제 사용', v:c6},
    {name:'주간 기능장애', v:c7},
  ];
  const total = c1+c2+c3+c4+c5+c6+c7;
  return { comps, total, eff: eff===null?null:Math.round(eff*10)/10 };
}

const BATTERY      = <?= $battery ? 'true' : 'false' ?>;
const BATTERY_LAST = <?= $isLastStep ? 'true' : 'false' ?>;
const NEXT_URL     = <?= json_encode('run.php?next=1') ?>;
const QUEUE        = <?= json_encode($queue) ?>;
const QPOS         = <?= $qpos ?>;
const NEXT_SCALE   = QUEUE[QPOS + 1] || '';
const PT = {
  birth:   <?= json_encode($birthDate) ?>,
  gender:  <?= json_encode($gender) ?>,
  phone:   <?= json_encode($phone) ?>,
  battery: <?= json_encode($batteryId) ?>
};

function showComplete(){
  if (state.completed) return; // 중복 호출로 인한 중복 자동저장 방지
  state.completed = true;
  const a = [];
  for (let i=0;i<TOTAL;i++){
    if (ITEM_TYPES[i]==='time') a.push(state.answers[i]||'');
    else a.push(state.answers[i]!==undefined ? state.answers[i] : 0);
  }
  const r = psqiScore(a);

  let label='', color='green';
  for (const s of SCORING){ if (r.total>=s.min && r.total<=s.max){ label=s.label; color=s.color; break; } }
  const hex = COLOR_HEX[color] || '#3b6cb7';

  if (typeof tossCountUp === 'function') tossCountUp(document.getElementById('cscore'), r.total, {suffix:' / 21점'});
  else document.getElementById('cscore').textContent = r.total + ' / 21점';
  const cl = document.getElementById('clabel');
  cl.textContent = label; cl.style.color = hex;

  const cn = document.getElementById('cutoffNote');
  if (r.total > CUTOFF){
    cn.className = 'cutoff-note cutoff-poor';
    cn.textContent = `절단점 ${CUTOFF}점 초과 — 수면의 질 저하로 해석`;
  } else {
    cn.className = 'cutoff-note cutoff-good';
    cn.textContent = `절단점 ${CUTOFF}점 이하 — 양호한 수면`;
  }

  // 구성요소 막대
  const list = document.getElementById('compList');
  list.innerHTML = r.comps.map(c=>{
    const w = Math.round(c.v/3*100);
    const barColor = c.v>=2 ? '#c0392b' : (c.v===1 ? '#e0a800' : '#27ae60');
    return `<div class="comp-row">
      <div class="comp-name">${c.name}</div>
      <div class="comp-bar"><div class="comp-fill" style="width:${w}%;background:${barColor}"></div></div>
      <div class="comp-val">${c.v}/3</div>
    </div>`;
  }).join('');

  const effEl = document.getElementById('effNote');
  effEl.textContent = r.eff!==null ? `수면 효율 ${r.eff}% (실제 수면시간 ÷ 침대에 누운 시간)` : '';

  // 연속검사(환자 직접 응답): 불안을 주지 않도록 상세·절단점 표시를 감추고 차분히
  if (BATTERY) {
    cl.textContent = '검사가 완료되었습니다'; cl.style.color = 'var(--primary)';
    cn.className = 'cutoff-note'; cn.textContent = '';
    list.innerHTML = ''; effEl.textContent = '';
  }

  document.querySelectorAll('.question-slide').forEach(e=>e.classList.remove('active'));
  document.getElementById('navbtns').style.display='none';
  document.getElementById('complete').classList.add('active');

  setTimeout(autoSave, 900);
}

// --- 저장 ---
async function doSave(memo){
  const patient = document.getElementById('f_patient').value.trim();
  if (!patient){ window.location='consent.php?step=1'; return; }

  const a = [];
  for (let i=0;i<TOTAL;i++){
    if (ITEM_TYPES[i]==='time') a.push(state.answers[i]||'');
    else a.push(state.answers[i]!==undefined ? state.answers[i] : 0);
  }

  const statusEl = document.getElementById('save-status');
  statusEl.innerHTML = '<span style="color:var(--muted)">💾 저장 중...</span>';
  const csrf = document.getElementById('csrf_token').value;

  try {
    const res = await fetch('save_psqi.php', {
      method:'POST',
      headers:{'Content-Type':'application/json; charset=utf-8'},
      body: JSON.stringify(Object.assign({ csrf_token:csrf, patient_name:patient, answers:a, memo:memo||'', assessment_id: state.savedId || null }, BATTERY ? { birth_date:PT.birth, gender:PT.gender, phone:PT.phone, battery_id:PT.battery } : {}))
    });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e){
      statusEl.innerHTML = `<span style="color:#c0392b;">❌ 서버 응답 오류</span>
        <button onclick="autoSave()" style="margin-left:8px;font-size:.82rem;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
      console.error('Response:', text); return;
    }
    if (data.success){
      statusEl.innerHTML = `<span style="color:#27ae60;font-weight:700;">✅ 저장 완료</span>`;
      if (data.id) state.savedId = data.id;
      if (BATTERY) {
        const nx = document.getElementById('battery-next');
        if (nx) nx.innerHTML = `<a href="${NEXT_URL}" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;padding:14px;">${BATTERY_LAST ? '검사 마치고 결과 보기 →' : ('다음 검사 진행 (' + NEXT_SCALE + ') →')}</a>`;
        const ra = document.querySelector('.result-actions'); if (ra) ra.style.display='none';
        const sn = document.querySelector('.src-note'); if (sn) sn.style.display='none';
      } else {
        document.getElementById('memo-card').style.display = 'block';
      }
    } else {
      statusEl.innerHTML = `<span style="color:#c0392b;">❌ 저장 실패: ${data.message}</span>
        <button onclick="autoSave()" style="margin-left:8px;font-size:.82rem;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
    }
  } catch(e){
    statusEl.innerHTML = `<span style="color:#c0392b;">❌ 네트워크 오류 — 연결을 확인해주세요</span>
      <button onclick="autoSave()" style="margin-left:8px;font-size:.82rem;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>`;
    console.error(e);
  }
}
function autoSave(){ doSave(''); }
function saveWithMemo(){ doSave(document.getElementById('memo').value); }

ITEM_TYPES.forEach((t,qi)=>{ if (t==='time') setupClockFace(qi); });
showSlide(0);
</script>
</body>
</html>
