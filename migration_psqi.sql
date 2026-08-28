-- =====================================================================
-- PSQI-K 추가 마이그레이션 (이미 운영 중인 라이브 DB용)
-- Railway MySQL 콘솔에서 1회 실행하세요.
-- =====================================================================

-- assessments.scale_type ENUM에 'PSQI-K' 추가
ALTER TABLE assessments
    MODIFY COLUMN scale_type ENUM('PHQ-9', 'GAD-7', 'PSS-10', 'PSQI-K') NOT NULL;

-- 확인: 아래 쿼리로 컬럼 정의에 PSQI-K가 포함됐는지 검증
-- SHOW COLUMNS FROM assessments LIKE 'scale_type';
