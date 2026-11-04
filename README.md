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

   To send email too, add the ZeptoMail settings. Email stays off until `MESSAGING_EMAIL_ENABLED` is true. The sender, reply-to, logo and organization name come from each message's scope (its `messageIdentity()`: `organizationName`, `emailFrom`, `emailReplyTo` and `logo`), so there is no sender to configure here, and an email whose sender or logo is missing throws:

```
MESSAGING_EMAIL_ENABLED=true
ZEPTOMAIL_API_TOKEN=
ZEPTOMAIL_TEMPLATE_KEY=
```

3. Optionally publish the configuration (see Configuration below):

```
php artisan vendor:publish --tag=messaging-config
```

4. Make sure messages can be sent. In an app with `nyumbani/essentials`, `php artisan nyumbani:install --with=messaging` does steps 1 to 3 for you.

Messages go through your queue. With `QUEUE_CONNECTION=sync` they are sent during the request and nothing else is needed; with any other connection, run a queue worker (`php artisan queue:work` or Horizon).

## Scopes

Every message belongs to a **scope**: the model that owns it, such as a project or an organisation. The scope decides which channels are on and who the message is from, so you define that once and every message of the project agrees. A message with no scope throws.

```php
use Illuminate\Database\Eloquent\Model;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;
use Nyumbani\Messaging\MessageIdentity;

class Project extends Model implements MessageScope
{
    public function isMessageChannelEnabled(MessageChannel $channel): bool
    {
        return match ($channel) {
            MessageChannel::SMS => (bool) $this->setting('notify_via_sms', true),
            MessageChannel::EMAIL => (bool) $this->setting('notify_via_email', false),
        };
    }

    public function messageIdentity(): MessageIdentity
    {
        return new MessageIdentity(
            organizationName: $this->name,
            header: $this->name,
            senderId: $this->setting('sms_sender_id'),
            logo: $this->avatar,
            emailFrom: ['address' => $this->email, 'name' => $this->name],
        );
    }
}
```

Every `MessageIdentity` argument is optional and named (`organizationName`, `header`, `footer`, `senderId`, `logo`, `emailFrom`, `emailReplyTo`), so a scope sets what it sends. For each value the message wins, then the scope, then the default, and the scope is read when the payload is built, so the order of calls does not matter. On any message setter `null` means *not set*; `withoutHeader()` and `withoutFooter()` send with none. An email needs an organization name, a sender and a logo, from the message or the scope, or it throws.

## Send an SMS

```php
use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;

message()
    ->scope($project)
    ->recipient($user)
    ->salutation()
    ->handler(new CheckInOtpMessageHandler($code))
    ->senderId('ACME')
    ->send('otp');
```

`message()` is the same as `Message::make()`, and `$project` is the model that owns the message, which implements `MessageScope`: every message needs a scope, and one without it throws. `send()` takes one tag or an array of tags and returns the message.

The recipient is a model with a `phone_number`. Numbers become `254` plus the last nine digits, messages are grouped by sender ID, and Connect always receives a list of messages.

## Handlers

A handler owns the wording of one message type: it takes its inputs in the constructor and writes the body in `handle()`. A protected method of the handler builds a variant, chosen with `handler($handler, 'reminder')`; any other method name throws an `InvalidArgumentException`. See the Message handlers docs page.

## Categories

`category('payment-receipt')` sets the label Nyumbani Connect files the SMS under, so its reports group messages by type. Set it in a handler's `handle()` so every use of the message shares it. It can be at most 100 characters (a longer one throws a `LogicException`), it is sent exactly as given, and email ignores it.

## Many recipients

`sendTo()` sends a copy of one message to each recipient, with one event for every 500 of them (`messaging.chunk_size`), so the gateways batch the requests. It accepts any iterable, including `cursor()` and `lazy()`.

```php
message()->scope($project)->text('Rent is due Friday.')->salutation()->sendTo($tenants, 'rent');
```

`salutation()` opens each copy with that recipient's name, so every tenant gets their own `Dear Jane, `. Leaving it out sends no salutation. For content that differs per recipient, pass a callback that receives the copy and its recipient. It runs after the shared content, so what it adds goes at the end of the body, and returning `false` skips the recipient:

