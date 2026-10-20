<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate limit
    |--------------------------------------------------------------------------
    |
    | The most queued requests that go out each second.
    |
    */
    'rate_limit_per_second' => 4,

    /*
    |--------------------------------------------------------------------------
    | Nyumbani Connect
    |--------------------------------------------------------------------------
    |
    | Every message goes out through Nyumbani Connect. The sms keys switch SMS
    | on or off, set the sender used when a message has none, and name the
    | Connect path messages are posted to.
    |
    */
    'connect' => [
        'base_url' => env('NYUMBANI_CONNECT_BASE_URL'),
        'access_token' => env('NYUMBANI_CONNECT_TOKEN'),
        'sms' => [
            'enabled' => env('MESSAGING_SMS_ENABLED', true),
            'sender_id' => env('MESSAGING_SMS_SENDER_ID', 'NYUMBANI'),
            'uri' => 'api/sms/send',
        ],
    ],
];
