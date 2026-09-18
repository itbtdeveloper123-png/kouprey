#!/usr/bin/env python3
"""
KouPrey / GoGo Brand - Telegram Personal Account Userbot
Automatically replies to new customers chatting with your personal Telegram account for the first time.
Connects directly using api_id and api_hash from my.telegram.org.
Dynamically fetches greeting message and links from your kouprey.asia Admin Panel!
"""

import os
import re
import sys
import json
import time
import random
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
    from telethon import TelegramClient, events, utils
except ImportError:
    logger.error("Telethon is not installed! Please run: pip install telethon")
    sys.exit(1)

# Default configuration file path
CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "userbot_config.json")
CACHE_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "responded_users.json")
SESSION_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "kouprey_personal")

# Keywords that re-trigger auto-reply even if customer already chatted before
TRIGGER_KEYWORDS = [
    "menu", "មីនុយ", "/start", "start", "តម្លៃ", "price", 
    "ទំនិញ", "info", "shop", "order", "កាតាឡុក", "catalog", "help"
]

def load_config():
    """Load config from local json file or create default template"""
    default_config = {
        "api_id": "",
        "api_hash": "",
        "phone_number": "",
        "admin_api_url": "https://www.kouprey.asia/admin-api.php?action=telegram_get_settings",
        "fallback_message": (
            "✨ **សួស្តី {name}! សូមស្វាគមន៍មកកាន់ GoGo Brand** ☕️🍹\n"
            "━━━━━━━━━━━━━━━━━━━━\n"
            "🌟 **យើងខ្ញុំជាអ្នកឯកទេសផ្គត់ផ្គង់គ្រឿងបន្ថែមរស់ជាតិភេសជ្ជៈ៖**\n"
            "• 🥤 **ស៊ីរ៉ូ (Syrup)** — ឈ្ងុយឆ្ងាញ់ បង្កើនគុណភាពភេសជ្ជៈ\n"
            "• 🥛 **ម្សៅ (Powder)** — ងាយស្រួលឆុង បង្កើនឱជារស\n\n"
            "🎯 **ស្វែងរក និងកម្ម៉ង់ទំនិញងាយស្រួល៖**\n"
            "👉 **បើកមើលទំនិញក្នុង Mini App៖**\n"
            "🤖 @gogobrand_bot\n\n"
            "👉 **ចូលរួម Telegram Channel ផ្លូវការ៖**\n"
            "📢 https://t.me/gogobrand98\n"
            "━━━━━━━━━━━━━━━━━━━━\n"
            "💬 *ត្រូវការជំនួយ ឬកម្ម៉ង់ទំនិញ សូមឆាតមកកាន់យើងខ្ញុំបានគ្រប់ពេល!*"
        ),
        "welcome_photo": "https://i.ibb.co/WW1FQSG2/Gemini-Generated-Image-l5ljj5l5ljj5l5lj.jpg",
        "cooldown_hours": 24
    }

    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
                # Only load credentials and system configs, never old fallback messages
                for k in ["api_id", "api_hash", "phone_number", "cooldown_hours"]:
                    if data.get(k):
                        default_config[k] = data[k]
        except Exception as e:
            logger.warning(f"Failed to read {CONFIG_FILE}: {e}")

    # Always synchronize CONFIG_FILE with the clean message and Gemini photo
    try:
        with open(CONFIG_FILE, "w", encoding="utf-8") as f:
            json.dump(default_config, f, indent=4, ensure_ascii=False)
    except Exception as e:
        logger.warning(f"Failed to update {CONFIG_FILE}: {e}")

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
    logger.info("✓ Userbot កំពុងរង់ចាំអតិថិជនឆាតមកដំបូង ឬវាយពាក្យ Menu ដើម្បីឆ្លើយតបស្វ័យប្រវត្តិ...")
    logger.info("=======================================================")

    responded_users = load_responded_cache()

    # Remote Management via Telegram 'Saved Messages' (សារដែលបានរក្សាទុក)
    @client.on(events.NewMessage(chats='me'))
    async def handle_saved_messages_command(event):
        raw_text = (event.raw_text or "").strip()
        cmd = raw_text.lower()

        # Prevent bot looping on its own responses
        if raw_text.startswith("✅") or raw_text.startswith("🤖"):
            return

        if cmd in ["/reset", "reset"]:
            cleared_count = len(responded_users)
            responded_users.clear()
            save_responded_cache(responded_users)
            await event.reply(
                f"✅ **បាន Reset ប្រវត្តិអតិថិជនជោគជ័យ!**\n\n"
                f"• ចំនួនសម្អាត: {cleared_count} នាក់\n"
                f"• ពេលនេះ Userbot នឹងឆ្លើយតបស្វាគមន៍ចំពោះអតិថិជនទាំងអស់ឡើងវិញ (ទោះធ្លាប់ឆាតរួចក៏ដោយ)។"
            )
            logger.info(f"Admin reset customer cache via Saved Messages ({cleared_count} records cleared).")

        elif cmd in ["/status", "status"]:
            photo_info = config.get("welcome_photo") or "(គ្មានរូបភាព)"
            await event.reply(
                f"🤖 **GoGo Brand Userbot Status**\n\n"
                f"• គណនី: {me.first_name} (@{me.username or 'No username'})\n"
                f"• ស្ថានភាព: កំពុងដំណើរការ (Online)\n"
                f"• ចំនួនអ្នកធ្លាប់ឆ្លើយតប: {len(responded_users)} នាក់\n"
                f"• រូបភាពស្វាគមន៍: {photo_info}\n"
                f"• Cooldown ធម្មតា: {config.get('cooldown_hours', 24)} ម៉ោង\n\n"
                f"💡 **ពាក្យបញ្ជាបញ្ជាពីចម្ងាយ (Remote Control):**\n"
                f"• វាយ `/test` ដើម្បីមើលទម្រង់សារ Auto-Reply លើខ្លួនឯង\n"
                f"• វាយ `/reset` ដើម្បី Reset អ្នកធ្លាប់ឆាតឱ្យឆ្លើយតបឡើងវិញទាំងអស់\n"
                f"• វាយ `/status` ដើម្បីឆែកមើលស្ថានភាព"
            )

        elif cmd in ["/test", "test"]:
            # Preview greeting message and photo directly in Saved Messages
            settings = fetch_admin_settings(config.get("admin_api_url", ""))
            preview_msg = config.get("fallback_message")
            photo_url = config.get("welcome_photo", "")

            my_full_name = utils.get_display_name(me).strip() if me else ""
            if not my_full_name:
                my_full_name = f"{(me.first_name or '')} {(me.last_name or '')}".strip() or "អតិថិជន"
            my_username = f"@{me.username}" if (me and me.username) else ""

            if settings:
                raw_msg = settings.get("telegram_autoreply_message")
                miniapp_url = settings.get("telegram_miniapp_url")
                channel_url = settings.get("telegram_channel_url")
                if not miniapp_url or "telegram.php" in miniapp_url:
                    miniapp_url = "@gogobrand_bot"
                if not channel_url or "kouprey_channel" in channel_url:
                    channel_url = "https://t.me/gogobrand98"

                if settings.get("telegram_autoreply_photo"):
                    photo_url = settings.get("telegram_autoreply_photo")

                if raw_msg:
                    raw_msg = raw_msg.replace("(KouPrey)", "").replace("  ", " ")
                    clean_msg = raw_msg.replace("<b>", "**").replace("</b>", "**")
                    clean_msg = clean_msg.replace("<i>", "*").replace("</i>", "*")
                    clean_msg = clean_msg.replace("{name}", my_full_name)
                    clean_msg = clean_msg.replace("{username}", my_username)
                    preview_msg = (
                        f"{clean_msg}\n\n"
                        f"👉 **បើកមើលទំនិញក្នុង Mini App៖**\n🤖 {miniapp_url}\n\n"
                        f"👉 **ចូលរួម Telegram Channel ផ្លូវការ៖**\n📢 {channel_url}"
                    )

            # Personalize fallback_message with Telegram name
            preview_msg = preview_msg.replace("{name}", my_full_name)
            preview_msg = preview_msg.replace("{username}", my_username)

            # Remove any traces of KouPrey or old links
            for old in ["( KouPrey )", "(KouPrey)", "( kouprey )", "(kouprey)", "KouPrey", "kouprey"]:
                preview_msg = preview_msg.replace(old, "")
            preview_msg = preview_msg.replace("https://www.kouprey.asia/telegram.php", "@gogobrand_bot")
            preview_msg = preview_msg.replace("https://t.me/kouprey_channel", "https://t.me/gogobrand98")
            preview_msg = re.sub(r'[ \t]+', ' ', preview_msg).strip()

            full_preview = "🔍 **[តេស្តសាកល្បង] ទម្រង់សារ Auto-Reply៖**\n\n" + preview_msg

            if photo_url and (os.path.exists(photo_url) or photo_url.startswith("http")):
                try:
                    await client.send_file('me', file=photo_url, caption=full_preview)
                except Exception as e:
                    await event.reply(f"{full_preview}\n\n⚠️ *(បញ្ជូនរូបភាពមិនជោគជ័យ: {e})*")
            else:
                await event.reply(full_preview)

    @client.on(events.NewMessage(incoming=True, func=lambda e: e.is_private))
    async def handle_private_message(event):
        sender = await event.get_sender()
        if not sender or sender.bot or sender.is_self:
            return

        customer_name = utils.get_display_name(sender).strip() if sender else ""
        if not customer_name:
            customer_name = f"{(sender.first_name or '')} {(sender.last_name or '')}".strip() or "អតិថិជន"
        customer_username = f"@{sender.username}" if (sender and sender.username) else ""

        user_id = str(sender.id)
        now = time.time()
        cooldown = config.get("cooldown_hours", 24) * 3600

        text = (event.raw_text or "").strip().lower()

        # Check if incoming text contains any trigger keywords (e.g. menu, price, /start)
        is_keyword = any(k in text for k in TRIGGER_KEYWORDS)

        last_reply_time = responded_users.get(user_id, 0)

        # Skip if already greeted within cooldown AND message is not a keyword request
        if (now - last_reply_time < cooldown) and not is_keyword:
            logger.info(f"User {sender.first_name} (ID: {user_id}) sent a message, but was already greeted. Skipping.")
            return

        # Mini rate-limit: avoid spamming back-to-back within 30 seconds for the same user
        if now - last_reply_time < 30:
            return

        if is_keyword:
            logger.info(f"Keyword trigger ('{text}') from {customer_name} (ID: {user_id}). Preparing reply...")
        else:
            logger.info(f"New customer detected: {customer_name} (@{sender.username or 'none'}). Preparing auto-reply...")

        # Fetch latest settings from Admin Panel
        settings = fetch_admin_settings(config.get("admin_api_url", ""))
        message_to_send = config.get("fallback_message")
        photo_to_send = config.get("welcome_photo", "")

        if settings:
            raw_msg = settings.get("telegram_autoreply_message")
            miniapp_url = settings.get("telegram_miniapp_url")
            channel_url = settings.get("telegram_channel_url")
            if not miniapp_url or "telegram.php" in miniapp_url:
                miniapp_url = "@gogobrand_bot"
            if not channel_url or "kouprey_channel" in channel_url:
                channel_url = "https://t.me/gogobrand98"

            if settings.get("telegram_autoreply_photo"):
                photo_to_send = settings.get("telegram_autoreply_photo")

            if raw_msg:
                raw_msg = raw_msg.replace("(KouPrey)", "").replace("  ", " ")
                # Strip basic HTML tags since MTProto client formats via markdown
                clean_msg = raw_msg.replace("<b>", "**").replace("</b>", "**")
                clean_msg = clean_msg.replace("<i>", "*").replace("</i>", "*")
                clean_msg = clean_msg.replace("{name}", customer_name)
                clean_msg = clean_msg.replace("{username}", customer_username)

                message_to_send = (
                    f"{clean_msg}\n\n"
                    f"👉 **បើកមើលទំនិញក្នុង Mini App៖**\n🤖 {miniapp_url}\n\n"
                    f"👉 **ចូលរួម Telegram Channel ផ្លូវការ៖**\n📢 {channel_url}"
                )

        # Personalize fallback_message with Telegram name
        message_to_send = message_to_send.replace("{name}", customer_name)
        message_to_send = message_to_send.replace("{username}", customer_username)

        # Remove any traces of KouPrey or old links
        for old in ["( KouPrey )", "(KouPrey)", "( kouprey )", "(kouprey)", "KouPrey", "kouprey"]:
            message_to_send = message_to_send.replace(old, "")
        message_to_send = message_to_send.replace("https://www.kouprey.asia/telegram.php", "@gogobrand_bot")
        message_to_send = message_to_send.replace("https://t.me/kouprey_channel", "https://t.me/gogobrand98")
        message_to_send = re.sub(r'[ \t]+', ' ', message_to_send).strip()

        try:
            # 1. Mark incoming message as seen/read
            await event.mark_read()

            # 2. Anti-ban: Simulate human typing behavior (2 to 3.5s delay)
            typing_delay = round(random.uniform(2.0, 3.5), 1)
            logger.info(f"Simulating human typing for {typing_delay}s before reply...")
            async with client.action(sender.id, 'typing'):
                await asyncio.sleep(typing_delay)

            # 3. Send auto-reply (with photo if configured, otherwise text message)
            if photo_to_send and (os.path.exists(photo_to_send) or photo_to_send.startswith("http")):
                try:
                    await client.send_file(sender.id, file=photo_to_send, caption=message_to_send)
                    logger.info(f"✓ Successfully sent auto-reply with photo to {sender.first_name} ({user_id})")
                except Exception as file_err:
                    logger.warning(f"Could not send photo ({file_err}). Falling back to text.")
                    await client.send_message(sender.id, message_to_send)
                    logger.info(f"✓ Successfully sent fallback text auto-reply to {sender.first_name} ({user_id})")
            else:
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
