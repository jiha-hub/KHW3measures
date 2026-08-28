-- =====================================================================
-- 2차 마이그레이션: 인구통계 저장 + 생년월일 식별 + 방문(연속검사) 묶음
-- 운영 중인 라이브 DB에 1회 실행. (migration_psqi.sql 이후에 실행)
-- =====================================================================

-- 1) 환자 테이블에 생년월일/성별/연락처 컬럼 추가
ALTER TABLE patients
    ADD COLUMN birth_date DATE NULL AFTER name,
    ADD COLUMN gender VARCHAR(10) NULL AFTER birth_date,
    ADD COLUMN phone VARCHAR(30) NULL AFTER gender;

-- 2) 이름+생년월일 식별 인덱스 (MySQL은 NULL을 서로 다른 값으로 취급하므로
--    생년월일이 없는 기존 행끼리는 충돌하지 않음)
ALTER TABLE patients
    ADD UNIQUE KEY uq_patient_name_birth (name, birth_date);

-- 3) 한 번의 방문에서 이어서 본 여러 검사를 묶는 식별자
ALTER TABLE assessments
    ADD COLUMN battery_id VARCHAR(40) NULL AFTER admin_id;
CREATE INDEX idx_assessments_battery ON assessments(battery_id);

-- 확인용
-- SHOW COLUMNS FROM patients;
-- SHOW COLUMNS FROM assessments LIKE 'battery_id';
