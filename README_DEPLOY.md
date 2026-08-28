# 배포 안내 — 연속검사 · PDF 내보내기 · 환자 추적 개선

이번 업데이트로 세 가지가 추가/개선되었습니다.

1. **환자 추적 강화** — 이름 하나로만 식별하던 것을 **이름 + 생년월일**로 바꿨고, 성별·연락처도 저장합니다.
2. **연속 검사(방문 배터리)** — 의료진이 여러 척도를 골라 세팅하면, 환자가 한 자리에서 **순서대로 이어서** 검사합니다.
3. **PDF 내보내기** — 한 번의 방문에서 본 검사 전체를 **의료진용 요약(summary.php)**으로 모아 **인쇄 → PDF 저장**할 수 있습니다.

---

## ⚠️ 배포 순서 (반드시 지킬 것)

코드가 배포되기 **전에** 아래 두 마이그레이션을 라이브 DB에서 실행하세요.
새 저장 로직이 `patients.birth_date/gender/phone`, `assessments.battery_id` 컬럼을 사용하므로, 컬럼이 없으면 저장이 실패합니다.

Railway MySQL 콘솔에서 순서대로:

```sql
-- (1) PSQI-K ENUM (이전 단계에서 이미 했다면 생략)
ALTER TABLE assessments
  MODIFY COLUMN scale_type ENUM('PHQ-9','GAD-7','PSS-10','PSQI-K') NOT NULL;

-- (2) 인구통계 + 방문 묶음
ALTER TABLE patients
  ADD COLUMN birth_date DATE NULL AFTER name,
  ADD COLUMN gender VARCHAR(10) NULL AFTER birth_date,
  ADD COLUMN phone VARCHAR(30) NULL AFTER gender;
ALTER TABLE patients
  ADD UNIQUE KEY uq_patient_name_birth (name, birth_date);
ALTER TABLE assessments
  ADD COLUMN battery_id VARCHAR(40) NULL AFTER admin_id;
CREATE INDEX idx_assessments_battery ON assessments(battery_id);
```

(각각 `migration_psqi.sql`, `migration2_battery.sql` 파일과 동일)

그다음 파일을 GitHub에 올리면 Railway가 자동 배포합니다. **`config.php`는 덮어쓰지 마세요**(로컬 DB 자격증명 보호).

---

## 새 사용 흐름

**의료진(세팅)**
1. 검사 입력 → 볼 검사를 **여러 개 체크**(PHQ-9 / GAD-7 / PSS-10 / PSQI-K)
2. 개인정보 동의
3. **이름 + 생년월일**(필수) + 성별·연락처 입력 → 태블릿을 환자에게 전달

**환자(응답)**
- 선택한 검사를 정해진 순서(PHQ-9 → GAD-7 → PSS-10 → PSQI-K)로 이어서 진행
- 각 검사 완료 화면은 **차분하게**(점수만 표시, "저하/중증" 같은 불안 유발 문구·자살문항 경고는 환자 화면에 띄우지 않음) → "다음 검사 →"

**의료진(결과)**
- 마지막 검사 후 **방문 요약(summary.php)**으로 이동: 전체 점수·해석, 절단점 도달 여부, **PHQ-9 자해·자살 문항 플래그**, PSQI 구성요소·수면효율까지 모두 표시
- **🖨️ PDF로 저장 / 인쇄** 버튼으로 그대로 PDF 저장 (브라우저 인쇄 기능 사용 — 별도 서버 설치 불필요, 태블릿에서 동작)
- 지난 방문도 **이력 조회**의 "🖨️ 방문" 링크로 다시 요약/PDF 가능

---

## 환자 데이터 저장 방식

- `patients` — 이름, **생년월일**, 성별, 연락처
- `assessments` — 환자, 척도, 답변(JSON), 총점, 결과, 메모, 작성자, **battery_id(방문 묶음)**, 생성일

같은 이름+생년월일이면 같은 환자로 누적되어 시간별 추적(그래프·이력)이 안정적으로 이어집니다.
※ 기존에 이름만으로 저장돼 있던 환자는 생년월일이 비어 있어, 새 검사(생년월일 입력)와는 **다른 환자로 분리**됩니다. 필요하면 DB에서 기존 행에 생년월일을 채워 병합하세요.

---

## 추가/변경된 파일

| 파일 | 상태 | 내용 |
|------|------|------|
| `patient_store.php` | 신규 | 이름+생년월일 기준 환자 저장/조회, 방문 ID 생성 |
| `run.php` | 신규 | 연속검사 라우터(선택한 척도 큐를 순서대로 진행) |
| `summary.php` | 신규 | 의료진용 방문 요약 + 인쇄→PDF |
| `migration2_battery.sql` | 신규 | 인구통계 + 방문 묶음 DB 변경 |
| `consent.php` | 수정 | 척도 다중선택(체크박스), 생년월일 입력 |
| `index.php` | 수정 | 연속검사 모드(단일 척도 렌더, 결과 순화, 다음검사 버튼) |
| `psqi.php` | 수정 | 연속검사 모드 동일 반영 |
| `save_assessment.php` / `save_psqi.php` | 수정 | 생년월일·성별·연락처·방문ID 저장 |
| `history.php` | 수정 | 방문 요약(PDF) 링크 |
| `setup.sql` | 수정 | 신규 설치용 스키마에 반영 |

---

## 배포 후 확인(스모크 테스트)

1. 검사 2개 이상 선택 → 생년월일 포함 정보 입력 → 환자가 순서대로 응답되는지
2. 마지막에 방문 요약이 뜨고 점수·해석·플래그가 맞는지
3. **PDF로 저장 / 인쇄** 버튼으로 PDF가 깔끔히 나오는지
4. 같은 이름+생년월일로 재검사 시 이력·그래프가 한 환자로 이어지는지
5. (문항 문안) PSQI-K 문안을 공식 문서와 대조 검수했는지

> 참고: 이번 변경으로 PHQ-9 자해·자살 문항 경고는 **환자 화면 대신 의료진용 방문 요약**에 표시됩니다. 요약 화면에서 반드시 확인하세요.
