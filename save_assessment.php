<?php
// 세션 먼저 시작 (config와 동일한 세션명 사용)
require_once __DIR__ . '/config.php';
session_name(SESSION_NAME);
session_start();
require_once __DIR__ . '/scales.php';

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
    $db     = getDB();

    // 환자 조회 또는 생성
    $stmt = $db->prepare('SELECT id FROM patients WHERE name = ?');
    $stmt->execute([$patientName]);
    $patient = $stmt->fetch();
    if (!$patient) {
        $stmt = $db->prepare('INSERT INTO patients (name) VALUES (?)');
        $stmt->execute([$patientName]);
        $patientId = $db->lastInsertId();
    } else {
        $patientId = $patient['id'];
    }

    // 결과 저장
    $stmt = $db->prepare(
        'INSERT INTO assessments (patient_id, scale_type, answers, total_score, result_label, memo, admin_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $patientId,
        $scaleType,
        json_encode($answers, JSON_UNESCAPED_UNICODE),
        $scored['total'],
        $scored['label'],
        $memo,
        (int)$_SESSION['admin_id'],
    ]);

    echo json_encode([
        'success' => true,
        'total'   => $scored['total'],
        'label'   => $scored['label'],
        'message' => '저장 완료',
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
