<?php
// PSQI-K 전용 저장 엔드포인트 (save_assessment.php와 동일한 구조)
require_once __DIR__ . '/config.php';
session_name(SESSION_NAME);
session_start();
require_once __DIR__ . '/psqi_scoring.php';
require_once __DIR__ . '/patient_store.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청']); exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input) { echo json_encode(['success' => false, 'message' => '데이터 파싱 오류']); exit; }

$token = $input['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    echo json_encode(['success' => false, 'message' => '보안 토큰 오류']); exit;
}

$patientName = trim($input['patient_name'] ?? '');
$answers     = $input['answers'] ?? [];
$memo        = trim($input['memo'] ?? '');
$birthDate   = trim($input['birth_date'] ?? '');
$gender      = trim($input['gender'] ?? '');
$phone       = trim($input['phone'] ?? '');
$batteryId   = trim($input['battery_id'] ?? '');
$assessmentId = (int)($input['assessment_id'] ?? 0);

if ($patientName === '') { echo json_encode(['success' => false, 'message' => '환자 이름 누락']); exit; }

$meta = getPsqiMeta();
if (count($answers) !== $meta['answer_count']) {
    echo json_encode(['success' => false, 'message' => "문항 수 불일치 (받은: " . count($answers) . ", 필요: {$meta['answer_count']})"]); exit;
}

try {
    $scored = calculatePSQI(array_values($answers));
    $db     = getDB();

    $patientId = upsertPatient($db, $patientName, $birthDate ?: null, $gender ?: null, $phone ?: null);

    // 이미 저장된 검사(assessment_id)라면 새 행을 만들지 않고 갱신 (메모 추가 재저장 시 중복 방지)
    $existing = null;
    if ($assessmentId > 0) {
        $chk = $db->prepare('SELECT id FROM assessments WHERE id = ? AND admin_id = ?');
        $chk->execute([$assessmentId, (int)$_SESSION['admin_id']]);
        $existing = $chk->fetch();
    }

    if ($existing) {
        $stmt = $db->prepare(
            'UPDATE assessments SET answers = ?, total_score = ?, result_label = ?, memo = ? WHERE id = ?'
        );
        $stmt->execute([
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            $scored['total'],
            $scored['label'],
            $memo,
            $assessmentId,
        ]);
        $savedId = $assessmentId;
    } else {
        $stmt = $db->prepare(
            'INSERT INTO assessments (patient_id, scale_type, answers, total_score, result_label, memo, admin_id, battery_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $patientId,
            'PSQI-K',
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            $scored['total'],
            $scored['label'],
            $memo,
            (int)$_SESSION['admin_id'],
            $batteryId ?: null,
        ]);
        $savedId = (int)$db->lastInsertId();
    }

    echo json_encode([
        'success'    => true,
        'id'         => $savedId,
        'total'      => $scored['total'],
        'label'      => $scored['label'],
        'components' => $scored['components'],
        'efficiency' => $scored['efficiency'],
        'poor_sleep' => $scored['poor_sleep'],
        'message'    => '저장 완료',
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
