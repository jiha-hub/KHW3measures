<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/patient_store.php';
requireLogin();
startSession();

$cd = $_SESSION['consent_data'] ?? null;
if (empty($cd['scale_queue']) || !is_array($cd['scale_queue'])) {
    header('Location: consent.php?step=1');
    exit;
}

// 직전 검사 저장 완료 후 다음으로 진행
if (isset($_GET['next'])) {
    $cd['qpos'] = (int)($cd['qpos'] ?? 0) + 1;
    $_SESSION['consent_data'] = $cd;
}

$queue = array_values($cd['scale_queue']);
$pos   = (int)($cd['qpos'] ?? 0);

// 모든 검사 완료 → 방문 요약(의료진용)으로
if ($pos >= count($queue)) {
    $_SESSION['last_battery'] = [
        'battery_id'   => $cd['battery_id']   ?? '',
        'patient_name' => $cd['patient_name'] ?? '',
        'birth_date'   => $cd['birth_date']   ?? '',
    ];
    unset($_SESSION['consent_data']);
    $bid = $_SESSION['last_battery']['battery_id'];
    header('Location: summary.php?battery=' . urlencode($bid));
    exit;
}

// 현재 척도로 이동
$scale = $queue[$pos];
if ($scale === 'PSQI-K') {
    header('Location: psqi.php?from=battery');
} elseif ($scale === 'CSEI-s') {
    header('Location: csei_survey.php?from=battery');
} else {
    header('Location: index.php?from=battery&scale=' . urlencode($scale));
}
exit;
