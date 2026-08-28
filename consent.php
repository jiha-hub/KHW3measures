<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scales.php';
require_once __DIR__ . '/psqi_scoring.php';
require_once __DIR__ . '/patient_store.php';
requireLogin();

$error = '';
$step  = (int)($_GET['step'] ?? 1);

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '잘못된 요청입니다.';
    } else {
        $postStep = (int)($_POST['step'] ?? 1);
        startSession();

        if ($postStep === 1) {
            // 척도 선택 (다중 선택 → 정규 순서로 정렬한 검사 큐)
            $sel = $_POST['scales'] ?? [];
            if (!is_array($sel)) $sel = [];
            $queue = array_values(array_intersect(scaleOrder(), $sel));
            if (!$queue) { $error = '검사를 1개 이상 선택해주세요.'; $step = 1; }
            else {
                $_SESSION['consent_data'] = ['scale_queue' => $queue];
                header('Location: consent.php?step=2'); exit;
            }
        } elseif ($postStep === 2) {
            // 개인정보 동의
            $consent = $_POST['consent'] ?? '';
            if ($consent !== 'yes') { $error = '개인정보 수집·이용에 동의하셔야 검사를 진행할 수 있습니다.'; $step = 2; }
            else {
                $_SESSION['consent_data']['consent']         = $consent;
                $_SESSION['consent_data']['counsel_consent'] = $_POST['counsel_consent'] ?? 'no';
                header('Location: consent.php?step=3'); exit;
            }
        } elseif ($postStep === 3) {
            // 개인정보 입력 (이름 + 생년월일이 식별 기준)
            $patientName = trim($_POST['patient_name'] ?? '');
            $birthRaw    = preg_replace('/[^0-9]/', '', trim($_POST['birth_date'] ?? ''));
            $birthDate   = '';
            if (strlen($birthRaw) === 8) {
                $y = substr($birthRaw,0,4); $m = substr($birthRaw,4,2); $d = substr($birthRaw,6,2);
                if (checkdate((int)$m, (int)$d, (int)$y)) {
                    $birthDate = "$y-$m-$d";
                }
            }
            $gender = $_POST['gender'] ?? '';
            if (!$patientName)      { $error = '이름을 입력해주세요.'; $step = 3; }
            elseif (!$birthDate)    { $error = '생년월일을 8자리 숫자로 정확히 입력해주세요. (예: 19800315)'; $step = 3; }
            elseif (!$gender)       { $error = '성별을 선택해주세요.'; $step = 3; }
            else {
                $cd = $_SESSION['consent_data'];
                $cd['patient_name'] = $patientName;
                $cd['birth_date']   = $birthDate;
                $cd['gender']       = $gender;
                $cd['phone']        = trim($_POST['phone'] ?? '');
                $cd['consent_date'] = date('Y-m-d H:i:s');
                $cd['battery_id']   = newBatteryId();
                $cd['qpos']         = 0;
                $_SESSION['consent_data'] = $cd;
                header('Location: run.php'); exit;   // 라우터가 첫 검사로 보냄
            }
        }
    }
}

// 세션 확인
startSession();
$cd = $_SESSION['consent_data'] ?? [];
// step 접근 가드
if ($step === 2 && empty($cd['scale_queue'])) { header('Location: consent.php?step=1'); exit; }
if ($step === 3 && empty($cd['consent']))     { header('Location: consent.php?step=2'); exit; }

