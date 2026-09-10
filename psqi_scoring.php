<?php
// =============================================================
// PSQI-K (한국판 피츠버그 수면의 질 지수) 문항 정의 및 채점 로직
// 원저: Buysse DJ et al. (1989) Psychiatry Res 28:193-213
// 한국판: Sohn SI et al. (2012) Sleep Breath 16:803-812
//
// ⚠️ 문항 텍스트는 표준 검증판을 참고한 것입니다.
//    실제 임상 사용 전 보유하신 공식 문서와 문안을 대조·검수하세요.
// =============================================================

// 응답 배열은 0-index 18개.
//  0: Q1  취침 시각            (time,   "HH:MM")
//  1: Q2  잠들기까지 걸린 시간   (number, 분)
//  2: Q3  기상 시각            (time,   "HH:MM")
//  3: Q4  실제 수면 시간        (number, 시간)
//  4: Q5a 30분 내 잠들지 못함    (likert 0~3)
//  5: Q5b 한밤중/새벽에 깸       (likert 0~3)
//  6: Q5c 화장실에 가려고 깸     (likert 0~3)
//  7: Q5d 숨쉬기가 불편함        (likert 0~3)
//  8: Q5e 기침하거나 심하게 코곪 (likert 0~3)
//  9: Q5f 너무 추움             (likert 0~3)
// 10: Q5g 너무 더움             (likert 0~3)
// 11: Q5h 나쁜 꿈을 꿈           (likert 0~3)
// 12: Q5i 통증이 있음           (likert 0~3)
// 13: Q5j 그 밖의 이유          (likert 0~3)
// 14: Q6  전반적 수면의 질       (likert 0~3, 매우 좋음0 ~ 매우 나쁨3)
// 15: Q7  수면제 복용 빈도       (likert 0~3)
// 16: Q8  깨어 있기 힘듦         (likert 0~3)
// 17: Q9  의욕 유지 곤란 정도    (likert 0~3, 전혀 없음0 ~ 매우 큰 문제3)

