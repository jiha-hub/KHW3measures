<?php
// 세션 먼저 시작 (config와 동일한 세션명 사용)
require_once __DIR__ . '/config.php';
session_name(SESSION_NAME);
session_start();
require_once __DIR__ . '/scales.php';
require_once __DIR__ . '/patient_store.php';

// JSON 응답 헤더
header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// POST 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청']);
    exit;
}

// JSON 입력 파싱
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => '데이터 파싱 오류']);
    exit;
}

// CSRF 확인
$token = $input['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    echo json_encode(['success' => false, 'message' => '보안 토큰 오류']);
    exit;
}

$patientName = trim($input['patient_name'] ?? '');
$scaleType   = $input['scale_type'] ?? '';
$answers     = $input['answers'] ?? [];
$memo        = trim($input['memo'] ?? '');
$birthDate   = trim($input['birth_date'] ?? '');
$gender      = trim($input['gender'] ?? '');
$phone       = trim($input['phone'] ?? '');
$batteryId   = trim($input['battery_id'] ?? '');
$assessmentId = (int)($input['assessment_id'] ?? 0);
$scales      = getScales();

if (empty($patientName)) {
    echo json_encode(['success' => false, 'message' => '환자 이름 누락']);
    exit;
}
if (!array_key_exists($scaleType, $scales)) {
    echo json_encode(['success' => false, 'message' => '척도 오류: ' . $scaleType]);
    exit;
}

$expectedCount = count($scales[$scaleType]['questions']);
$actualCount   = count($answers);
if ($actualCount !== $expectedCount) {
    echo json_encode(['success' => false, 'message' => "문항 수 불일치 (받은: {$actualCount}, 필요: {$expectedCount})"]);
    exit;
}

try {
    $scored = calculateScore($scaleType, array_values($answers));
    // 하위영역(subscale)이 있는 척도(SSD-12 등)는 factor_scores(JSON)에 저장
    $factorScores = !empty($scored['subscales'])
        ? json_encode(['subscales' => $scored['subscales'], 'flag' => $scored['flag'] ?? null], JSON_UNESCAPED_UNICODE)
        : null;
    $db     = getDB();

    // 환자 조회/생성 (이름 + 생년월일 기준)
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
            'INSERT INTO assessments (patient_id, scale_type, answers, total_score, result_label, memo, admin_id, battery_id, factor_scores)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $patientId,
            $scaleType,
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            $scored['total'],
            $scored['label'],
            $memo,
            (int)$_SESSION['admin_id'],
            $batteryId ?: null,
            $factorScores,
        ]);
        $savedId = (int)$db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'id'      => $savedId,
        'total'   => $scored['total'],
        'label'   => $scored['label'],
        'message' => '저장 완료',
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
