<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PMO Timezone
    |--------------------------------------------------------------------------
    |
    | Used for business-date calculations such as "overdue" detection.
    |
    */

    'timezone' => env('PMO_TIMEZONE', 'Asia/Kuala_Lumpur'),

    /*
    |--------------------------------------------------------------------------
    | Team Roster (Module 01)
    |--------------------------------------------------------------------------
    |
    | A PIC is flagged as "Overloaded" when their open task count reaches
    | this threshold.
    |
    */

    'roster' => [
        'overload_threshold' => (int) env('PMO_PIC_OVERLOAD_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Automation (Module 05)
    |--------------------------------------------------------------------------
    */

    'standup_time' => env('PMO_STANDUP_TIME', '08:30'),

    'overdue_check_time' => env('PMO_OVERDUE_CHECK_TIME', '09:00'),

    /*
    |--------------------------------------------------------------------------
    | AI PMO Assistant (Module 05)
    |--------------------------------------------------------------------------
    |
    | provider: "claude" (Anthropic SDK), "openai" or "custom" (OpenAI-compatible
    | chat-completions endpoint). While the key (or, for "custom", the endpoint)
    | is empty the assistant runs in Smart Simulation mode — no API calls, no
    | charges — and every run is logged as "simulated".
    |
    */

    'ai' => [
        'provider' => env('PMO_AI_PROVIDER', 'claude'),
        'model' => env('PMO_AI_MODEL', 'claude-sonnet-5-5'),
        'api_key' => env('PMO_AI_API_KEY'),
        'custom_endpoint' => env('PMO_AI_CUSTOM_ENDPOINT'),
        'effort' => env('PMO_AI_EFFORT', 'medium'),
        'max_tokens' => (int) env('PMO_AI_MAX_TOKENS', 16000),
        'timeout' => (int) env('PMO_AI_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Omnichannel Notifications (Module 05)
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'enabled' => (bool) env('PMO_TELEGRAM_ENABLED', false),
        'bot_token' => env('PMO_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('PMO_TELEGRAM_CHAT_ID'),
    ],

    'email' => [
        'enabled' => (bool) env('PMO_EMAIL_ENABLED', false),
        'recipient' => env('PMO_EMAIL_RECIPIENT'),
    ],

    'whatsapp' => [
        'enabled' => (bool) env('PMO_WHATSAPP_ENABLED', false),
        'gateway_url' => env('PMO_WHATSAPP_GATEWAY_URL'),
        'api_token' => env('PMO_WHATSAPP_API_TOKEN'),
        'default_phone' => env('PMO_WHATSAPP_DEFAULT_PHONE'),
    ],

];
