<?php

return [
    'version' => env('APP_VERSION', '0.1.0'),

    'default_timezone' => env('USANDUS_DEFAULT_TIMEZONE', 'Asia/Jakarta'),

    'invitations' => [
        'ttl_hours' => 48,
        'length' => 8,
    ],

    // End-to-end encryption: content is locked in the browser with a key that only the couple's
    // devices hold, so the database and the files on the server only contain scrambled data.
    // Needs HTTPS (or localhost): browsers only offer WebCrypto on a secure page.
    'e2e' => [
        'enabled' => (bool) env('E2E_ENABLED', true),
    ],

    // Linked sign-in: phone one-time codes (see App\Services\Auth\OtpService for the drivers). Nothing paid is on by default.
    'auth' => [
        'otp' => [
            'driver' => env('OTP_DRIVER', 'log'),
            'webhook_url' => env('OTP_WEBHOOK_URL'),
            'webhook_token' => env('OTP_WEBHOOK_TOKEN'),
            'fonnte_token' => env('OTP_FONNTE_TOKEN'),
            'length' => 6,
            'ttl_minutes' => 5,
            'cooldown_seconds' => 60,
            'per_hour' => 5,
            'max_attempts' => 5,
        ],
    ],

    'chat' => [
        'max_length' => 2000,
        'page_size' => 30,
        // Own messages can be edited for this long; deleting is always allowed.
        'edit_window_minutes' => 15,
        'max_pinned' => 5,
        // Built-in stickers: key => [emoji, label]. Sent as a key, never as an uploaded file.
        'stickers' => [
            'sayang' => ['🥰', 'Sayang'],
            'peluk' => ['🤗', 'Peluk'],
            'cium' => ['😘', 'Cium'],
            'kangen' => ['🥺', 'Kangen'],
            'hati' => ['💖', 'Cinta'],
            'bunga' => ['🌷', 'Bunga'],
            'semangat' => ['💪', 'Semangat'],
            'selamat' => ['🎉', 'Selamat'],
            'lapar' => ['🍜', 'Lapar'],
            'tidur' => ['😴', 'Ngantuk'],
            'ketawa' => ['😂', 'Ngakak'],
            'maaf' => ['🙏', 'Maaf'],
        ],
    ],

    // Live chat through Reverb. Needs `php artisan reverb:start`; without it the
    // chat keeps working through polling.
    'realtime' => [
        'enabled' => env('BROADCAST_CONNECTION') === 'reverb' && env('REVERB_APP_KEY') !== null && env('REVERB_APP_KEY') !== '',
        'key' => env('REVERB_APP_KEY'),
        'port' => (int) env('REVERB_SERVER_PORT', 8080),
        'scheme' => env('REVERB_CLIENT_SCHEME', 'http'),
    ],

    // Optional: AI-written quiz questions. Leave the key empty to turn it off.
    'ai_provider' => env('AI_PROVIDER'), // groq | gemini; empty picks whichever has a key

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'endpoint' => env('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    // The Android app (APK). Raise version_code and replace storage/app/apk/LoveyNest.apk together:
    // the app compares its own version code with this one and offers the update.
    // WebRTC (calls in chat, voice in Arena). STUN is enough on most Wi-Fi; on many mobile networks a TURN relay is
    // needed for the call to connect. Set TURN_URL (e.g. turn:turn.example.com:3478), TURN_USERNAME, TURN_CREDENTIAL.
    // TURN relay providers for calls and Arena voice (read by App\Support\IceServers; see .env.example).
    'turn' => [
        'cloudflare_key_id' => env('CLOUDFLARE_TURN_KEY_ID'),
        'cloudflare_token' => env('CLOUDFLARE_TURN_API_TOKEN'),
        'metered_domain' => env('METERED_TURN_DOMAIN'),
        'metered_key' => env('METERED_TURN_API_KEY'),
        'url' => env('TURN_URL'),
        'username' => env('TURN_USERNAME'),
        'credential' => env('TURN_CREDENTIAL'),
    ],

    'ice' => array_values(array_filter([
        ['urls' => ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302']],
        env('TURN_URL') ? ['urls' => array_map('trim', explode(',', (string) env('TURN_URL'))), 'username' => (string) env('TURN_USERNAME', ''), 'credential' => (string) env('TURN_CREDENTIAL', '')] : null,
    ])),

    'android' => [
        'version_code' => 9,
        'version_name' => '2.2',
        'notes' => 'Versi 2.2: tampilan lebih halus (tanpa kedip), pilihan nada notifikasi, dan isi pesan tampil di notifikasi (terbuka hanya di HP). Ada telepon, Arena baru, photobooth v2, dan notifikasi latar belakang.',
        'file' => 'apk/LoveyNest.apk',
        // Package name of the Android app 2.x (WebView of the live site). Google sign-in hands the session back to it.
        'package' => 'id.my.loveynest.nest',
    ],

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@usandus.local'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'notifications' => [
        // Defaults when the user has not changed a preference. Push only ever
        // reaches devices where the user pressed "Aktifkan notifikasi".
        'defaults' => [
            // The chat badge already counts unread messages, so chat is push-only.
            'chat_message' => ['in_app' => false, 'push' => true, 'show_preview' => true],
            'event_reminder' => ['in_app' => true, 'push' => true, 'show_preview' => true],
            // The brief says important-task reminders are pushed only when switched on.
            'task_reminder' => ['in_app' => true, 'push' => false, 'show_preview' => true],
            'partner_activity' => ['in_app' => true, 'push' => false, 'show_preview' => true],
            'couple_update' => ['in_app' => true, 'push' => true, 'show_preview' => true],
        ],
        // At most one chat push per recipient in this window; later ones fold into it.
        'chat_push_cooldown_seconds' => 4,
    ],

    'push' => [
        // The server POSTs to subscription endpoints, so only real push
        // services are accepted (prevents using it to reach LAN addresses).
        'allowed_hosts' => array_filter(explode(',', env('PUSH_ALLOWED_HOSTS', implode(',', [
            'fcm.googleapis.com',
            'updates.push.services.mozilla.com',
            '*.push.apple.com',
            '*.notify.windows.com',
        ])))),
        'max_failures' => 5,
        'ttl' => [
            'chat_message' => 3600,
            'default' => 6 * 3600,
        ],
    ],

    'reminders' => [
        'batch' => 200,
        // After a long outage, reminders older than this are skipped instead of flooding.
        'stale_after_minutes' => 360,
        'stuck_after_minutes' => 10,
        'max_attempts' => 3,
    ],

    'backup' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL_PATH', 'mysql'),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'path' => storage_path('app/private/backups'),
    ],

    // Cards on the home screen that can be hidden or reordered (key => label key in the UI).
    'dashboard_widgets' => [
        'quote' => 'Kutipan hari ini',
        'nudge' => 'Kirim rasa',
        'spaces' => 'My Space dan Our Space',
        'question' => 'Pertanyaan harian',
        'memories' => 'Kenangan',
        'today' => 'Tugas hari ini',
        'events' => 'Acara berikutnya',
    ],
];
