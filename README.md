# nyumbani/messaging

Queue SMS and email from any Nyumbani app: a message builder, one handler class per message type, and rate-limited, retried delivery. SMS goes through Nyumbani Connect and email through ZeptoMail; each channel has one fixed driver that an app cannot replace. It requires only Laravel.

## Requirements

- PHP 8.3 or newer and Laravel 12 or 13
- An access token for Nyumbani Connect
- For email, a ZeptoMail Send Mail token, a template and a verified sender domain

## Install

1. Require it:

```
composer require nyumbani/messaging
```

2. Set the Connect address and token in `.env`:

```
NYUMBANI_CONNECT_BASE_URL=
NYUMBANI_CONNECT_TOKEN=
MESSAGING_SMS_SENDER_ID=NYUMBANI
```

   To send email too, add the ZeptoMail settings. Email stays off until `MESSAGING_EMAIL_ENABLED` is true:

```
MESSAGING_EMAIL_ENABLED=true
MESSAGING_EMAIL_FROM_ADDRESS=
MESSAGING_EMAIL_FROM_NAME=
ZEPTOMAIL_API_TOKEN=
ZEPTOMAIL_TEMPLATE_KEY=
```

3. Optionally publish the configuration (see Configuration below):

```
php artisan vendor:publish --tag=messaging-config
```

4. Make sure messages can be sent. In an app with `nyumbani/essentials`, `php artisan nyumbani:install --with=messaging` does steps 1 to 3 for you.

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

## Send an email

```php
use Nyumbani\Messaging\Events\MessageProcessed;
use Nyumbani\Messaging\Message;

$email = Message::make()
    ->recipient($user)
    ->from('billing@acme.example', 'Acme Homes')
    ->logo('https://acme.example/logo.png')
    ->salutation()
    ->text('Your rent is due on Friday.')
    ->toEmail('Rent reminder');

MessageProcessed::dispatch(['email' => [$email]], ['rent']);
```

The recipient is a model with an `email` and a `name`. Branding is set on each message (`from()`, `replyTo()`, `logo()`, `organization()`), and whatever it leaves out comes from `messaging.email`. `emailOptions(['template_key' => '...'])` uses another ZeptoMail template than the configured one. The body is shared with the SMS unless you give the email its own with `emailBody()` or a handler's `email()` method and `emailHandler()`. Emails are grouped by sender and template and sent 50 to a request. See the Sending email docs page.

## Handlers

A handler owns the wording of one message type: it takes its inputs in the constructor and writes the body in `handle()`. A protected method of the handler builds a variant, chosen with `handler($handler, 'reminder')`; any other method name throws an `InvalidArgumentException`. See the Sending SMS with handlers docs page.

## Messenger

`Messenger::for($project)` holds the channel switches (the scope is required and must implement `MessageScope`, which also supplies `messageHeader()`, `messageFooter()` and `messageSenderId()`, each `?string`; use `Messenger::global()` when there is none) and starts messages: `$messenger->recipient($admin)->salutation()->handler(...)->toSMS('ACME')`. Implement `Nyumbani\Messaging\Contracts\MessageScope` on the project to switch a channel off for it. `canSend()` and `emailEnabled()` answer early, and the listener drops messages whose scope has the channel off. See the Messenger docs page.

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
| `email.enabled` | `MESSAGING_EMAIL_ENABLED` | Switch email on (off by default) |
| `email.from.address`, `email.from.name` | `MESSAGING_EMAIL_FROM_ADDRESS`, `MESSAGING_EMAIL_FROM_NAME` | Sender when a message has none |
| `email.reply_to.address`, `email.reply_to.name` | `MESSAGING_EMAIL_REPLY_TO_ADDRESS`, `MESSAGING_EMAIL_REPLY_TO_NAME` | Reply-to when a message has none (the sender if unset) |
| `email.logo` | `MESSAGING_EMAIL_LOGO` | Logo when a message has none |
| `email.organization_name` | `MESSAGING_EMAIL_ORGANIZATION_NAME` | Organization name when a message has none (the app name if unset) |
| `email.zeptomail.uri` | | ZeptoMail template batch endpoint |
| `email.zeptomail.api_token` | `ZEPTOMAIL_API_TOKEN` | Send Mail token (the `Zoho-enczapikey` prefix is added when missing) |
| `email.zeptomail.template_key` | `ZEPTOMAIL_TEMPLATE_KEY` | Template used when a message names none |

There is no `driver` key: SMS always uses Nyumbani Connect and email always uses ZeptoMail.

A failed request throws, so the queue tries again: a job is allowed three failures (about 60 seconds, then 90, between them) and then logs `Message sending failed`. Waiting for the rate limit is not a failure.

## Docs

The package suggests `nyumbani/docs` (install it with `composer require --dev nyumbani/docs`), which then serves the docs locally at `/nyumbani-messaging/docs`, in any Laravel app. The package works without it.
