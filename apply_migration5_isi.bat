@echo off
setlocal
chcp 65001 >nul
title ISI 척도 - DB 마이그레이션
cd /d "%~dp0"
set "XAMPP=C:\xampp"
set "MYSQL=%XAMPP%\mysql\bin\mysql.exe"
set "DBNAME=khw3"
set "DBUSER=root"

if not exist "%MYSQL%" (
  echo [오류] XAMPP를 "%XAMPP%" 에서 찾지 못했습니다. install.bat 처럼 경로를 확인하세요.
  pause & exit /b 1
)
echo ISI 척도를 위한 DB 변경을 적용합니다 (기존 데이터는 그대로 보존)...
"%MYSQL%" -u %DBUSER% %DBNAME% < "migration5_isi.sql"
if errorlevel 1 (
  echo [오류] 적용 실패. XAMPP의 MySQL이 실행 중인지 확인하세요.
  pause & exit /b 1
)
echo.
echo 완료! 이제 검사 선택 화면에 ISI(불면증 심각도 지수)가 나타납니다.
pause
