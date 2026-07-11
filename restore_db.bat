@echo off
echo Restoring your Kash-Invest database...

set MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe
set DUMP_FILE=C:\Users\gz275\Documents\GitHub\Kash-Invest\database.sql

if not exist "%DUMP_FILE%" (
    echo Could not find database.sql!
    pause
    exit /b
)

echo 1. Creating the kash_invest database...
"%MYSQL_BIN%" -u root -e "CREATE DATABASE IF NOT EXISTS kash_invest;"

echo 2. Importing data from database.sql...
"%MYSQL_BIN%" -u root kash_invest < "%DUMP_FILE%"

echo.
echo Restore complete! You should now be able to view your site at http://127.0.0.1:8000
pause
