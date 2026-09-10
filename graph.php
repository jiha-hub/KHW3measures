<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scales.php';
require_once __DIR__ . '/psqi_scoring.php';
require_once __DIR__ . '/csei.php';
require_once __DIR__ . '/date_field.php';   // 숫자패드+달력 날짜 입력
requireLogin();

$db = getDB();

$patients        = $db->query('SELECT id, name, birth_date, phone FROM patients ORDER BY name, birth_date')->fetchAll();
// 환자별 검사일 목록 (검사일로 환자 찾기용)
$patientDates = [];
foreach ($db->query("SELECT DISTINCT patient_id, DATE(created_at) d FROM assessments WHERE deleted_at IS NULL")->fetchAll() as $pd) {
    $patientDates[(int)$pd['patient_id']][] = $pd['d'];
}
$selectedPatient = (int)($_GET['patient_id'] ?? 0);
$selectedScale   = $_GET['scale'] ?? 'PHQ-9';
$dateFrom        = $_GET['date_from'] ?? date('Y-m-d', strtotime('-6 months'));
$dateTo          = $_GET['date_to']   ?? date('Y-m-d');
$chartData       = [];
$scales          = getScales();
// PSQI-K는 별도 엔진이지만, 그래프는 메타(max_score/scoring/절단점)만 사용하므로 병합해 재사용
$psqiMeta        = getPsqiMeta();
$scales['PSQI-K'] = [
    'name'      => $psqiMeta['name'],
    'full_name' => $psqiMeta['full_name'],
    'scoring'   => $psqiMeta['scoring'],
    'max_score' => $psqiMeta['max_score'],
    'cutoff'    => $psqiMeta['cutoff'],
];
// CSEI-s: 종합 T점수(0~100) 추이. T-score 구간 밴드로 재사용
$scales['CSEI-s'] = [
    'name'      => 'CSEI-s',
    'full_name' => '핵심칠정척도 단축형',
    'scoring'   => [
        ['min'=>0,  'max'=>29,  'color'=>'red',    'label'=>'위험(낮음)'],
        ['min'=>30, 'max'=>40,  'color'=>'orange', 'label'=>'주의'],
        ['min'=>41, 'max'=>59,  'color'=>'green',  'label'=>'정상'],
        ['min'=>60, 'max'=>70,  'color'=>'orange', 'label'=>'주의'],
        ['min'=>71, 'max'=>100, 'color'=>'red',    'label'=>'위험(높음)'],
    ],
    'max_score' => 100,
    'cutoff'    => null,
];
$scale           = $scales[$selectedScale] ?? $scales['PHQ-9'];
$cutoffMap       = [
    'PHQ-9'  => ['value' => 10, 'label' => '절단점 10점 (중간정도 우울)'],
    'GAD-7'  => ['value' => 10, 'label' => '절단점 10점 (중간 불안)'],
    'PSS-10' => null,
    'PHQ-15' => ['value' => 10, 'label' => '절단점 10점 (중등도 이상 신체증상)'],
    'BDI-9'  => ['value' => 1,  'label' => '1점 이상 시 자살사고 있음 — 안전 평가 필요'],
    'S-GDpS' => ['value' => 8,  'label' => '절단점 8점 (우울 의심)'],
    'K-MDQ'  => ['value' => 7,  'label' => '절단점 7점 (양극성 선별 양성)'],
    'SSD-12' => ['value' => 23, 'label' => '절단점 23점 (국제 표준)'],
    'PSQI-K' => ['value' => 5,  'label' => '절단점 5점 초과 시 수면의 질 저하'],
    'CSEI-s' => null,
];
$selectedPatientName  = '';
$selectedPatientBirth = '';
$patientLabel         = '';

