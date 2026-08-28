<?php
// =============================================================
// EMR 붙여넣기용 자연어 텍스트 생성 (summary.php / history.php 공용)
// =============================================================
require_once __DIR__ . '/scales.php';
require_once __DIR__ . '/psqi_scoring.php';
require_once __DIR__ . '/csei.php';

/** 한 검사행(assessments 테이블 row + scale_type)의 임상 해석을 계산 */
function interpret(array $row, array $scales, array $psqiMeta): array {
    $answers = json_decode($row['answers'], true);
    if (!is_array($answers)) $answers = [];
    $answers = array_values($answers);
    $type = $row['scale_type'];

    if ($type === 'CSEI-s') {
        $fs = json_decode($row['factor_scores'] ?? '', true) ?: [];
        $ov = $fs['overall'] ?? ['tScore'=>(int)($row['total_score'] ?? 0), 'groupLabel'=>($row['result_label'] ?? ''), 'group'=>'normal'];
        $cmap = ['normal'=>'green', 'caution'=>'yellow', 'risk'=>'red'];
        return [
            'total'        => (int)$ov['tScore'],
            'max'          => null,
            'label'        => $ov['groupLabel'],
            'color'        => $cmap[$ov['group']] ?? '',
            'cutoff'       => null,
            'poor'         => null,
            'psqi'         => null,
            'flag'         => null,
            'csei'         => $fs['factors'] ?? [],
            'csei_overall' => $ov,
        ];
    }

    if ($type === 'PSQI-K') {
        $r = calculatePSQI($answers);
        return [
            'total'   => $r['total'],
            'max'     => $psqiMeta['max_score'],
            'label'   => $r['label'],
            'color'   => $r['color'],
            'cutoff'  => $psqiMeta['cutoff'],
            'poor'    => $r['poor_sleep'],
            'psqi'    => $r,
            'flag'    => null,
        ];
    }

    $s = $scales[$type] ?? null;
    $sc = calculateScore($type, $answers);
    $flag = null;
    if ($type === 'PHQ-9') {
        $q9 = (int)($answers[8] ?? 0); // 9번(자해·자살 사고)
        if ($q9 >= 2)      $flag = ['level' => 'urgent', 'text' => '9번 문항(자해·자살 사고) ' . $q9 . '점 — 즉각적 안전 평가 필요'];
        elseif ($q9 >= 1)  $flag = ['level' => 'warn',   'text' => '9번 문항(자해·자살 사고) ' . $q9 . '점 — 추가 임상 판단 필요'];
    }
    return [
        'total'  => $sc['total'],
        'max'    => $s['max_score'] ?? null,
        'label'  => $sc['label'],
        'color'  => $sc['color'],
        'cutoff' => $s['cutoff'] ?? null,
        'poor'   => ($s['cutoff'] ?? null) !== null ? ($sc['total'] >= $s['cutoff']) : null,
        'psqi'   => null,
        'flag'   => $flag,
    ];
}

/** 한 척도 결과($it = interpret()의 반환값)를 한 줄의 자연어 문장으로 변환 */
function buildEmrLine(array $row, array $it, ?array $scales = null, ?array $psqiMeta = null): string {
    $scaleFullNames = [
        'PHQ-9'  => '우울증 선별',
        'GAD-7'  => '불안장애 선별',
        'PSS-10' => '스트레스',
        'PSQI-K' => '수면의 질',
        'CSEI-s' => '핵심칠정 감정',
    ];
    $type = $row['scale_type'];
    $full = $scaleFullNames[$type] ?? '';

    // CSEI-s: 종합 T점수 + 7감정 요약 (전용 서식)
    if ($type === 'CSEI-s') {
        $line = "CSEI-s({$full}): 종합 T{$it['total']} — {$it['label']}";
        if (!empty($it['csei'])) {
            $parts = array_map(fn($f) => "{$f['name']} T{$f['tScore']}({$f['groupLabel']})", $it['csei']);
            $line .= "\n   - " . implode(', ', $parts);
        }
        return $line;
    }

    $maxTxt = $it['max'] !== null ? '/' . $it['max'] . '점' : '점';
    $line = "{$type}({$full}): {$it['total']}{$maxTxt} — {$it['label']}";

    if ($it['cutoff'] !== null) {
        if ($type === 'PSQI-K') {
            $line .= $it['poor']
                ? " (절단점 {$it['cutoff']}점 초과로 수면의 질 저하 시사)"
                : " (절단점 {$it['cutoff']}점 이하로 양호)";
        } else {
            $line .= $it['poor']
                ? " (절단점 {$it['cutoff']}점 이상으로 추가 평가 권고)"
                : " (절단점 {$it['cutoff']}점 미만)";
        }
    } elseif ($type === 'PSS-10') {
        $line .= " (공식 절단점 없음)";
    }

    if ($it['psqi']) {
        $r = $it['psqi'];
        $bed  = $row['answers_decoded'][0] ?? '';
        $wake = $row['answers_decoded'][2] ?? '';
        $extra = [];
        if ($bed !== '' || $wake !== '') $extra[] = "취침 " . ($bed ?: '—') . ", 기상 " . ($wake ?: '—');
        if ($r['hours_in_bed'] !== null) $extra[] = "실 수면시간 " . ($row['answers_decoded'][3] ?? '?') . "시간";
        if ($r['efficiency'] !== null)   $extra[] = "수면효율 {$r['efficiency']}%";
        if ($extra) $line .= "\n   - " . implode(', ', $extra) . '.';
    }

    if ($it['flag']) {
        $mark = $it['flag']['level'] === 'urgent' ? '[긴급]' : '[주의]';
        $line .= "\n   {$mark} {$it['flag']['text']}";
    }

    return $line;
}

/** 리포트 상단 헤더(환자 정보) */
function buildEmrHeader(string $title, ?string $patientName, ?string $gender, ?int $age, ?string $visitDate, ?string $adminName): string {
    $meta = [];
    if ($gender) $meta[] = $gender;
    if ($age !== null) $meta[] = "만 {$age}세";
    $metaStr = $meta ? ' (' . implode(', ', $meta) . ')' : '';

    $lines = [];
    $lines[] = "[{$title}]";
    if ($patientName) $lines[] = "환자: {$patientName}{$metaStr}";
    if ($visitDate)   $lines[] = "검사일: {$visitDate}";
    if ($adminName)   $lines[] = "검사자: {$adminName}";
    return implode("\n", $lines);
}
