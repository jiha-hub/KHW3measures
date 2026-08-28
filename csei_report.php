<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csei.php';
require_once __DIR__ . '/patient_store.php';
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, p.name AS patient_name, p.birth_date, p.gender, ad.name AS admin_name
    FROM assessments a
    JOIN patients p ON a.patient_id = p.id
    JOIN admins  ad ON a.admin_id   = ad.id
    WHERE a.id = ? AND a.scale_type = 'CSEI-s'
");
$stmt->execute([$id]);
$rec = $stmt->fetch();

$payload = $rec ? (json_decode($rec['factor_scores'] ?? '', true) ?: []) : [];
$factors = $payload['factors'] ?? [];
$overall = $payload['overall'] ?? ['tScore'=>(int)($rec['total_score'] ?? 0),'groupLabel'=>($rec['result_label'] ?? ''),'group'=>'normal'];
$ageGroup = $payload['age_group'] ?? '';

$riskItems    = array_values(array_filter($factors, fn($f) => $f['group'] === 'risk'));
$cautionItems = array_values(array_filter($factors, fn($f) => $f['group'] === 'caution'));

if (count($riskItems) > 0) {
    $names = implode(', ', array_map(fn($i) => $i['name'], $riskItems));
    $overallInsight = "현재 {$names} 영역에서 임상적으로 유의미한 수치(위험 범위)가 관찰됩니다. 이는 신체적 증상(두통, 소화불량, 수면장애 등)으로 발현될 가능성이 있으므로 전문적인 인지재구성 훈련 및 적극적인 스트레스 관리가 권장됩니다.";
} elseif (count($cautionItems) > 0) {
    $names = implode(', ', array_map(fn($i) => $i['name'], $cautionItems));
    $overallInsight = "{$names} 영역이 주의 단계에 머물러 있습니다. 잠재적인 스트레스 요인이 누적되어 있을 수 있으므로 충분한 휴식과 가벼운 운동을 통한 예방적 조치가 권장됩니다.";
} else {
    $overallInsight = "전반적인 감정 균형이 잘 유지되고 있습니다. 현재의 생활 패턴과 스트레스 관리 방식을 유지하십시오.";
}

$guideText = '"감정은 흐름입니다. 하나의 감정에 매몰되는 것이 문제 감정입니다. 다른 감정으로 대체할 수 있도록, 자연스러운 흐름과 상호견제에 의해서 나의 감정은 흘러갑니다. 현재의 핵심 감정을 다른 감정으로 옮겨 마음의 균형을 회복해 보세요."';

