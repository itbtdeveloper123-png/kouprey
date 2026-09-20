#!/bin/bash
# Start both Telegram Userbots in the background
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR"

echo "=== Starting GoGo Brand Telegram Userbots ==="

# Account 1
if [ -f "kouprey_personal.session" ]; then
    pkill -f "telegram_userbot.py" 2>/dev/null
    nohup python3 -u telegram_userbot.py > userbot.log 2>&1 &
    echo "✓ Userbot 1 started (PID: $!). Log: userbot.log"
else
    echo "⚠️ Userbot 1 session not found! Run 'python3 telegram_userbot.py' first to login."
fi

# Account 2
if [ -f "kouprey_personal2.session" ]; then
    pkill -f "telegram_userbot2.py" 2>/dev/null
    nohup python3 -u telegram_userbot2.py > userbot2.log 2>&1 &
    echo "✓ Userbot 2 started (PID: $!). Log: userbot2.log"
else
    echo "⚠️ Userbot 2 session not found! Run 'python3 telegram_userbot2.py' first to login."
fi
