# 다른 컴퓨터에 설치하기 (Windows + XAMPP)

새 컴퓨터에서 아래 순서대로 하면 한 번에 설치됩니다.

## 준비물 (한 번만)
1. **XAMPP 설치** — https://www.apachefriends.org (PHP 8.x 포함 버전)
   - 기본 경로 `C:\xampp` 권장. 다른 경로면 `install.bat` 안의 `XAMPP` 값만 수정.
2. XAMPP 제어판에서 **Apache** 와 **MySQL** 을 **Start**.

## 앱 내려받기
### 방법 A) Git 사용
```
cd C:\xampp\htdocs
git clone https://github.com/jiha-hub/KHW3measures.git
```
### 방법 B) ZIP 다운로드
- GitHub 저장소 → **Code ▸ Download ZIP** → 압축 해제 →
  폴더 이름을 **`KHW3measures`** 로 맞춰 `C:\xampp\htdocs\` 안에 넣기.

## 한 번에 설치
`C:\xampp\htdocs\KHW3measures\` 폴더에서 **`install.bat` 더블클릭**.
자동으로:
1. `config.php` 생성 (config.example.php 복사)
2. 데이터베이스 `khw3` 생성
3. 테이블 + 기본 관리자 계정 생성 (`setup.sql`)

## 접속
- 주소: **http://localhost/KHW3measures/**
- 로그인: **admin / admin1234**  → 로그인 후 **비밀번호 변경 필수**

---
### 참고
- `config.php` 는 컴퓨터마다 다른 접속정보라 저장소에 올라가지 않습니다(.gitignore).
  값이 다르면(예: DB 비밀번호) `config.php` 를 직접 수정하세요.
- `setup.sql` 은 새 설치용 전체 스키마(10개 척도 포함)입니다.
- `migration*.sql` 은 **이미 운영 중인 옛 DB를 업그레이드**할 때만 쓰는 파일이라
  새 설치에서는 실행할 필요가 없습니다.
- root 비밀번호를 설정한 XAMPP라면 `install.bat` 의 mysql 명령에 `-p` 가 필요합니다.