$age = $rec ? ageFromBirth($rec['birth_date'] ?? null) : null;
$genderDisp = ($rec['gender'] ?? '') === 'male' ? '남성' : ((($rec['gender'] ?? '') === 'female') ? '여성' : ($rec['gender'] ?? ''));
$ageGroupDisp = cseiAgeGroupLabel($ageGroup);
$judge = count($riskItems) > 0 ? ['위험 구간','#c0392b'] : (count($cautionItems) > 0 ? ['주의 요망','#e67e22'] : ['정상 안정','#27ae60']);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CSEI-s 심층 리포트 — <?= APP_NAME ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;--text:#1a2236;--muted:#6b7a99;--border:#dce3ef;--green:#27ae60;--orange:#e67e22;--red:#c0392b;}
body{background:#f3f6f4;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
.report-wrap{max-width:860px;margin:0 auto;padding:28px 16px 80px;}
.paper{background:#fff;border-radius:18px;box-shadow:0 10px 40px rgba(0,0,0,.06);padding:40px 40px 48px;}
.rp-head{border-bottom:4px solid var(--text);padding-bottom:20px;margin-bottom:28px;text-align:center;position:relative;}
.confidential{position:absolute;top:0;left:0;background:#eef2fb;color:var(--primary);padding:4px 10px;font-size:.68rem;font-weight:700;letter-spacing:.1em;border-radius:4px;}
.rp-head h1{font-size:1.9rem;font-weight:800;letter-spacing:-.02em;margin-bottom:6px;}
.rp-head .sub{font-size:.9rem;color:var(--muted);font-style:italic;}
.meta-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;background:#f8fafd;border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:32px;}
.meta-grid .mk{font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:3px;}
.meta-grid .mv{font-size:.92rem;font-weight:800;}
.section{margin-bottom:32px;}
.section h2{display:flex;align-items:center;gap:10px;font-size:1.2rem;font-weight:800;border-bottom:1px solid var(--border);padding-bottom:10px;margin-bottom:16px;}
.section h2 .n{width:28px;height:28px;background:var(--primary-dark);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.opinion{background:#f8fafd;border:1px solid var(--border);border-radius:14px;padding:20px 22px;font-size:1rem;line-height:1.85;}
.tags{margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;}
.tag{font-size:.72rem;font-weight:700;background:#eef2fb;color:var(--primary);padding:5px 12px;border-radius:20px;}
.factor{display:flex;gap:18px;border:1px solid var(--border);border-radius:14px;padding:16px 18px;margin-bottom:12px;align-items:center;}
.factor.risk{background:#fdedec;border-color:#f2b8b1;} .factor.caution{background:#fef4e9;border-color:#f6d5ac;} .factor.normal{background:#f8fafd;}
.factor .fscore{flex-shrink:0;width:96px;text-align:center;border-right:1px solid var(--border);padding-right:16px;}
.factor .fscore .t{font-size:2rem;font-weight:800;line-height:1;}
.factor .fscore .emo{font-size:.85rem;font-weight:800;margin-top:6px;}
.factor .fscore .badge{display:inline-block;margin-top:6px;font-size:.66rem;font-weight:700;padding:2px 8px;border-radius:20px;}
.factor .finsight{flex:1;font-size:.88rem;line-height:1.65;color:#444;}
.factor .finsight .ft{font-weight:800;color:var(--text);margin-bottom:4px;font-size:.82rem;}
.b-normal{background:#eafaf1;color:var(--green);} .b-caution{background:#fef4e9;color:var(--orange);} .b-risk{background:#fdedec;color:var(--red);}
.guide{background:#e8efe9;border:1px solid #d0dfd3;border-radius:16px;padding:26px 28px;font-size:1.02rem;line-height:1.9;word-break:keep-all;}
.toolbar{max-width:860px;margin:20px auto 0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.btn{padding:12px 22px;border-radius:12px;font-size:.9rem;font-weight:700;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-primary{background:var(--primary);color:#fff;} .btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:#fff;color:var(--text);border:1.5px solid var(--border);}
.footer-note{font-size:.72rem;color:var(--muted);font-weight:700;letter-spacing:.05em;}
.empty{text-align:center;padding:60px 20px;color:var(--muted);}
@media print{
  body{background:#fff;}
  .toolbar,.size-fab,.ui-size-panel,.site-credit{display:none !important;}
  .report-wrap{padding:0;} .paper{box-shadow:none;border-radius:0;padding:0;}
  .factor{break-inside:avoid;}
}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
</head>
    <?php include __DIR__ . '/ui_settings.php'; ?>
<body>

<?php if (!$rec): ?>
<div class="report-wrap"><div class="paper"><div class="empty">리포트를 찾을 수 없습니다.<br><a href="consent.php?step=1" class="btn btn-primary" style="margin-top:16px;">새 검사 시작</a></div></div></div>
<?php else: ?>

<div class="report-wrap">
  <div class="paper">
    <div class="rp-head">
      <span class="confidential">CONFIDENTIAL</span>
      <h1>CSEI-s 심리 진단 심층 리포트</h1>
      <div class="sub">Clinical 7-Emotions Evaluation Report</div>
    </div>

    <div class="meta-grid">
      <div><div class="mk">진단 일자</div><div class="mv"><?= date('Y년 m월 d일 H:i', strtotime($rec['created_at'])) ?></div></div>
      <div><div class="mk">진단 대상</div><div class="mv"><?= htmlspecialchars($rec['patient_name']) ?> · <?= htmlspecialchars($genderDisp) ?><?= $ageGroupDisp ? ' · '.htmlspecialchars($ageGroupDisp) : '' ?><?= $age!==null ? " (만 {$age}세)" : '' ?></div></div>
      <div><div class="mk">종합 T점수</div><div class="mv"><?= (int)$overall['tScore'] ?> (<?= htmlspecialchars($overall['groupLabel']) ?>)</div></div>
      <div><div class="mk">종합 판정</div><div class="mv" style="color:<?= $judge[1] ?>"><?= $judge[0] ?></div></div>
    </div>

    <div class="section">
      <h2><span class="n">A</span> 종합 의학 소견</h2>
      <div class="opinion">
        <?= htmlspecialchars($overallInsight) ?>
        <div class="tags"><span class="tag">T점수 기준 산출</span><span class="tag">성별·연령 임상 규준 적용</span></div>
      </div>
    </div>

    <div class="section">
      <h2><span class="n">B</span> 7가지 핵심 감정 심층 분석</h2>
      <?php foreach ($factors as $f):
        $insight = cseiMedicalInsight($f['factor'], $f['group']);
        $col = $f['group']==='risk'?'var(--red)':($f['group']==='caution'?'var(--orange)':'var(--green)'); ?>
      <div class="factor <?= $f['group'] ?>">
        <div class="fscore">
          <div class="t" style="color:<?= $col ?>"><?= (int)$f['tScore'] ?></div>
          <div class="emo"><?= htmlspecialchars($f['name']) ?></div>
          <span class="badge b-<?= $f['group'] ?>"><?= htmlspecialchars($f['groupLabel']) ?></span>
        </div>
        <div class="finsight">
          <div class="ft">🩺 유의 사항 및 신체 증상</div>
          <?= htmlspecialchars($insight) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="section" style="margin-bottom:8px;">
      <h2><span class="n">C</span> 권장 가이드라인</h2>
      <div class="guide"><?= htmlspecialchars($guideText) ?></div>
    </div>

    <div style="text-align:center;margin-top:28px;">
      <span class="footer-note">— <?= htmlspecialchars(APP_NAME) ?> · CSEI-s CONFIDENTIAL REPORT —</span>
    </div>
  </div>
</div>

<div class="toolbar">
  <a href="csei_result.php?id=<?= (int)$rec['id'] ?>" class="btn btn-secondary">← 결과 화면</a>
  <button onclick="window.print()" class="btn btn-primary">⬇ PDF 저장 / 인쇄</button>
  <span class="footer-note">인쇄 대화상자에서 '대상: PDF로 저장'을 선택하세요</span>
</div>

<?php endif; ?>
</body>
</html>
