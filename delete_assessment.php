<?php
// =============================================================
// 검사 기록 소프트 삭제(숨김) / 복구 엔드포인트
//   - 실제로 행을 지우지 않고 deleted_at 값을 채워 "숨김" 처리합니다.
//   - 의료·연구 데이터 보존을 위해 완전 삭제 대신 숨김을 사용합니다.
//   - action=delete  → deleted_at = NOW()
//     action=restore → deleted_at = NULL
//   POST(form) 또는 JSON 모두 허용, CSRF 검증.
// =============================================================
require_once __DIR__ . '/auth.php';
requireLogin();
startSession();

$isJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
if ($isJson) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

$token  = $input['csrf_token'] ?? '';
$action = $input['action'] ?? 'delete';
$return = $input['return'] ?? 'history.php';

// 대상 id: 단일(id) 또는 일괄(ids[])
$ids = [];
if (isset($input['ids']) && is_array($input['ids'])) {
    foreach ($input['ids'] as $v) { $v = (int)$v; if ($v > 0) $ids[] = $v; }
}
if (isset($input['id']) && (int)$input['id'] > 0) $ids[] = (int)$input['id'];
$ids = array_values(array_unique($ids));

$respond = function (bool $ok, string $msg = '') use ($isJson, $return) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    } else {
        // 폼 제출: 이력 화면으로 돌아가기 (안전한 내부 경로만)
        $loc = 'history.php';
        if (is_string($return) && preg_match('#^(history|graph|summary)\.php#', $return)) $loc = $return;
        $sep = str_contains($loc, '?') ? '&' : '?';
        header('Location: ' . $loc . $sep . 'msg=' . urlencode($ok ? ($msg ?: 'done') : ('err:' . $msg)));
    }
    exit;
};

if (!verifyCsrfToken($token)) $respond(false, '보안 토큰 오류');
if (empty($ids))              $respond(false, '선택된 대상이 없습니다');
if (!in_array($action, ['delete', 'restore'], true)) $respond(false, '알 수 없는 작업');

try {
    $db = getDB();
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $n  = count($ids);
    if ($action === 'delete') {
        $stmt = $db->prepare("UPDATE assessments SET deleted_at = NOW() WHERE id IN ($ph) AND deleted_at IS NULL");
        $stmt->execute($ids);
        $respond(true, $n > 1 ? "{$n}건을 숨김 처리했습니다." : '검사 기록을 숨김 처리했습니다.');
    } else {
        $stmt = $db->prepare("UPDATE assessments SET deleted_at = NULL WHERE id IN ($ph)");
        $stmt->execute($ids);
        $respond(true, $n > 1 ? "{$n}건을 복구했습니다." : '검사 기록을 복구했습니다.');
    }
} catch (Exception $e) {
    $respond(false, 'DB 오류: ' . $e->getMessage());
}
