@echo off
set PHP=C:\xampp\php\php.exe
set SCRIPT=C:\xampp\htdocs\INVENTORY\scripts\vehicle_due_notify.php
set LOG=C:\xampp\htdocs\INVENTORY\logs\vehicle_due_notify.log

"%PHP%" "%SCRIPT%" >> "%LOG%" 2>&1