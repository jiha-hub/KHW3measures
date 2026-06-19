<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scales.php';
requireLogin();

$db = getDB();

$patients        = $db->query('SELECT id, name FROM patients ORDER BY name')->fetchAll();
$selectedPatient = (int)($_GET['patient_id'] ?? 0);
$selectedScale   = $_GET['scale'] ?? 'PHQ-9';
$dateFrom        = $_GET['date_from'] ?? date('Y-m-d', strtotime('-6 months'));
$dateTo          = $_GET['date_to']   ?? date('Y-m-d');
$chartData       = [];
$scales          = getScales();
$scale           = $scales[$selectedScale] ?? $scales['PHQ-9'];
$cutoffMap       = [
    'PHQ-9'  => ['value' => 10, 'label' => '절단점 10점 (중간정도 우울)'],
    'GAD-7'  => ['value' => 10, 'label' => '절단점 10점 (중간 불안)'],
    'PSS-10' => null,
];
$selectedPatientName = '';

if ($selectedPatient) {
    $stmt = $db->prepare("
        SELECT a.total_score, a.result_label, a.created_at, a.memo
        FROM assessments a
        WHERE a.patient_id = ? AND a.scale_type = ?
          AND DATE(a.created_at) BETWEEN ? AND ?
        ORDER BY a.created_at ASC
    ");
    $stmt->execute([$selectedPatient, $selectedScale, $dateFrom, $dateTo]);
    $chartData = $stmt->fetchAll();

    $ps = $db->prepare('SELECT name FROM patients WHERE id = ?');
    $ps->execute([$selectedPatient]);
    $selectedPatientName = $ps->fetchColumn();
}

$labelColorMap = [
    '우울아님'=>'green','가벼운 우울'=>'yellow','중간정도 우울'=>'orange',
    '중한 우울'=>'red','심한 우울'=>'darkred',
    '불안아님'=>'green','가벼운 불안'=>'yellow','중간 불안'=>'orange','심한 불안'=>'red',
    '낮은 스트레스'=>'green','중간 스트레스'=>'yellow','높은 스트레스'=>'red',
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>그래프 — <?= APP_NAME ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;--text:#1a2236;--muted:#6b7a99;--border:#dce3ef;--radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);--green:#27ae60;--yellow:#f39c12;--orange:#e67e22;--red:#c0392b;}
body{background:var(--bg);font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);}
.header{background:var(--primary);color:#fff;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.15);}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:10px;align-items:center;}
.header-nav a{color:rgba(255,255,255,.85);text-decoration:none;font-size:.82rem;padding:5px 10px;border-radius:6px;transition:background .2s;}
.header-nav a:hover,.header-nav a.active{background:rgba(255,255,255,.2);}
.admin-badge{font-size:.75rem;color:rgba(255,255,255,.65);}
.container{max-width:960px;margin:0 auto;padding:20px 16px;}
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px 24px;margin-bottom:16px;}
.card-title{font-size:1rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--bg);}
.filter-row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.filter-group{display:flex;flex-direction:column;gap:5px;}
.filter-group label{font-size:.78rem;font-weight:600;color:var(--muted);}
select,input[type=date]{padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem;color:var(--text);background:#fafbfd;outline:none;font-family:inherit;}
select:focus,input:focus{border-color:var(--primary);}
.scale-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
.scale-tab{padding:7px 18px;border:2px solid var(--border);border-radius:8px;text-decoration:none;font-size:.875rem;font-weight:700;color:var(--muted);background:#fafbfd;transition:all .2s;}
.scale-tab:hover{border-color:var(--primary);color:var(--primary);}
.scale-tab.active{border-color:var(--primary);background:var(--primary);color:#fff;}
.btn{padding:9px 20px;border-radius:8px;font-size:.875rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.period-btns{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;}
.period-btn{padding:5px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:.8rem;cursor:pointer;background:#fff;font-family:inherit;transition:all .15s;}
.period-btn:hover{border-color:var(--primary);color:var(--primary);}
.summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;}
.summary-box{background:#f8fafd;border:1.5px solid var(--border);border-radius:10px;padding:14px;text-align:center;}
.summary-box .val{font-size:1.7rem;font-weight:800;color:var(--primary);}
.summary-box .lbl{font-size:.75rem;color:var(--muted);margin-top:3px;}
.chart-wrap{position:relative;height:clamp(260px,42vh,400px);}
.empty{text-align:center;padding:60px 20px;color:var(--muted);font-size:.9rem;}
table{width:100%;border-collapse:collapse;}
th{background:#f5f7fc;padding:10px 14px;font-size:.78rem;font-weight:700;color:var(--muted);text-align:left;border-bottom:2px solid var(--border);}
td{padding:11px 14px;font-size:.875rem;border-bottom:1px solid var(--border);}
tr:hover td{background:#f8fafd;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;color:#fff;}
.badge-green{background:var(--green);}
.badge-yellow{background:var(--yellow);}
.badge-orange{background:var(--orange);}
.badge-red{background:var(--red);}
.badge-darkred{background:#1a237e;}
@media(max-width:600px){.summary-grid{grid-template-columns:repeat(2,1fr);}.filter-row{flex-direction:column;}.card{padding:16px;}}
</style>
</head>
<body>
<header class="header">
  <h1><a href="consent.php" style="color:white;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
  <nav class="header-nav">
    <a href="consent.php">검사 입력</a>
    <a href="history.php">이력 조회</a>
    <a href="graph.php" class="active">그래프</a>
    <a href="admin.php">설정</a>
    <span class="admin-badge"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">로그아웃</a>
  </nav>
</header>

<div class="container">

<!-- 필터 -->
<div class="card">
  <div class="card-title">📈 점수 추이 그래프</div>
  <form method="get" action="graph.php">
    <div class="filter-row">
      <div class="filter-group">
        <label>환자 선택</label>
        <select name="patient_id" onchange="this.form.submit()" style="min-width:150px;">
          <option value="">— 환자 선택 —</option>
          <?php foreach ($patients as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $selectedPatient===(int)$p['id']?'selected':'' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($selectedPatient): ?>
      <div class="filter-group">
        <label>시작</label>
        <input type="date" name="date_from" value="<?= $dateFrom ?>">
      </div>
      <div class="filter-group">
        <label>종료</label>
        <input type="date" name="date_to" value="<?= $dateTo ?>">
      </div>
      <input type="hidden" name="scale" value="<?= htmlspecialchars($selectedScale) ?>">
      <div class="filter-group">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">조회</button>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($selectedPatient): ?>
    <div class="period-btns">
      <?php foreach (['1개월'=>'-1 month','3개월'=>'-3 months','6개월'=>'-6 months','1년'=>'-1 year','전체'=>'-10 years'] as $lbl=>$off): ?>
      <button type="button" class="period-btn"
        onclick="document.querySelector('[name=date_from]').value='<?= date('Y-m-d',strtotime($off)) ?>';document.querySelector('[name=date_to]').value='<?= date('Y-m-d') ?>';this.form.submit()">
        <?= $lbl ?>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="scale-tabs">
      <?php foreach ($scales as $key => $s): ?>
      <a href="?patient_id=<?= $selectedPatient ?>&scale=<?= $key ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
         class="scale-tab <?= $selectedScale===$key?'active':'' ?>"><?= $key ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php if (!$selectedPatient): ?>
  <div class="card"><div class="empty">👆 환자를 선택하면 점수 추이 그래프를 확인할 수 있습니다.</div></div>

<?php elseif (empty($chartData)): ?>
  <div class="card">
    <div class="empty">
      <?= htmlspecialchars($selectedPatientName) ?> 환자의 <?= $selectedScale ?> 검사 기록이 없습니다.<br>
      <small style="margin-top:8px;display:block;">기간을 조정하거나 검사를 먼저 진행해주세요.</small>
    </div>
  </div>

<?php else: ?>
  <?php
  $scores    = array_column($chartData, 'total_score');
  $avgScore  = round(array_sum($scores)/count($scores), 1);
  $maxScore  = max($scores);
  $minScore  = min($scores);
  $lastScore = end($scores);
  $firstScore= reset($scores);
  $trend     = $lastScore - $firstScore;
  ?>

  <!-- 요약 -->
  <div class="summary-grid">
    <div class="summary-box"><div class="val"><?= count($chartData) ?></div><div class="lbl">총 검사 횟수</div></div>
    <div class="summary-box"><div class="val"><?= $avgScore ?></div><div class="lbl">평균 점수</div></div>
    <div class="summary-box"><div class="val"><?= $lastScore ?></div><div class="lbl">최근 점수</div></div>
    <div class="summary-box">
      <div class="val" style="color:<?= $trend>0?'#c0392b':($trend<0?'#27ae60':'var(--muted)') ?>">
        <?= $trend>0?'▲':($trend<0?'▼':'—') ?><?= abs($trend) ?>
      </div>
      <div class="lbl">첫 검사 대비</div>
    </div>
    <div class="summary-box"><div class="val"><?= $maxScore ?></div><div class="lbl">최고 점수</div></div>
    <div class="summary-box"><div class="val"><?= $minScore ?></div><div class="lbl">최저 점수</div></div>
  </div>

  <!-- 그래프 -->
  <div class="card">
    <div class="card-title">
      <?= htmlspecialchars($selectedPatientName) ?> — <?= $selectedScale ?> 점수 추이
      <span style="font-size:.78rem;font-weight:400;color:var(--muted);margin-left:8px;"><?= $dateFrom ?> ~ <?= $dateTo ?></span>
    </div>
    <div class="chart-wrap">
      <canvas id="trendChart"></canvas>
    </div>
  </div>

  <!-- 이력 테이블 -->
  <div class="card">
    <div class="card-title">📋 검사 이력</div>
    <table>
      <thead><tr><th>검사일시</th><th>점수</th><th>결과</th><th>메모</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($chartData) as $row):
          $ck = $labelColorMap[$row['result_label']] ?? 'green'; ?>
        <tr>
          <td><?= date('Y.m.d H:i', strtotime($row['created_at'])) ?></td>
          <td><strong><?= $row['total_score'] ?>점</strong></td>
          <td><span class="badge badge-<?= $ck ?>"><?= htmlspecialchars($row['result_label']) ?></span></td>
          <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($row['memo']?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>
</div>

<?php if (!empty($chartData)): ?>
<script>
const labels  = <?= json_encode(array_map(fn($r) => date('m/d', strtotime($r['created_at'])), $chartData)) ?>;
const scores  = <?= json_encode(array_map(fn($r) => (int)$r['total_score'], $chartData)) ?>;
const maxScore = <?= $scale['max_score'] ?>;
const cutoff   = <?= json_encode($cutoffMap[$selectedScale]['value'] ?? null) ?>;
const cutoffLabel = <?= json_encode($cutoffMap[$selectedScale]['label'] ?? null) ?>;
const scoringRanges = <?= json_encode(array_map(fn($r) => ['min'=>$r['min'],'max'=>$r['max'],'color'=>$r['color'],'label'=>$r['label']], $scale['scoring'])) ?>;

const colorMap = {
  green:'#27ae6022', yellow:'#f39c1222', orange:'#e67e2222',
  red:'#c0392b22', darkred:'#1a237e22'
};
const colorFull = {
  green:'#27ae60', yellow:'#d4a017', orange:'#d35400',
  red:'#c0392b', darkred:'#1a237e'
};

const ctx = document.getElementById('trendChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 380);
gradient.addColorStop(0, 'rgba(59,108,183,0.3)');
gradient.addColorStop(1, 'rgba(59,108,183,0.0)');

// 배경 구간 플러그인
const bgBandPlugin = {
  id:'bgBand',
  beforeDraw(chart){
    const {ctx,chartArea,scales}=chart;
    scoringRanges.forEach(r=>{
      const yTop=scales.y.getPixelForValue(Math.min(r.max,maxScore));
      const yBot=scales.y.getPixelForValue(r.min);
      ctx.save();
      ctx.fillStyle=colorMap[r.color]||'rgba(0,0,0,0.05)';
      ctx.fillRect(chartArea.left,yTop,chartArea.width,yBot-yTop);
      ctx.restore();
    });
  }
};

// Y축 라벨 플러그인
const yLabelPlugin = {
  id:'yBandLabel',
  afterDraw(chart){
    const {ctx,chartArea,scales}=chart;
    ctx.save();
    ctx.font='bold 11px Apple SD Gothic Neo,Noto Sans KR,sans-serif';
    ctx.textAlign='left';
    scoringRanges.forEach(r=>{
      const yTop=scales.y.getPixelForValue(Math.min(r.max,maxScore));
      const yBot=scales.y.getPixelForValue(r.min);
      const yMid=(yTop+yBot)/2;
      if(yBot-yTop>14){
        ctx.fillStyle=colorFull[r.color]||'#888';
        ctx.fillText(r.label, chartArea.left+6, yMid+4);
      }
    });
    ctx.restore();
  }
};

// 절단점 플러그인
const cutoffPlugin = {
  id:'cutoffLine',
  afterDraw(chart){
    if(!cutoff) return;
    const {ctx,chartArea,scales}=chart;
    const y=scales.y.getPixelForValue(cutoff);
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(chartArea.left,y);
    ctx.lineTo(chartArea.right,y);
    ctx.strokeStyle='#c0392b';
    ctx.lineWidth=2;
    ctx.setLineDash([6,4]);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle='#c0392b';
    ctx.font='bold 11px sans-serif';
    ctx.textAlign='right';
    ctx.fillText(`절단점 ${cutoff}점`, chartArea.right-4, y-5);
    ctx.restore();
  }
};

new Chart(ctx, {
  type:'line',
  data:{
    labels,
    datasets:[{
      label:`${<?= json_encode($selectedScale) ?>} 점수`,
      data:scores,
      borderColor:'#3b6cb7',
      backgroundColor:gradient,
      borderWidth:2.5,
      pointBackgroundColor:scores.map(s=>{
        for(const r of scoringRanges){
          if(s>=r.min&&s<=r.max) return colorFull[r.color];
        }
        return '#3b6cb7';
      }),
      pointRadius:6,
      pointHoverRadius:9,
      fill:true,
      tension:0.3,
    }]
  },
  options:{
    responsive:true,
    maintainAspectRatio:false,
    plugins:{
      legend:{display:false},
      tooltip:{
        callbacks:{
          label(c){
            const s=c.parsed.y;
            let lbl='';
            for(const r of scoringRanges){if(s>=r.min&&s<=r.max){lbl=r.label;break;}}
            return `${s}점 — ${lbl}`;
          }
        }
      }
    },
    scales:{
      y:{min:0,max:maxScore,ticks:{stepSize:Math.ceil(maxScore/6)},grid:{color:'#dce3ef'}},
      x:{grid:{color:'#dce3ef'}}
    }
  },
  plugins:[bgBandPlugin,cutoffPlugin,yLabelPlugin]
});
</script>
<?php endif; ?>
</body>
</html>