```php
use App\Models\Tenant;
use App\Services\Messaging\Handlers\RentReminderHandler;
use Nyumbani\Messaging\Message;

message()
    ->scope($project)
    ->salutation()
    ->sendTo($tenants, 'rent', fn (Message $message, Tenant $tenant) => $message
        ->handler(new RentReminderHandler($tenant)));
```

Tags apply to the whole dispatch, so they are the second argument of `sendTo()` and not per recipient. `$messenger->message()` starts a message scoped to the project, with no recipient yet, for `sendTo()`. Load whatever the callback reads before calling `sendTo()` to avoid a query per recipient. To build the items yourself, dispatch them together: `MessageProcessed::dispatch(['sms' => $items], $tags)`.

## Send an email

```php
message()
    ->scope($project)
    ->recipient($user)
    ->from('billing@acme.example', 'Acme Homes')
    ->logo('https://acme.example/logo.png')
    ->salutation()
    ->text('Your rent is due on Friday.')
    ->subject('Rent reminder')
    ->send('rent');
```

A message with a `subject()` goes out by SMS and email. `via(['sms', 'email'])` chooses the channels instead (`via('email')` is email only), and an email without a subject throws. The channel switches still apply: email stays off until `MESSAGING_EMAIL_ENABLED` is on.

The recipient is a model with an `email` and a `name`. Branding is set on each message (`from()`, `replyTo()`, `logo()`, `organization()`), and whatever it leaves out comes from its scope. An email needs a sender and a logo, from the message or the scope, or it throws; replies go to the sender unless a reply-to is set. `emailOptions(['template_key' => '...'])` uses another ZeptoMail template than the configured one. The body is shared with the SMS unless you give the email its own with `emailBody()` or a handler's `email()` method and `emailHandler()`. Emails are grouped by sender and template and sent 50 to a request. See the Sending email docs page.

## The channel switches

A channel goes out only when it is on globally (`MESSAGING_SMS_ENABLED`, `MESSAGING_EMAIL_ENABLED`) and for the scope. `Messenger::for($project)` asks both (`canSend()`, `smsEnabled()`, `emailEnabled()`) and starts scoped messages with `recipient($model)` and `message()`. `send()` and `sendTo()` check each channel against the scope in the request and leave out the ones that are off; an event you dispatch yourself is checked by the listener, which needs every item to carry its `scope_type` and `scope_id`. See the channel switches docs page.

## Configuration

Publish `config/messaging.php` with `php artisan vendor:publish --tag=messaging-config`.

| Key | Environment | Purpose |
| --- | --- | --- |
| `event` | | The event `send()` dispatches and the package listens to (`MessageProcessed`); a custom class must extend it |
| `chunk_size` | | Most recipients `sendTo()` puts in one event (500) |
| `rate_limit_per_second` | | Queued requests sent each second (4) |
| `connect.base_url` | `NYUMBANI_CONNECT_BASE_URL` | Address of Nyumbani Connect |
| `connect.access_token` | `NYUMBANI_CONNECT_TOKEN` | Bearer token |
| `connect.sms.enabled` | `MESSAGING_SMS_ENABLED` | Switch SMS off when false |
| `connect.sms.sender_id` | `MESSAGING_SMS_SENDER_ID` | Sender when a message has none; an SMS with no sender anywhere throws |
| `connect.sms.uri` | | Connect path for SMS |
| `email.enabled` | `MESSAGING_EMAIL_ENABLED` | Switch email on (off by default) |
| `email.zeptomail.uri` | | ZeptoMail template batch endpoint |
| `email.zeptomail.api_token` | `ZEPTOMAIL_API_TOKEN` | Send Mail token (the `Zoho-enczapikey` prefix is added when missing) |
| `email.zeptomail.template_key` | `ZEPTOMAIL_TEMPLATE_KEY` | Template used when a message names none |

There is no `driver` key: SMS always uses Nyumbani Connect and email always uses ZeptoMail.

A failed request throws, so the queue tries again: a job is allowed three failures (about 60 seconds, then 90, between them) and then logs `Message sending failed`. Waiting for the rate limit is not a failure.

## Docs

The package suggests `nyumbani/docs` (install it with `composer require --dev nyumbani/docs`), which then serves the docs locally at `/nyumbani-messaging/docs`, in any Laravel app. The package works without it.
