<?php
/**
 * 환자 저장/조회 공통 로직.
 * 식별 기준: 이름 + 생년월일 (birth_date). 생년월일이 없으면 이름만으로 매칭(레거시 호환).
 * 성별/연락처가 새로 들어오면 비어있던 값에 한해 채워 넣는다(기존 값 훼손 방지).
 */

if (!function_exists('upsertPatient')) {
    /**
     * @param PDO         $db
     * @param string      $name   환자 이름(필수)
     * @param string|null $birth  생년월일 'YYYY-MM-DD' 또는 null
     * @param string|null $gender '남'|'여'|null
     * @param string|null $phone  연락처 또는 null
     * @return int patient id
     */
    function upsertPatient(PDO $db, string $name, ?string $birth = null, ?string $gender = null, ?string $phone = null): int
    {
        $name  = trim($name);
        $birth = ($birth !== null && trim($birth) !== '') ? trim($birth) : null;

        // 조회: 생년월일이 있으면 이름+생년월일, 없으면 이름만
        if ($birth !== null) {
            $stmt = $db->prepare('SELECT id, gender, phone FROM patients WHERE name = ? AND birth_date = ? LIMIT 1');
            $stmt->execute([$name, $birth]);
        } else {
            $stmt = $db->prepare('SELECT id, gender, phone FROM patients WHERE name = ? AND birth_date IS NULL LIMIT 1');
            $stmt->execute([$name]);
        }
        $row = $stmt->fetch();

        if ($row) {
            $pid = (int)$row['id'];
            // 비어있던 값만 보강
            $sets = [];
            $args = [];
            if ($gender !== null && $gender !== '' && empty($row['gender'])) { $sets[] = 'gender = ?'; $args[] = $gender; }
            if ($phone  !== null && $phone  !== '' && empty($row['phone']))  { $sets[] = 'phone = ?';  $args[] = $phone; }
            if ($sets) {
                $args[] = $pid;
                $db->prepare('UPDATE patients SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
            }
            return $pid;
        }

        // 신규 생성
        $stmt = $db->prepare('INSERT INTO patients (name, birth_date, gender, phone) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $birth, ($gender !== '' ? $gender : null), ($phone !== '' ? $phone : null)]);
        return (int)$db->lastInsertId();
    }
}

if (!function_exists('newBatteryId')) {
    /** 한 번의 방문(여러 척도)을 묶는 식별자 */
    function newBatteryId(): string
    {
        return 'B' . date('ymdHis') . substr(bin2hex(random_bytes(4)), 0, 6);
    }
}

if (!function_exists('scaleOrder')) {
    /** 검사 진행 및 표시의 정규 순서 */
    function scaleOrder(): array
    {
        return ['CSEI-s', 'PHQ-9', 'GAD-7', 'PSS-10', 'PHQ-15', 'BDI-9', 'S-GDpS', 'K-MDQ', 'SSD-12', 'PSQI-K'];
    }
}

if (!function_exists('ageFromBirth')) {
    function ageFromBirth(?string $birth): ?int
    {
        if (!$birth) return null;
        $t = strtotime($birth);
        if ($t === false) return null;
        $b = new DateTime(date('Y-m-d', $t));
        $now = new DateTime('today');
        return (int)$b->diff($now)->y;
    }
}
