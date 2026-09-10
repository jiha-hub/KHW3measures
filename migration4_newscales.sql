-- =====================================================================
-- 4차 마이그레이션 (2026.09): 신규 척도 5종 + 소프트 삭제(숨김)
--   * 신규 척도: PHQ-15, BDI-9, S-GDpS, K-MDQ, SSD-12
--   * 소프트 삭제: deleted_at 컬럼 (검사 기록을 실제로 지우지 않고 숨김 → 복구 가능)
--   * 하위영역 점수: 기존 factor_scores(JSON) 컬럼을 SSD-12 하위영역 저장에 재사용
--   phpMyAdmin → SQL 탭에 붙여넣고 실행하세요. 기존 데이터는 보존됩니다.
-- MariaDB 10.x / MySQL 8.x
-- =====================================================================

SET NAMES utf8mb4;

-- 1) scale_type ENUM 에 신규 척도 5종 추가 (기존 값 모두 유지)
ALTER TABLE assessments
    MODIFY scale_type ENUM(
        'PHQ-9','GAD-7','PSS-10','PSQI-K','CSEI-s',
        'PHQ-15','BDI-9','S-GDpS','K-MDQ','SSD-12'
    ) NOT NULL;

-- 2) 소프트 삭제용 컬럼 (없을 때만). NULL = 정상, 값 있음 = 숨김(삭제됨)
ALTER TABLE assessments
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL AFTER created_at;

-- 3) 숨김 처리 조회 최적화용 인덱스 (없을 때만)
--    MariaDB에서 IF NOT EXISTS 미지원 시 아래 줄에서 오류가 나면 무시하고 넘어가세요.
CREATE INDEX idx_assessments_deleted ON assessments(deleted_at);

-- (factor_scores JSON 컬럼은 migration3_csei.sql 에서 이미 추가됨 — SSD-12 하위영역 저장에 재사용)

-- 확인용
-- SHOW COLUMNS FROM assessments;
