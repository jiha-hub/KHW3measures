<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csei.php';
require_once __DIR__ . '/patient_store.php';
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, p.name AS patient_name, p.birth_date, p.gender, p.phone, ad.name AS admin_name
    FROM assessments a
    JOIN patients p ON a.patient_id = p.id
    JOIN admins  ad ON a.admin_id   = ad.id
    WHERE a.id = ? AND a.scale_type = 'CSEI-s'
");
$stmt->execute([$id]);
$rec = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CSEI-s 결과 — <?= APP_NAME ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;--text:#1a2236;--muted:#6b7a99;--border:#dce3ef;--radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);--green:#27ae60;--orange:#e67e22;--red:#c0392b;}
body{background:#f8f9fa;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
.header{background:#fff;color:#111827;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;border-bottom:1px solid #e5e7eb;}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:6px;align-items:center;}
.header-nav a{color:#4b5563;text-decoration:none;font-size:.82rem;padding:6px 12px;border-radius:8px;font-weight:500;transition:background .2s,color .2s;}
.header-nav a:hover{background:#f3f4f6;}
.header-nav a.active{background:#eff6ff;color:#2563eb;font-weight:700;}
.admin-badge{font-size:.75rem;color:#9ca3af;}
.container{max-width:960px;margin:0 auto;padding:20px 16px 60px;}
.card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);padding:20px 24px;margin-bottom:16px;}
.card-title{font-size:1rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;color:#111827;}
.legend-note{font-size:.72rem;color:var(--muted);font-weight:400;}
.meta-row{display:flex;gap:16px;flex-wrap:wrap;font-size:.85rem;color:var(--muted);margin-bottom:16px;}
.meta-row strong{color:var(--text);}
.score-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
.score-box{border:1.5px solid var(--border);border-radius:14px;padding:12px 8px;text-align:center;background:#fafbfd;}
.score-box .emo{font-size:.82rem;font-weight:800;color:var(--primary);margin-bottom:6px;}
.score-box .tval{font-size:1.9rem;font-weight:800;line-height:1;}
.score-box .tlbl{font-size:.62rem;color:var(--muted);margin-top:2px;}
.score-box .grp{display:inline-block;margin-top:8px;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:20px;}
.grp-normal{background:#eafaf1;color:var(--green);} .grp-caution{background:#fef4e9;color:var(--orange);} .grp-risk{background:#fdedec;color:var(--red);}
.box-normal{border-color:#bfe6cd;} .box-caution{border-color:#f6d5ac;} .box-risk{border-color:#f2b8b1;}
.score-box.total{background:#eff6ff;border-color:var(--primary);grid-column:span 2;}
.score-box.total .tval{color:var(--primary);}
.charts{display:grid;grid-template-columns:1fr;gap:16px;}
.charts>div{min-width:0;}
.chart-wrap{position:relative;height:clamp(280px,44vh,360px);min-width:0;width:100%;overflow:hidden;}
.chart-wrap canvas{max-width:100%!important;}
.summary-lead{background:#f8fafd;border:1px solid var(--border);border-radius:12px;padding:16px;font-size:.92rem;line-height:1.7;margin-bottom:14px;}
.cmt-item{display:flex;gap:8px;align-items:flex-start;padding:8px 0;border-bottom:1px dashed var(--border);font-size:.88rem;}
.cmt-item:last-child{border-bottom:none;}
.cmt-dot{width:9px;height:9px;border-radius:50%;margin-top:6px;flex-shrink:0;}
.dot-normal{background:var(--green);} .dot-caution{background:var(--orange);} .dot-risk{background:var(--red);}
.btn{padding:11px 20px;border-radius:12px;font-size:.88rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-primary{background:var(--primary);color:#fff;} .btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg);color:var(--text);border:1.5px solid var(--border);}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;}
.empty{text-align:center;padding:60px 20px;color:var(--muted);}
@media(min-width:720px){.score-grid{grid-template-columns:repeat(4,1fr);}.score-box.total{grid-column:span 4;}.charts{grid-template-columns:1fr 1fr;}}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
</head>
    <?php include __DIR__ . '/ui_settings.php'; ?>
<body>
<header class="header">
  <h1><a href="consent.php" style="color:#111827;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="graph.php">그래프</a>
    <a href="admin.php">설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">로그아웃</a>
  </nav>
</header>

<div class="container">
<?php if (!$rec): ?>
  <div class="card"><div class="empty">결과를 찾을 수 없습니다.<br><a href="consent.php?step=1" class="btn btn-primary" style="margin-top:16px;">새 검사 시작</a></div></div>
<?php else:
    $payload  = json_decode($rec['factor_scores'] ?? '', true) ?: [];
    $factors  = $payload['factors'] ?? [];
    $overall  = $payload['overall'] ?? ['tScore'=>(int)$rec['total_score'],'groupLabel'=>$rec['result_label'],'group'=>'normal'];
    $attention = array_values(array_filter($factors, fn($f) => $f['group'] !== 'normal'));

    if (count($attention) > 0) {
        $main = null;
        foreach ($attention as $f) { if ($f['group'] === 'risk') { $main = $f; break; } }
        if (!$main) $main = $attention[0];
        $lead = "현재 돌봄이 필요한 감정들이 감지되었습니다. 특히 <strong>{$main['name']}</strong> 지표가 <strong>{$main['groupLabel']}</strong> 상태입니다. 이 부분을 중심으로 전문적인 접근 방향을 모색해보는 것이 좋습니다.";
    } else {
        $lead = "전체적인 감정 상태가 모두 정상 범위에 있으며 안정적입니다. 지금의 안정을 유지하기 위한 가벼운 마음챙김이나 일상 속 관리를 계속해 주세요.";
    }
    $age = ageFromBirth($rec['birth_date'] ?? null);
?>
  <div class="card">
    <div class="card-title">
      <span>🌀 CSEI-s 핵심칠정 감정 진단 결과</span>
      <span class="legend-note">종합 T <?= (int)$overall['tScore'] ?> · <?= htmlspecialchars($overall['groupLabel']) ?></span>
    </div>
    <div class="meta-row">
      <span>👤 <strong><?= htmlspecialchars($rec['patient_name']) ?></strong></span>
      <?php if ($rec['gender']): ?><span><?= htmlspecialchars($rec['gender']) ?></span><?php endif; ?>
      <?php if ($rec['birth_date']): ?><span><?= htmlspecialchars($rec['birth_date']) ?><?= $age!==null ? " (만 {$age}세)" : '' ?></span><?php endif; ?>
      <span>📅 <?= date('Y년 m월 d일 H:i', strtotime($rec['created_at'])) ?></span>
      <span>👨‍⚕️ <?= htmlspecialchars($rec['admin_name']) ?></span>
    </div>

    <div class="score-grid">
      <?php foreach ($factors as $f): ?>
      <div class="score-box box-<?= $f['group'] ?>">
        <div class="emo"><?= htmlspecialchars($f['name']) ?></div>
        <div class="tval" style="color:<?= $f['group']==='risk'?'var(--red)':($f['group']==='caution'?'var(--orange)':'var(--green)') ?>"><?= (int)$f['tScore'] ?></div>
        <div class="tlbl">T-score</div>
        <span class="grp grp-<?= $f['group'] ?>"><?= htmlspecialchars($f['groupLabel']) ?></span>
      </div>
      <?php endforeach; ?>
      <div class="score-box total">
        <div class="emo">종합 지수</div>
        <div class="tval"><?= (int)$overall['tScore'] ?></div>
        <div class="tlbl">Overall T-score</div>
        <span class="grp grp-<?= $overall['group'] ?>"><?= htmlspecialchars($overall['groupLabel']) ?></span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📊 감정 프로파일 <span class="legend-note">T 40~60 정상 · 30~40/60~70 주의 · 그 외 위험</span></div>
    <div class="charts">
      <div class="chart-wrap"><canvas id="radarChart"></canvas></div>
      <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📝 현 상태 요약</div>
    <div class="summary-lead"><?= $lead ?></div>
    <?php if (count($attention) > 0): foreach ($attention as $f):
      $msg = $f['group']==='risk' ? "'{$f['name']}' 감정이 위험 수준입니다. 각별한 관리와 주의가 필요합니다."
                                  : "'{$f['name']}' 감정이 다소 불안정합니다. 편안한 휴식이 도움이 될 수 있습니다."; ?>
      <div class="cmt-item"><span class="cmt-dot dot-<?= $f['group'] ?>"></span><span><strong><?= htmlspecialchars($f['name']) ?></strong> — <?= htmlspecialchars($msg) ?></span></div>
    <?php endforeach; else: ?>
      <div class="cmt-item"><span class="cmt-dot dot-normal"></span><span>모든 감정 영역이 통제 범위 내에 있습니다.</span></div>
    <?php endif; ?>

    <div class="actions">
      <a href="csei_report.php?id=<?= (int)$rec['id'] ?>" class="btn btn-primary">🧠 의학적 심층 리포트 / PDF</a>
      <?php if (!empty($rec['battery_id'])): ?><a href="summary.php?battery=<?= urlencode($rec['battery_id']) ?>" class="btn btn-secondary">🖨️ 방문 요약</a><?php endif; ?>
      <a href="graph.php?patient_id=<?= (int)$rec['patient_id'] ?>&scale=CSEI-s" class="btn btn-secondary">📈 추이 그래프</a>
      <a href="history.php" class="btn btn-secondary">📋 이력 조회</a>
    </div>
  </div>

<script>
const factorData = <?= json_encode(array_map(fn($f)=>['name'=>$f['name'],'t'=>(int)$f['tScore'],'group'=>$f['group']], $factors), JSON_UNESCAPED_UNICODE) ?>;
const labels = factorData.map(f=>f.name);
const tvals  = factorData.map(f=>f.t);
const grpColor = {normal:'#27ae60', caution:'#e67e22', risk:'#c0392b'};
const ptColors = factorData.map(f=>grpColor[f.group]);
const grpKr = g => g==='risk'?'위험군':(g==='caution'?'주의군':'정상군');

new Chart(document.getElementById('radarChart').getContext('2d'), {
  type:'radar',
  data:{ labels, datasets:[
    { label:'평균(50)', data:labels.map(()=>50), borderColor:'#94a3b8', borderDash:[4,4], borderWidth:1, pointRadius:0, fill:false },
    { label:'T점수', data:tvals, borderColor:'#3b6cb7', backgroundColor:'rgba(59,108,183,0.15)', borderWidth:2.5, pointBackgroundColor:ptColors, pointRadius:5, pointHoverRadius:7 }
  ]},
  options:{ responsive:true, maintainAspectRatio:false,
    scales:{ r:{ min:0, max:100, ticks:{ stepSize:20, backdropColor:'transparent', color:'#999', font:{size:10} }, grid:{color:'#e5e7eb'}, angleLines:{color:'#e5e7eb'}, pointLabels:{ font:{size:12,weight:'bold'}, color:'#4b5563' } } },
    plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, boxWidth:12 } },
      tooltip:{ callbacks:{ label:(c)=> c.datasetIndex===1 ? `T ${c.parsed.r} — ${grpKr(factorData[c.dataIndex].group)}` : `평균 50` } } } }
});

const bands = [ {y1:0,y2:30,c:'rgba(192,57,43,0.06)'},{y1:30,y2:40,c:'rgba(230,126,34,0.08)'},{y1:40,y2:60,c:'rgba(39,174,96,0.06)'},{y1:60,y2:70,c:'rgba(230,126,34,0.08)'},{y1:70,y2:100,c:'rgba(192,57,43,0.06)'} ];
const bandPlugin = { id:'bands', beforeDraw(chart){ const {ctx,chartArea,scales}=chart; bands.forEach(b=>{ const yT=scales.y.getPixelForValue(b.y2), yB=scales.y.getPixelForValue(b.y1); ctx.save(); ctx.fillStyle=b.c; ctx.fillRect(chartArea.left,yT,chartArea.width,yB-yT); ctx.restore(); }); } };

new Chart(document.getElementById('lineChart').getContext('2d'), {
  type:'line',
  data:{ labels, datasets:[{ label:'T점수', data:tvals, borderColor:'#3b6cb7', borderWidth:2.5, pointBackgroundColor:ptColors, pointBorderColor:'#fff', pointBorderWidth:2, pointRadius:6, pointHoverRadius:9, tension:0.3, fill:false }] },
  options:{ responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label:(c)=>`T ${c.parsed.y} — ${grpKr(factorData[c.dataIndex].group)}` } } },
    scales:{ y:{ min:0, max:100, ticks:{ stepSize:20, color:'#999' }, grid:{color:'#eef1f6'}, title:{display:true,text:'T-score',color:'#999',font:{size:11}} }, x:{ grid:{display:false}, ticks:{ font:{size:11,weight:'bold'}, color:'#666' } } } },
  plugins:[bandPlugin]
});
</script>
<?php endif; ?>
</div>
</body>
</html>
