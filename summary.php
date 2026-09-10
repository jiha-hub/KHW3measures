<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scales.php';
require_once __DIR__ . '/psqi_scoring.php';
require_once __DIR__ . '/patient_store.php';
require_once __DIR__ . '/emr_text.php';
requireLogin();
startSession();

$bid = trim($_GET['battery'] ?? '');
if ($bid === '' && !empty($_SESSION['last_battery']['battery_id'])) {
    $bid = $_SESSION['last_battery']['battery_id'];
}

$patient = null;
$rows    = [];
if ($bid !== '') {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT a.*, p.name AS p_name, p.birth_date, p.gender, p.phone
         FROM assessments a JOIN patients p ON p.id = a.patient_id
         WHERE a.battery_id = ? AND a.deleted_at IS NULL ORDER BY a.created_at ASC'
    );
    $stmt->execute([$bid]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $patient = [
            'name'  => $rows[0]['p_name'],
            'birth' => $rows[0]['birth_date'],
            'gender'=> $rows[0]['gender'],
            'phone' => $rows[0]['phone'],
        ];
    }
}

// 정규 순서로 정렬
$order = array_flip(scaleOrder());
usort($rows, fn($x, $y) => ($order[$x['scale_type']] ?? 99) <=> ($order[$y['scale_type']] ?? 99));

$scales   = getScales();
$psqiMeta = getPsqiMeta();
// 중한 결과는 붉은색 대신 검정 계열로 표현
$colorHex = ['green' => '#27ae60', 'yellow' => '#e0a800', 'orange' => '#e67e22', 'red' => '#4b5563', 'darkred' => '#4b5563', 'black' => '#4b5563', '' => '#3b6cb7'];

$visitDate = $rows ? date('Y-m-d H:i', strtotime($rows[0]['created_at'])) : '';
$age = $patient ? ageFromBirth($patient['birth']) : null;