function getPsqiMeta(): array {
    return [
        'name'      => 'PSQI-K',
        'full_name' => '한국판 피츠버그 수면의 질 지수 (PSQI-K)',
        'period'    => '지난 1개월 동안',
        'answer_count' => 18,
        'items' => [
            // idx, type, question, (options for likert), meta
            ['type' => 'time',   'q' => '지난 한 달간, 보통 몇 시에 잠자리에 들었습니까? (취침 시각)'],
            ['type' => 'number', 'q' => '지난 한 달간, 잠자리에 든 후 잠들기까지 보통 몇 분이 걸렸습니까?', 'unit' => '분', 'max' => 300, 'presets' => [0, 5, 10, 15, 20, 30, 45, 60, 90]],
            ['type' => 'time',   'q' => '지난 한 달간, 보통 몇 시에 일어났습니까? (기상 시각)'],
            ['type' => 'number', 'q' => '지난 한 달간, 실제로 잠을 잔 시간은 하룻밤에 보통 몇 시간입니까?', 'unit' => '시간', 'max' => 16, 'step' => 0.5, 'presets' => [4, 5, 6, 6.5, 7, 7.5, 8, 9]],

            ['type' => 'likert', 'group' => '5', 'q' => '지난 한 달간, 다음의 이유로 잠을 잘 이루지 못한 적이 얼마나 자주 있었습니까? — 잠자리에 든 후 30분 이내에 잠들지 못했다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '한밤중이나 새벽에 잠에서 깼다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '화장실에 가려고 일어났다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '숨쉬기가 불편했다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '기침을 하거나 코를 심하게 골았다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '너무 추웠다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '너무 더웠다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '나쁜 꿈을 꾸었다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '통증이 있었다.'],
            ['type' => 'likert', 'group' => '5', 'q' => '그 밖의 다른 이유로 잠을 이루지 못했다.'],

            ['type' => 'likert', 'q' => '지난 한 달간, 자신의 전반적인 수면의 질을 어떻게 평가하십니까?',
             'options' => ['매우 좋음', '대체로 좋음', '대체로 나쁨', '매우 나쁨']],
            ['type' => 'likert', 'q' => '지난 한 달간, 잠들기 위해 수면제(처방약 또는 일반약)를 얼마나 자주 복용했습니까?'],
            ['type' => 'likert', 'q' => '지난 한 달간, 운전 중, 식사 중, 사회활동 중에 깨어 있기가 얼마나 자주 힘들었습니까?'],
            ['type' => 'likert', 'q' => '지난 한 달간, 일을 해나가는 데 필요한 의욕을 유지하는 것이 얼마나 문제가 되었습니까?',
             'options' => ['전혀 문제 없음', '아주 약간 문제', '다소 문제', '매우 큰 문제']],

            // ===== 아래는 채점에 포함되지 않는 참고(선택) 문항입니다 (인덱스 18~) =====
            // PSQI 원 설문의 문항 10 (동거인/잠자리 공유) — 채점에는 반영되지 않으며,
            // 수면무호흡·주기성 사지운동 등 선별을 위한 임상 참고용입니다. (원판과 동일)
            ['type' => 'choice', 'optional' => true, 'unscored' => true, 'group' => '10',
             'q' => '(선택) 당신은 다른 사람과 같은 잠자리에 자거나 집을 같이 쓰는 사람이 있습니까?',
             'options' => [
                 '같은 잠자리에 자거나 집을 같이 쓰는 사람이 없다',
                 '집에 다른 방을 쓰는 사람이 있다',
                 '방을 같이 쓰지만 같은 잠자리에서 자지 않는다',
                 '같은 잠자리에 자는 사람이 있다',
             ]],
            ['type' => 'likert', 'optional' => true, 'unscored' => true, 'group' => '10',
             'q' => '(선택·동거인 관찰) 지난 한 달간 당신이 심하게 코를 골았다.'],
            ['type' => 'likert', 'optional' => true, 'unscored' => true, 'group' => '10',
             'q' => '(선택·동거인 관찰) 잠잘 때 숨을 한동안 멈추었다가 다시 쉬었다.'],
            ['type' => 'likert', 'optional' => true, 'unscored' => true, 'group' => '10',
             'q' => '(선택·동거인 관찰) 잠잘 때 다리를 갑자기 떨거나 흔들었다.'],
            ['type' => 'likert', 'optional' => true, 'unscored' => true, 'group' => '10',
             'q' => '(선택·동거인 관찰) 잠자다가 잠시 시간·장소·상황을 인식하지 못하거나 혼란스러워했다.'],
            ['type' => 'text', 'optional' => true, 'unscored' => true, 'group' => '10',
             'q' => '(선택·동거인 관찰) 그 밖에 잠자는 동안 뒤척이는 등 특이한 행동이 있었다면 적어주세요.',
             'placeholder' => '예: 잠꼬대, 이갈이 등 (없으면 비워두세요)'],
        ],
        // 채점에 사용되는 문항 수(0~17). 그 이후 인덱스(문항 10 등)는 참고용.
        'scored_count' => 18,
        // 5-j "그 밖의 다른 이유" 주관식 답변은 응답 배열의 마지막(별도)으로 저장됩니다.
        // 빈도형 리커트 기본 선택지 (5b~5j, 7, 8 등)
        'freq_options' => ['없음', '주 1회 미만', '주 1~2회', '주 3회 이상'],
        'scoring' => [
            ['min' => 0,  'max' => 5,  'label' => '양호한 수면',    'color' => 'green'],
            ['min' => 6,  'max' => 10, 'label' => '경도 수면문제',  'color' => 'yellow'],
            ['min' => 11, 'max' => 21, 'label' => '수면의 질 저하', 'color' => 'red'],
        ],
        'cutoff'    => 5,   // 5점 초과(≥6)면 수면의 질 저하
        'max_score' => 21,
        'note'      => '7개 구성요소(각 0~3점) 합산으로 채점됩니다. 절단점 5점을 초과하면 수면의 질 저하로 해석합니다. 오직 절단점 5점만 검증된 기준이며, 그 이상의 구간 구분은 시각화 편의를 위한 것입니다.',
        'source'    => 'Sohn SI 외(2012) Sleep Breath 16:803-812 / 원저: Buysse DJ 외(1989) / 임상 사용 시 공식 문안 검수 필요',
    ];
}

// "HH:MM" → 분(0~1439). 실패 시 null
function psqiParseTime($s): ?int {
    if (!is_string($s) || !preg_match('/^(\d{1,2}):(\d{2})$/', trim($s), $m)) return null;
    $h = (int)$m[1]; $min = (int)$m[2];
    if ($h > 23 || $min > 59) return null;
    return $h * 60 + $min;
}

// 취침~기상 사이 침대에 누운 시간(시간 단위). 자정 넘김 처리.
function psqiHoursInBed(?int $bedMin, ?int $wakeMin): ?float {
    if ($bedMin === null || $wakeMin === null) return null;
    $diff = $wakeMin - $bedMin;
    if ($diff <= 0) $diff += 24 * 60;   // 자정을 넘겨 잔 경우
    return $diff / 60.0;
}

