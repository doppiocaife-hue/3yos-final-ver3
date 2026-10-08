@echo off
setlocal
cd /d "%~dp0.."

set "PHP_EXE=php"
where php >nul 2>nul
if errorlevel 1 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_EXE=C:\xampp\php\php.exe"
    ) else (
        echo PHP CLI was not found on PATH or in C:\xampp\php.
        exit /b 1
    )
)

"%PHP_EXE%" artisan schedule:work
exit /b %errorlevel%
