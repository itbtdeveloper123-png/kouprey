import React, { useState, useEffect, useRef } from 'react';
import {
  Send,
  Bot,
  MessageSquare,
  Settings,
  Sparkles,
  ExternalLink,
  Image as ImageIcon,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Trash2,
  RefreshCw,
  Eye,
  Link as LinkIcon,
  ShieldCheck,
  Smartphone,
  Users,
  Copy,
  Check,
  Layers,
  ArrowRight,
  Info,
  Sliders,
  Radio,
  Plus,
  X
} from 'lucide-react';
import { adminApi } from '../api/adminClient';

// Common Emojis Quick Bar
const QUICK_EMOJIS = ['☕', '🛍️', '✨', '📢', '🔥', '🎉', '🚀', '❤️', '📦', '📍', '📞', '👉', '🌟', '💎', '🥤'];

// Pre-made Khmer Marketing Message Templates
const MESSAGE_TEMPLATES = [
  {
    title: '📢 ប្រូម៉ូសិនបញ្ចុះតម្លៃពិសេស (Special Promotion)',
    text: `<b>🎉 ការបញ្ចុះតម្លៃពិសេសពី GoGo Brand! 🎉</b>\n\nទទួលបានការបញ្ចុះតម្លៃរហូតដល់ <b>20%</b> លើគ្រប់មុខផលិតផលស៊ីរ៉ូ (Syrup) និងម្សៅ (Powder) លំដាប់ពិសេស!\n\n✨ រសជាតិឈ្ងុយឆ្ងាញ់ បង្កើនគុណភាពភេសជ្ជៈរបស់អ្នក\n🚚 សេវាដឹកជញ្ជូនរហ័សទូទាំងប្រទេស\n\n👉 <b>ចុចប៊ូតុងខាងក្រោមដើម្បីចូលមើល និងកម្ម៉ង់ទំនិញក្នុង Bot Mini App ឥឡូវនេះ!</b>`
  },
  {
    title: '✨ ផលិតផលថ្មីទើបមកដល់ (New Product Arrival)',
    text: `<b>✨ ផលិតផលថ្មីទើបមកដល់ស្តុកហើយ! ✨</b>\n\nយើងខ្ញុំសូមណែនាំនូវរសជាតិថ្មីប្លែក ដែលជាជម្រើសល្អឥតខ្ចោះសម្រាប់ហាងកាហ្វេ និងភេសជ្ជៈសម័យថ្មី។\n\n🔹 គុណភាពខ្ពស់ ស្តង់ដារអន្តរជាតិ\n🔹 រសជាតិដិតជាប់ចិត្ត ងាយស្រួលឆុង\n\n👉 <b>សូមចុចបើក Bot Mini App ខាងក្រោមដើម្បីពិនិត្យមើលតម្លៃ និងព័ត៌មានលម្អិត៖</b>`
  },
  {
    title: '☕ ការណែនាំរសជាតិប្រចាំថ្ងៃ (Daily Recommendation)',
    text: `<b>☕ រសជាតិពិសេសប្រចាំថ្ងៃពី GoGo Brand ☕</b>\n\nតើលោកអ្នកបានសាកល្បងរូបមន្តភេសជ្ជៈថ្មីហើយឬនៅ? គ្រឿងផ្សំគុណភាពខ្ពស់ពី GoGo Brand នឹងជួយឱ្យគ្រប់កែវភេសជ្ជៈរបស់អ្នកកាន់តែមានរស់ជាតិទាក់ទាញអតិថិជន!\n\n👉 <b>ចូលទស្សនាទំនិញទាំងអស់ក្នុង Bot Mini App ខាងក្រោម៖</b>`
  }
];

