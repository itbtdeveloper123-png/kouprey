#!/usr/bin/env python3
"""
KouPrey / GoGo Brand - Telegram Personal Account Userbot
Automatically replies to new customers chatting with your personal Telegram account for the first time.
Connects directly using api_id and api_hash from my.telegram.org.
Dynamically fetches greeting message and links from your kouprey.asia Admin Panel!
"""

import os
import sys
import json
import time
import asyncio
import logging
import urllib.request
from datetime import datetime

# Setup logging
logging.basicConfig(
    format='[%(asctime)s] %(levelname)s: %(message)s',
    level=logging.INFO,
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler("userbot.log", encoding="utf-8")
    ]
)
logger = logging.getLogger("Userbot")

try:
    from telethon import TelegramClient, events
except ImportError:
    logger.error("Telethon is not installed! Please run: pip install telethon")
    sys.exit(1)

# Default configuration file path
CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "userbot_config.json")
CACHE_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "responded_users.json")
SESSION_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "kouprey_personal")

def load_config():
    """Load config from local json file or create default template"""
    default_config = {
        "api_id": "",
        "api_hash": "",
        "phone_number": "",
        "admin_api_url": "https://www.kouprey.asia/admin-api.php?action=telegram_get_settings",
        "fallback_message": (
            "សួស្តី! សូមស្វាគមន៍មកកាន់ GoGo Brand (KouPrey) ☕✨\n\n"
            "យើងខ្ញុំមានលក់ផលិតផលគ្រឿងបន្ថែមរស់ជាតិភេសជ្ជៈ ស៊ីរ៉ូ (Syrup) និងម្សៅ (Powder) គុណភាពខ្ពស់។\n\n"
            "👉 បើកមើលទំនិញក្នុង Mini App៖\n"
            "https://www.kouprey.asia/telegram.php\n\n"
            "👉 ចូលរួម Telegram Channel៖\n"
            "https://t.me/kouprey_channel"
        ),
        "cooldown_hours": 24
    }

    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
                default_config.update(data)
        except Exception as e:
            logger.warning(f"Failed to read {CONFIG_FILE}: {e}")
    else:
        with open(CONFIG_FILE, "w", encoding="utf-8") as f:
            json.dump(default_config, f, indent=4, ensure_ascii=False)
        logger.info(f"Created template config at {CONFIG_FILE}. Please fill in api_id and api_hash.")

    return default_config

def fetch_admin_settings(api_url):
    """Fetch latest greeting message and links dynamically from kouprey.asia Admin API"""
    try:
        req = urllib.request.Request(
            api_url,
            headers={'User-Agent': 'Mozilla/5.0 (KouPrey Userbot 2.0)'}
        )
        with urllib.request.urlopen(req, timeout=10) as response:
            if response.status == 200:
                data = json.loads(response.read().decode('utf-8'))
                if data.get("success") and "settings" in data:
                    return data["settings"]
    except Exception as e:
        logger.warning(f"Could not sync with Admin API ({e}). Using local fallback message.")
    return None

def load_responded_cache():
    """Load timestamps of previously greeted users"""
    if os.path.exists(CACHE_FILE):
        try:
            with open(CACHE_FILE, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return {}
    return {}

def save_responded_cache(cache):
    """Save timestamps of greeted users"""
    try:
        with open(CACHE_FILE, "w", encoding="utf-8") as f:
            json.dump(cache, f, indent=2)
    except Exception as e:
        logger.error(f"Failed to save {CACHE_FILE}: {e}")

async def main():
    config = load_config()

    api_id = config.get("api_id")
    api_hash = config.get("api_hash")

    if not api_id or not api_hash or str(api_id) == "":
        logger.error("=======================================================")
        logger.error("កំហុស៖ សូមបញ្ចូល api_id និង api_hash ក្នុង userbot_config.json")
        logger.error("ទទួលបានពី: https://my.telegram.org (API development tools)")
        logger.error("=======================================================")
        # Prompt interactively if run in terminal
        try:
            api_id = input("បញ្ចូល API ID: ").strip()
            api_hash = input("បញ្ចូល API HASH: ").strip()
            config["api_id"] = int(api_id) if api_id.isdigit() else api_id
            config["api_hash"] = api_hash
            with open(CONFIG_FILE, "w", encoding="utf-8") as f:
                json.dump(config, f, indent=4, ensure_ascii=False)
        except Exception:
            return

    client = TelegramClient(SESSION_FILE, int(api_id), api_hash)

    logger.info("Connecting to Telegram...")
    await client.start(phone=lambda: config.get("phone_number") or input("បញ្ចូលលេខទូរស័ព្ទ Telegram (ឧ. +855...): "))

    me = await client.get_me()
    logger.info("=======================================================")
    logger.info(f"✓ ភ្ជាប់ជោគជ័យជាមួយ Personal Account: {me.first_name} (@{me.username or 'No username'})")
    logger.info("✓ Userbot កំពុងរង់ចាំអតិថិជនឆាតមកដំបូងដើម្បីឆ្លើយតបស្វ័យប្រវត្តិ...")
    logger.info("=======================================================")

    responded_users = load_responded_cache()

    @client.on(events.NewMessage(incoming=True, func=lambda e: e.is_private))
    async def handle_private_message(event):
        sender = await event.get_sender()
        if not sender or sender.bot or sender.is_self:
            return

        user_id = str(sender.id)
        now = time.time()
        cooldown = config.get("cooldown_hours", 24) * 3600

        # Check if customer already received a greeting within cooldown period
        last_reply_time = responded_users.get(user_id, 0)
        if now - last_reply_time < cooldown:
            logger.info(f"User {sender.first_name} (ID: {user_id}) sent a message, but was already greeted. Skipping.")
            return

        logger.info(f"New customer detected: {sender.first_name} (@{sender.username or 'none'}). Preparing auto-reply...")

        # Fetch latest settings from Admin Panel
        settings = fetch_admin_settings(config.get("admin_api_url", ""))
        message_to_send = config.get("fallback_message")

        if settings:
            raw_msg = settings.get("telegram_autoreply_message")
            miniapp_url = settings.get("telegram_miniapp_url") or "https://www.kouprey.asia/telegram.php"
            channel_url = settings.get("telegram_channel_url") or "https://t.me/kouprey_channel"

            if raw_msg:
                # Strip basic HTML tags since MTProto client formats via markdown
                clean_msg = raw_msg.replace("<b>", "**").replace("</b>", "**")
                clean_msg = clean_msg.replace("<i>", "*").replace("</i>", "*")
                clean_msg = clean_msg.replace("{name}", sender.first_name or "អតិថិជន")
                clean_msg = clean_msg.replace("{username}", f"@{sender.username}" if sender.username else "")

                message_to_send = (
                    f"{clean_msg}\n\n"
                    f"👉 **បើកមើលទំនិញក្នុង Mini App៖**\n{miniapp_url}\n\n"
                    f"👉 **ចូលរួម Telegram Channel៖**\n{channel_url}"
                )

        try:
            # Send auto-reply directly from personal account
            await client.send_message(sender.id, message_to_send)
            logger.info(f"✓ Successfully sent auto-reply to {sender.first_name} ({user_id})")

            # Update cache
            responded_users[user_id] = now
            save_responded_cache(responded_users)

        except Exception as err:
            logger.error(f"Failed to send auto-reply to {user_id}: {err}")

    # Keep running forever
    await client.run_until_disconnected()

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except (KeyboardInterrupt, SystemExit):
        logger.info("Userbot stopped by user.")
