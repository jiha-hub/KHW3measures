-- =====================================================================
-- 5차 마이그레이션 (2026.09): ISI(불면증 심각도 지수) 척도 추가
--   * assessments.scale_type ENUM 에 'ISI' 추가
--   phpMyAdmin → SQL 탭에 붙여넣고 실행하세요. 기존 데이터는 보존됩니다.
-- MariaDB 10.x / MySQL 8.x
-- =====================================================================

ALTER TABLE assessments
    MODIFY COLUMN scale_type ENUM('PHQ-9', 'GAD-7', 'PSS-10', 'PSQI-K', 'CSEI-s',
                    'PHQ-15', 'BDI-9', 'S-GDpS', 'K-MDQ', 'SSD-12', 'ISI') NOT NULL;
