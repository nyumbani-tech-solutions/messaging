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

Messages go through your queue. With `QUEUE_CONNECTION=sync` they are sent during the request and nothing else is needed; with any other connection, run a queue worker (`php artisan queue:work` or Horizon).

## Send an SMS

```php
use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
use Nyumbani\Messaging\Message;
use Nyumbani\Messaging\Events\MessageProcessed;

$sms = Message::make()
    ->recipient($user)
    ->salutation()
    ->handler(new CheckInOtpMessageHandler($code))
    ->toSMS('ACME');

MessageProcessed::dispatch(['sms' => [$sms]], ['otp']);
```

The recipient is a model with a `phone_number`. Numbers become `254` plus the last nine digits, messages are grouped by sender ID, and Connect always receives a list of messages.

## Handlers

A handler owns the wording of one message type: it takes its inputs in the constructor and writes the body in `handle()`. A protected method of the handler builds a variant, chosen with `handler($handler, 'reminder')`; any other method name throws an `InvalidArgumentException`. See the Sending SMS with handlers docs page.

## Messenger

`Messenger::for($project)` holds the channel switches (the scope is required and must implement `MessageScope`, which also supplies `messageHeader()`, `messageFooter()` and `messageSenderId()`, each `?string`; use `Messenger::global()` when there is none) and starts messages: `$messenger->recipient($admin)->salutation()->handler(...)->toSMS('ACME')`. Implement `Nyumbani\Messaging\Contracts\MessageScope` on the project to switch SMS off for it. `canSend()` answers early, and the listener drops messages whose scope has the channel off. See the Messenger docs page.

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

A failed request throws, so the queue tries again: a job is allowed three failures (about 60 seconds, then 90, between them) and then logs `Message sending failed`. Waiting for the rate limit is not a failure.

## Docs

With `nyumbani/docs` installed (Essentials requires it), the docs are served locally at `/nyumbani-messaging/docs`, in any Laravel app.