if ($selectedPatient) {
    $stmt = $db->prepare("
        SELECT a.id, a.total_score, a.result_label, a.created_at, a.memo, a.factor_scores
        FROM assessments a
        WHERE a.patient_id = ? AND a.scale_type = ?
          AND a.deleted_at IS NULL
          AND DATE(a.created_at) BETWEEN ? AND ?
        ORDER BY a.created_at ASC
    ");
    $stmt->execute([$selectedPatient, $selectedScale, $dateFrom, $dateTo]);
    $chartData = $stmt->fetchAll();

    $ps = $db->prepare('SELECT name, birth_date FROM patients WHERE id = ?');
    $ps->execute([$selectedPatient]);
    $selectedPatientRow  = $ps->fetch();
    $selectedPatientName = $selectedPatientRow['name'] ?? '';
    $selectedPatientBirth = $selectedPatientRow['birth_date'] ?? '';
    $patientLabel = $selectedPatientName . ($selectedPatientBirth ? ' (' . $selectedPatientBirth . ')' : '');
}

// CSEI-s: 7감정 시계열 + 최신 프로파일
$isCseiGraph = ($selectedScale === 'CSEI-s');
$cseiSeries  = [];
if ($isCseiGraph) {
    foreach ($chartData as $row) {
        $fs = json_decode($row['factor_scores'] ?? '', true) ?: [];
        $entry = [
            'id'      => (int)$row['id'],
            'date'    => date('m/d', strtotime($row['created_at'])),
            'fulldate'=> date('Y-m-d H:i', strtotime($row['created_at'])),
            'overall' => (int)$row['total_score'],
            'label'   => $row['result_label'],
            'factors' => $fs['factors'] ?? [],
        ];
        foreach (($fs['factors'] ?? []) as $f) { $entry[$f['factor']] = (int)$f['tScore']; }
        $cseiSeries[] = $entry;
    }
}

$labelColorMap = [
    '우울아님'=>'green','가벼운 우울'=>'yellow','중간정도 우울'=>'orange',
    '중한 우울'=>'black','심한 우울'=>'black',
    '불안아님'=>'green','가벼운 불안'=>'yellow','중간 불안'=>'orange','심한 불안'=>'black',
    '낮은 스트레스'=>'green','중간 스트레스'=>'yellow','높은 스트레스'=>'black',
    '정상군'=>'green','주의군'=>'yellow','위험군'=>'black',
    '최소 신체증상'=>'green','경도 신체증상'=>'yellow','중등도 신체증상'=>'orange','고도 신체증상'=>'black',
    '자살사고 없음'=>'green','자살사고 – 경도'=>'yellow','자살사고 – 뚜렷'=>'black',
    '정상'=>'green','경도 우울 경향'=>'yellow','우울 의심'=>'black',
    '선별 음성'=>'green','선별 양성'=>'black','임상적 위험군'=>'black',
    '양호한 수면'=>'green','경도 수면문제'=>'yellow','수면의 질 저하'=>'black',
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>그래프 — <?= APP_NAME ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-zoom/2.0.1/chartjs-plugin-zoom.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f0f4f8;--card:#fff;--primary:#3b6cb7;--primary-dark:#2d549a;--text:#1a2236;--muted:#6b7a99;--border:#dce3ef;--radius:12px;--shadow:0 4px 24px rgba(59,108,183,.10);--green:#27ae60;--yellow:#f39c12;--orange:#e67e22;--red:#c0392b;}
html{overflow-x:hidden;}
body{background:#f8f9fa;font-family:'Apple SD Gothic Neo','Noto Sans KR',sans-serif;color:var(--text);overflow-x:hidden;max-width:100%;}
.header{background:#fff;color:#111827;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;border-bottom:1px solid #e5e7eb;}
.header h1{font-size:.95rem;font-weight:700;}
.header-nav{display:flex;gap:6px;align-items:center;}
.header-nav a{color:#4b5563;text-decoration:none;font-size:.82rem;padding:6px 12px;border-radius:8px;font-weight:500;transition:background .2s,color .2s;}
.header-nav a:hover{background:#f3f4f6;}
.header-nav a.active{background:#eff6ff;color:#2563eb;font-weight:700;}
.admin-badge{font-size:.75rem;color:#9ca3af;}
.container{max-width:1180px;margin:0 auto;padding:20px 16px;}
.layout{display:block;}
.sidebar{margin-bottom:16px;min-width:0;}
.content{min-width:0;}
/* 환자 검색 입력창 — 크고 시원하게 */
#patientSearch{width:100%;padding:14px 16px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:1rem;color:var(--text);background:#fafbfd;outline:none;font-family:inherit;min-height:50px;}
#patientSearch:focus{border-color:#2563eb;background:#fff;}
#patientSearch::placeholder{color:#9ca3af;}
@media(min-width:900px){
  .layout{display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start;}
  /* 스크롤해도 항상 따라오는 고정 사이드바. overflow 제거 → 환자 검색 드롭다운이 잘리지 않음 */
  .sidebar{position:sticky;top:72px;margin-bottom:0;}
}
.card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);padding:20px;margin-bottom:16px;}
.card-title{font-size:1rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f3f4f6;color:#111827;}
.filter-group{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;}
.filter-group label{font-size:.78rem;font-weight:600;color:#6b7280;}
.filter-group.split{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
select,input[type=date],input[type=text].df-text{width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:.875rem;color:var(--text);background:#fafbfd;outline:none;font-family:inherit;}
select:focus,input:focus{border-color:#2563eb;}

/* 척도 선택 (2x2 그리드) */
.scale-grid-mini{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.scale-tab{display:block;padding:9px 6px;border:1.5px solid #e5e7eb;border-radius:10px;text-decoration:none;font-size:.82rem;font-weight:600;color:#6b7280;background:#fff;text-align:center;transition:all .15s;}
.scale-tab:hover{border-color:#2563eb;color:#2563eb;}
.scale-tab.active{border-color:#2563eb;background:#eff6ff;color:#2563eb;font-weight:700;}

.btn{padding:9px 20px;border-radius:12px;font-size:.875rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit;}
.btn-primary{width:100%;background:#2563eb;color:#fff;padding:11px 0;}
.btn-primary:hover{background:#1b4fc4;}

/* 기간 세그먼트 컨트롤 */
.period-seg{display:flex;gap:2px;background:#f3f4f6;border-radius:10px;padding:3px;margin-bottom:12px;}
.period-btn{flex:1;padding:6px 4px;border:none;border-radius:8px;font-size:.76rem;font-weight:600;cursor:pointer;background:transparent;color:#6b7280;font-family:inherit;transition:all .15s;}
.period-btn.active{background:#fff;color:#111827;box-shadow:0 1px 2px rgba(0,0,0,.08);font-weight:700;}

.summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;}
.summary-box{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;text-align:center;}
.summary-box .val{font-size:1.75rem;font-weight:800;color:#111827;}
.summary-box .lbl{font-size:.8rem;color:#6b7280;margin-top:4px;}
.chart-wrap{position:relative;height:clamp(260px,42vh,400px);}
.empty{text-align:center;padding:60px 20px;color:var(--muted);font-size:.9rem;}
table{width:100%;border-collapse:collapse;}
th{background:#f9fafb;padding:10px 14px;font-size:.72rem;font-weight:700;color:#6b7280;text-align:left;border-bottom:1px solid #f3f4f6;text-transform:uppercase;letter-spacing:.03em;}
td{padding:12px 14px;font-size:.875rem;border-bottom:1px solid #f3f4f6;}
tr:hover td{background:#f3f4f6;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;}
.badge-green{background:#dcfce7;color:#166534;}
.badge-yellow{background:#fef3c7;color:#92400e;}
.badge-orange{background:#ffedd5;color:#9a3412;}
.badge-red{background:#e5e7eb;color:#4b5563;}
.badge-darkred{background:#e5e7eb;color:#4b5563;}
.badge-black{background:#e5e7eb;color:#4b5563;}
@media(max-width:600px){.summary-grid{grid-template-columns:repeat(2,1fr);}.card{padding:16px;}}
/* 환자 검색 드롭다운 */
.patient-dropdown{position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;margin-top:4px;max-height:280px;overflow-y:auto;z-index:60;box-shadow:0 8px 24px rgba(0,0,0,.12);display:none;}
.patient-dropdown.open{display:block;}
.patient-item{padding:10px 12px;font-size:.85rem;cursor:pointer;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:8px;}
.patient-item:last-child{border-bottom:none;}
.patient-item:hover,.patient-item.active{background:#eff6ff;color:#2563eb;}
.patient-item .pi-birth{color:#9ca3af;font-size:.78rem;white-space:nowrap;}
.patient-empty{padding:12px;color:#9ca3af;font-size:.82rem;text-align:center;}
</style>
  <?php include __DIR__ . '/pwa_head.php'; ?>
  <?php include __DIR__ . '/design_tokens.php'; ?>
  <?php dateFieldAssets(); ?>
</head>
    <?php include __DIR__ . '/ui_settings.php'; ?>
<body>
<header class="header">
  <h1><a href="consent.php" style="color:#111827;text-decoration:none;">🧠 <?= APP_NAME ?></a></h1>
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
<div class="layout">

<!-- 필터 (좌측 고정 패널) -->
<aside class="sidebar">
<div class="card">
  <div class="card-title">📈 점수 추이 그래프</div>
  <form method="get" action="graph.php">
    <?php
      $selPatientLabel = '';
      foreach ($patients as $p) {
        if ($selectedPatient === (int)$p['id']) {
          $selPatientLabel = $p['name'] . ($p['birth_date'] ? ' (' . $p['birth_date'] . ')' : ' (생년월일 미상)');
          break;
        }
      }
    ?>
    <div class="filter-group">
      <label>검사일로 찾기 <span style="font-weight:400;color:#9ca3af;">(선택)</span></label>
      <?php renderDateField('', '', 'examDateFilter', '검사일 (숫자·달력)'); ?>
    </div>
    <div class="filter-group filter-patient-wrap" style="position:relative;">
      <label>환자 검색 <span id="examDateNote" style="font-weight:400;color:#2563eb;"></span></label>
      <input type="text" id="patientSearch" autocomplete="off" placeholder="이름 입력 (예: 정지하)"
             value="<?= htmlspecialchars($selPatientLabel) ?>">
      <input type="hidden" name="patient_id" id="patientId" value="<?= $selectedPatient ?: '' ?>">
      <input type="hidden" name="scale" value="<?= htmlspecialchars($selectedScale) ?>">
      <div id="patientList" class="patient-dropdown"></div>
    </div>
    <?php if ($selectedPatient): ?>
    <div class="filter-group split">
      <div>
        <label>시작</label>
        <?php renderDateField('date_from', $dateFrom, 'dateFromInput', '시작일'); ?>
      </div>
      <div>
        <label>종료</label>
        <?php renderDateField('date_to', $dateTo, 'dateToInput', '종료일'); ?>
      </div>
    </div>
    <input type="hidden" name="scale" value="<?= htmlspecialchars($selectedScale) ?>">
    <button type="submit" class="btn btn-primary">조회</button>

    <div class="period-seg" style="margin-top:16px;">
      <?php foreach (['1개월'=>'-1 month','3개월'=>'-3 months','6개월'=>'-6 months','1년'=>'-1 year','전체'=>'-10 years'] as $lbl=>$off): ?>
      <button type="button" class="period-btn"
        onclick="document.querySelector('[name=date_from]').value='<?= date('Y-m-d',strtotime($off)) ?>';document.querySelector('[name=date_to]').value='<?= date('Y-m-d') ?>';this.form.submit()">
        <?= $lbl ?>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="scale-grid-mini">
      <?php foreach ($scales as $key => $s): ?>
      <a href="?patient_id=<?= $selectedPatient ?>&scale=<?= $key ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
         class="scale-tab <?= $selectedScale===$key?'active':'' ?>"><?= $key ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </form>
</div>
</aside>

<main class="content">
<?php if (!$selectedPatient): ?>
  <div class="card"><div class="empty">👆 환자를 선택하면 점수 추이 그래프를 확인할 수 있습니다.</div></div>

<?php elseif (empty($chartData)): ?>
  <div class="card">
    <div class="empty">
      <?= htmlspecialchars($patientLabel) ?> 환자의 <?= $selectedScale ?> 검사 기록이 없습니다.<br>
      <small style="margin-top:8px;display:block;">기간을 조정하거나 검사를 먼저 진행해주세요.</small>
    </div>
  </div>

<?php elseif ($isCseiGraph): ?>
  <?php
  $latest = end($cseiSeries); reset($cseiSeries);
  $firstOv = $cseiSeries[0]['overall']; $lastOv = $latest['overall'];
  $ovTrend = $lastOv - $firstOv;
  ?>
  <!-- KPI 요약 (종합 T) -->
  <div class="summary-grid">
    <div class="summary-box"><div class="val"><?= count($cseiSeries) ?></div><div class="lbl">총 검사 횟수</div></div>
    <div class="summary-box"><div class="val"><?= $lastOv ?></div><div class="lbl">최근 종합 T</div></div>
    <div class="summary-box">
      <div class="val" style="color:<?= $ovTrend>0?'#dc2626':($ovTrend<0?'#16a34a':'#111827') ?>">
        <?= $ovTrend>0?'▲':($ovTrend<0?'▼':'—') ?><?= abs($ovTrend) ?>
      </div>
      <div class="lbl">첫 검사 대비</div>
    </div>
  </div>

  <!-- 최근 감정 프로파일 (레이더) -->
  <div class="card">
    <div class="card-title">최근 감정 프로파일 (<?= htmlspecialchars($latest['fulldate']) ?>)
      <span style="font-size:.78rem;font-weight:400;color:var(--muted);margin-left:8px;">T 40~60 정상 · 30~40/60~70 주의 · 그 외 위험</span>
    </div>
    <div class="chart-wrap" style="min-width:0;width:100%;overflow:hidden;"><canvas id="cseiRadar"></canvas></div>
  </div>

  <!-- 7가지 감정 T점수 날짜별 추이 -->
  <div class="card">
    <div class="card-title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <span>7가지 감정 T점수 추이</span>
      <span style="font-size:.78rem;font-weight:400;color:var(--muted);"><?= $dateFrom ?> ~ <?= $dateTo ?></span>
      <span style="margin-left:auto;display:flex;gap:6px;align-items:center;">
        <span style="font-size:.72rem;color:#9ca3af;">🔍 휠·핀치로 확대 / 드래그로 이동</span>
        <button type="button" class="btn" style="background:#eef2fb;color:#2563eb;padding:6px 12px;" onclick="cseiTrendChart.resetZoom()">확대 초기화</button>
      </span>
    </div>
    <div class="chart-wrap" style="height:clamp(300px,46vh,420px);min-width:0;width:100%;overflow:hidden;"><canvas id="cseiTrend"></canvas></div>
  </div>

  <!-- 검사별 목록 -->
  <div class="card">
    <div class="card-title">📋 검사 이력</div>
    <table>
      <thead><tr><th>검사일시</th><th>종합 T</th><th>분류</th><th></th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($cseiSeries) as $row):
          $ck = $labelColorMap[$row['label']] ?? 'green'; ?>
        <tr>
          <td><?= htmlspecialchars($row['fulldate']) ?></td>
          <td><strong>T <?= $row['overall'] ?></strong></td>
          <td><span class="badge badge-<?= $ck ?>"><?= htmlspecialchars($row['label']) ?></span></td>
          <td style="white-space:nowrap;">
            <a href="csei_result.php?id=<?= $row['id'] ?>" class="scale-tab" style="display:inline-block;padding:5px 12px;">📊 결과</a>
            <a href="csei_report.php?id=<?= $row['id'] ?>" class="scale-tab" style="display:inline-block;padding:5px 12px;">🧠 리포트</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <?php
  $scores    = array_column($chartData, 'total_score');
  $maxScore  = max($scores);
  $minScore  = min($scores);
  $lastScore = end($scores);
  $firstScore= reset($scores);
  $trend     = $lastScore - $firstScore;
  ?>

  <!-- KPI 요약 -->
  <div class="summary-grid">
    <div class="summary-box">
      <div class="val" style="color:<?= $trend>0?'#dc2626':($trend<0?'#16a34a':'#111827') ?>">
        <?= $trend>0?'▲':($trend<0?'▼':'—') ?><?= abs($trend) ?>
      </div>
      <div class="lbl">첫 검사 대비</div>
    </div>
    <div class="summary-box"><div class="val"><?= $maxScore ?></div><div class="lbl">최고 점수</div></div>
    <div class="summary-box"><div class="val"><?= $minScore ?></div><div class="lbl">최저 점수</div></div>
  </div>

  <!-- 그래프 -->
  <div class="card">
    <div class="card-title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <span><?= htmlspecialchars($patientLabel) ?> — <?= $selectedScale ?> 점수 추이</span>
      <span style="font-size:.78rem;font-weight:400;color:var(--muted);"><?= $dateFrom ?> ~ <?= $dateTo ?></span>
      <span style="margin-left:auto;display:flex;gap:6px;align-items:center;">
        <span style="font-size:.72rem;color:#9ca3af;">🔍 휠·핀치 확대</span>
        <button type="button" class="btn" style="background:#eef2fb;color:#2563eb;padding:6px 12px;" onclick="trendChart.resetZoom()">확대 초기화</button>
      </span>
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
</main>

</div>
</div>

<?php if (!empty($chartData) && !$isCseiGraph): ?>
<script>
const labels  = <?= json_encode(array_map(fn($r) => date('m/d', strtotime($r['created_at'])), $chartData)) ?>;
const scores  = <?= json_encode(array_map(fn($r) => (int)$r['total_score'], $chartData)) ?>;
const maxScore = <?= $scale['max_score'] ?>;
const cutoff   = <?= json_encode($cutoffMap[$selectedScale]['value'] ?? null) ?>;
const cutoffLabel = <?= json_encode($cutoffMap[$selectedScale]['label'] ?? null) ?>;
const scoringRanges = <?= json_encode(array_map(fn($r) => ['min'=>$r['min'],'max'=>$r['max'],'color'=>$r['color'],'label'=>$r['label']], $scale['scoring'])) ?>;

const colorMap = {
  green:'#27ae6022', yellow:'#f39c1222', orange:'#e67e2222',
  red:'#4b556322', darkred:'#4b556322', black:'#4b556322'
};
const colorFull = {
  green:'#27ae60', yellow:'#d4a017', orange:'#d35400',
  red:'#4b5563', darkred:'#4b5563', black:'#4b5563'
};

const ctx = document.getElementById('trendChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 380);
gradient.addColorStop(0, 'rgba(37,99,235,0.25)');
gradient.addColorStop(1, 'rgba(37,99,235,0.0)');

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
    ctx.strokeStyle='#4b5563';
    ctx.lineWidth=2;
    ctx.setLineDash([6,4]);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle='#4b5563';
    ctx.font='bold 11px sans-serif';
    ctx.textAlign='right';
    ctx.fillText(`절단점 ${cutoff}점`, chartArea.right-4, y-5);
    ctx.restore();
  }
};

const T_XMAX = Math.max(1, labels.length - 1);
const tXTick = (v)=>{ const i=Math.round(v); return (Math.abs(v-i)<1e-6 && i>=0 && i<labels.length) ? labels[i] : ''; };
const trendChart = new Chart(ctx, {
  type:'line',
  data:{
    datasets:[{
      label:`${<?= json_encode($selectedScale) ?>} 점수`,
      data:scores.map((s,i)=>({x:i, y:s})),
      borderColor:'#2563eb',
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
    interaction:{mode:'index',intersect:false,axis:'x'},
    plugins:{
      legend:{display:false},
      tooltip:{
        callbacks:{
          title(items){ const i=Math.round(items[0].parsed.x); return labels[i] ?? ''; },
          label(c){
            const s=c.parsed.y;
            let lbl='';
            for(const r of scoringRanges){if(s>=r.min&&s<=r.max){lbl=r.label;break;}}
            return `${s}점 — ${lbl}`;
          }
        }
      },
      zoom:{
        zoom:{ wheel:{enabled:true, speed:0.06}, pinch:{enabled:true}, mode:'x' },
        pan:{ enabled:true, mode:'x', threshold:5 },
        limits:{ x:{min:0, max:T_XMAX, minRange:1.5} }
      }
    },
    scales:{
      y:{min:0,max:maxScore,ticks:{stepSize:Math.ceil(maxScore/6)},grid:{color:'#e5e7eb'}},
      x:{type:'linear', min:0, max:T_XMAX, grid:{color:'#e5e7eb'},
         ticks:{stepSize:1, autoSkip:true, maxRotation:0, callback:tXTick}}
    }
  },
  plugins:[bgBandPlugin,cutoffPlugin,yLabelPlugin]
});
</script>
<?php endif; ?>

<?php if ($isCseiGraph && !empty($cseiSeries)): ?>
<script>
const CS = <?= json_encode($cseiSeries, JSON_UNESCAPED_UNICODE) ?>;
const FORDER = ['JOY','ANGER','THOUGHT','DEPRESSION','SORROW','FRIGHT','FEAR'];
const FNAME  = {JOY:'기쁨',ANGER:'분노',THOUGHT:'고민',DEPRESSION:'근심',SORROW:'슬픔',FRIGHT:'두려움',FEAR:'놀람'};
const FCOLOR = {JOY:'#2563eb',ANGER:'#dc2626',THOUGHT:'#d97706',DEPRESSION:'#7c3aed',SORROW:'#0891b2',FRIGHT:'#059669',FEAR:'#db2777'};
const grpKr = g => g==='risk'?'위험군':(g==='caution'?'주의군':'정상군');
const dates = CS.map(r=>r.date);

// T-score 구간 배경 밴드 (정상/주의/위험)
const bands = [ {y1:0,y2:30,c:'rgba(192,57,43,0.05)'},{y1:30,y2:40,c:'rgba(230,126,34,0.07)'},{y1:40,y2:60,c:'rgba(39,174,96,0.06)'},{y1:60,y2:70,c:'rgba(230,126,34,0.07)'},{y1:70,y2:100,c:'rgba(192,57,43,0.05)'} ];
const bandPlugin = { id:'bands', beforeDraw(chart){ const {ctx,chartArea,scales}=chart; if(!scales.y)return; bands.forEach(b=>{ const yT=scales.y.getPixelForValue(b.y2), yB=scales.y.getPixelForValue(b.y1); ctx.save(); ctx.fillStyle=b.c; ctx.fillRect(chartArea.left,yT,chartArea.width,yB-yT); ctx.restore(); }); } };

// 1) 최근 감정 프로파일 레이더
const latest = CS[CS.length-1];
const rlabels = FORDER.map(k=>FNAME[k]);
const rvals   = FORDER.map(k=> (latest[k] ?? 0));
const rColors = (latest.factors||[]).reduce((m,f)=>{m[f.factor]=f.group;return m;},{});
const rPoint  = FORDER.map(k=> ({normal:'#27ae60',caution:'#e67e22',risk:'#4b5563'})[rColors[k]] || '#3b6cb7');
new Chart(document.getElementById('cseiRadar').getContext('2d'), {
  type:'radar',
  data:{ labels:rlabels, datasets:[
    {label:'평균(50)', data:rlabels.map(()=>50), borderColor:'#94a3b8', borderDash:[4,4], borderWidth:1, pointRadius:0, fill:false},
    {label:'T점수', data:rvals, borderColor:'#3b6cb7', backgroundColor:'rgba(59,108,183,0.15)', borderWidth:2.5, pointBackgroundColor:rPoint, pointRadius:5}
  ]},
  options:{responsive:true,maintainAspectRatio:false,
    scales:{r:{min:0,max:100,ticks:{stepSize:20,backdropColor:'transparent',color:'#999',font:{size:10}},grid:{color:'#e5e7eb'},angleLines:{color:'#e5e7eb'},pointLabels:{font:{size:12,weight:'bold'},color:'#4b5563'}}},
    plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}}
});

// 2) 7감정 날짜별 추이 — 선형 x축(연속)으로 부드러운 확대 + 좌우 드래그(패닝)
const trendDs = FORDER.map(k=>({
  label:FNAME[k], data:CS.map((r,i)=>({x:i, y:(r[k] ?? null)})), borderColor:FCOLOR[k], backgroundColor:FCOLOR[k],
  borderWidth:2, pointRadius:4, pointHoverRadius:6, tension:0.3, fill:false, spanGaps:true
}));
trendDs.push({label:'종합', data:CS.map((r,i)=>({x:i, y:r.overall})), borderColor:'#0f172a', borderDash:[6,4], borderWidth:2.5, pointRadius:3, tension:0.3, fill:false});
const XMAX = Math.max(1, dates.length - 1);
const xTickLabel = (v)=>{ const i=Math.round(v); return (Math.abs(v-i)<1e-6 && i>=0 && i<dates.length) ? dates[i] : ''; };
const cseiTrendChart = new Chart(document.getElementById('cseiTrend').getContext('2d'), {
  type:'line',
  data:{ datasets:trendDs },
  options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false,axis:'x'},
    plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:14,usePointStyle:true}},
      tooltip:{callbacks:{title:(items)=>{ const r=CS[Math.round(items[0].parsed.x)]; return r?r.fulldate:''; }}},
      zoom:{
        zoom:{ wheel:{enabled:true, speed:0.06}, pinch:{enabled:true}, mode:'x' },
        pan:{ enabled:true, mode:'x', threshold:5 },
        // 한 번에 최소 2개 지점은 보이도록(minRange) + 데이터 범위 밖으로는 못 나가게
        limits:{ x:{ min:0, max:XMAX, minRange:1.5 } }
      }},
    scales:{
      y:{min:0,max:100,ticks:{stepSize:20,color:'#999'},grid:{color:'#eef1f6'},title:{display:true,text:'T-score',color:'#999',font:{size:11}}},
      x:{type:'linear', min:0, max:XMAX, grid:{display:false},
         ticks:{stepSize:1, autoSkip:true, maxRotation:0, includeBounds:true, font:{size:11,weight:'bold'},color:'#666', callback:xTickLabel}}}},
  plugins:[bandPlugin]
});
</script>
<?php endif; ?>

<script>
// 환자 검색형 선택
(function(){
  const PATIENTS = <?= json_encode(array_map(fn($p)=>['id'=>(int)$p['id'],'name'=>$p['name'],'birth'=>$p['birth_date']], $patients), JSON_UNESCAPED_UNICODE) ?>;
  const PATIENT_DATES = <?= json_encode($patientDates) ?>;  // {pid:[YYYY-MM-DD,...]}
  const input = document.getElementById('patientSearch');
  const list  = document.getElementById('patientList');
  const hid   = document.getElementById('patientId');
  const dateF = document.getElementById('examDateFilter');
  const dNote = document.getElementById('examDateNote');
  if (!input || !list || !hid) return;
  const form  = input.closest('form');
  const esc = s => String(s==null?'':s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function render(q){
    q = (q||'').trim().toLowerCase();
    const day = dateF ? dateF.value : '';
    let items = PATIENTS;
    // 검사일 필터: 그 날 검사한 환자만
    if (day) items = items.filter(p => (PATIENT_DATES[p.id]||[]).includes(day));
    if (q) items = items.filter(p => (p.name||'').toLowerCase().includes(q) || (p.birth||'').includes(q));
    if (!items.length){
      list.innerHTML = '<div class="patient-empty">' + (day ? '이 날짜에 검사한 환자가 없습니다' : '일치하는 환자가 없습니다') + '</div>';
    } else {
      list.innerHTML = items.slice(0,50).map(p =>
        `<div class="patient-item" data-id="${p.id}" data-label="${esc(p.name)} (${esc(p.birth||'생년월일 미상')})">
           <span>${esc(p.name)}</span><span class="pi-birth">${esc(p.birth||'생년월일 미상')}</span>
         </div>`).join('');
    }
    list.classList.add('open');
  }
  function onExamDate(){
    const v = dateF.value;
    if (dNote) dNote.textContent = /^\d{4}-\d{2}-\d{2}$/.test(v) ? '· '+v+' 검사자' : '';
    hid.value=''; render(input.value);
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) input.focus();
  }
  if (dateF){ dateF.addEventListener('change', onExamDate); dateF.addEventListener('input', onExamDate); }
  input.addEventListener('focus', ()=>render(input.value));
  input.addEventListener('input', ()=>{ hid.value=''; render(input.value); });
  list.addEventListener('click', e=>{
    const it = e.target.closest('.patient-item'); if(!it) return;
    hid.value = it.dataset.id;
    input.value = it.dataset.label;
    list.classList.remove('open');
    form.submit();
  });
  document.addEventListener('click', e=>{ if(!e.target.closest('.filter-patient-wrap')) list.classList.remove('open'); });
  input.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); const first=list.querySelector('.patient-item'); if(first){ hid.value=first.dataset.id; input.value=first.dataset.label; form.submit(); } } });
})();
</script>
</body>
</html>
