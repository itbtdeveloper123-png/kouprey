@echo off
chcp 65001 >nul
set PYTHONIOENCODING=utf-8
set PYTHONUTF8=1
title KouPrey Telegram Personal Account Userbot
cd /d "%~dp0"

echo =======================================================
echo    KouPrey Telegram Personal Account Auto-Responder
echo =======================================================
echo.

echo Checking and installing dependencies...
python -m pip install -r requirements.txt --quiet
echo.
python telegram_userbot.py

pause

