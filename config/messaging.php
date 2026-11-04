<?php

use Nyumbani\Messaging\Events\MessageProcessed;

return [
    /*
    |--------------------------------------------------------------------------
    | Event
    |--------------------------------------------------------------------------
    |
    | The event Message::send() dispatches, and the one the package listens to
    | in order to deliver messages. Use your own class only when it extends
    | MessageProcessed.
    |
    */
    'event' => MessageProcessed::class,

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
    | Chunk size
    |--------------------------------------------------------------------------
    |
    | The most recipients Message::sendTo() puts in one event.
    |
    */
    'chunk_size' => 500,

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
    | Who the email is from (organization name, sender, reply-to and logo) comes
    | from the message's scope, and a message may override it. A message may
    | also set its own template key: the value here fills in what it leaves out.
    |
    */
    'email' => [
        'enabled' => env('MESSAGING_EMAIL_ENABLED', false),
        'zeptomail' => [
            'uri' => 'https://api.zeptomail.com/v1.1/email/template/batch',
            'api_token' => env('ZEPTOMAIL_API_TOKEN'),
            'template_key' => env('ZEPTOMAIL_TEMPLATE_KEY'),
        ],
    ],
];