export default function TelegramPage() {
  const [activeTab, setActiveTab] = useState('broadcast'); // 'broadcast' | 'autoreply' | 'settings'
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);
  const [testingBot, setTestingBot] = useState(false);
  const [webhookLoading, setWebhookLoading] = useState(false);
  const [copiedKey, setCopiedKey] = useState('');

  const [notification, setNotification] = useState(null); // { type: 'success'|'error', text: '' }

  // Settings State
  const [settings, setSettings] = useState({
    telegram_bot_token: '',
    telegram_group_id: '',
    telegram_channel_url: 'https://t.me/kouprey_channel',
    telegram_miniapp_url: 'https://www.kouprey.asia/telegram.php',
    telegram_support_url: 'https://t.me/Bos_Sauveli98',
    telegram_webhook_url: 'https://www.kouprey.asia/telegram-webhook.php',
    telegram_autoreply_enabled: '1',
    telegram_autoreply_business_enabled: '1',
    telegram_autoreply_message: '',
    telegram_autoreply_photo: '',
    telegram_autoreply_btn_miniapp_text: '🛍️ បើកមើលទំនិញ (Open Mini App)',
    telegram_autoreply_btn_miniapp_url: 'https://www.kouprey.asia/telegram.php',
    telegram_autoreply_btn_channel_text: '📢 ចូលរួម Telegram Channel',
    telegram_autoreply_btn_channel_url: 'https://t.me/kouprey_channel',
    telegram_autoreply_btn_support_text: '💬 ទាក់ទងផ្ទាល់ / កម្ម៉ង់',
    telegram_autoreply_btn_support_url: 'https://t.me/Bos_Sauveli98',
    telegram_api_id: '',
    telegram_api_hash: '',
    telegram_phone_number: ''
  });

  // Bot Information Status
  const [botInfo, setBotInfo] = useState(null);
  const [webhookInfo, setWebhookInfo] = useState(null);

  // Broadcast Form State
  const [broadcast, setBroadcast] = useState({
    chat_id: '',
    message_text: MESSAGE_TEMPLATES[0].text,
    photo_url: '',
    include_miniapp: true,
    miniapp_text: '🛍️ បើកមើលទំនិញ (Open Mini App)',
    miniapp_url: 'https://www.kouprey.asia/telegram.php',
    miniapp_mode: 'web_app', // 'web_app' | 'url'
    include_channel: true,
    channel_text: '📢 ចូលរួម Telegram Channel',
    channel_url: 'https://t.me/kouprey_channel',
    include_support: true,
    support_text: '💬 ទាក់ទងផ្ទាល់ / កម្ម៉ង់',
    support_url: 'https://t.me/Bos_Sauveli98'
  });

  // Broadcast History
  const [history, setHistory] = useState([]);
  const [customerLogs, setCustomerLogs] = useState([]);

  const fileInputRef = useRef(null);
  const autoReplyFileRef = useRef(null);

  // Show notification helper
  const notify = (type, text) => {
    setNotification({ type, text });
    setTimeout(() => {
      setNotification(null);
    }, 6000);
  };

  // Copy helper
  const handleCopy = (text, key) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(''), 2500);
  };

  // Load initial settings and history
  const loadData = async () => {
    setLoading(true);
    try {
      const res = await adminApi.telegramGetSettings();
      if (res && res.settings) {
        setSettings((prev) => ({ ...prev, ...res.settings }));
        // Sync default broadcast chat ID
        if (res.settings.telegram_group_id) {
          setBroadcast((prev) => ({
            ...prev,
            chat_id: prev.chat_id || res.settings.telegram_group_id,
            miniapp_url: res.settings.telegram_miniapp_url || prev.miniapp_url,
            channel_url: res.settings.telegram_channel_url || prev.channel_url,
            support_url: res.settings.telegram_support_url || prev.support_url
          }));
        }

        // Test bot token if available
        if (res.settings.telegram_bot_token) {
          adminApi
            .telegramTestBot(res.settings.telegram_bot_token)
            .then((bRes) => {
              if (bRes.success && bRes.bot) setBotInfo(bRes.bot);
            })
            .catch(() => {});
        }
      }

      // Load history
      const histRes = await adminApi.telegramGetHistory();
      if (histRes && histRes.history) {
        setHistory(histRes.history);
      }

      // Load customer logs
      const logRes = await adminApi.telegramGetCustomerLogs();
      if (logRes && logRes.logs) {
        setCustomerLogs(logRes.logs);
      }
    } catch (err) {
      console.error('Error loading telegram data:', err);
      notify('error', 'មិនអាចទាញយកទិន្នន័យ Telegram បានទេ៖ ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  // Save Settings
  const handleSaveSettings = async (customSettings = null) => {
    setSaving(true);
    try {
      const toSave = customSettings || settings;
      const res = await adminApi.telegramSaveSettings(toSave);
      if (res.success) {
        notify('success', 'បានរក្សាទុកការកំណត់ Telegram ដោយជោគជ័យ!');
      } else {
        notify('error', res.error || 'បរាជ័យក្នុងការរក្សាទុក');
      }
    } catch (err) {
      notify('error', 'កំហុស៖ ' + err.message);
    } finally {
      setSaving(false);
    }
  };

  // Test Bot Connection
  const handleTestBot = async () => {
    const token = settings.telegram_bot_token?.trim();
    if (!token) {
      notify('error', 'សូមបញ្ចូល Bot Token ជាមុនសិន');
      return;
    }
    setTestingBot(true);
    try {
      const res = await adminApi.telegramTestBot(token);
      if (res.success && res.bot) {
        setBotInfo(res.bot);
        notify('success', `Bot ដំណើរការល្អ! ឈ្មោះ: ${res.bot.first_name} (@${res.bot.username})`);
      } else {
        setBotInfo(null);
        notify('error', 'Bot Token មិនត្រឹមត្រូវ៖ ' + (res.error || 'Error'));
      }
    } catch (err) {
      setBotInfo(null);
      notify('error', 'កំហុសតេស្ត Bot: ' + err.message);
    } finally {
      setTestingBot(false);
    }
  };

  // Handle Photo Upload for Broadcast
  const handlePhotoUpload = async (e, targetField = 'broadcast') => {
    const file = e.target.files?.[0];
    if (!file) return;

    try {
      const uploadRes = await adminApi.uploadImage(file, 'banner');
      if (uploadRes && uploadRes.success && uploadRes.path) {
        const fullUrl = uploadRes.path.startsWith('http')
          ? uploadRes.path
          : `https://www.kouprey.asia${uploadRes.path}`;

        if (targetField === 'broadcast') {
          setBroadcast((prev) => ({ ...prev, photo_url: fullUrl }));
        } else {
          setSettings((prev) => ({ ...prev, telegram_autoreply_photo: fullUrl }));
        }
        notify('success', 'បាន Upload រូបភាពដោយជោគជ័យ!');
      } else {
        notify('error', 'Upload រូបភាពមិនបានសម្រេច');
      }
    } catch (err) {
      notify('error', 'កំហុស Upload រូបភាព៖ ' + err.message);
    }
  };

  // Send Broadcast Message
  const handleSendBroadcast = async () => {
    if (!broadcast.chat_id?.trim()) {
      notify('error', 'សូមបញ្ចូល Group ឬ Channel ID');
      return;
    }
    if (!broadcast.message_text?.trim() && !broadcast.photo_url?.trim()) {
      notify('error', 'សូមបញ្ចូលអត្ថបទ ឬរូបភាពដែលត្រូវផ្ញើ');
      return;
    }

    setSending(true);
    try {
      const payload = {
        chat_id: broadcast.chat_id.trim(),
        message_text: broadcast.message_text,
        photo_url: broadcast.photo_url,
        include_miniapp: broadcast.include_miniapp,
        miniapp_text: broadcast.miniapp_text,
        miniapp_url: broadcast.miniapp_url,
        miniapp_mode: broadcast.miniapp_mode,
        include_channel: broadcast.include_channel,
        channel_text: broadcast.channel_text,
        channel_url: broadcast.channel_url,
        include_support: broadcast.include_support,
        support_text: broadcast.support_text,
        support_url: broadcast.support_url
      };

      const res = await adminApi.telegramSendBroadcast(payload);
      if (res.success) {
        notify('success', '🎉 បានផ្ញើសារទៅកាន់ Telegram Group រួចរាល់ដោយជោគជ័យ!');
        // Refresh history
        const histRes = await adminApi.telegramGetHistory();
        if (histRes && histRes.history) setHistory(histRes.history);
      } else {
        notify('error', 'ការផ្ញើសារបានបរាជ័យ៖ ' + (res.error || 'Unknown error'));
      }
    } catch (err) {
      notify('error', 'កំហុសក្នុងការផ្ញើសារ៖ ' + err.message);
    } finally {
      setSending(false);
    }
  };

  // Setup Webhook
  const handleSetWebhook = async () => {
    setWebhookLoading(true);
    try {
      const res = await adminApi.telegramSetWebhook(settings.telegram_webhook_url, settings.telegram_bot_token);
      if (res.success) {
        notify('success', '🎉 បានដំឡើង Webhook ទៅ Telegram ដោយជោគជ័យ!');
        handleCheckWebhookInfo();
      } else {
        notify('error', 'ដំឡើង Webhook បរាជ័យ៖ ' + (res.error || 'Error'));
      }
    } catch (err) {
      notify('error', 'កំហុសដំឡើង Webhook៖ ' + err.message);
    } finally {
      setWebhookLoading(false);
    }
  };

  // Check Webhook Info
  const handleCheckWebhookInfo = async () => {
    setWebhookLoading(true);
    try {
      const res = await adminApi.telegramGetWebhookInfo();
      if (res.success) {
        setWebhookInfo(res.info);
        notify('success', 'បានទាញយកព័ត៌មាន Webhook រួចរាល់');
      } else {
        notify('error', res.error || 'មិនអាចពិនិត្យ Webhook បានទេ');
      }
    } catch (err) {
      notify('error', 'កំហុសពិនិត្យ Webhook: ' + err.message);
    } finally {
      setWebhookLoading(false);
    }
  };

  // Delete Webhook
  const handleDeleteWebhook = async () => {
    if (!window.confirm('តើអ្នកពិតជាចង់លុប Webhook នេះមែនទេ?')) return;
    setWebhookLoading(true);
    try {
      const res = await adminApi.telegramDeleteWebhook();
      if (res.success) {
        setWebhookInfo(null);
        notify('success', 'បានលុប Webhook រួចរាល់');
      } else {
        notify('error', res.error || 'លុប Webhook បរាជ័យ');
      }
    } catch (err) {
      notify('error', 'កំហុសលុប Webhook: ' + err.message);
    } finally {
      setWebhookLoading(false);
    }
  };

  // Insert Emoji into Message Text
  const insertEmoji = (emoji) => {
    setBroadcast((prev) => ({
      ...prev,
      message_text: (prev.message_text || '') + emoji
    }));
  };

  // Insert Tag into Auto Reply
  const insertAutoReplyTag = (tag) => {
    setSettings((prev) => ({
      ...prev,
      telegram_autoreply_message: (prev.telegram_autoreply_message || '') + tag
    }));
  };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] gap-3 text-emerald-700">
        <Loader2 size={36} className="animate-spin" />
        <span className="text-sm font-medium">កំពុងទាញយកទិន្នន័យ Telegram...</span>
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      {/* Toast Notification */}
      {notification && (
        <div
          className={`fixed bottom-5 right-5 z-50 flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl border text-sm font-medium transition-all duration-300 animate-slide-up ${
            notification.type === 'success'
              ? 'bg-emerald-900/95 text-white border-emerald-700 shadow-emerald-950/20'
              : 'bg-red-900/95 text-white border-red-700 shadow-red-950/20'
          }`}
        >
          {notification.type === 'success' ? (
            <CheckCircle2 size={20} className="text-emerald-400 shrink-0" />
          ) : (
            <AlertCircle size={20} className="text-red-400 shrink-0" />
          )}
          <span>{notification.text}</span>
          <button
            onClick={() => setNotification(null)}
            className="ml-2 text-white/70 hover:text-white"
          >
            <X size={16} />
          </button>
        </div>
      )}

      {/* Header Banner */}
      <div className="bg-gradient-to-r from-emerald-900 via-emerald-800 to-teal-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl shadow-emerald-950/15 relative overflow-hidden">
        <div className="absolute right-0 top-0 bottom-0 opacity-10 pointer-events-none flex items-center pr-10">
          <Bot size={280} />
        </div>

        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="space-y-2">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-700/60 border border-emerald-500/40 text-xs font-semibold tracking-wide text-emerald-200">
              <Sparkles size={13} />
              <span>Telegram Marketing & Automation Suite</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
              គ្រប់គ្រង Telegram Bot & Auto-Reply
            </h1>
            <p className="text-emerald-100/80 text-sm max-w-2xl leading-relaxed">
              ផ្ញើសារប្រូម៉ូសិនទៅកាន់ Telegram Group ជាមួយរូបភាព និង Inline Button បើក Bot Mini App
              ព្រមទាំងកំណត់ប្រព័ន្ធឆ្លើយតបសារស្វ័យប្រវត្តិសម្រាប់ Personal Account និងអតិថិជនថ្មី។
            </p>
          </div>

          {/* Quick Status Pill */}
          <div className="flex flex-wrap items-center gap-2.5">
            <div className="bg-emerald-950/50 backdrop-blur-xs border border-emerald-600/30 rounded-2xl p-3 px-4 flex items-center gap-3">
              <div
                className={`w-3 h-3 rounded-full animate-pulse ${
                  botInfo ? 'bg-emerald-400 shadow-md shadow-emerald-400/50' : 'bg-amber-400'
                }`}
              />
              <div className="text-left">
                <div className="text-[10px] text-emerald-300 font-medium uppercase">Bot Status</div>
                <div className="text-xs font-bold text-white truncate max-w-[150px]">
                  {botInfo ? `@${botInfo.username}` : 'មិនទាន់តេស្ត Token'}
                </div>
              </div>
            </div>

            <button
              onClick={handleTestBot}
              disabled={testingBot}
              className="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-emerald-700/70 hover:bg-emerald-600 text-xs font-semibold text-white border border-emerald-500/40 transition cursor-pointer"
            >
              <RefreshCw size={14} className={testingBot ? 'animate-spin' : ''} />
              <span>តេស្ត Bot</span>
            </button>
          </div>
        </div>

        {/* Tabs Bar */}
        <div className="flex items-center gap-2 mt-8 border-t border-emerald-700/50 pt-4 overflow-x-auto no-scrollbar">
          <button
            onClick={() => setActiveTab('broadcast')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'broadcast'
                ? 'bg-white text-emerald-900 shadow-md'
                : 'text-emerald-100/80 hover:text-white hover:bg-emerald-800/60'
            }`}
          >
            <Send size={16} />
            <span>ផ្ញើសារទៅកាន់ Group (Broadcast)</span>
          </button>

          <button
            onClick={() => setActiveTab('autoreply')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'autoreply'
                ? 'bg-white text-emerald-900 shadow-md'
                : 'text-emerald-100/80 hover:text-white hover:bg-emerald-800/60'
            }`}
          >
            <MessageSquare size={16} />
            <span>ឆ្លើយតបស្វ័យប្រវត្តិ (Auto-Reply & Personal)</span>
          </button>

          <button
            onClick={() => setActiveTab('settings')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'settings'
                ? 'bg-white text-emerald-900 shadow-md'
                : 'text-emerald-100/80 hover:text-white hover:bg-emerald-800/60'
            }`}
          >
            <Settings size={16} />
            <span>ការកំណត់ Bot & Webhook</span>
          </button>
        </div>
      </div>

      {/* ─────────────────────────────────────────────────────────────
          TAB 1: BROADCAST TO GROUP
      ───────────────────────────────────────────────────────────── */}
      {activeTab === 'broadcast' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* Left Form: 7 cols */}
          <div className="lg:col-span-7 space-y-6">
            <div className="bg-white rounded-2xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                <div className="flex items-center gap-2.5">
                  <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm">
                    1
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 text-base">
                      បង្កើតសារផ្សព្វផ្សាយ (Compose Broadcast)
                    </h3>
                    <p className="text-xs text-gray-500">
                      កំណត់ទិសដៅ អត្ថបទពិពណ៌នា រូបភាព និងប៊ូតុង Inline
                    </p>
                  </div>
                </div>
              </div>

              {/* Target Group ID */}
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1.5">
                  Telegram Group / Channel ID <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <input
                    type="text"
                    value={broadcast.chat_id}
                    onChange={(e) => setBroadcast({ ...broadcast, chat_id: e.target.value })}
                    placeholder="ឧទាហរណ៍៖ -100xxxxxxxxxx ឬ @channel_username"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-hidden font-mono"
                  />
                  {settings.telegram_group_id && broadcast.chat_id !== settings.telegram_group_id && (
                    <button
                      type="button"
                      onClick={() =>
                        setBroadcast({ ...broadcast, chat_id: settings.telegram_group_id })
                      }
                      className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg transition"
                    >
                      ប្រើ Default Group ID
                    </button>
                  )}
                </div>
                <p className="text-[11px] text-gray-500 mt-1">
                  💡 សម្រាប់ Group ឬ Supergroup ត្រូវមានលេខសញ្ញាដកខាងមុខ ដូចជា{' '}
                  <code className="bg-gray-100 px-1 py-0.5 rounded text-emerald-800">-10023456789</code>{' '}
                  (ត្រូវប្រាកដថា Bot បាន Add ចូលក្នុង Group និងមានសិទ្ធិ Post Messages)។
                </p>
              </div>

              {/* Quick Template Selector */}
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1.5">
                  គំរូអត្ថបទរហ័ស (Marketing Templates)
                </label>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  {MESSAGE_TEMPLATES.map((tmpl, idx) => (
                    <button
                      key={idx}
                      type="button"
                      onClick={() => setBroadcast({ ...broadcast, message_text: tmpl.text })}
                      className="text-left p-2.5 rounded-xl border border-gray-200 hover:border-emerald-400 hover:bg-emerald-50/50 transition cursor-pointer text-xs font-medium text-gray-700"
                    >
                      <div className="font-semibold truncate">{tmpl.title}</div>
                      <div className="text-[10px] text-gray-400 mt-0.5">ចុចដើម្បីបំពេញស្វ័យប្រវត្តិ</div>
                    </button>
                  ))}
                </div>
              </div>

              {/* Message / Caption Area */}
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label className="text-xs font-bold text-gray-700">
                    ខ្លឹមសារ / ការពិពណ៌នា (Message Text / Caption)
                  </label>
                  <span className="text-[11px] text-gray-400">គាំទ្រ HTML (b, i, a, code)</span>
                </div>

                {/* Quick Emoji Bar */}
                <div className="flex items-center gap-1 overflow-x-auto pb-2 mb-1.5 no-scrollbar">
                  <span className="text-[11px] font-semibold text-gray-400 mr-1 shrink-0">
                    Emojis:
                  </span>
                  {QUICK_EMOJIS.map((emoji, i) => (
                    <button
                      key={i}
                      type="button"
                      onClick={() => insertEmoji(emoji)}
                      className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-sm transition shrink-0 cursor-pointer"
                    >
                      {emoji}
                    </button>
                  ))}
                </div>

                <textarea
                  rows={6}
                  value={broadcast.message_text}
                  onChange={(e) => setBroadcast({ ...broadcast, message_text: e.target.value })}
                  placeholder="សរសេរសារពិពណ៌នាអំពីផលិតផល ឬប្រូម៉ូសិន..."
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-hidden leading-relaxed font-sans"
                />
              </div>

              {/* Photo Upload / URL */}
              <div className="space-y-2">
                <label className="block text-xs font-bold text-gray-700">
                  រូបភាពភ្ជាប់ជាមួយសារ (Photo / Banner)
                </label>
                <div className="flex flex-col sm:flex-row items-center gap-3">
                  <input
                    type="text"
                    value={broadcast.photo_url}
                    onChange={(e) => setBroadcast({ ...broadcast, photo_url: e.target.value })}
                    placeholder="https://... ឬ Upload ពីកុំព្យូទ័រ"
                    className="flex-1 w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-hidden font-mono"
                  />
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*"
                    onChange={(e) => handlePhotoUpload(e, 'broadcast')}
                    className="hidden"
                  />
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold transition cursor-pointer"
                  >
                    <ImageIcon size={15} />
                    <span>Upload រូបភាព</span>
                  </button>
                  {broadcast.photo_url && (
                    <button
                      type="button"
                      onClick={() => setBroadcast({ ...broadcast, photo_url: '' })}
                      className="p-2.5 rounded-xl text-red-500 hover:bg-red-50 transition cursor-pointer"
                      title="ដករូបភាពចេញ"
                    >
                      <Trash2 size={16} />
                    </button>
                  )}
                </div>
              </div>

              {/* Inline Buttons Configurator */}
              <div className="border border-emerald-200/80 bg-emerald-50/40 rounded-2xl p-4.5 space-y-4">
                <div className="flex items-center justify-between pb-2 border-b border-emerald-200/60">
                  <div className="flex items-center gap-2 text-emerald-900 font-bold text-xs sm:text-sm">
                    <Radio size={16} className="text-emerald-700" />
                    <span>ប៊ូតុង Inline (Inline Keyboard Buttons)</span>
                  </div>
                  <span className="text-[11px] text-emerald-700 font-medium">
                    បើក Mini App & Channels
                  </span>
                </div>

                {/* Button 1: Mini App */}
                <div className="bg-white rounded-xl p-3.5 border border-emerald-100 space-y-2.5 shadow-xs">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        id="toggle-miniapp"
                        checked={broadcast.include_miniapp}
                        onChange={(e) =>
                          setBroadcast({ ...broadcast, include_miniapp: e.target.checked })
                        }
                        className="w-4 h-4 text-emerald-600 rounded-sm border-gray-300 focus:ring-emerald-500"
                      />
                      <label
                        htmlFor="toggle-miniapp"
                        className="text-xs font-bold text-gray-900 cursor-pointer flex items-center gap-1.5"
                      >
                        <Smartphone size={14} className="text-emerald-600" />
                        <span>ប៊ូតុងបើក Bot Mini App (Web App)</span>
                      </label>
                    </div>
                    <span className="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800">
                      Primary Action
                    </span>
                  </div>

                  {broadcast.include_miniapp && (
                    <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 pt-1">
                      <div className="sm:col-span-5">
                        <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                          ឈ្មោះលើប៊ូតុង (Button Label)
                        </label>
                        <input
                          type="text"
                          value={broadcast.miniapp_text}
                          onChange={(e) =>
                            setBroadcast({ ...broadcast, miniapp_text: e.target.value })
                          }
                          className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 focus:ring-1 focus:ring-emerald-500 outline-hidden"
                        />
                      </div>
                      <div className="sm:col-span-7">
                        <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                          URL Mini App (Default: /telegram.php)
                        </label>
                        <input
                          type="text"
                          value={broadcast.miniapp_url}
                          onChange={(e) =>
                            setBroadcast({ ...broadcast, miniapp_url: e.target.value })
                          }
                          className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 focus:ring-1 focus:ring-emerald-500 outline-hidden font-mono"
                        />
                      </div>
                    </div>
                  )}
                </div>

                {/* Button 2: Telegram Channel */}
                <div className="bg-white rounded-xl p-3.5 border border-emerald-100 space-y-2.5 shadow-xs">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        id="toggle-channel"
                        checked={broadcast.include_channel}
                        onChange={(e) =>
                          setBroadcast({ ...broadcast, include_channel: e.target.checked })
                        }
                        className="w-4 h-4 text-emerald-600 rounded-sm border-gray-300 focus:ring-emerald-500"
                      />
                      <label
                        htmlFor="toggle-channel"
                        className="text-xs font-bold text-gray-900 cursor-pointer flex items-center gap-1.5"
                      >
                        <Users size={14} className="text-teal-600" />
                        <span>ប៊ូតុងចូលរួម Telegram Channel</span>
                      </label>
                    </div>
                  </div>

                  {broadcast.include_channel && (
                    <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 pt-1">
                      <div className="sm:col-span-5">
                        <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                          ឈ្មោះលើប៊ូតុង
                        </label>
                        <input
                          type="text"
                          value={broadcast.channel_text}
                          onChange={(e) =>
                            setBroadcast({ ...broadcast, channel_text: e.target.value })
                          }
                          className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 focus:ring-1 focus:ring-emerald-500 outline-hidden"
                        />
                      </div>
                      <div className="sm:col-span-7">
                        <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                          Channel Link (https://t.me/...)
                        </label>
                        <input
                          type="text"
                          value={broadcast.channel_url}
                          onChange={(e) =>
                            setBroadcast({ ...broadcast, channel_url: e.target.value })
                          }
                          className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 focus:ring-1 focus:ring-emerald-500 outline-hidden font-mono"
                        />
                      </div>
                    </div>
                  )}
                </div>

                {/* Button 3: Support / Contact */}
                <div className="bg-white rounded-xl p-3.5 border border-emerald-100 space-y-2.5 shadow-xs">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        id="toggle-support"
                        checked={broadcast.include_support}
                        onChange={(e) =>
                          setBroadcast({ ...broadcast, include_support: e.target.checked })
                        }
                        className="w-4 h-4 text-emerald-600 rounded-sm border-gray-300 focus:ring-emerald-500"
                      />
                      <label
                        htmlFor="toggle-support"
                        className="text-xs font-bold text-gray-900 cursor-pointer flex items-center gap-1.5"
                      >
                        <MessageSquare size={14} className="text-indigo-600" />
                        <span>ប៊ូតុងទាក់ទងផ្ទាល់ / កម្ម៉ង់ (Personal Account)</span>
                      </label>
                    </div>
                  </div>

                  {broadcast.include_support && (
                    <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 pt-1">
                      <div className="sm:col-span-5">
                        <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                          ឈ្មោះលើប៊ូតុង
                        </label>
                        <input
                          type="text"
                          value={broadcast.support_text}
                          onChange={(e) =>
                            setBroadcast({ ...broadcast, support_text: e.target.value })
                          }
                          className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 focus:ring-1 focus:ring-emerald-500 outline-hidden"
                        />
                      </div>
                      <div className="sm:col-span-7">
                        <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                          Link Personal / Support (https://t.me/...)
                        </label>
                        <input
                          type="text"
                          value={broadcast.support_url}
                          onChange={(e) =>
                            setBroadcast({ ...broadcast, support_url: e.target.value })
                          }
                          className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 focus:ring-1 focus:ring-emerald-500 outline-hidden font-mono"
                        />
                      </div>
                    </div>
                  )}
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex items-center justify-between pt-2">
                <button
                  type="button"
                  onClick={() =>
                    handleSaveSettings({
                      ...settings,
                      telegram_group_id: broadcast.chat_id,
                      telegram_miniapp_url: broadcast.miniapp_url,
                      telegram_channel_url: broadcast.channel_url,
                      telegram_support_url: broadcast.support_url
                    })
                  }
                  className="px-4 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-50 text-xs font-semibold text-gray-700 transition cursor-pointer"
                >
                  រក្សាទុកជា Default
                </button>

                <button
                  type="button"
                  onClick={handleSendBroadcast}
                  disabled={sending}
                  className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-md shadow-emerald-700/25 transition cursor-pointer disabled:opacity-50"
                >
                  {sending ? (
                    <>
                      <Loader2 size={18} className="animate-spin" />
                      <span>កំពុងផ្ញើសារ...</span>
                    </>
                  ) : (
                    <>
                      <Send size={18} />
                      <span>ផ្ញើសារទៅ Group ឥឡូវនេះ</span>
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>

          {/* Right: Live Telegram Preview & History (5 cols) */}
          <div className="lg:col-span-5 space-y-6">
            {/* Realistic Telegram Message Preview */}
            <div className="bg-gradient-to-b from-[#0f172a] to-[#1e293b] rounded-3xl p-5 text-white shadow-xl border border-slate-700/60 sticky top-4">
              <div className="flex items-center justify-between pb-3 mb-3 border-b border-slate-800">
                <div className="flex items-center gap-2">
                  <div className="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse" />
                  <span className="text-xs font-bold text-slate-200 uppercase tracking-wider">
                    Live Telegram Preview
                  </span>
                </div>
                <span className="text-[10px] text-slate-400">Telegram Messenger Style</span>
              </div>

              {/* Chat Bubble Simulation */}
              <div className="bg-[#182533] rounded-2xl p-4 border border-slate-700/40 max-w-[380px] mx-auto shadow-md">
                {/* Header inside chat */}
                <div className="flex items-center gap-2.5 mb-2.5">
                  <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                    KP
                  </div>
                  <div>
                    <div className="flex items-center gap-1.5">
                      <span className="text-xs font-bold text-emerald-400">
                        {botInfo?.first_name || 'KouPrey Bot'}
                      </span>
                      <span className="text-[9px] bg-emerald-500/20 text-emerald-300 font-semibold px-1 rounded-xs">
                        BOT
                      </span>
                    </div>
                    <div className="text-[10px] text-slate-400">
                      to {broadcast.chat_id || 'Telegram Group'}
                    </div>
                  </div>
                </div>

                {/* Photo Preview if present */}
                {broadcast.photo_url ? (
                  <div className="rounded-xl overflow-hidden mb-3 border border-slate-700/50 bg-black/40">
                    <img
                      src={broadcast.photo_url}
                      alt="Broadcast preview"
                      className="w-full max-h-52 object-cover"
                      onError={(e) => {
                        e.currentTarget.style.display = 'none';
                      }}
                    />
                  </div>
                ) : null}

                {/* Text Body */}
                <div
                  className="text-xs text-slate-100 leading-relaxed space-y-1 whitespace-pre-wrap break-words"
                  dangerouslySetInnerHTML={{
                    __html:
                      broadcast.message_text ||
                      '<span class="text-slate-400 italic">បញ្ចូលអត្ថបទដើម្បីមើលរូបរាង...</span>'
                  }}
                />

                {/* Timestamp */}
                <div className="text-right text-[10px] text-slate-400 mt-2">
                  {new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} ✓✓
                </div>

                {/* Inline Buttons Preview */}
                <div className="mt-3 space-y-1.5 pt-2 border-t border-slate-700/40">
                  {broadcast.include_miniapp && (
                    <button
                      type="button"
                      className="w-full py-2 px-3 rounded-xl bg-[#2b5278] hover:bg-[#34608c] text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-xs"
                    >
                      <Smartphone size={13} className="text-teal-300" />
                      <span>{broadcast.miniapp_text}</span>
                    </button>
                  )}

                  <div className="grid grid-cols-2 gap-1.5">
                    {broadcast.include_channel && (
                      <button
                        type="button"
                        className="py-1.5 px-2 rounded-xl bg-[#24374b] hover:bg-[#2b5278] text-white text-[11px] font-semibold transition flex items-center justify-center gap-1"
                      >
                        <Users size={12} className="text-teal-300" />
                        <span className="truncate">{broadcast.channel_text}</span>
                      </button>
                    )}

                    {broadcast.include_support && (
                      <button
                        type="button"
                        className="py-1.5 px-2 rounded-xl bg-[#24374b] hover:bg-[#2b5278] text-white text-[11px] font-semibold transition flex items-center justify-center gap-1"
                      >
                        <MessageSquare size={12} className="text-teal-300" />
                        <span className="truncate">{broadcast.support_text}</span>
                      </button>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Broadcast History List */}
            <div className="bg-white rounded-2xl p-5 border border-gray-200/80 shadow-xs space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                <div className="flex items-center gap-2">
                  <Layers size={16} className="text-emerald-700" />
                  <h4 className="font-bold text-gray-900 text-sm">ប្រវត្តិផ្ញើសារ (History)</h4>
                </div>
                {history.length > 0 && (
                  <button
                    onClick={async () => {
                      if (window.confirm('តើអ្នកចង់សម្អាតប្រវត្តិសារទាំងអស់មែនទេ?')) {
                        await adminApi.telegramClearHistory();
                        setHistory([]);
                        notify('success', 'បានសម្អាតប្រវត្តិសាររួចរាល់');
                      }
                    }}
                    className="text-[11px] text-red-600 hover:text-red-700 font-medium"
                  >
                    Clear All
                  </button>
                )}
              </div>

              {history.length === 0 ? (
                <div className="text-center py-6 text-xs text-gray-400">
                  មិនទាន់មានប្រវត្តិសារដែលបានផ្ញើនៅឡើយទេ
                </div>
              ) : (
                <div className="space-y-3 max-h-[360px] overflow-y-auto pr-1">
                  {history.map((item) => (
                    <div
                      key={item.id}
                      className="p-3 rounded-xl border border-gray-100 hover:border-emerald-200 bg-gray-50/50 hover:bg-emerald-50/20 transition space-y-1.5 text-xs"
                    >
                      <div className="flex items-center justify-between">
                        <span
                          className={`font-semibold px-2 py-0.5 rounded-full text-[10px] ${
                            item.status === 'success'
                              ? 'bg-emerald-100 text-emerald-800'
                              : 'bg-red-100 text-red-800'
                          }`}
                        >
                          {item.status === 'success' ? '✓ ជោគជ័យ' : '✕ បរាជ័យ'}
                        </span>
                        <span className="text-[10px] text-gray-400">
                          {new Date(item.created_at).toLocaleString('km-KH')}
                        </span>
                      </div>
                      <div className="text-gray-700 line-clamp-2">{item.message_text || '(Photo only)'}</div>
                      <div className="text-[10px] text-gray-500 font-mono">Chat ID: {item.chat_id}</div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ─────────────────────────────────────────────────────────────
          TAB 2: AUTO-REPLY & PERSONAL ACCOUNT
      ───────────────────────────────────────────────────────────── */}
      {activeTab === 'autoreply' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {/* Left Form: 7 cols */}
            <div className="lg:col-span-7 space-y-6">
              <div className="bg-white rounded-2xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-5">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                  <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center font-bold text-sm">
                      2
                    </div>
                    <div>
                      <h3 className="font-bold text-gray-900 text-base">
                        កំណត់សារឆ្លើយតបស្វ័យប្រវត្តិ (Auto-Responder)
                      </h3>
                      <p className="text-xs text-gray-500">
                        ឆ្លើយតបពេលអតិថិជនឆាតមកលើកដំបូង និងពេលទាក់ទងមកកាន់ Personal Account
                      </p>
                    </div>
                  </div>
                </div>

                {/* Master Toggles */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="p-4 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 flex items-start justify-between gap-3">
                    <div>
                      <div className="text-xs font-bold text-emerald-950">Bot Direct Auto-Reply</div>
                      <div className="text-[11px] text-emerald-800/80 mt-0.5">
                        ឆ្លើយតបពេលអតិថិជនចុច /start ក្នុង Bot
                      </div>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={settings.telegram_autoreply_enabled === '1'}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_enabled: e.target.checked ? '1' : '0'
                          })
                        }
                        className="sr-only peer"
                      />
                      <div className="w-9 h-5 bg-gray-300 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600" />
                    </label>
                  </div>

                  <div className="p-4 rounded-2xl border border-teal-200/80 bg-teal-50/40 flex items-start justify-between gap-3">
                    <div>
                      <div className="text-xs font-bold text-teal-950">
                        Telegram Business (Personal Account)
                      </div>
                      <div className="text-[11px] text-teal-800/80 mt-0.5">
                        ឆ្លើយតបក្នុង Chat ផ្ទាល់ខ្លួនរបស់ Admin
                      </div>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={settings.telegram_autoreply_business_enabled === '1'}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_business_enabled: e.target.checked ? '1' : '0'
                          })
                        }
                        className="sr-only peer"
                      />
                      <div className="w-9 h-5 bg-gray-300 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-teal-600" />
                    </label>
                  </div>
                </div>

                {/* Greeting Message Body */}
                <div>
                  <div className="flex items-center justify-between mb-1.5">
                    <label className="text-xs font-bold text-gray-700">
                      អត្ថបទស្វាគមន៍ (Welcome / Greeting Message)
                    </label>
                    <div className="flex items-center gap-1.5 text-[11px]">
                      <span className="text-gray-400">ស្លាកស្វ័យប្រវត្តិ:</span>
                      <button
                        type="button"
                        onClick={() => insertAutoReplyTag('{name}')}
                        className="px-1.5 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-mono text-[10px]"
                      >
                        {'{name}'}
                      </button>
                      <button
                        type="button"
                        onClick={() => insertAutoReplyTag('{username}')}
                        className="px-1.5 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-mono text-[10px]"
                      >
                        {'{username}'}
                      </button>
                    </div>
                  </div>

                  <textarea
                    rows={6}
                    value={settings.telegram_autoreply_message}
                    onChange={(e) =>
                      setSettings({ ...settings, telegram_autoreply_message: e.target.value })
                    }
                    placeholder="សរសេរសារស្វាគមន៍អតិថិជន..."
                    className="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-hidden leading-relaxed"
                  />
                </div>

                {/* Welcome Photo */}
                <div className="space-y-2">
                  <label className="block text-xs font-bold text-gray-700">
                    រូបភាព Banner ស្វាគមន៍ (Optional Welcome Photo)
                  </label>
                  <div className="flex flex-col sm:flex-row items-center gap-3">
                    <input
                      type="text"
                      value={settings.telegram_autoreply_photo}
                      onChange={(e) =>
                        setSettings({ ...settings, telegram_autoreply_photo: e.target.value })
                      }
                      placeholder="https://... ឬ Upload រូបភាពស្វាគមន៍"
                      className="flex-1 w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-hidden font-mono"
                    />
                    <input
                      ref={autoReplyFileRef}
                      type="file"
                      accept="image/*"
                      onChange={(e) => handlePhotoUpload(e, 'autoreply')}
                      className="hidden"
                    />
                    <button
                      type="button"
                      onClick={() => autoReplyFileRef.current?.click()}
                      className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold transition cursor-pointer"
                    >
                      <ImageIcon size={15} />
                      <span>Upload រូបភាព</span>
                    </button>
                  </div>
                </div>

                {/* Buttons for Auto Reply */}
                <div className="border border-gray-200 rounded-2xl p-4.5 space-y-3.5 bg-gray-50/50">
                  <h4 className="text-xs font-bold text-gray-800">
                    ប៊ូតុងដែលអតិថិជននឹងឃើញក្នុងសារឆ្លើយតប (Buttons in Auto-Reply)
                  </h4>

                  {/* Mini App Button */}
                  <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 bg-white p-3 rounded-xl border border-gray-200">
                    <div className="sm:col-span-5">
                      <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                        ប៊ូតុង Mini App
                      </label>
                      <input
                        type="text"
                        value={settings.telegram_autoreply_btn_miniapp_text}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_btn_miniapp_text: e.target.value
                          })
                        }
                        className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 outline-hidden"
                      />
                    </div>
                    <div className="sm:col-span-7">
                      <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                        Mini App URL
                      </label>
                      <input
                        type="text"
                        value={settings.telegram_autoreply_btn_miniapp_url}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_btn_miniapp_url: e.target.value
                          })
                        }
                        className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 outline-hidden font-mono"
                      />
                    </div>
                  </div>

                  {/* Channel Button */}
                  <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 bg-white p-3 rounded-xl border border-gray-200">
                    <div className="sm:col-span-5">
                      <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                        ប៊ូតុង Channel
                      </label>
                      <input
                        type="text"
                        value={settings.telegram_autoreply_btn_channel_text}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_btn_channel_text: e.target.value
                          })
                        }
                        className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 outline-hidden"
                      />
                    </div>
                    <div className="sm:col-span-7">
                      <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                        Channel Link
                      </label>
                      <input
                        type="text"
                        value={settings.telegram_autoreply_btn_channel_url}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_btn_channel_url: e.target.value
                          })
                        }
                        className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 outline-hidden font-mono"
                      />
                    </div>
                  </div>

                  {/* Support Button */}
                  <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 bg-white p-3 rounded-xl border border-gray-200">
                    <div className="sm:col-span-5">
                      <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                        ប៊ូតុង Personal / Support
                      </label>
                      <input
                        type="text"
                        value={settings.telegram_autoreply_btn_support_text}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_btn_support_text: e.target.value
                          })
                        }
                        className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 outline-hidden"
                      />
                    </div>
                    <div className="sm:col-span-7">
                      <label className="block text-[10px] font-semibold text-gray-500 mb-1">
                        Personal Account Link
                      </label>
                      <input
                        type="text"
                        value={settings.telegram_autoreply_btn_support_url}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            telegram_autoreply_btn_support_url: e.target.value
                          })
                        }
                        className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 outline-hidden font-mono"
                      />
                    </div>
                  </div>
                </div>

                {/* Personal Account Userbot (api_id & api_hash) */}
                <div className="border border-blue-200/80 bg-blue-50/40 rounded-2xl p-4.5 space-y-3.5">
                  <div className="flex items-center justify-between pb-2 border-b border-blue-200/60">
                    <div className="flex items-center gap-2 text-blue-950 font-bold text-xs sm:text-sm">
                      <ShieldCheck size={16} className="text-blue-700" />
                      <span>វិធីទី ៣: Telegram Userbot (api_id & api_hash សម្រាប់ cPanel)</span>
                    </div>
                    <a
                      href="https://my.telegram.org"
                      target="_blank"
                      rel="noreferrer"
                      className="text-[11px] font-semibold text-blue-700 hover:underline flex items-center gap-1"
                    >
                      <ExternalLink size={12} />
                      <span>យក Keys នៅ my.telegram.org</span>
                    </a>
                  </div>

                  <p className="text-[11px] text-blue-900/80 leading-relaxed">
                    ប្រសិនបើលោកអ្នកចង់ឱ្យ Personal Account ឆ្លើយតបស្វ័យប្រវត្តិតាមរយៈ Python Userbot លើ cPanel
                    សូមបញ្ចូល <code>api_id</code> និង <code>api_hash</code> ខាងក្រោម។ Script របស់យើងនឹងទាញយកទិន្នន័យនេះទៅដំណើរការស្វ័យប្រវត្តិ៖
                  </p>

                  <div className="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div className="sm:col-span-4">
                      <label className="block text-[10px] font-bold text-blue-900 mb-1">
                        API ID (លេខ)
                      </label>
                      <input
                        type="text"
                        value={settings.telegram_api_id || ''}
                        onChange={(e) =>
                          setSettings({ ...settings, telegram_api_id: e.target.value })
                        }
                        placeholder="28472910"
                        className="w-full px-3 py-1.5 text-xs rounded-lg border border-blue-300 bg-white font-mono outline-hidden focus:ring-1 focus:ring-blue-500"
                      />
                    </div>

                    <div className="sm:col-span-8">
                      <label className="block text-[10px] font-bold text-blue-900 mb-1">
                        API HASH
                      </label>
                      <input
                        type="password"
                        value={settings.telegram_api_hash || ''}
                        onChange={(e) =>
                          setSettings({ ...settings, telegram_api_hash: e.target.value })
                        }
                        placeholder="a1b2c3d4e5f6g7h8..."
                        className="w-full px-3 py-1.5 text-xs rounded-lg border border-blue-300 bg-white font-mono outline-hidden focus:ring-1 focus:ring-blue-500"
                      />
                    </div>
                  </div>

                  <div className="bg-white p-3 rounded-xl border border-blue-200 text-[11px] text-blue-900 flex items-center gap-2">
                    <Info size={16} className="text-blue-600 shrink-0" />
                    <span>
                      Script Python ត្រូវបានបង្កើតរួចរាល់នៅ៖ <code>scripts/telegram_userbot.py</code> (អាចរត់លើ cPanel តាមរយៈ Cron Job ឬ Setup Python App)។
                    </span>
                  </div>
                </div>

                <div className="flex justify-end pt-2">
                  <button
                    type="button"
                    onClick={() => handleSaveSettings()}
                    disabled={saving}
                    className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-md shadow-emerald-700/20 transition cursor-pointer disabled:opacity-50"
                  >
                    {saving ? (
                      <Loader2 size={16} className="animate-spin" />
                    ) : (
                      <CheckCircle2 size={16} />
                    )}
                    <span>រក្សាទុកការកំណត់ Auto-Reply</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Right Guide: 5 cols */}
            <div className="lg:col-span-5 space-y-6">
              {/* cPanel Setup Guide */}
              <div className="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-indigo-700/50 space-y-4">
                <div className="flex items-center gap-2.5 text-indigo-300 font-bold text-sm">
                  <Layers size={20} />
                  <span>របៀបរត់ Userbot (api_id/hash) លើ cPanel</span>
                </div>

                <p className="text-xs text-indigo-200/80 leading-relaxed">
                  ដើម្បីឱ្យ Script ដំណើរការលើ Web Hosting <code>kouprey.asia</code> ២៤/៧៖
                </p>

                <div className="space-y-3 pt-1">
                  <div className="bg-slate-800/80 p-3 rounded-xl border border-slate-700 text-xs space-y-1">
                    <div className="font-bold text-indigo-300">ជម្រើស A: ប្រើ cPanel Cron Job (@reboot)</div>
                    <div className="text-[11px] text-slate-300">
                      1. ចូល cPanel &rarr; <b>Cron Jobs</b><br />
                      2. ត្រង់ Common Settings ជ្រើសយក <b>Once Per Minute</b> ឬ <b>@reboot</b><br />
                      3. Command: <code className="bg-black/50 px-1 py-0.5 rounded text-indigo-200">nohup python3 /home/.../scripts/telegram_userbot.py &gt;/dev/null 2&gt;&amp;1 &amp;</code>
                    </div>
                  </div>

                  <div className="bg-slate-800/80 p-3 rounded-xl border border-slate-700 text-xs space-y-1">
                    <div className="font-bold text-teal-300">ជម្រើស B: ប្រើ Setup Python App លើ cPanel</div>
                    <div className="text-[11px] text-slate-300">
                      1. ចូល cPanel &rarr; <b>Setup Python App</b><br />
                      2. បង្កើត Application (Python 3.10+) ដាក់ Application root: <code>scripts</code><br />
                      3. ចុច Run Pip Install: <code>telethon</code> រួច Start App ជាការស្រេច!
                    </div>
                  </div>
                </div>
              </div>

              {/* Telegram Business Setup Visual Guide */}
              <div className="bg-gradient-to-br from-teal-900 to-emerald-950 text-white rounded-3xl p-6 shadow-xl border border-teal-700/50 space-y-4">
                <div className="flex items-center gap-2.5 text-teal-300 font-bold text-sm">
                  <Smartphone size={20} />
                  <span>របៀបភ្ជាប់ Bot ទៅ Personal Account (Telegram Business)</span>
                </div>

                <p className="text-xs text-teal-100/80 leading-relaxed">
                  Telegram អនុញ្ញាតឱ្យលោកអ្នកភ្ជាប់ Bot ទៅកាន់ <b>Personal Account</b> របស់អ្នកផ្ទាល់
                  ដើម្បីឆ្លើយតបសារស្វ័យប្រវត្តិក្នុ​ង Chat ដោយផ្ទាល់ពេលមានអតិថិជនឆាតមកលើកដំបូង៖
                </p>

                <div className="space-y-3 pt-2">
                  <div className="flex items-start gap-3 bg-teal-950/60 p-3 rounded-xl border border-teal-700/40">
                    <span className="w-5 h-5 rounded-full bg-teal-400 text-teal-950 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                      1
                    </span>
                    <div className="text-xs">
                      <div className="font-semibold text-white">បើក Telegram លើទូរស័ព្ទដៃ</div>
                      <div className="text-teal-200/70 text-[11px] mt-0.5">
                        ចូលទៅកាន់ <b>Settings</b> &rarr; <b>Telegram Business</b> &rarr; <b>Chatbots</b>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 bg-teal-950/60 p-3 rounded-xl border border-teal-700/40">
                    <span className="w-5 h-5 rounded-full bg-teal-400 text-teal-950 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                      2
                    </span>
                    <div className="text-xs">
                      <div className="font-semibold text-white">បញ្ចូល Username Bot របស់អ្នក</div>
                      <div className="text-teal-200/70 text-[11px] mt-0.5">
                        ស្វែងរក <b>@{botInfo?.username || 'Bot_Username'}</b> រួចចុច <b>Connect</b>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 bg-teal-950/60 p-3 rounded-xl border border-teal-700/40">
                    <span className="w-5 h-5 rounded-full bg-teal-400 text-teal-950 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                      3
                    </span>
                    <div className="text-xs">
                      <div className="font-semibold text-white">ដំឡើង Webhook ក្នុង Tab 3</div>
                      <div className="text-teal-200/70 text-[11px] mt-0.5">
                        ចុចប៊ូតុង <b>ដំឡើង Webhook</b> ក្នុងផ្ទាំង ការកំណត់ Bot & Webhook ដើម្បីទទួលសារ
                      </div>
                    </div>
                  </div>
                </div>

                <div className="bg-emerald-800/40 p-3 rounded-xl border border-emerald-500/30 text-[11px] text-emerald-100 flex items-center gap-2">
                  <ShieldCheck size={16} className="text-emerald-400 shrink-0" />
                  <span>
                    ដំណើរការដោយសុវត្ថិភាព 24/7 តាមរយៈ Telegram Official Business API។
                  </span>
                </div>
              </div>

              {/* Customer Chat Logs Table */}
              <div className="bg-white rounded-2xl p-5 border border-gray-200/80 shadow-xs space-y-3">
                <div className="flex items-center justify-between pb-2 border-b border-gray-100">
                  <div className="flex items-center gap-2">
                    <Users size={16} className="text-emerald-700" />
                    <h4 className="font-bold text-gray-900 text-sm">
                      អតិថិជនដែលបានឆាតមក (Recent Chats)
                    </h4>
                  </div>
                  <span className="text-[11px] text-gray-400">{customerLogs.length} នាក់</span>
                </div>

                {customerLogs.length === 0 ? (
                  <div className="text-center py-6 text-xs text-gray-400">
                    មិនទាន់មានកំណត់ត្រាអតិថិជនឆាតមកនៅឡើយទេ
                  </div>
                ) : (
                  <div className="space-y-2 max-h-[300px] overflow-y-auto pr-1">
                    {customerLogs.map((log) => (
                      <div
                        key={log.id}
                        className="p-2.5 rounded-xl border border-gray-100 bg-gray-50/50 text-xs flex items-center justify-between gap-2"
                      >
                        <div className="min-w-0">
                          <div className="font-semibold text-gray-800 truncate">
                            {log.customer_name || 'Customer'}{' '}
                            {log.customer_username && (
                              <span className="text-gray-400 font-normal">
                                (@{log.customer_username})
                              </span>
                            )}
                          </div>
                          <div className="text-[10px] text-gray-500 truncate">
                            {log.received_text || '(Action)'}
                          </div>
                        </div>
                        <div className="text-right shrink-0">
                          <span
                            className={`text-[9px] font-semibold px-1.5 py-0.5 rounded ${
                              log.replied_status === 'replied'
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-amber-100 text-amber-800'
                            }`}
                          >
                            {log.replied_status}
                          </span>
                          <div className="text-[9px] text-gray-400 mt-0.5">
                            {new Date(log.created_at).toLocaleTimeString([], {
                              hour: '2-digit',
                              minute: '2-digit'
                            })}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ─────────────────────────────────────────────────────────────
          TAB 3: BOT SETTINGS & WEBHOOK
      ───────────────────────────────────────────────────────────── */}
      {activeTab === 'settings' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {/* Left: Bot Credentials (7 cols) */}
            <div className="lg:col-span-7 space-y-6">
              <div className="bg-white rounded-2xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-5">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                  <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm">
                      3
                    </div>
                    <div>
                      <h3 className="font-bold text-gray-900 text-base">
                        ការកំណត់ Telegram Bot & Target Group
                      </h3>
                      <p className="text-xs text-gray-500">
                        គ្រប់គ្រង Bot Token, Default Group ID និងតំណភ្ជាប់ផ្លូវការ
                      </p>
                    </div>
                  </div>
                </div>

                {/* Bot Token Input */}
                <div>
                  <div className="flex items-center justify-between mb-1.5">
                    <label className="text-xs font-bold text-gray-700">
                      Telegram Bot Token <span className="text-red-500">*</span>
                    </label>
                    <a
                      href="https://t.me/BotFather"
                      target="_blank"
                      rel="noreferrer"
                      className="text-[11px] text-emerald-700 hover:underline flex items-center gap-1 font-semibold"
                    >
                      <ExternalLink size={12} />
                      <span>បង្កើត Token នៅ @BotFather</span>
                    </a>
                  </div>
                  <div className="flex gap-2">
                    <input
                      type="password"
                      value={settings.telegram_bot_token}
                      onChange={(e) =>
                        setSettings({ ...settings, telegram_bot_token: e.target.value })
                      }
                      placeholder="1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ..."
                      className="flex-1 px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs font-mono focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-hidden"
                    />
                    <button
                      type="button"
                      onClick={handleTestBot}
                      disabled={testingBot}
                      className="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                      {testingBot ? (
                        <Loader2 size={14} className="animate-spin" />
                      ) : (
                        <CheckCircle2 size={14} />
                      )}
                      <span>Test Token</span>
                    </button>
                  </div>
                </div>

                {/* Default Group ID */}
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1.5">
                    Default Telegram Group ID (លេខសម្គាល់ Group លំនាំដើម)
                  </label>
                  <input
                    type="text"
                    value={settings.telegram_group_id}
                    onChange={(e) =>
                      setSettings({ ...settings, telegram_group_id: e.target.value })
                    }
                    placeholder="-100xxxxxxxxxx"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs font-mono focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-hidden"
                  />
                  <p className="text-[11px] text-gray-500 mt-1">
                    💡 របៀបរក Group ID: បន្ថែម <code>@RawDataBot</code> ឬ <code>@userinfobot</code> ចូលក្នុង Group របស់អ្នក នោះវានឹងប្រាប់ Chat ID (ឧទាហរណ៍៖ -100...)។
                  </p>
                </div>

                {/* URLs Configuration */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                  <div>
                    <label className="block text-[11px] font-bold text-gray-700 mb-1">
                      Mini App URL
                    </label>
                    <input
                      type="text"
                      value={settings.telegram_miniapp_url}
                      onChange={(e) =>
                        setSettings({ ...settings, telegram_miniapp_url: e.target.value })
                      }
                      className="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono outline-hidden"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-bold text-gray-700 mb-1">
                      Telegram Channel Link
                    </label>
                    <input
                      type="text"
                      value={settings.telegram_channel_url}
                      onChange={(e) =>
                        setSettings({ ...settings, telegram_channel_url: e.target.value })
                      }
                      className="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono outline-hidden"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-bold text-gray-700 mb-1">
                      Personal Support Link
                    </label>
                    <input
                      type="text"
                      value={settings.telegram_support_url}
                      onChange={(e) =>
                        setSettings({ ...settings, telegram_support_url: e.target.value })
                      }
                      className="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono outline-hidden"
                    />
                  </div>
                </div>

                <div className="flex justify-end pt-3">
                  <button
                    type="button"
                    onClick={() => handleSaveSettings()}
                    disabled={saving}
                    className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-md shadow-emerald-700/20 transition cursor-pointer disabled:opacity-50"
                  >
                    {saving ? (
                      <Loader2 size={16} className="animate-spin" />
                    ) : (
                      <CheckCircle2 size={16} />
                    )}
                    <span>រក្សាទុកការកំណត់</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Right: Webhook Management (5 cols) */}
            <div className="lg:col-span-5 space-y-6">
              <div className="bg-white rounded-2xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-5">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                  <div className="flex items-center gap-2">
                    <Radio size={18} className="text-emerald-700" />
                    <h3 className="font-bold text-gray-900 text-sm">
                      គ្រប់គ្រង Webhook (Auto-Responder Engine)
                    </h3>
                  </div>
                  <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full uppercase">
                    API 7.2+
                  </span>
                </div>

                <p className="text-xs text-gray-500 leading-relaxed">
                  Webhook ត្រូវប្រើដើម្បីឱ្យ Telegram បញ្ជូនសាររបស់អតិថិជន និងសារពី Telegram Business
                  មកកាន់ប្រព័ន្ធ Server របស់យើងដើម្បីឱ្យ Chatbot ឆ្លើយតបស្វ័យប្រវត្តិតាមពេលកំណត់។
                </p>

                {/* Webhook URL */}
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1.5">
                    Webhook URL Endpoint
                  </label>
                  <div className="flex gap-2">
                    <input
                      type="text"
                      value={settings.telegram_webhook_url}
                      onChange={(e) =>
                        setSettings({ ...settings, telegram_webhook_url: e.target.value })
                      }
                      className="flex-1 px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono outline-hidden"
                    />
                    <button
                      type="button"
                      onClick={() => handleCopy(settings.telegram_webhook_url, 'webhook')}
                      className="p-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 transition"
                      title="Copy URL"
                    >
                      {copiedKey === 'webhook' ? (
                        <Check size={16} className="text-emerald-600" />
                      ) : (
                        <Copy size={16} />
                      )}
                    </button>
                  </div>
                </div>

                {/* Webhook Actions */}
                <div className="flex flex-wrap items-center gap-2.5 pt-1">
                  <button
                    type="button"
                    onClick={handleSetWebhook}
                    disabled={webhookLoading}
                    className="flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                  >
                    {webhookLoading ? (
                      <Loader2 size={14} className="animate-spin" />
                    ) : (
                      <CheckCircle2 size={14} />
                    )}
                    <span>ដំឡើង Webhook ស្វ័យប្រវត្តិ</span>
                  </button>

                  <button
                    type="button"
                    onClick={handleCheckWebhookInfo}
                    disabled={webhookLoading}
                    className="py-2.5 px-3 rounded-xl border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold transition cursor-pointer"
                  >
                    Check Status
                  </button>

                  <button
                    type="button"
                    onClick={handleDeleteWebhook}
                    disabled={webhookLoading}
                    className="p-2.5 rounded-xl text-red-600 hover:bg-red-50 transition cursor-pointer"
                    title="Delete Webhook"
                  >
                    <Trash2 size={16} />
                  </button>
                </div>

                {/* Webhook Diagnostic Card */}
                {webhookInfo && (
                  <div className="bg-slate-900 text-slate-200 p-4 rounded-xl text-xs space-y-2 border border-slate-700 font-mono">
                    <div className="flex items-center justify-between text-[11px] font-bold pb-1 border-b border-slate-800 text-emerald-400">
                      <span>Webhook Status</span>
                      <span>{webhookInfo.has_custom_certificate ? 'Custom Cert' : 'HTTPS Standard'}</span>
                    </div>
                    <div>
                      <span className="text-slate-400">URL:</span> {webhookInfo.url || 'Not set'}
                    </div>
                    <div>
                      <span className="text-slate-400">Pending Updates:</span>{' '}
                      {webhookInfo.pending_update_count ?? 0}
                    </div>
                    {webhookInfo.last_error_message && (
                      <div className="text-red-400">
                        <span className="text-slate-400">Last Error:</span>{' '}
                        {webhookInfo.last_error_message}
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
