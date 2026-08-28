<?php
// =============================================
// CSEI-s (핵심칠정척도 단축형, Core Seven-Emotions Inventory - short form)
// 문항 / 규준(M,SD) / 채점(T점수) / 분류 로직
//   - 7개 핵심 감정: 기쁨·분노·고민·근심·슬픔·두려움·놀람
//   - 28문항, 5점 척도(1~5). 각 요인 4문항: index (idx, idx+7, idx+14, idx+21)
//   - 성별×연령대 규준으로 Z→T점수 산출, 정상/주의/위험 분류
// =============================================

if (!function_exists('cseiFactorNames')) {
function cseiFactorNames(): array {
    return [
        'JOY'        => '기쁨',
        'ANGER'      => '분노',
        'THOUGHT'    => '고민',
        'DEPRESSION' => '근심',
        'SORROW'     => '슬픔',
        'FRIGHT'     => '두려움',
        'FEAR'       => '놀람',
    ];
}
}

if (!function_exists('cseiFactorOrder')) {
function cseiFactorOrder(): array {
    return ['JOY', 'ANGER', 'THOUGHT', 'DEPRESSION', 'SORROW', 'FRIGHT', 'FEAR'];
}
}

if (!function_exists('getCseiScale')) {
function getCseiScale(): array {
    return [
        'name'        => 'CSEI-s',
        'full_name'   => '핵심칠정척도 단축형 (CSEI-s: Core Seven-Emotions Inventory - short form)',
        'instruction' => '평소 자신의 상태에 가장 가깝다고 생각되는 정도를 선택해 주세요. 한의학의 칠정(희·노·우·사·비·공·경)을 바탕으로 감정 상태를 다각도로 평가합니다.',
        'options'       => ['정말 그렇다', '자주 그렇다', '보통이다', '가끔 그렇다', '그렇지 않다'],
        'option_values' => [5, 4, 3, 2, 1],
        // 문항 순서는 요인 계산과 직결됨 — 원본 순서 그대로 유지
        'questions'   => [
            '내게 좋은 일이 생길 것 같다.',                         // 0  JOY
            '나는 주변 사람들에게 화를 잘 낸다.',                    // 1  ANGER
            '나는 생각이 많다.',                                    // 2  THOUGHT
            '나는 아무 일도 하고 싶은 의욕이 없다.',                 // 3  DEPRESSION
            '나는 서글플 때가 있다.',                               // 4  SORROW
            '나는 간이 작은 것 같다.',                               // 5  FRIGHT
            '나는 깜짝깜짝 놀랜다.',                                 // 6  FEAR
            '나는 기분이 들뜬다.',                                   // 7  JOY
            '나는 다른 사람보다 화를 자주 낸다.',                    // 8  ANGER
            '나는 고민거리가 많다.',                                 // 9  THOUGHT
            '내 미래는 어두울 것 같다.',                             // 10 DEPRESSION
            '나는 구슬플 때가 있다.',                                // 11 SORROW
            '나는 쉽게 당황한다.',                                   // 12 FRIGHT
            '나는 잘 놀랜다.',                                       // 13 FEAR
            '나는 활기차다.',                                        // 14 JOY
            '나도 모르게 불끈 성을 낸다.',                           // 15 ANGER
            '나는 걱정을 많이 한다.',                                // 16 THOUGHT
            '나는 만사가 귀찮다.',                                   // 17 DEPRESSION
            '나는 슬플 때가 있다.',                                  // 18 SORROW
            '나는 낯선 사람이 두렵다.',                              // 19 FRIGHT
            '나는 놀라서 소스라치곤 한다.',                          // 20 FEAR
            '내 삶은 만족스럽다.',                                   // 21 JOY
            '내 주변에는 나를 화나게 하는 게 많다.',                 // 22 ANGER
            '나는 반복적으로 떠오르는 생각을 지우기가 어렵다.',      // 23 THOUGHT
            '내 미래는 희망이 없을 것 같다.',                        // 24 DEPRESSION
            '나는 외롭다.',                                          // 25 SORROW
            '나는 여러 사람 앞에 나가 이야기하는 것이 어렵다.',      // 26 FRIGHT
            '나는 작은 소리에도 잘 놀란다.',                         // 27 FEAR
        ],
        'answer_count'=> 28,
        'max_score'   => 140,
        'source'      => 'CSEI-s (핵심칠정척도 단축형) — 한의학 칠정 기반 표준화 감정평가 도구',
    ];
}
}

