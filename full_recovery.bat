@echo off
echo Running Full XAMPP MySQL Recovery...
echo.

set MYSQL_DIR=C:\xampp\mysql
set DATA_DIR=%MYSQL_DIR%\data
set BACKUP_DIR=%MYSQL_DIR%\backup
set DATA_OLD_DIR=%MYSQL_DIR%\data_old

if not exist "%BACKUP_DIR%" (
    echo Backup directory not found! Cannot proceed.
    pause
    exit /b
)

if exist "%DATA_OLD_DIR%" (
    echo Cleaning up previous backup attempt...
    rmdir /S /Q "%DATA_OLD_DIR%"
)

echo 1. Renaming current corrupted data folder to data_old...
ren "%DATA_DIR%" "data_old"

echo 2. Copying clean backup folder to new data folder...
xcopy "%BACKUP_DIR%" "%DATA_DIR%" /E /I /H /K /Y > nul

echo 3. Restoring your custom databases...
xcopy "%DATA_OLD_DIR%\ecommerce_db" "%DATA_DIR%\ecommerce_db" /E /I /H /K /Y > nul
xcopy "%DATA_OLD_DIR%\hoffmeyer" "%DATA_DIR%\hoffmeyer" /E /I /H /K /Y > nul
xcopy "%DATA_OLD_DIR%\kash_invest" "%DATA_DIR%\kash_invest" /E /I /H /K /Y > nul
xcopy "%DATA_OLD_DIR%\leaders_db" "%DATA_DIR%\leaders_db" /E /I /H /K /Y > nul
xcopy "%DATA_OLD_DIR%\scraper" "%DATA_DIR%\scraper" /E /I /H /K /Y > nul

echo 4. Restoring ibdata1 file (contains your table data)...
copy /Y "%DATA_OLD_DIR%\ibdata1" "%DATA_DIR%\ibdata1" > nul

echo.
echo Recovery complete! Please try starting MySQL from the XAMPP Control Panel now.
pause
