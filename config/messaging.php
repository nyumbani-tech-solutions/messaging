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

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    |
    | Email goes out through ZeptoMail and a template you made there; an app
    | cannot choose another provider. The switch is off until you turn it on.
    | A message may set its own sender, reply-to, logo, organization name and
    | template key: the values here fill in what it leaves out.
    |
    */
    'email' => [
        'enabled' => env('MESSAGING_EMAIL_ENABLED', false),
        'from' => [
            'address' => env('MESSAGING_EMAIL_FROM_ADDRESS'),
            'name' => env('MESSAGING_EMAIL_FROM_NAME'),
        ],
        'reply_to' => [
            'address' => env('MESSAGING_EMAIL_REPLY_TO_ADDRESS'),
            'name' => env('MESSAGING_EMAIL_REPLY_TO_NAME'),
        ],
        'logo' => env('MESSAGING_EMAIL_LOGO'),
        'organization_name' => env('MESSAGING_EMAIL_ORGANIZATION_NAME'),
        'zeptomail' => [
            'uri' => 'https://api.zeptomail.com/v1.1/email/template/batch',
            'api_token' => env('ZEPTOMAIL_API_TOKEN'),
            'template_key' => env('ZEPTOMAIL_TEMPLATE_KEY'),
        ],
    ],
];