if (!function_exists('cseiNorms')) {
function cseiNorms(): array {
    return [
        '20s' => [
            'male'   => ['JOY'=>[10,3],'ANGER'=>[8,3],'THOUGHT'=>[13,4],'DEPRESSION'=>[10,4],'SORROW'=>[10,4],'FRIGHT'=>[11,4],'FEAR'=>[9,4],'TOTAL'=>[72,18]],
            'female' => ['JOY'=>[11,4],'ANGER'=>[9,4],'THOUGHT'=>[14,4],'DEPRESSION'=>[10,4],'SORROW'=>[11,4],'FRIGHT'=>[11,4],'FEAR'=>[11,4],'TOTAL'=>[76,18]],
        ],
        '30s' => [
            'male'   => ['JOY'=>[11,3],'ANGER'=>[9,4],'THOUGHT'=>[13,4],'DEPRESSION'=>[10,4],'SORROW'=>[10,4],'FRIGHT'=>[10,4],'FEAR'=>[9,3],'TOTAL'=>[71,18]],
            'female' => ['JOY'=>[10,3],'ANGER'=>[9,4],'THOUGHT'=>[13,4],'DEPRESSION'=>[10,4],'SORROW'=>[11,4],'FRIGHT'=>[11,4],'FEAR'=>[10,4],'TOTAL'=>[75,18]],
        ],
        '40s' => [
            'male'   => ['JOY'=>[11,3],'ANGER'=>[9,3],'THOUGHT'=>[12,4],'DEPRESSION'=>[9,4],'SORROW'=>[10,4],'FRIGHT'=>[10,3],'FEAR'=>[8,4],'TOTAL'=>[67,18]],
            'female' => ['JOY'=>[10,3],'ANGER'=>[8,3],'THOUGHT'=>[12,4],'DEPRESSION'=>[9,4],'SORROW'=>[10,4],'FRIGHT'=>[10,4],'FEAR'=>[9,4],'TOTAL'=>[68,17]],
        ],
        '50s_plus' => [
            'male'   => ['JOY'=>[10,3],'ANGER'=>[8,3],'THOUGHT'=>[11,4],'DEPRESSION'=>[8,4],'SORROW'=>[9,4],'FRIGHT'=>[9,4],'FEAR'=>[7,4],'TOTAL'=>[61,18]],
            'female' => ['JOY'=>[10,3],'ANGER'=>[7,3],'THOUGHT'=>[10,4],'DEPRESSION'=>[8,4],'SORROW'=>[9,4],'FRIGHT'=>[9,4],'FEAR'=>[8,4],'TOTAL'=>[62,18]],
        ],
    ];
}
}

if (!function_exists('cseiAgeGroup')) {
function cseiAgeGroup(?int $age): string {
    if ($age === null || $age <= 0) return '20s';
    if ($age < 30) return '20s';
    if ($age < 40) return '30s';
    if ($age < 50) return '40s';
    return '50s_plus';
}
}

if (!function_exists('cseiGenderKey')) {
function cseiGenderKey(?string $g): string {
    $g = trim((string)$g);
    if ($g === '여' || strtolower($g) === 'female' || $g === 'F' || $g === 'f') return 'female';
    return 'male';
}
}

if (!function_exists('cseiAgeGroupLabel')) {
function cseiAgeGroupLabel(string $ageGroup): string {
    return ['20s'=>'20대','30s'=>'30대','40s'=>'40대','50s_plus'=>'50대 이상'][$ageGroup] ?? '';
}
}

// T-점수 기반 군 분류
//   정상: 40 < T < 60 / 주의: 30~40 또는 60~70 / 위험: <30 또는 >70
if (!function_exists('classifyCseiGroup')) {
function classifyCseiGroup(int $tScore): array {
    if ($tScore < 30 || $tScore > 70)  return ['group' => 'risk',    'label' => '위험군'];
    if (($tScore >= 30 && $tScore <= 40) || ($tScore >= 60 && $tScore <= 70))
                                        return ['group' => 'caution', 'label' => '주의군'];
    return ['group' => 'normal', 'label' => '정상군'];
}
}

/**
 * CSEI-s 분석 실행
 * @param array    $answers 0-index 문항 => 응답값(1~5)
 * @param string   $gender  '남'/'여' 또는 'male'/'female'
 * @param int|null $age     나이(정수)
 */
