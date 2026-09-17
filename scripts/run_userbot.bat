@echo off
title KouPrey Telegram Personal Account Userbot
cd /d "%~dp0"

echo =======================================================
echo    KouPrey Telegram Personal Account Auto-Responder
echo =======================================================
echo.

python -m pip install -r requirements.txt >nul 2>&1
python telegram_userbot.py

pause
