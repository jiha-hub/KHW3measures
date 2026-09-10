@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul
title KHW3measures 자동 설치
cd /d "%~dp0"

echo ============================================
echo   KHW3measures 척도 앱 - 자동 설치
echo ============================================
echo.

REM ---- XAMPP 경로 (다르면 아래 한 줄만 수정) ----
set "XAMPP=C:\xampp"
set "MYSQL=%XAMPP%\mysql\bin\mysql.exe"
set "DBNAME=khw3"
set "DBUSER=root"

if not exist "%MYSQL%" (
  echo [오류] XAMPP를 "%XAMPP%" 에서 찾지 못했습니다.
  echo         설치 경로가 다르면 install.bat 의 XAMPP 변수를 수정하세요.
  echo.
  pause
  exit /b 1
)

echo [1/3] config.php 준비...
if exist "config.php" (
  echo       - config.php 가 이미 있어 그대로 사용합니다.
) else (
  copy /y "config.example.php" "config.php" >nul
  echo       - config.example.php 를 복사해 config.php 를 만들었습니다.
)

echo [2/3] 데이터베이스 "%DBNAME%" 생성...
"%MYSQL%" -u %DBUSER% -e "CREATE DATABASE IF NOT EXISTS %DBNAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
  echo.
  echo [오류] MySQL 접속 실패. XAMPP 제어판에서 MySQL(MariaDB)을 먼저 START 하세요.
  echo         (root 비밀번호를 설정했다면 이 스크립트에 -p 옵션이 필요합니다.)
  echo.
  pause
  exit /b 1
)

echo [3/3] 테이블/기본 관리자 계정 생성 (setup.sql)...
"%MYSQL%" -u %DBUSER% %DBNAME% < "setup.sql"
if errorlevel 1 (
  echo       - 경고: 이미 설치된 DB일 수 있습니다. 테이블이 있으면 무시해도 됩니다.
) else (
  echo       - 완료.
)

echo.
echo ============================================
echo   설치 완료!
echo ============================================
echo  브라우저에서 열기 :  http://localhost/KHW3measures/
echo  관리자 로그인    :  admin  /  admin1234
echo  ( 로그인 후 반드시 비밀번호를 변경하세요 )
echo ============================================
echo.
pause