// EMR 붙여넣기용 자연어 텍스트 생성
$emrText = '';
if ($patient) {
    $emrLines = [buildEmrHeader('정신건강/수면 선별검사 결과', $patient['name'], $patient['gender'], $age, $visitDate, $_SESSION['admin_name'] ?? null), ''];
    foreach ($rows as $row) {
        $decoded = json_decode($row['answers'], true);
        $row['answers_decoded'] = is_array($decoded) ? array_values($decoded) : [];
        $it = interpret($row, $scales, $psqiMeta);
        $emrLines[] = buildEmrLine($row, $it);
    }
    $emrText = implode("\n", $emrLines);
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>방문 검사 요약 — <?= APP_NAME ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--text:#1a2236;--muted:#6b7a99;--border:#dce3ef;}
html,body{font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);background:var(--bg);}
.header{background:#fff;color:#111827;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e5e7eb;}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav a{color:#4b5563;text-decoration:none;font-size:.82rem;padding:6px 12px;border-radius:8px;font-weight:500;margin-left:4px;transition:background .2s,color .2s;}
.header-nav a:hover{background:#f3f4f6;}
.header-nav a.active{background:#eff6ff;color:#2563eb;font-weight:700;}
.wrap{max-width:760px;margin:0 auto;padding:20px 16px 60px;}
.report{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(59,108,183,.10);padding:28px;}
.rep-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--border);padding-bottom:16px;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.rep-title{font-size:1.25rem;font-weight:800;}
.rep-sub{font-size:.82rem;color:var(--muted);margin-top:4px;}
.pt-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px 24px;font-size:.9rem;margin-bottom:22px;}
.pt-grid div span{color:var(--muted);display:inline-block;min-width:64px;}
.scale-block{border:1.5px solid var(--border);border-radius:10px;padding:16px 18px;margin-bottom:14px;}
.sb-top{display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap;}
.sb-name{font-weight:800;font-size:1rem;color:var(--primary);}
.sb-full{font-size:.74rem;color:var(--muted);font-weight:400;}
.sb-score{font-size:1.6rem;font-weight:800;}
.sb-band{display:inline-block;padding:3px 12px;border-radius:20px;font-size:.82rem;font-weight:700;color:#fff;margin-top:4px;}
.sb-cut{font-size:.8rem;color:var(--muted);margin-top:8px;}
.flag{margin-top:10px;padding:10px 12px;border-radius:8px;font-size:.85rem;line-height:1.5;}
.flag.warn{background:#fff8e6;border:1px solid #f0ad4e;color:#7d5a00;}
.flag.urgent{background:#fdedec;border:1px solid #e74c3c;color:#a93226;font-weight:700;}
.comp-list{margin-top:12px;display:grid;gap:6px;}
.comp-row{display:grid;grid-template-columns:110px 1fr 36px;align-items:center;gap:10px;font-size:.82rem;}
.comp-bar{height:8px;background:var(--border);border-radius:99px;overflow:hidden;}
.comp-fill{height:100%;border-radius:99px;}
.comp-val{text-align:right;color:var(--muted);}
.eff{font-size:.82rem;color:var(--muted);margin-top:8px;}
.memo{font-size:.82rem;color:var(--text);background:#f8fafd;border-left:3px solid var(--primary);padding:8px 12px;border-radius:0 6px 6px 0;margin-top:10px;white-space:pre-wrap;}
.emr-box{margin-top:16px;background:#f8fafd;border:1.5px solid var(--border);border-radius:10px;padding:14px 16px;}
.emr-box-label{font-size:.78rem;font-weight:700;color:var(--muted);margin-bottom:8px;}
.emr-box textarea{width:100%;min-height:160px;border:1.5px solid var(--border);border-radius:8px;padding:12px;font-size:.85rem;line-height:1.6;font-family:inherit;color:var(--text);background:#fff;resize:vertical;white-space:pre-wrap;}
.actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;}
.btn{padding:12px 20px;border-radius:8px;font-weight:700;font-size:.9rem;cursor:pointer;border:none;text-decoration:none;text-align:center;}
.btn-print{background:var(--primary);color:#fff;}
.btn-ghost{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}
.disclaimer{font-size:.72rem;color:var(--muted);margin-top:18px;line-height:1.6;border-top:1px dashed var(--border);padding-top:12px;}
.empty{background:#fff;border-radius:12px;padding:40px;text-align:center;color:var(--muted);}

@media print{
  @page{margin:14mm;}
  *{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  body{background:#fff;}
  .header,.header-nav,.actions,.noprint{display:none !important;}
  .wrap{max-width:none;padding:0;}
  .report{box-shadow:none;border-radius:0;padding:0;}
  .scale-block{break-inside:avoid;}
}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
</head>
<?php include __DIR__ . '/ui_settings.php'; ?>
<body>
<header class="header noprint">
  <h1><a href="consent.php" style="color:#111827;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="graph.php">그래프</a>
    <a href="logout.php">로그아웃</a>
  </nav>
</header>

<div class="wrap">
<?php if (!$patient): ?>
  <div class="empty">
    <p style="font-size:1rem;font-weight:700;margin-bottom:8px;">표시할 방문 검사가 없습니다.</p>
    <p>검사를 완료하면 이 페이지에서 방문 전체 결과를 확인·인쇄할 수 있습니다.</p>
    <p style="margin-top:16px;"><a href="consent.php?step=1" class="btn btn-print" style="display:inline-block;">검사 시작 →</a></p>
  </div>
<?php else: ?>
  <div class="report" id="report">
    <div class="rep-head">
      <div>
        <div class="rep-title">방문 검사 요약</div>
        <div class="rep-sub"><?= APP_NAME ?> · 검사일 <?= htmlspecialchars($visitDate) ?></div>
      </div>
      <div class="rep-sub" style="text-align:right;">작성자 <?= htmlspecialchars($_SESSION['admin_name'] ?? '') ?></div>
    </div>

    <div class="pt-grid">
      <div><span>이름</span> <strong><?= htmlspecialchars($patient['name']) ?></strong></div>
      <div><span>생년월일</span> <?= htmlspecialchars($patient['birth'] ?: '—') ?><?= $age !== null ? " (만 {$age}세)" : '' ?></div>
      <div><span>성별</span> <?= htmlspecialchars($patient['gender'] ?: '—') ?></div>
      <div><span>연락처</span> <?= htmlspecialchars($patient['phone'] ?: '—') ?></div>
    </div>

    <?php foreach ($rows as $row):
      $it = interpret($row, $scales, $psqiMeta);
      $hex = $colorHex[$it['color']] ?? $colorHex[''];
      $isCsei = ($row['scale_type'] === 'CSEI-s');
      $full = $row['scale_type'] === 'PSQI-K' ? $psqiMeta['full_name'] : ($isCsei ? '핵심칠정척도 단축형' : ($scales[$row['scale_type']]['full_name'] ?? ''));
    ?>
    <div class="scale-block">
      <div class="sb-top">
        <div>
          <div class="sb-name"><?= htmlspecialchars($row['scale_type']) ?> <span class="sb-full"><?= htmlspecialchars($full) ?></span></div>
          <div class="sb-band" style="background:<?= $hex ?>;"><?= htmlspecialchars($it['label']) ?></div>
        </div>
        <div style="text-align:right;">
          <?php if ($isCsei): ?>
          <div class="sb-score" style="color:<?= $hex ?>;"><span style="font-size:.9rem;color:var(--muted);font-weight:600;">종합 T</span> <?= $it['total'] ?></div>
          <?php else: ?>
          <div class="sb-score" style="color:<?= $hex ?>;"><?= $it['total'] ?><span style="font-size:.9rem;color:var(--muted);font-weight:600;"><?= $it['max'] !== null ? ' / ' . $it['max'] : '' ?>점</span></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($it['cutoff'] !== null): ?>
        <div class="sb-cut">절단점 <?= $it['cutoff'] ?>점<?= $row['scale_type']==='PSQI-K' ? ' 초과 시 수면의 질 저하' : ' 이상 시 추가 평가 권고' ?><?= $it['poor'] ? ' — <strong>절단점 도달</strong>' : '' ?></div>
      <?php elseif ($row['scale_type'] === 'PSS-10'): ?>
        <div class="sb-cut">공식 절단점은 없으며 점수가 높을수록 지각된 스트레스가 큼</div>
      <?php endif; ?>

      <?php if ($it['flag']): ?>
        <div class="flag <?= $it['flag']['level'] ?>">⚠️ <?= htmlspecialchars($it['flag']['text']) ?></div>
      <?php endif; ?>

      <?php if ($it['psqi']): $r = $it['psqi']; ?>
        <div class="comp-list">
          <?php foreach ($r['components'] as $c):
            $w = round($c['score'] / 3 * 100);
            $cc = $c['score'] >= 2 ? '#4b5563' : ($c['score'] === 1 ? '#e0a800' : '#27ae60');
          ?>
          <div class="comp-row">
            <div><?= htmlspecialchars($c['name']) ?></div>
            <div class="comp-bar"><div class="comp-fill" style="width:<?= $w ?>%;background:<?= $cc ?>;"></div></div>
            <div class="comp-val"><?= $c['score'] ?>/3</div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($r['efficiency'] !== null): ?>
          <div class="eff">수면 효율 <?= $r['efficiency'] ?>% · 침대에 누운 시간 <?= $r['hours_in_bed'] ?>시간</div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($isCsei && !empty($it['csei'])): ?>
        <div class="comp-list">
          <?php foreach ($it['csei'] as $f):
            $w = min(100, round($f['tScore']));
            $cc = $f['group']==='risk' ? '#4b5563' : ($f['group']==='caution' ? '#e0a800' : '#27ae60');
          ?>
          <div style="display:grid;grid-template-columns:52px 50px 1fr 40px;align-items:center;gap:10px;font-size:.82rem;">
            <div style="font-weight:600;"><?= htmlspecialchars($f['name']) ?></div>
            <div style="font-weight:800;color:<?= $cc ?>;font-size:.76rem;white-space:nowrap;"><?= htmlspecialchars($f['groupLabel']) ?></div>
            <div class="comp-bar"><div class="comp-fill" style="width:<?= $w ?>%;background:<?= $cc ?>;"></div></div>
            <div class="comp-val" style="color:<?= $cc ?>;font-weight:700;">T<?= (int)$f['tScore'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="eff noprint" style="margin-top:10px;">
          <a href="csei_result.php?id=<?= (int)$row['id'] ?>" style="color:var(--primary);font-weight:700;text-decoration:none;">📊 감정 프로파일 결과</a>
          &nbsp;·&nbsp;
          <a href="csei_report.php?id=<?= (int)$row['id'] ?>" style="color:var(--primary);font-weight:700;text-decoration:none;">🧠 심층 리포트 / PDF</a>
        </div>
      <?php endif; ?>

      <?php if (trim((string)$row['memo']) !== ''): ?>
        <div class="memo">📝 <?= nl2br(htmlspecialchars($row['memo'])) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="disclaimer">
      본 요약은 선별검사 결과이며 확정 진단이 아닙니다. 해석은 임상 평가와 함께 이루어져야 합니다.
      PHQ-9의 자해·자살 관련 문항에서 응답이 있는 경우 반드시 직접 안전 평가를 시행하십시오.
      척도별 저작권 및 사용 조건을 확인하시기 바랍니다.
    </div>

    <div class="emr-box noprint" id="emrBox" style="display:none;">
      <div class="emr-box-label">EMR 붙여넣기용 텍스트</div>
      <textarea id="emrTextArea" readonly><?= htmlspecialchars($emrText) ?></textarea>
    </div>

    <div class="actions noprint">
      <a href="#" class="btn btn-print" onclick="window.print();return false;">🖨️ PDF로 저장 / 인쇄</a>
      <a href="#" class="btn btn-ghost" onclick="toggleEmrBox();return false;" id="btn-emr-toggle">📋 EMR 텍스트 생성</a>
      <a href="#" class="btn btn-ghost" onclick="copyEmrText();return false;" id="btn-emr" style="display:none;">📋 클립보드에 복사</a>
      <a href="history.php" class="btn btn-ghost">이력 조회</a>
      <a href="consent.php?step=1" class="btn btn-ghost">새 검사</a>
    </div>
  </div>
<?php endif; ?>
</div>
<script>
function toggleEmrBox() {
  const box = document.getElementById('emrBox');
  const copyBtn = document.getElementById('btn-emr');
  const show = box.style.display === 'none';
  box.style.display = show ? 'block' : 'none';
  copyBtn.style.display = show ? 'inline-block' : 'none';
}
function copyEmrText() {
  const emrString = document.getElementById('emrTextArea').value;
  navigator.clipboard.writeText(emrString).then(() => {
    const btn = document.getElementById('btn-emr');
    const originalText = btn.innerHTML;
    btn.innerHTML = '✅ 복사 완료';
    btn.style.color = '#27ae60';
    btn.style.borderColor = '#27ae60';
    setTimeout(() => {
      btn.innerHTML = originalText;
      btn.style.color = '';
      btn.style.borderColor = '';
    }, 2000);
  }).catch(err => {
    alert('복사에 실패했습니다. 권한을 확인해주세요.');
    console.error('Copy failed', err);
  });
}
</script>
</body>
</html>
