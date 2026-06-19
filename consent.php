<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scales.php';
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
            // 척도 선택
            $scaleType = $_POST['scale_type'] ?? '';
            if (!$scaleType) { $error = '척도를 선택해주세요.'; $step = 1; }
            else {
                $_SESSION['consent_data'] = ['scale_type' => $scaleType];
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
            // 개인정보 입력
            $patientName = trim($_POST['patient_name'] ?? '');
            if (!$patientName) { $error = '이름을 입력해주세요.'; $step = 3; }
            else {
                $_SESSION['consent_data']['patient_name'] = $patientName;
                $_SESSION['consent_data']['gender']       = $_POST['gender'] ?? '';
                $_SESSION['consent_data']['age']          = trim($_POST['age'] ?? '');
                $_SESSION['consent_data']['phone']        = trim($_POST['phone'] ?? '');
                $_SESSION['consent_data']['consent_date'] = date('Y-m-d H:i:s');
                header('Location: index.php?from=consent'); exit;
            }
        }
    }
}

// 세션 확인
startSession();
$cd = $_SESSION['consent_data'] ?? [];
// step 접근 가드
if ($step === 2 && empty($cd['scale_type'])) { header('Location: consent.php?step=1'); exit; }
if ($step === 3 && empty($cd['consent']))    { header('Location: consent.php?step=2'); exit; }

