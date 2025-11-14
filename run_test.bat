@echo off
cd /d %~dp0
php artisan test --filter=AdminNavigationServiceTest
pause
