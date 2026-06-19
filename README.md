# 정신건강 척도 검사 앱

PHQ-9, GAD-7, PSS-10 척도 검사 및 이력 관리 시스템
PHP 8.4 + MariaDB 10.x + UTF-8

---

## 파일 구성

```
├── config.php      ← DB 연결 설정 (반드시 수정)
├── auth.php        ← 로그인/세션 처리
├── scales.php      ← 척도 문항 및 채점 기준
├── login.php       ← 로그인 페이지
├── index.php       ← 검사 입력 메인
├── history.php     ← 이력 조회
├── admin.php       ← 관리자 설정
├── logout.php      ← 로그아웃
└── setup.sql       ← DB 테이블 생성 SQL
```

---

## 설치 순서

### 1단계: DB 설정

Cafe24 관리페이지 → DB 관리 → phpMyAdmin 접속
setup.sql 파일 전체 내용을 복사 후 SQL 실행

### 2단계: config.php 수정

```php
define('DB_HOST', 'localhost');
define('DB_NAME', '실제_DB명');
define('DB_USER', '실제_DB아이디');
define('DB_PASS', '실제_DB비밀번호');
```

### 3단계: FTP 업로드

FileZilla 등으로 모든 .php 파일을 서버 웹루트(public_html 또는 www)에 업로드

### 4단계: 초기 로그인 후 비밀번호 변경

- 초기 아이디: admin
- 초기 비밀번호: admin1234
- 로그인 후 [관리자 설정] → [내 비밀번호 변경]에서 즉시 변경

---

## 척도 출처

- PHQ-9: 박승진 외(2010). 저작권 Pfizer Inc.
- GAD-7: Spitzer et al.(2006). 저작권 Pfizer Inc.
- PSS-10: 이종하 외(2012). 저작권 Cohen S, Kamarck T / 한국판: 한창수
