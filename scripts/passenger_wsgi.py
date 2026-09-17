import os
import sys
import threading
import asyncio

# Add current directory to path
sys.path.insert(0, os.path.dirname(__file__))

# Import the userbot main function
try:
    from telegram_userbot import main as run_userbot
except ImportError:
    run_userbot = None

userbot_thread = None
userbot_status = "Initializing..."

def start_background_userbot():
    global userbot_status
    if run_userbot is None:
        userbot_status = "Error: telegram_userbot.py not found"
        return
    try:
        userbot_status = "Running"
        asyncio.run(run_userbot())
    except Exception as e:
        userbot_status = f"Stopped with error: {e}"

# Start Userbot in a background thread when cPanel Passenger starts
if userbot_thread is None or not userbot_thread.is_alive():
    userbot_thread = threading.Thread(target=start_background_userbot, daemon=True)
    userbot_thread.start()

# WSGI Application entry point for cPanel Passenger
def application(environ, start_response):
    status = '200 OK'
    headers = [('Content-Type', 'text/html; charset=utf-8')]
    start_response(status, headers)

    html = f"""
    <!DOCTYPE html>
    <html>
    <head>
        <title>KouPrey Telegram Userbot Status</title>
        <style>
            body {{ font-family: sans-serif; text-align: center; padding: 50px; background: #0f172a; color: white; }}
            .card {{ background: #1e293b; padding: 30px; border-radius: 16px; display: inline-block; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }}
            .status {{ color: #10b981; font-weight: bold; font-size: 20px; margin: 15px 0; }}
        </style>
    </head>
    <body>
        <div class="card">
            <h2>☕ KouPrey Telegram Userbot</h2>
            <p>Personal Account Auto-Responder</p>
            <div class="status">● Status: {userbot_status}</div>
            <p style="color: #94a3b8; font-size: 13px;">Running on cPanel CloudLinux Passenger</p>
        </div>
    </body>
    </html>
    """
    return [html.encode('utf-8')]