$scales    = getScales();
$csrf      = getCsrfToken();
$stepLabels = ['척도 선택', '개인정보 동의', '정보 입력'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
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
.header{background:var(--primary);color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.15);}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:12px;align-items:center;}
.header-nav a{color:rgba(255,255,255,.85);text-decoration:none;font-size:.82rem;padding:5px 10px;border-radius:6px;transition:background .2s;}
.header-nav a:hover,.header-nav a.active{background:rgba(255,255,255,.2);color:#fff;}
.admin-badge{font-size:.78rem;color:rgba(255,255,255,.65);}

/* 메인 */
.main{flex:1;display:flex;flex-direction:column;padding:20px 16px 16px;max-width:640px;width:100%;margin:0 auto;}

/* 스텝 인디케이터 */
.step-bar{display:flex;align-items:center;gap:0;margin-bottom:20px;}
.step-item{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;}
.step-item:not(:last-child)::after{content:'';position:absolute;top:14px;left:50%;width:100%;height:2px;background:var(--border);z-index:0;}
.step-item.done::after{background:var(--primary);}
.step-circle{width:28px;height:28px;border-radius:50%;border:2px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--muted);position:relative;z-index:1;transition:all .3s;}
.step-item.active .step-circle{border-color:var(--primary);background:var(--primary);color:#fff;}
.step-item.done .step-circle{border-color:var(--primary);background:var(--primary);color:#fff;}
.step-label{font-size:.72rem;color:var(--muted);margin-top:5px;font-weight:500;}
.step-item.active .step-label{color:var(--primary);font-weight:700;}

/* 카드 */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;flex:1;display:flex;flex-direction:column;}
.card-title{font-size:1.05rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid var(--bg);}

/* 척도 선택 그리드 */
.scale-grid{display:grid;grid-template-columns:1fr;gap:12px;flex:1;}
.scale-option{border:2px solid var(--border);border-radius:12px;padding:18px 20px;cursor:pointer;transition:all .2s;background:#fff;display:flex;align-items:center;gap:16px;}
.scale-option:hover{border-color:var(--primary);background:#f5f8ff;}
.scale-option.selected{border-color:var(--primary);background:#eef2fb;}
.scale-option input{display:none;}
.scale-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.icon-phq{background:#fff0f3;}
.icon-gad{background:#fff8e6;}
.icon-pss{background:#f0fff4;}
.scale-info strong{display:block;font-size:1rem;color:var(--primary);margin-bottom:3px;}
.scale-info span{font-size:.82rem;color:var(--muted);}
.scale-count{margin-left:auto;font-size:.78rem;color:var(--muted);background:var(--bg);padding:4px 10px;border-radius:20px;white-space:nowrap;}

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
input[type=text],input[type=tel],select{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.9rem;color:var(--text);background:#fafbfd;outline:none;font-family:inherit;transition:border-color .2s;}
input:focus,select:focus{border-color:var(--primary);background:#fff;}
.date-disp{padding:10px 13px;background:var(--bg);border-radius:8px;font-size:.875rem;color:var(--muted);}

/* 버튼 */
.btn-row{display:flex;gap:10px;margin-top:auto;padding-top:16px;}
.btn{padding:13px 0;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;flex:1;text-align:center;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.gender-radio-group{display:flex;gap:10px;}
.gender-radio{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border:2px solid var(--border);border-radius:8px;cursor:pointer;background:#fff;transition:all .2s;font-size:.95rem;font-weight:600;}
.gender-radio:hover{border-color:var(--primary);}
.gender-radio.selected{border-color:var(--primary);background:#eef2fb;color:var(--primary);}
.gender-radio input{display:none;}
.alert-error{padding:10px 14px;border-radius:8px;font-size:.85rem;background:#fdedec;border:1px solid #f1948a;color:#c0392b;margin-bottom:14px;}

/* 태블릿 이상 */
@media(min-width:600px){
  .scale-grid{grid-template-columns:1fr 1fr 1fr;}
  .scale-option{flex-direction:column;align-items:flex-start;gap:10px;padding:20px;}
  .scale-count{margin-left:0;}
  .main{padding:24px 24px 20px;}
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
  <!-- 스텝 바 -->
  <div class="step-bar">
    <?php foreach ($stepLabels as $i => $label): 
      $num = $i + 1;
      $cls = $num < $step ? 'done' : ($num === $step ? 'active' : '');
    ?>
    <div class="step-item <?= $cls ?>">
      <div class="step-circle"><?= $num < $step ? '✓' : $num ?></div>
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
      <div class="card-title">📊 검사 척도를 선택하세요</div>
      <div class="scale-grid" id="scaleGrid">
        <?php
        $scaleIcons  = ['PHQ-9'=>['🌸','icon-phq','우울 선별'], 'GAD-7'=>['🌼','icon-gad','불안 선별'], 'PSS-10'=>['🌿','icon-pss','스트레스 측정']];
        $scaleItems  = ['PHQ-9'=>'9문항 / 0~27점', 'GAD-7'=>'7문항 / 0~21점', 'PSS-10'=>'10문항 / 0~40점'];
        foreach ($scales as $key => $scale):
          $ico = $scaleIcons[$key];
        ?>
        <label class="scale-option" id="opt-<?= $key ?>" onclick="selectScale('<?= $key ?>')">
          <input type="radio" name="scale_type" value="<?= $key ?>" id="radio-<?= $key ?>"
                 <?= ($cd['scale_type'] ?? '') === $key ? 'checked' : '' ?> required>
          <div class="scale-icon <?= $ico[1] ?>"><?= $ico[0] ?></div>
          <div class="scale-info">
            <strong><?= $key ?></strong>
            <span><?= $ico[2] ?></span>
          </div>
          <span class="scale-count"><?= $scaleItems[$key] ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-primary">다음 →</button>
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

  <!-- STEP 3: 개인정보 입력 -->
  <?php elseif ($step === 3): ?>
  <form method="post" action="consent.php" style="display:flex;flex-direction:column;flex:1;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="step" value="3">
    <div class="card">
      <div class="card-title">👤 기본 정보 입력</div>
      <div class="form-grid">
        <div class="form-group full">
          <label for="patient_name">이름 <span class="req">*</span></label>
          <input type="text" id="patient_name" name="patient_name" required placeholder="이름을 입력하세요">
        </div>
        <div class="form-group">
          <label>성별</label>
          <div class="gender-radio-group">
            <label class="gender-radio" id="gr-m">
              <input type="radio" name="gender" value="남" onchange="selectGender('gr-m')">
              <span>남</span>
            </label>
            <label class="gender-radio" id="gr-f">
              <input type="radio" name="gender" value="여" onchange="selectGender('gr-f')">
              <span>여</span>
            </label>
          </div>
        </div>
        <div class="form-group">
          <label>나이</label>
          <input type="text" name="age" placeholder="예: 45">
        </div>
        <div class="form-group full">
          <label>연락처</label>
          <input type="tel" name="phone" placeholder="010-0000-0000">
        </div>
      </div>
      <div class="btn-row">
        <a href="consent.php?step=2" class="btn btn-secondary">← 이전</a>
        <button type="submit" class="btn btn-primary">검사 시작 →</button>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
function selectScale(key) {
  document.querySelectorAll('.scale-option').forEach(el => el.classList.remove('selected'));
  document.getElementById('opt-' + key).classList.add('selected');
  document.getElementById('radio-' + key).checked = true;
}
// 초기 선택 복원
<?php if ($cd['scale_type'] ?? ''): ?>selectScale('<?= $cd['scale_type'] ?>');<?php endif; ?>

// 실시간 시계
function selectGender(id) {
  document.querySelectorAll('.gender-radio').forEach(el => el.classList.remove('selected'));
  const el = document.getElementById(id);
  if (el) el.classList.add('selected');
}
</script>
</body>
</html>
