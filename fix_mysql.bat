@echo off
echo Renaming ib_logfile0 and ib_logfile1 to force MySQL to recreate them...
ren "C:\xampp\mysql\data\ib_logfile0" "ib_logfile0.bak"
ren "C:\xampp\mysql\data\ib_logfile1" "ib_logfile1.bak"
echo.
echo Done! Please try starting MySQL from the XAMPP Control Panel now.
echo You can delete this file after you are done.
pause