/**
 * PSQI-K 채점.
 * @param array $a 18개 응답 (0-index)
 * @return array components(7), global, label, color, efficiency, cutoff 등
 */
function calculatePSQI(array $a): array {
    $a = array_values($a);
    $lik = fn($i) => max(0, min(3, (int)($a[$i] ?? 0)));  // 리커트 안전 변환

    // --- 구성요소 1: 주관적 수면의 질 (Q6) ---
    $c1 = $lik(14);

    // --- 구성요소 2: 수면 잠복기 (Q2 분 + Q5a) ---
    $latMin = max(0, (float)($a[1] ?? 0));
    if     ($latMin <= 15) $latScore = 0;
    elseif ($latMin <= 30) $latScore = 1;
    elseif ($latMin <= 60) $latScore = 2;
    else                   $latScore = 3;
    $c2sum = $latScore + $lik(4);
    if     ($c2sum == 0)  $c2 = 0;
    elseif ($c2sum <= 2)  $c2 = 1;
    elseif ($c2sum <= 4)  $c2 = 2;
    else                  $c2 = 3;

    // --- 구성요소 3: 수면 시간 (Q4) ---
    $hours = max(0, (float)($a[3] ?? 0));
    if     ($hours > 7) $c3 = 0;
    elseif ($hours >= 6) $c3 = 1;
    elseif ($hours >= 5) $c3 = 2;
    else                 $c3 = 3;

    // --- 구성요소 4: 수면 효율 (Q1, Q3, Q4) ---
    $bed  = psqiParseTime($a[0] ?? null);
    $wake = psqiParseTime($a[2] ?? null);
    $inBed = psqiHoursInBed($bed, $wake);
    if ($inBed && $inBed > 0) {
        $eff = ($hours / $inBed) * 100;
        if ($eff > 100) $eff = 100;
    } else {
        $eff = null;
    }
    if     ($eff === null) $c4 = 0;    // 시간 미입력 시 0 처리(가장 보수적)
    elseif ($eff >= 85)    $c4 = 0;
    elseif ($eff >= 75)    $c4 = 1;
    elseif ($eff >= 65)    $c4 = 2;
    else                   $c4 = 3;

    // --- 구성요소 5: 수면 방해 (Q5b~Q5j, idx 5~13) ---
    $distSum = 0;
    for ($i = 5; $i <= 13; $i++) $distSum += $lik($i);
    if     ($distSum == 0)  $c5 = 0;
    elseif ($distSum <= 9)  $c5 = 1;
    elseif ($distSum <= 18) $c5 = 2;
    else                    $c5 = 3;

    // --- 구성요소 6: 수면제 사용 (Q7) ---
    $c6 = $lik(15);

    // --- 구성요소 7: 주간 기능장애 (Q8 + Q9) ---
    $ddSum = $lik(16) + $lik(17);
    if     ($ddSum == 0)  $c7 = 0;
    elseif ($ddSum <= 2)  $c7 = 1;
    elseif ($ddSum <= 4)  $c7 = 2;
    else                  $c7 = 3;

    $components = [
        ['key' => 'C1', 'name' => '주관적 수면의 질', 'score' => $c1],
        ['key' => 'C2', 'name' => '수면 잠복기',      'score' => $c2],
        ['key' => 'C3', 'name' => '수면 시간',        'score' => $c3],
        ['key' => 'C4', 'name' => '수면 효율',        'score' => $c4],
        ['key' => 'C5', 'name' => '수면 방해',        'score' => $c5],
        ['key' => 'C6', 'name' => '수면제 사용',      'score' => $c6],
        ['key' => 'C7', 'name' => '주간 기능장애',    'score' => $c7],
    ];
    $global = $c1 + $c2 + $c3 + $c4 + $c5 + $c6 + $c7;

    $meta  = getPsqiMeta();
    $label = ''; $color = '';
    foreach ($meta['scoring'] as $r) {
        if ($global >= $r['min'] && $global <= $r['max']) { $label = $r['label']; $color = $r['color']; break; }
    }

    return [
        'components'  => $components,
        'total'       => $global,
        'label'       => $label,
        'color'       => $color,
        'efficiency'  => $eff === null ? null : round($eff, 1),
        'hours_in_bed'=> $inBed === null ? null : round($inBed, 1),
        'poor_sleep'  => $global > $meta['cutoff'],
    ];
}
