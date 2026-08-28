<?php
// =============================================
// CSEI-s 결과 저장 (AJAX / JSON) — save_psqi.php 와 동일한 구조
//   - 성별·나이(생년월일) 규준으로 7감정 T점수 산출 후 저장
//   - factor_scores(JSON) 컬럼에 요인별 결과 + 종합 저장, battery_id 로 방문 묶음
// =============================================
require_once __DIR__ . '/config.php';
session_name(SESSION_NAME);
session_start();
require_once __DIR__ . '/csei.php';
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
if ($gender === '')      { echo json_encode(['success' => false, 'message' => '성별 정보가 없습니다.']); exit; }

$scale    = getCseiScale();
$expected = $scale['answer_count'];
if (count($answers) !== $expected) {
    echo json_encode(['success' => false, 'message' => "문항 수 불일치 (받은: " . count($answers) . ", 필요: {$expected})"]); exit;
}
// 값 정규화(1~5)
$answers = array_map(fn($v) => max(1, min(5, (int)$v)), array_values($answers));
$age = ageFromBirth($birthDate ?: null);

try {
    $result  = analyzeCsei($answers, $gender, $age);
    $overall = $result['overall'];

    $db = getDB();
    $patientId = upsertPatient($db, $patientName, $birthDate ?: null, $gender ?: null, $phone ?: null);

    $factorPayload = json_encode([
        'factors'   => $result['factors'],
        'overall'   => $overall,
        'age_group' => $result['age_group'],
    ], JSON_UNESCAPED_UNICODE);

    // 재저장(assessment_id) 시 갱신, 아니면 신규 insert
    $existing = null;
    if ($assessmentId > 0) {
        $chk = $db->prepare('SELECT id FROM assessments WHERE id = ? AND admin_id = ?');
        $chk->execute([$assessmentId, (int)$_SESSION['admin_id']]);
        $existing = $chk->fetch();
    }

    if ($existing) {
        $stmt = $db->prepare(
            'UPDATE assessments SET answers = ?, total_score = ?, result_label = ?, memo = ?, factor_scores = ? WHERE id = ?'
        );
        $stmt->execute([
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            $overall['tScore'],
            $overall['groupLabel'],
            $memo,
            $factorPayload,
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
            'CSEI-s',
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            $overall['tScore'],
            $overall['groupLabel'],
            $memo,
            (int)$_SESSION['admin_id'],
            $batteryId ?: null,
            $factorPayload,
        ]);
        $savedId = (int)$db->lastInsertId();
    }

    echo json_encode([
        'success'     => true,
        'id'          => $savedId,
        'overall_t'   => $overall['tScore'],
        'overall_grp' => $overall['groupLabel'],
        'message'     => '저장 완료',
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
