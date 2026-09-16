<?php
// =============================================================
// 환자 기본정보 수정 엔드포인트
//   - 이름 / 생년월일 / 성별 / 연락처 를 수정합니다.
//   - 검사 기록(assessments)은 patient_id 로 연결되어 있어 그대로 유지됩니다.
//   - (이름, 생년월일) 조합이 다른 환자와 겹치면 저장을 막습니다(동명이인 안전장치).
//   POST(form) 전용, CSRF 검증, 로그인 필요.
// =============================================================
require_once __DIR__ . '/auth.php';
requireLogin();
startSession();

$token  = $_POST['csrf_token'] ?? '';
$pid    = (int)($_POST['patient_id'] ?? 0);
$name   = trim((string)($_POST['name'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$phone  = trim((string)($_POST['phone'] ?? ''));

// 생년월일: 숫자만 추려 8자리면 YYYY-MM-DD, 아니면 null
$birthDigits = preg_replace('/[^0-9]/', '', (string)($_POST['birth_date'] ?? ''));
$birth = null;
if (strlen($birthDigits) === 8) {
    $birth = substr($birthDigits, 0, 4) . '-' . substr($birthDigits, 4, 2) . '-' . substr($birthDigits, 6, 2);
}

$back = function (string $msg) {
    header('Location: history.php?msg=' . urlencode($msg));
    exit;
};

if (!verifyCsrfToken($token)) $back('err:보안 토큰 오류');
if ($pid <= 0)               $back('err:잘못된 환자');
if ($name === '')            $back('err:이름을 입력하세요');
if ($gender !== '' && !in_array($gender, ['남', '여'], true)) $gender = '';
if (strlen($birthDigits) > 0 && strlen($birthDigits) !== 8)   $back('err:생년월일은 8자리 숫자로 입력하세요 (예: 19800315)');
// 유효 날짜 검사
if ($birth !== null) {
    [$yy, $mm, $dd] = array_map('intval', explode('-', $birth));
    if (!checkdate($mm, $dd, $yy)) $back('err:올바른 날짜가 아닙니다');
}

try {
    $db = getDB();

    // 대상 환자 존재 확인
    $chk = $db->prepare('SELECT id FROM patients WHERE id = ? LIMIT 1');
    $chk->execute([$pid]);
    if (!$chk->fetch()) $back('err:환자를 찾을 수 없습니다');

    // (이름, 생년월일) 중복 방지 — 다른 환자와 겹치면 막기
    if ($birth !== null) {
        $dup = $db->prepare('SELECT id FROM patients WHERE name = ? AND birth_date = ? AND id <> ? LIMIT 1');
        $dup->execute([$name, $birth, $pid]);
    } else {
        $dup = $db->prepare('SELECT id FROM patients WHERE name = ? AND birth_date IS NULL AND id <> ? LIMIT 1');
        $dup->execute([$name, $pid]);
    }
    if ($dup->fetch()) $back('err:같은 이름·생년월일의 다른 환자가 이미 있습니다. 확인해 주세요.');

    $stmt = $db->prepare('UPDATE patients SET name = ?, birth_date = ?, gender = ?, phone = ? WHERE id = ?');
    $stmt->execute([
        $name,
        $birth,
        ($gender !== '' ? $gender : null),
        ($phone  !== '' ? $phone  : null),
        $pid,
    ]);

    $back('환자 정보를 수정했습니다.');
} catch (Exception $e) {
    $back('err:DB 오류: ' . $e->getMessage());
}
