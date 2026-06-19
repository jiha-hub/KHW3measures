<?php
// =============================================
// 척도 문항 및 채점 기준 정의 (원본 파일 기준)
// =============================================

function getScales(): array {
    return [

        'PHQ-9' => [
            'name'        => 'PHQ-9',
            'full_name'   => '한글판 우울증 선별도구 (PHQ-9: Patient Health Questionnaire-9)',
            'period'      => '지난 2주 동안에',
            'instruction' => '지난 2주 동안에 다음과 같은 문제들로 얼마나 자주 방해를 받았는지 해당 번호에 표시해 주세요.',
            'options'     => ['없음', '2~3일', '7일 이상', '거의 매일'],
            'option_values' => [0, 1, 2, 3],
            'questions'   => [
                '기분이 가라앉거나, 우울하거나, 희망이 없다고 느꼈다.',
                '평소 하던 일에 대한 흥미가 없어지거나 즐거움을 느끼지 못했다.',
                '잠들기가 어렵거나 자주 깼다 / 혹은 너무 많이 잤다.',
                '평소보다 식욕이 줄었다 / 혹은 평소보다 많이 먹었다.',
                '다른 사람들이 눈치 챌 정도로 평소보다 말과 행동이 느려졌다 / 혹은 너무 안절부절 못해서 가만히 앉아있을 수 없었다.',
                '피곤하고 기운이 없었다.',
                '내가 잘못 했거나, 실패했다는 생각이 들었다 / 혹은 자신과 가족을 실망시켰다고 생각했다.',
                '신문을 읽거나 TV를 보는 것과 같은 일상적인 일에도 집중할 수가 없었다.',
                '차라리 죽는 것이 더 낫겠다고 생각했다 / 혹은 자해할 생각을 했다.',
            ],
            'scoring' => [
                ['min' => 0,  'max' => 4,  'label' => '우울아님',     'color' => 'green'],
                ['min' => 5,  'max' => 9,  'label' => '가벼운 우울',  'color' => 'yellow'],
                ['min' => 10, 'max' => 14, 'label' => '중간정도 우울','color' => 'orange'],
                ['min' => 15, 'max' => 19, 'label' => '중한 우울',    'color' => 'red'],
                ['min' => 20, 'max' => 27, 'label' => '심한 우울',    'color' => 'darkred'],
            ],
            'cutoff'     => 10,
            'max_score'  => 27,
            'source'     => '최홍석 외(2007). 가정의학회지 28:114-119 / 저작권: Pfizer Inc.',
        ],

        'GAD-7' => [
            'name'        => 'GAD-7',
            'full_name'   => '일반화된 불안장애 척도 (GAD-7: Generalized Anxiety Disorder-7)',
            'period'      => '지난 2주 동안',
            'instruction' => '지난 2주 동안 당신은 다음의 문제들로 인해서 얼마나 자주 방해를 받았는지 해당 번호에 표시해 주세요.',
            'options'     => ['전혀 방해받지 않았다', '며칠 동안 방해받았다', '7일 이상 방해받았다', '거의 매일 방해받았다'],
            'option_values' => [0, 1, 2, 3],
            'questions'   => [
                '초조하거나 불안하거나 조마조마하게 느낀다.',
                '걱정하는 것을 멈추거나 조절할 수가 없다.',
                '여러 가지 것들에 대해 걱정을 너무 많이 한다.',
                '편하게 있기가 어렵다.',
                '너무 안절부절못해서 가만히 있기가 힘들다.',
                '쉽게 짜증이 나거나 쉽게 성을 내게 된다.',
                '마치 끔찍한 일이 생길 것처럼 두렵게 느껴진다.',
            ],
            'scoring' => [
                ['min' => 0,  'max' => 4,  'label' => '불안아님',   'color' => 'green'],
                ['min' => 5,  'max' => 9,  'label' => '가벼운 불안','color' => 'yellow'],
                ['min' => 10, 'max' => 14, 'label' => '중간 불안',  'color' => 'orange'],
                ['min' => 15, 'max' => 21, 'label' => '심한 불안',  'color' => 'red'],
            ],
            'cutoff'     => 10,
            'max_score'  => 21,
            'source'     => 'Spitzer et al.(2006). Arch Intern Med 166, 1092-7 / 저작권: Pfizer Inc.',
        ],

        'PSS-10' => [
            'name'        => 'PSS-10',
            'full_name'   => '스트레스 척도 (수정된 PSS-10: Perceived Stress Scale)',
            'period'      => '지난 1개월 동안',
            'instruction' => '지난 1개월 동안 당신이 느끼고 생각한 것에 대한 것입니다. 각 문항의 내용을 얼마나 자주 느꼈는지 해당 번호에 표시해 주세요.',
            'options'     => ['매우 아니다', '아니다', '보통', '그렇다', '매우 그렇다'],
            'option_values' => [0, 1, 2, 3, 4],
            // 역채점 문항: 4,5,7,8번 (0-index: 3,4,6,7)
            'reverse_items' => [3, 4, 6, 7],
            'questions'   => [
                '지난 1개월 동안, 예상치 못한 일이 생겨서 기분 나빠진 적이 있었다.',
                '지난 1개월 동안, 중요한 일들을 통제할 수 없다고 느낀다.',
                '지난 1개월 동안, 초조하거나 스트레스가 쌓인다고 느낀다.',
                '지난 1개월 동안, 짜증나고 성가신 일들을 성공적으로 처리할 수 있다고 느낀다.',
                '지난 1개월 동안, 생활 속에서 일어난 중요한 변화들을 효과적으로 대처할 수 있다고 느낀다.',
                '지난 1개월 동안, 개인적인 문제를 처리하는 능력에 대해 자신감을 느낀다.',
                '지난 1개월 동안, 나의 뜻대로 일이 진행된다고 느낀다.',
                '지난 1개월 동안, 매사를 잘 컨트롤 할 수 있다고 느낀다.',
                '지난 1개월 동안, 내가 통제할 수 없는 범위에서 발생한 일 때문에 화가 났다.',
                '지난 1개월 동안, 어려운 일이 너무 많이 쌓여서 극복할 수 없다고 느낀다.',
            ],
            'scoring' => [
                ['min' => 0,  'max' => 13, 'label' => '낮은 스트레스', 'color' => 'green'],
                ['min' => 14, 'max' => 26, 'label' => '중간 스트레스', 'color' => 'yellow'],
                ['min' => 27, 'max' => 40, 'label' => '높은 스트레스', 'color' => 'red'],
            ],
            'cutoff'     => null, // PSS는 공식 절단점 없음
            'max_score'  => 40,
            'note'       => '역채점 항목이 포함되어 있습니다. PSS는 진단 도구가 아니며 공식 절단점이 없습니다.',
            'source'     => '이종하 외(2012). 정신신체의학 20(2), 127-134 / 저작권: Cohen S, Kamarck T (한국판: 한창수)',
        ],
    ];
}

// 점수 계산 함수
function calculateScore(string $scaleType, array $answers): array {
    $scales = getScales();
    $scale  = $scales[$scaleType];
    $total  = 0;

    foreach ($answers as $i => $val) {
        $val = (int)$val;
        if (isset($scale['reverse_items']) && in_array((int)$i, $scale['reverse_items'])) {
            $maxVal = max($scale['option_values']);
            $val    = $maxVal - $val;
        }
        $total += $val;
    }

    $label = '';
    $color = '';
    foreach ($scale['scoring'] as $range) {
        if ($total >= $range['min'] && $total <= $range['max']) {
            $label = $range['label'];
            $color = $range['color'];
            break;
        }
    }

    return ['total' => $total, 'label' => $label, 'color' => $color];
}
