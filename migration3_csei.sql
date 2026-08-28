-- =============================================
-- CSEI-s 추가 마이그레이션 (기존 운영 DB용)
--   * PSQI-K 등 기존 ENUM 값을 보존하면서 'CSEI-s' 를 추가
--   * factor_scores(JSON) 컬럼 추가 — CSEI-s 요인별 결과 저장
--   * phpMyAdmin → SQL 탭에 붙여넣고 실행. 기존 데이터는 보존됩니다.
-- MariaDB 10.x / MySQL 8.x
-- =============================================

SET NAMES utf8mb4;

-- 1) scale_type ENUM 에 'CSEI-s' 추가 (기존 값 유지)
ALTER TABLE assessments
    MODIFY scale_type ENUM('PHQ-9','GAD-7','PSS-10','PSQI-K','CSEI-s') NOT NULL;

-- 2) CSEI-s 요인 점수 저장용 컬럼 (없을 때만)
ALTER TABLE assessments
    ADD COLUMN IF NOT EXISTS factor_scores JSON NULL AFTER battery_id;