$scales    = getScales();
$csrf      = getCsrfToken();
$stepLabels = ['척도 선택', '개인정보 동의', '정보 입력'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>검사 시작 — <?= APP_NAME ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;
  --text:#1a2236;--muted:#6b7a99;--border:#dce3ef;
  --radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);
}
html,body{height:100%;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
body{background:var(--bg);display:flex;flex-direction:column;min-height:100vh;}

/* 헤더 */
.header{background:#fff;color:#111827;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;border-bottom:1px solid #e5e7eb;}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:6px;align-items:center;}
.header-nav a{color:#4b5563;text-decoration:none;font-size:.82rem;padding:6px 12px;border-radius:8px;transition:background .2s,color .2s;font-weight:500;}
.header-nav a:hover{background:#f3f4f6;}
.header-nav a.active{background:#eff6ff;color:#2563eb;font-weight:700;}
.admin-badge{font-size:.78rem;color:#9ca3af;}

/* 메인 */
.main{flex:1;display:flex;flex-direction:column;padding:20px 16px 16px;max-width:960px;width:100%;margin:0 auto;}

/* 스텝 인디케이터 */
.step-bar{display:flex;align-items:center;gap:0;margin-bottom:20px;}
.step-item{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;}
.step-item:not(:last-child)::after{content:'';position:absolute;top:14px;left:50%;width:100%;height:2px;background:var(--border);z-index:0;}
.step-item.done::after{background:var(--primary);}
.step-circle{width:30px;height:30px;border-radius:50%;border:2px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--muted);position:relative;z-index:1;transition:all .3s;letter-spacing:-.02em;}
.step-item.active .step-circle{border-color:var(--primary);background:var(--primary);color:#fff;}
.step-item.done .step-circle{border-color:var(--primary);background:var(--primary);color:#fff;}
.step-label{font-size:.72rem;color:var(--muted);margin-top:5px;font-weight:500;}
.step-item.active .step-label{color:var(--primary);font-weight:700;}

/* 카드 */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;flex:1;display:flex;flex-direction:column;}
.card-title{font-size:1.05rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid var(--bg);}

/* 척도 선택 그리드 */
.scale-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;justify-items:center;}
.scale-option{position:relative;width:100%;max-width:200px;aspect-ratio:1/1;border:1px solid #e5e7eb;border-top:4px solid var(--sclr,#e5e7eb);border-radius:16px;padding:14px;cursor:pointer;transition:transform .15s ease,border-color .2s ease,background-color .2s ease,box-shadow .2s ease;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:6px;}
.scale-option:hover{border-color:#2563eb;transform:translateY(-2px);box-shadow:0 6px 16px rgba(37,99,235,.12);}
.scale-option.selected{border-color:#2563eb;background:#eff6ff;box-shadow:0 0 0 1px #2563eb inset;}
.scale-option input{display:none;}
.scale-check{position:absolute;top:10px;right:10px;width:22px;height:22px;border-radius:6px;border:2px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:.76rem;color:#fff;background:#fff;flex-shrink:0;transition:all .15s;}
.scale-option.selected .scale-check{background:#2563eb;border-color:#2563eb;}
.scale-info strong{display:block;font-size:1.25rem;color:#111827;margin-bottom:2px;}
.scale-info span{font-size:.78rem;color:#6b7280;}
.scale-count{font-size:.72rem;color:#6b7280;background:#f3f4f6;padding:3px 9px;border-radius:20px;white-space:nowrap;}

/* 동의서 */
.consent-box{background:#f8fafd;border:1.5px solid var(--border);border-radius:10px;padding:16px 18px;margin-bottom:16px;font-size:.875rem;line-height:1.8;}
.consent-box h3{font-size:.9rem;font-weight:700;margin-bottom:10px;color:var(--primary);}
.ci{display:flex;gap:8px;margin-bottom:4px;}
.ci::before{content:'•';color:var(--primary);flex-shrink:0;}
.consent-q{font-weight:700;margin:14px 0 8px;font-size:.875rem;}
.c-choice{display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid var(--border);border-radius:9px;cursor:pointer;margin-bottom:8px;background:#fff;transition:all .2s;}
.c-choice:hover{border-color:var(--primary);}
.c-choice input{accent-color:var(--primary);width:16px;height:16px;flex-shrink:0;}
.c-choice label{font-size:.875rem;font-weight:600;cursor:pointer;}
.c-sub{font-size:.78rem;color:var(--muted);font-weight:400;}

/* 정보 입력 폼 */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-group{margin-bottom:14px;}
.form-group.full{grid-column:1/-1;}
.form-group label{display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;}
.req{color:#c0392b;margin-left:2px;}
input[type=text],input[type=tel],select{width:100%;padding:13px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:1rem;color:var(--text);background:#fafbfd;outline:none;font-family:inherit;transition:border-color .2s;min-height:46px;}
input:focus,select:focus{border-color:var(--primary);background:#fff;}
.date-disp{padding:10px 13px;background:var(--bg);border-radius:8px;font-size:.875rem;color:var(--muted);}

/* 버튼 */
.btn-row{display:flex;gap:10px;margin-top:auto;padding-top:16px;}
.btn-row.center{justify-content:center;margin-top:0;}
.btn{padding:13px 0;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;flex:1;text-align:center;}
.btn-cta{flex:none;min-width:260px;padding:14px 0;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.gender-radio-group{display:flex;gap:10px;}
.gender-radio{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;min-height:46px;border:2px solid var(--border);border-radius:8px;cursor:pointer;background:#fff;transition:all .2s;font-size:.95rem;font-weight:600;}
.gender-radio:hover{border-color:var(--primary);}
.gender-radio.selected{border-color:var(--primary);background:#eef2fb;color:var(--primary);}
.gender-radio input{display:none;}
.alert-error{padding:10px 14px;border-radius:8px;font-size:.85rem;background:#fdedec;border:1px solid #f1948a;color:#c0392b;margin-bottom:14px;}

/* 점진적 공개(progressive disclosure) 입력 */
.pd-hidden{display:none;}
.pd-stage{animation:fadeSlideUp .35s ease both;}

/* 태블릿 이상 */
@media(min-width:600px){
  .main{padding:24px 24px 20px;}
}

/* 데스크톱: 4개 척도를 한 줄에 */
@media(min-width:900px){
  .scale-grid{grid-template-columns:1fr 1fr 1fr 1fr;}
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

<div class="main">
  <!-- 스텝 바 -->
  <div class="step-bar">
    <?php foreach ($stepLabels as $i => $label): 
      $num = $i + 1;
      $cls = $num < $step ? 'done' : ($num === $step ? 'active' : '');
    ?>
    <div class="step-item <?= $cls ?>">
      <div class="step-circle"><?= $num < $step ? '✓' : sprintf('%02d', $num) ?></div>
      <div class="step-label"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- STEP 1: 척도 선택 -->
  <?php if ($step === 1): ?>
  <form method="post" action="consent.php" style="display:flex;flex-direction:column;flex:1;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="step" value="1">
    <div class="card">
      <div class="card-title">📊 검사 척도를 선택하세요 <span style="font-weight:500;font-size:.8rem;color:var(--muted);">(여러 개 선택 가능)</span></div>
      <div class="scale-grid" id="scaleGrid">
        <?php
        $meta = [
          'PHQ-9'  => ['우울 선별','9문항 / 0~27점','#b93d6c','#fff0f5'],
          'GAD-7'  => ['불안 선별','7문항 / 0~21점','#8a6100','#fff8e6'],
          'PSS-10' => ['스트레스 측정','10문항 / 0~40점','#1b7a43','#f0fff4'],
          'PSQI-K' => ['수면의 질','수면 문항 / 0~21점','#6c5ce7','#f2effc'],
          'CSEI-s' => ['핵심칠정 감정','28문항 / 7감정 T점수','#0b8f8a','#e6f7f6'],
        ];
        $preSel = $cd['scale_queue'] ?? [];
        foreach (scaleOrder() as $key):
          if (!isset($meta[$key])) continue;
          $m = $meta[$key];
          $checked = in_array($key, $preSel, true);
        ?>
        <label class="scale-option <?= $checked ? 'selected' : '' ?>" id="opt-<?= $key ?>" style="--sclr:<?= $m[2] ?>;--stint:<?= $m[3] ?>;" onclick="toggleScale(event,'<?= $key ?>')">
          <input type="checkbox" name="scales[]" value="<?= $key ?>" id="chk-<?= $key ?>" <?= $checked ? 'checked' : '' ?>>
          <div class="scale-check">✓</div>
          <div class="scale-info">
            <strong><?= $key ?></strong>
            <span><?= $m[0] ?></span>
          </div>
          <span class="scale-count"><?= $m[1] ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <p style="font-size:.8rem;color:var(--muted);margin-top:12px;line-height:1.6;">여러 개를 선택하면 환자가 <strong>순서대로 이어서</strong> 검사합니다. 순서: PHQ-9 → GAD-7 → PSS-10 → PSQI-K → CSEI-s</p>
      <div class="btn-row center">
        <button type="submit" class="btn btn-primary btn-cta">다음 단계로 →</button>
      </div>
    </div>
  </form>

  <!-- STEP 2: 개인정보 동의 -->
  <?php elseif ($step === 2): ?>
  <form method="post" action="consent.php" style="display:flex;flex-direction:column;flex:1;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="step" value="2">
    <div class="card" style="overflow-y:auto;">
      <div class="card-title">📋 개인정보 수집·이용 동의</div>
      <div class="consent-box">
        <h3>개인정보 수집·이용 안내</h3>
        <div class="ci">수집 항목: 이름, 성별, 나이, 연락처</div>
        <div class="ci">수집 목적: 정신건강 척도 검사 결과 관리 및 진료 보조</div>
        <div class="ci">보유 및 이용기간: 정보 수집일로부터 2년</div>
        <div class="ci">개인정보는 진료 목적 외에 사용되지 않습니다.</div>
      </div>
      <p class="consent-q">☞ 위와 같이 개인정보를 수집·이용하는데 동의하십니까? <span class="req">*</span></p>
      <label class="c-choice"><input type="radio" name="consent" value="yes" required><label>예, 동의합니다.</label></label>
      <label class="c-choice"><input type="radio" name="consent" value="no"><div><label>아니오, 동의하지 않습니다.</label><div class="c-sub">동의하지 않으실 경우 검사를 진행할 수 없습니다.</div></div></label>
      <p class="consent-q">☞ 필요 시 상담을 받는 데 동의하십니까?</p>
      <label class="c-choice"><input type="radio" name="counsel_consent" value="yes"><label>예, 동의합니다.</label></label>
      <label class="c-choice"><input type="radio" name="counsel_consent" value="no"><label>아니오, 동의하지 않습니다.</label></label>
      <div class="btn-row">
        <a href="consent.php?step=1" class="btn btn-secondary">← 이전</a>
        <button type="submit" class="btn btn-primary">다음 →</button>
      </div>
    </div>
  </form>

  <!-- STEP 3: 개인정보 입력 (한 번에 한 항목씩 진행) -->
  <?php elseif ($step === 3): ?>
  <form method="post" action="consent.php" style="display:flex;flex-direction:column;flex:1;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="step" value="3">
    <div class="card">
      <div class="card-title" style="display:flex;align-items:center;justify-content:space-between;">
        <span>👤 기본 정보 입력</span>
        <a href="consent.php?step=2" style="font-size:.78rem;font-weight:600;color:var(--muted);text-decoration:none;">← 이전</a>
      </div>
      <div class="form-grid">
        <div class="form-group full pd-stage" data-pd="1">
          <label for="patient_name">이름 <span class="req">*</span></label>
          <input type="text" id="patient_name" name="patient_name" required placeholder="이름을 입력하세요" autocomplete="off"
                 onblur="if(this.value.trim())pdReveal(2)"
                 onkeydown="if(event.key==='Enter'){event.preventDefault();if(this.value.trim())pdReveal(2);}">
        </div>
        <div class="form-group full pd-stage pd-hidden" data-pd="2">
          <label>생년월일 <span class="req">*</span></label>
          <input type="text" name="birth_date" id="birth_date" required
                 inputmode="numeric" maxlength="10" pattern="[0-9]{4}-?[0-9]{2}-?[0-9]{2}"
                 placeholder="예: 19800315" autocomplete="off"
                 style="letter-spacing:1px;font-variant-numeric:tabular-nums;">
          <div id="birth_hint" style="font-size:.75rem;color:var(--muted);margin-top:4px;">8자리 숫자 입력 (예: 19800315)</div>
        </div>
        <div class="form-group full pd-stage pd-hidden" data-pd="3">
          <label>성별 <span class="req">*</span></label>
          <div class="gender-radio-group">
            <label class="gender-radio" id="gr-m">
              <input type="radio" name="gender" value="남" required onchange="selectGender('gr-m');pdReveal(4)">
              <span>남</span>
            </label>
            <label class="gender-radio" id="gr-f">
              <input type="radio" name="gender" value="여" required onchange="selectGender('gr-f');pdReveal(4)">
              <span>여</span>
            </label>
          </div>
        </div>
        <div class="form-group full pd-stage pd-hidden" data-pd="4">
          <label>연락처 <span style="font-weight:400;color:var(--muted);font-size:.78rem;">(선택)</span></label>
          <input type="tel" name="phone" id="phone" placeholder="010-0000-0000" inputmode="numeric" maxlength="13" autocomplete="off">
        </div>
      </div>
      <div class="btn-row center pd-stage pd-hidden" data-pd="4">
        <button type="submit" class="btn btn-primary btn-cta">검사 시작 →</button>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
function toggleScale(ev, key) {
  ev.preventDefault();
  const chk = document.getElementById('chk-' + key);
  chk.checked = !chk.checked;
  document.getElementById('opt-' + key).classList.toggle('selected', chk.checked);
}

function selectGender(id) {
  document.querySelectorAll('.gender-radio').forEach(el => el.classList.remove('selected'));
  const el = document.getElementById(id);
  if (el) el.classList.add('selected');
}

// 점진적 공개: 다음 항목을 부드럽게 펼치고 포커스 이동
function pdReveal(n) {
  document.querySelectorAll('.pd-stage[data-pd="' + n + '"]').forEach(el => el.classList.remove('pd-hidden'));
  const target = document.querySelector('.pd-stage[data-pd="' + n + '"]');
  if (!target) return;
  requestAnimationFrame(() => {
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const input = target.querySelector('input[type=text],input[type=tel]');
    if (input) input.focus({ preventScroll: true });
  });
}

// 생년월일 8자리 숫자 입력 처리
(function(){
  const inp = document.getElementById('birth_date');
  const hint = document.getElementById('birth_hint');
  if (!inp) return;

  inp.addEventListener('input', function(e) {
    // 숫자만 남기기
    let digits = this.value.replace(/[^0-9]/g, '');
    if (digits.length > 8) digits = digits.slice(0, 8);

    // 자동 하이픈 포매팅 (표시용)
    let formatted = digits;
    if (digits.length >= 5) {
      formatted = digits.slice(0,4) + '-' + digits.slice(4);
    }
    if (digits.length >= 7) {
      formatted = digits.slice(0,4) + '-' + digits.slice(4,6) + '-' + digits.slice(6);
    }
    this.value = formatted;

    // 유효성 검증
    if (digits.length === 8) {
      const y = parseInt(digits.slice(0,4));
      const m = parseInt(digits.slice(4,6));
      const d = parseInt(digits.slice(6,8));
      const dt = new Date(y, m-1, d);
      const today = new Date();
      if (dt.getFullYear()===y && dt.getMonth()===m-1 && dt.getDate()===d && dt <= today && y >= 1900) {
        hint.textContent = `✅ ${y}년 ${m}월 ${d}일`;
        hint.style.color = '#27ae60';
        inp.style.borderColor = '#27ae60';
        if (typeof pdReveal === 'function') pdReveal(3);
      } else {
        hint.textContent = '❌ 유효하지 않은 날짜입니다';
        hint.style.color = '#c0392b';
        inp.style.borderColor = '#c0392b';
      }
    } else if (digits.length > 0) {
      hint.textContent = `${digits.length}/8자리 입력 중...`;
      hint.style.color = 'var(--muted)';
      inp.style.borderColor = 'var(--border)';
    } else {
      hint.textContent = '8자리 숫자 입력 (예: 19800315)';
      hint.style.color = 'var(--muted)';
      inp.style.borderColor = 'var(--border)';
    }
  });
})();

// 연락처 자동 하이픈 포매팅
(function(){
  const inp = document.getElementById('phone');
  if (!inp) return;
  inp.addEventListener('input', function() {
    let d = this.value.replace(/[^0-9]/g, '');
    if (d.length > 11) d = d.slice(0, 11);

    let formatted = d;
    if (d.startsWith('02')) {
      if (d.length > 9)      formatted = d.slice(0,2) + '-' + d.slice(2,6) + '-' + d.slice(6,10);
      else if (d.length > 5) formatted = d.slice(0,2) + '-' + d.slice(2,5) + '-' + d.slice(5,9);
      else if (d.length > 2) formatted = d.slice(0,2) + '-' + d.slice(2);
    } else {
      if (d.length > 10)     formatted = d.slice(0,3) + '-' + d.slice(3,7) + '-' + d.slice(7,11);
      else if (d.length > 6) formatted = d.slice(0,3) + '-' + d.slice(3,6) + '-' + d.slice(6,10);
      else if (d.length > 3) formatted = d.slice(0,3) + '-' + d.slice(3);
    }
    this.value = formatted;
  });
})();
</script>
</body>
</html>