if (!function_exists('analyzeCsei')) {
function analyzeCsei(array $answers, string $gender, ?int $age): array {
    $genderKey = cseiGenderKey($gender);
    $ageGroup  = cseiAgeGroup($age);
    $norms     = cseiNorms();
    $normSet   = $norms[$ageGroup][$genderKey] ?? $norms['20s'][$genderKey];
    $names     = cseiFactorNames();
    $order     = cseiFactorOrder();

    $factors  = [];
    $totalRaw = 0;
    foreach ($order as $idx => $factor) {
        $rawScore = 0;
        for ($i = 0; $i < 4; $i++) {
            $qIdx = $idx + ($i * 7);
            $rawScore += (int)($answers[$qIdx] ?? 0);
        }
        [$mean, $sd] = $normSet[$factor];
        $z = $sd != 0 ? ($rawScore - $mean) / $sd : 0;
        $t = (int)round(50 + (10 * $z));
        $cls = classifyCseiGroup($t);
        $factors[] = [
            'factor'     => $factor,
            'name'       => $names[$factor],
            'rawScore'   => $rawScore,
            'zScore'     => round($z, 2),
            'tScore'     => $t,
            'group'      => $cls['group'],
            'groupLabel' => $cls['label'],
        ];
        $totalRaw += $rawScore;
    }

    [$tMean, $tSd] = $normSet['TOTAL'];
    $totalZ = $tSd != 0 ? ($totalRaw - $tMean) / $tSd : 0;
    $totalT = (int)round(50 + (10 * $totalZ));
    $totalCls = classifyCseiGroup($totalT);

    return [
        'factors'    => $factors,
        'overall'    => [
            'factor'     => 'TOTAL',
            'name'       => '종합 지수',
            'rawScore'   => $totalRaw,
            'zScore'     => round($totalZ, 2),
            'tScore'     => $totalT,
            'group'      => $totalCls['group'],
            'groupLabel' => $totalCls['label'],
        ],
        'gender_key' => $genderKey,
        'age_group'  => $ageGroup,
    ];
}
}

// 감정별 한의학 소견 (report용)
if (!function_exists('cseiMedicalInsight')) {
function cseiMedicalInsight(string $factor, string $group): string {
    $risk = ($group === 'risk');
    return match ($factor) {
        'JOY'        => $risk ? '과도한 희(喜) 감정은 기(氣)를 느슨하게 하여 심장에 무리를 주고 산만함을 유발할 수 있습니다.'
                              : '적절한 희(喜) 감정은 심신의 이완을 돕지만, 과도해지지 않도록 주의가 필요합니다.',
        'ANGER'      => $risk ? '심한 노(怒)는 기를 위로 치밀어 오르게 하여 간을 상하게 하고 두통이나 소화불량을 야기할 수 있습니다.'
                              : '스트레스에 대한 분노 반응이 관찰됩니다. 심호흡과 명상으로 화기를 아래로 내려주는 것이 좋습니다.',
        'THOUGHT'    => $risk ? '지나친 사(思)는 기를 뭉치게 하여 비장을 상하게 하며, 수면 장애와 식욕 부진을 유발합니다.'
                              : '생각이 많은 상태입니다. 신체 활동을 늘려 머리쪽으로 집중된 기운을 분산시키세요.',
        'DEPRESSION' => $risk ? '깊은 우(憂) 감정은 기를 막히게 하여 폐 기능을 저하시키고 호흡을 얕게 만듭니다.'
                              : '우울감이 관찰됩니다. 가벼운 유산소 운동으로 폐활량을 늘리고 기운을 순환시키세요.',
        'SORROW'     => $risk ? '극심한 비(悲)는 기를 소모시켜 폐를 상하게 하며 전신의 무기력증을 초래합니다.'
                              : '슬픔으로 인해 에너지가 소진되고 있습니다. 충분한 휴식과 따뜻한 차로 몸을 달래주세요.',
        'FRIGHT'     => $risk ? '만성적인 공(恐)은 기를 아래로 가라앉혀 신장을 상하게 하고 만성 피로를 유발합니다.'
                              : '공포와 불안이 내재되어 있습니다. 작은 목표를 달성하며 자신감을 회복하는 과정이 필요합니다.',
        'FEAR'       => $risk ? '갑작스러운 경(驚)은 기를 흐트러뜨려 심장과 담력을 상하게 하며 불안장애로 이어질 수 있습니다.'
                              : '자율신경계가 다소 예민해져 있습니다. 안정적인 환경에서 규칙적인 생활을 권장합니다.',
        default      => '',
    };
}
}
