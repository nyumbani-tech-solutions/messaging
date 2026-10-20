# nyumbani/messaging

Queue SMS from any Nyumbani app through Nyumbani Connect: a message builder, one handler class per message type, and rate-limited, retried delivery. It requires only Laravel.

## Install

```
composer require nyumbani/messaging
```

```
NYUMBANI_CONNECT_BASE_URL=
NYUMBANI_CONNECT_TOKEN=
MESSAGING_SMS_SENDER_ID=NYUMBANI
```

Messages are queued, so run a queue worker (`php artisan queue:work` or Horizon).

## Send an SMS

```php
use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
use Nyumbani\Messaging\Message;
use Nyumbani\Messaging\MessageProcessed;

$message = Message::make()
    ->salutation($user->name)
    ->handler(new CheckInOtpMessageHandler($code));

MessageProcessed::dispatch(['sms' => [$message->toSMS($user)]], ['otp']);
```

The recipient is a model with a `phone_number`. Numbers become `254` plus the last nine digits, messages are grouped by sender ID, and Connect always receives a list of messages.

## Configuration

Publish `config/messaging.php` with `php artisan vendor:publish --tag=messaging-config`.

| Key | Environment | Purpose |
| --- | --- | --- |
| `rate_limit_per_second` | | Queued requests sent each second (4) |
| `connect.base_url` | `NYUMBANI_CONNECT_BASE_URL` | Address of Nyumbani Connect |
| `connect.access_token` | `NYUMBANI_CONNECT_TOKEN` | Bearer token |
| `connect.sms.enabled` | `MESSAGING_SMS_ENABLED` | Switch SMS off when false |
| `connect.sms.sender_id` | `MESSAGING_SMS_SENDER_ID` | Sender when a message has none |
| `connect.sms.uri` | | Connect path for SMS |

A failed request throws, so the queue retries it up to 25 times (60, 90, then 120 seconds apart) and logs `Message sending failed` when it gives up.

## Docs

With `nyumbani/essentials` installed, the docs are served locally at `/nyumbani-messaging/docs`.
