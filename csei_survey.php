<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csei.php';
requireLogin();
startSession();

$consentData = $_SESSION['consent_data'] ?? null;
// consent/연속검사 흐름 밖에서 직접 들어온 경우 가드
if (in_array($_GET['from'] ?? '', ['consent','battery'], true) && empty($consentData['patient_name'])) {
    header('Location: consent.php?step=1'); exit;
}
if (empty($consentData['patient_name'])) { header('Location: consent.php?step=1'); exit; }

$scale = getCseiScale();
$csrf  = getCsrfToken();

$initPatient  = $consentData['patient_name'] ?? '';
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
$TOTAL        = count($scale['questions']);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CSEI-s 핵심칠정 감정 검사 — <?= APP_NAME ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;--text:#1a2236;--muted:#6b7a99;--border:#dce3ef;--radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);--severe:#4b5563;}
/* ===== 2단 레이아웃(사이드바 + 문항) ===== */
.layout{flex:1;display:flex;gap:18px;max-width:1080px;width:100%;margin:0 auto;padding:16px;align-items:flex-start;}
.sidebar{width:300px;flex-shrink:0;position:sticky;top:72px;display:flex;flex-direction:column;gap:12px;}
.content{flex:1;min-width:0;display:flex;flex-direction:column;}
.sb-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;}
.sb-scale-name{font-size:1.5rem;font-weight:800;color:#0b8f8a;line-height:1.2;}
.sb-scale-full{font-size:.78rem;color:var(--muted);margin-top:6px;line-height:1.5;}
.sb-period-box{margin-top:14px;background:#e6f7f6;border:1.5px solid #0b8f8a;border-radius:12px;padding:12px 14px;text-align:center;}
.sb-period-label{font-size:.72rem;color:var(--muted);font-weight:700;letter-spacing:.03em;}
.sb-period-value{font-size:1.15rem;font-weight:800;color:#0b8f8a;margin-top:2px;}
.sb-guide-title{font-size:.74rem;font-weight:800;color:var(--muted);margin:16px 0 6px;letter-spacing:.03em;}
.sb-guide{background:#f8fafd;border-left:4px solid #0b8f8a;border-radius:0 8px 8px 0;padding:11px 13px;font-size:.86rem;color:var(--text);line-height:1.65;}
.sb-progress{margin-top:14px;}
.sb-progress-label{display:flex;justify-content:space-between;font-size:.76rem;color:var(--muted);margin-bottom:6px;font-weight:600;}
.sb-progress-bar{height:8px;background:var(--border);border-radius:99px;overflow:hidden;}
.sb-progress-fill{height:100%;background:#0b8f8a;border-radius:99px;transition:width .3s;}
.sb-patient{font-size:.82rem;color:var(--muted);display:flex;flex-wrap:wrap;gap:4px 8px;align-items:center;margin-bottom:10px;}
.sb-patient b{color:var(--text);}
.sb-patient .ok{color:#16a34a;font-weight:700;}
.cancel-btn{width:100%;padding:11px;border-radius:10px;border:1.5px solid var(--border);background:#f8fafd;color:var(--muted);font-family:inherit;font-size:.86rem;font-weight:700;cursor:pointer;transition:all .2s;}
.cancel-btn:hover{border-color:var(--severe);color:var(--severe);}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal-box{background:#fff;border-radius:16px;max-width:380px;width:100%;padding:24px;box-shadow:0 12px 48px rgba(0,0,0,.3);text-align:center;}
.modal-box h3{font-size:1.1rem;font-weight:800;margin-bottom:10px;}
.modal-box p{font-size:.9rem;color:var(--muted);line-height:1.6;margin-bottom:20px;}
.modal-btns{display:flex;gap:10px;}.modal-btns .btn{flex:1;}
body.font-lg .sb-guide{font-size:1rem !important;}
body.font-lg .sb-period-value{font-size:1.3rem !important;}
@media(max-width:860px){
  .layout{flex-direction:column;gap:12px;padding:12px;}
  .sidebar{width:100%;position:static;top:auto;}
  .sb-scale-name{font-size:1.3rem;}
}
html,body{height:100%;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
body{background:var(--bg);display:flex;flex-direction:column;min-height:100vh;}
.header{background:#fff;color:#111827;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;border-bottom:1px solid #e5e7eb;}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:6px;align-items:center;}
.header-nav a{color:#4b5563;text-decoration:none;font-size:.82rem;padding:6px 12px;border-radius:8px;font-weight:500;transition:background .2s,color .2s;}
.header-nav a:hover{background:#f3f4f6;}
.header-nav a.active{background:#eff6ff;color:#2563eb;font-weight:700;}
.admin-badge{font-size:.75rem;color:#9ca3af;}
.main{flex:1;display:flex;flex-direction:column;padding:16px;max-width:700px;width:100%;margin:0 auto;}
.info-bar{display:flex;align-items:center;justify-content:space-between;background:var(--card);border-radius:10px;padding:12px 18px;box-shadow:var(--shadow);margin-bottom:12px;flex-wrap:wrap;gap:8px;}
.info-patient{font-size:.95rem;font-weight:700;}
.info-meta{display:flex;gap:14px;font-size:.82rem;color:var(--muted);align-items:center;}
.scale-tag{display:inline-block;padding:3px 8px;border-radius:4px;font-size:.72rem;font-weight:700;background:#e6f7f6;color:#0b8f8a;}
.info-badge{background:#eef2fb;color:var(--primary);padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;}
.battery-chip{background:#eef2fb;color:var(--primary);padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:12px;}
.progress-wrap{margin-bottom:16px;}
.progress-label{display:flex;justify-content:space-between;font-size:.78rem;color:var(--muted);margin-bottom:6px;}
.progress-bar{height:5px;background:var(--border);border-radius:99px;overflow:hidden;}
.progress-fill{height:100%;background:var(--primary);border-radius:99px;transition:width .3s;}
.instruction{background:#eef2fb;border-left:4px solid var(--primary);padding:10px 14px;border-radius:0 8px 8px 0;font-size:.85rem;color:var(--text);margin-bottom:14px;line-height:1.6;}
.question-slide{display:none;}
.question-slide.active{display:block;}
.q-card{background:#f8fafd;border:1.5px solid var(--border);border-radius:11px;padding:20px 18px;margin-bottom:12px;text-align:center;}
.q-number{font-size:.75rem;font-weight:700;color:var(--primary);margin-bottom:10px;letter-spacing:.05em;}
.q-text{font-size:1.05rem;font-weight:600;line-height:1.65;color:var(--text);margin-bottom:18px;word-break:keep-all;}
/* 터치용 음영 세그먼트 5점 리커트 (기존 척도와 동일) */
.q-options{display:flex;gap:6px;}
.q-option-btn{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:6px;padding:0;border:none;background:none;cursor:pointer;font-family:inherit;-webkit-tap-highlight-color:transparent;}
.seg-block{width:100%;min-height:72px;border-radius:16px;background:var(--seg-bg);display:flex;align-items:center;justify-content:center;transition:transform .15s ease,box-shadow .2s ease;}
.seg-check{color:var(--seg-text);font-size:1.5rem;font-weight:800;opacity:0;transform:scale(.5);transition:all .2s ease;}
.q-option-btn:hover .seg-block{transform:translateY(-2px);}
.q-option-btn.selected .seg-block{box-shadow:0 0 0 3px var(--card),0 0 0 5px var(--seg-bg),0 6px 16px rgba(0,0,0,.2);transform:scale(1.04);}
.q-option-btn.selected .seg-check{opacity:1;transform:scale(1);}
.seg-label{font-size:.88rem;font-weight:600;color:var(--muted);text-align:center;line-height:1.3;}
.q-option-btn.selected .seg-label{color:var(--text);font-weight:800;}
.complete-screen{display:none;text-align:center;padding:20px 0;}
.complete-screen.active{display:block;}
.complete-icon{font-size:2.5rem;margin-bottom:10px;}
.save-status-box{min-height:28px;padding:6px 0;font-size:.9rem;}
.nav-btns{display:flex;gap:10px;margin-top:8px;}
.btn{padding:12px 0;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;flex:1;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.btn:disabled{opacity:.4;cursor:not-allowed;}
.zoom-btn,.float-btn{position:fixed;right:20px;width:48px;height:48px;border-radius:50%;background:var(--primary);color:#fff;border:none;font-size:1.3rem;cursor:pointer;box-shadow:0 4px 12px rgba(59,108,183,.4);display:flex;align-items:center;justify-content:center;z-index:999;transition:all .2s;text-decoration:none;}
.zoom-btn{bottom:20px;}
.zoom-btn:hover,.float-btn:hover{background:var(--primary-dark);transform:scale(1.1);}
.zoom-tooltip{position:fixed;bottom:76px;right:14px;background:#1a2236;color:#fff;border-radius:8px;padding:8px 12px;font-size:.8rem;display:none;white-space:nowrap;z-index:999;}
body.font-lg .q-text{font-size:1.3rem !important;}
body.font-lg .seg-label{font-size:1.05rem !important;font-weight:700 !important;}
body.font-lg .seg-block{min-height:84px !important;}
body.font-lg .instruction{font-size:1rem !important;}
@media(min-width:768px){
  .main{padding:20px 24px;}
  .seg-block{min-height:88px;}
  .seg-label{font-size:.95rem;}
}
@media(max-width:420px){
  .q-options{gap:4px;}
  .seg-block{min-height:60px;border-radius:12px;}
  .seg-label{font-size:.72rem;}
}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
</head>
    <?php include __DIR__ . '/ui_settings.php'; ?>
<body>
<header class="header">
  <h1><a href="consent.php" style="color:#111827;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php" class="active">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="graph.php">그래프</a>
    <a href="admin.php">설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">로그아웃</a>
  </nav>
</header>

<div class="layout">
  <!-- ===== 왼쪽 사이드바 ===== -->
  <aside class="sidebar">
    <div class="sb-card">
      <div class="sb-scale-name">🌀 CSEI-s</div>
      <div class="sb-scale-full">핵심칠정척도 단축형 · 한의학 칠정(七情) 기반 감정평가</div>
      <div class="sb-period-box">
        <div class="sb-period-label">응답 기간</div>
        <div class="sb-period-value">최근 일주일 동안</div>
      </div>
      <div class="sb-guide-title">📝 문항 안내</div>
      <div class="sb-guide"><?= htmlspecialchars($scale['instruction']) ?></div>
      <div class="sb-progress">
        <div class="sb-progress-label"><span id="ptxt">1 / <?= $TOTAL ?></span><span id="ppct">0%</span></div>
        <div class="sb-progress-bar"><div class="sb-progress-fill" id="pfill" style="width:0%"></div></div>
      </div>
    </div>
    <div class="sb-card">
      <?php if ($initPatient): ?>
      <div class="sb-patient">
        <b><?= htmlspecialchars($initPatient) ?></b>
        <?php if ($gender): ?><span><?= htmlspecialchars($gender) ?></span><?php endif; ?>
        <?php if ($battery && $batteryTotal > 1): ?><span>· 검사 <?= $batteryStep ?>/<?= $batteryTotal ?></span><?php endif; ?>
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
  <div class="card">

    <?php
    // 음영 세그먼트: 응답값 오름차순(1→5)으로 배치, 연한 파랑→진한 네이비
    $optPairs = [];
    foreach ($scale['options'] as $oi => $lab) { $optPairs[] = ['v' => $scale['option_values'][$oi], 'label' => $lab]; }
    usort($optPairs, fn($a, $b) => $a['v'] <=> $b['v']);
    $segColors   = ['#E0F2FE','#BAE6FD','#3B82F6','#1D4ED8','#0F172A'];
    $segTextDark = [true,true,true,false,false];
    ?>
    <?php foreach ($scale['questions'] as $qi => $question): ?>
    <div class="question-slide <?= $qi===0?'active':'' ?>" id="slide-<?= $qi ?>">
      <div class="q-card">
        <div class="q-number">문항 <?= $qi+1 ?> / <?= $TOTAL ?></div>
        <div class="q-text"><?= htmlspecialchars($question) ?></div>
        <div class="q-options">
          <?php foreach ($optPairs as $si => $opt):
            $segBg   = $segColors[$si] ?? '#3B82F6';
            $segText = ($segTextDark[$si] ?? false) ? '#0f172a' : '#fff';
          ?>
          <button type="button" class="q-option-btn"
                  style="--seg-bg:<?= $segBg ?>;--seg-text:<?= $segText ?>;"
                  data-qi="<?= $qi ?>" data-value="<?= $opt['v'] ?>"
                  onclick="selectAnswer(this)">
            <span class="seg-block"><span class="seg-check">✓</span></span>
            <span class="seg-label"><?= htmlspecialchars($opt['label']) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- 완료 -->
    <div class="complete-screen" id="complete">
      <div class="complete-icon">🌀</div>
      <div style="font-size:1.05rem;font-weight:700;color:var(--primary);margin-bottom:14px;">응답이 완료되었습니다</div>
      <div id="save-status" class="save-status-box"></div>
      <div id="battery-next" style="margin-top:8px;"></div>
    </div>

    <div class="nav-btns" id="navbtns">
      <button type="button" class="btn btn-secondary" id="btn-prev" onclick="prevQ()" disabled>← 이전</button>
      <button type="button" class="btn btn-primary"   id="btn-next" onclick="nextQ()" disabled>다음 →</button>
    </div>
  </div>

  <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" id="f_patient" value="<?= htmlspecialchars($initPatient) ?>">
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

<script>
const TOTAL = <?= $TOTAL ?>;
const state = { current:0, answers:{}, advancing:false, completed:false, savedId:null };

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

function showSlide(idx){
  document.querySelectorAll('.question-slide').forEach(e=>e.classList.remove('active'));
  document.getElementById('complete').classList.remove('active');
  document.getElementById('navbtns').style.display='flex';
  const slide=document.getElementById('slide-'+idx);
  if(slide){
    slide.classList.add('active');
    if(state.answers[idx]!==undefined){
      slide.querySelectorAll('.q-option-btn').forEach(b=>b.classList.toggle('selected', parseInt(b.dataset.value)===state.answers[idx]));
    }
  }
  document.getElementById('btn-prev').disabled = idx===0;
  const nv=document.getElementById('btn-next');
  nv.disabled = state.answers[idx]===undefined;
  nv.textContent = idx===TOTAL-1 ? '완료 →' : '다음 →';
}

function selectAnswer(btn){
  if(state.advancing) return;
  const qi=parseInt(btn.dataset.qi), val=parseInt(btn.dataset.value);
  document.querySelectorAll('#slide-'+qi+' .q-option-btn').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
  state.answers[qi]=val;
  document.getElementById('btn-next').disabled=false;
  updateProg();
  state.advancing=true;
  setTimeout(()=>{ state.advancing=false; nextQ(); }, 260);
}

function prevQ(){ if(state.current>0){ state.current--; showSlide(state.current); updateProg(); } }
function nextQ(){
  if(state.answers[state.current]===undefined) return;
  if(state.current<TOTAL-1){ state.current++; showSlide(state.current); updateProg(); }
  else showComplete();
}
function updateProg(){
  const answered=Object.keys(state.answers).length;
  const pct=Math.round(answered/TOTAL*100);
  document.getElementById('pfill').style.width=pct+'%';
  document.getElementById('ptxt').textContent=(state.current+1)+' / '+TOTAL;
  document.getElementById('ppct').textContent=pct+'%';
}

function showComplete(){
  if(state.completed) return;
  state.completed=true;
  document.querySelectorAll('.question-slide').forEach(e=>e.classList.remove('active'));
  document.getElementById('navbtns').style.display='none';
  document.getElementById('complete').classList.add('active');
  setTimeout(save, 700);
}

async function save(){
  const patient = document.getElementById('f_patient').value.trim();
  if(!patient){ window.location='consent.php?step=1'; return; }
  const a=[];
  for(let i=0;i<TOTAL;i++) a.push(state.answers[i]!==undefined?state.answers[i]:3);
  const statusEl=document.getElementById('save-status');
  statusEl.innerHTML='<span style="color:var(--muted)">💾 결과를 분석·저장하는 중...</span>';
  const csrf=document.getElementById('csrf_token').value;
  try{
    const res=await fetch('csei_save.php',{
      method:'POST',
      headers:{'Content-Type':'application/json; charset=utf-8'},
      body:JSON.stringify(Object.assign(
        { csrf_token:csrf, patient_name:patient, answers:a, memo:'', assessment_id: state.savedId||null },
        { birth_date:PT.birth, gender:PT.gender, phone:PT.phone, battery_id:PT.battery }
      ))
    });
    const text=await res.text();
    let data;
    try{ data=JSON.parse(text); }
    catch(e){ statusEl.innerHTML='<span style="color:#c0392b;">❌ 서버 응답 오류</span> <button onclick="save()" style="margin-left:8px;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>'; console.error('Response:',text); return; }
    if(data.success){
      if(data.id) state.savedId=data.id;
      statusEl.innerHTML='<span style="color:#27ae60;font-weight:700;">✅ 저장 완료</span>';
      const nx=document.getElementById('battery-next');
      if(BATTERY){
        nx.innerHTML=`<a href="${NEXT_URL}" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;padding:14px;">${BATTERY_LAST ? '검사 마치고 결과 보기 →' : ('다음 검사 진행 (' + NEXT_SCALE + ') →')}</a>`;
      } else {
        nx.innerHTML=`<a href="csei_result.php?id=${data.id}" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;padding:14px;">결과 화면 보기 →</a>`;
      }
    } else {
      statusEl.innerHTML='<span style="color:#c0392b;">❌ 저장 실패: '+(data.message||'')+'</span> <button onclick="save()" style="margin-left:8px;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>';
    }
  }catch(e){
    statusEl.innerHTML='<span style="color:#c0392b;">❌ 네트워크 오류 — 연결을 확인해주세요</span> <button onclick="save()" style="margin-left:8px;border:none;background:none;color:var(--primary);cursor:pointer;font-weight:700;">다시 시도</button>';
    console.error(e);
  }
}

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

showSlide(0); updateProg();
</script>

<a href="consent.php?step=1" class="float-btn" style="bottom:76px;" title="새 검사 입력">✏️</a>
<div class="zoom-tooltip" id="zoomTooltip">글자 크게/작게</div>
<button class="zoom-btn" id="zoomBtn" onclick="toggleZoom()" title="글자 크기 조절">🔍</button>
<script>
function toggleZoom(){
  const body=document.body,btn=document.getElementById('zoomBtn'),tip=document.getElementById('zoomTooltip');
  if(body.classList.contains('font-lg')){body.classList.remove('font-lg');btn.textContent='🔍';tip.textContent='글자 크게';}
  else{body.classList.add('font-lg');btn.textContent='🔎';tip.textContent='글자 작게';}
  tip.style.display='block';setTimeout(()=>tip.style.display='none',1500);
}
</script>
</body>
</html>
