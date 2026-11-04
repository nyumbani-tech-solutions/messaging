---
name: nyumbani-messaging
description: "Invoke when a Nyumbani app sends an SMS or an email: building the text, a message handler, sending with `message()->...->send()`, the sender ID, email branding and template, the Nyumbani Connect or ZeptoMail settings, or a message that is not delivered. Skip for WhatsApp and Daraja callbacks."
metadata:
  author: nyumbani
---

# Nyumbani Messaging

SMS goes out through Nyumbani Connect and email through ZeptoMail. Do not call a provider such as Bunicom or ZeptoMail, or `Http`, from an app, and do not look for a way to choose a provider: each channel has one fixed driver inside the package, with no `driver` config key, and an app cannot replace it. SMS and email exist; WhatsApp is not available yet.

## Flow
1. Start with `message()` (the same as `Message::make()`), or `$messenger->recipient($model)` when a project can switch SMS off.
2. Set the recipient with `->recipient($model)`, build the text (a handler, `text()`, `action()`), and set `->senderId('ACME')` when the default sender is wrong.
3. Finish with `->send($tags)`. `$tags` is one tag or an array, and `send()` returns the message.

```php
message()->recipient($user)->salutation()->handler(new CheckInOtpMessageHandler($code))->senderId('ACME')->send('otp');
```

`send()` dispatches the event named in `messaging.event` (`MessageProcessed` by default; a custom class must extend it, and the package listens to whichever is configured). Its payload is keyed by channel. SMS always goes out; a message with a `subject()` also goes out by email; `via(['sms', 'email'])` chooses the channels instead (`via('email')` is email only), and an email without a subject throws a `LogicException`.

The package listener queues the SMS through `SmsGateway`, which cleans the numbers, groups them by `sender_id`, chunks them by 500 and queues one `SendMessageJob` per chunk. Email follows the same flow through `EmailGateway` (see Email below).

### Many recipients
Use `sendTo($recipients, $tags = [], $each = null)`, not a loop of `send()`, which queues one job per message. It sends a copy to each recipient with one event per `messaging.chunk_size` (500), and accepts `cursor()` and `lazy()`.

```php
message()->text('Rent is due Friday.')->salutation()->sendTo($tenants, 'rent');

message()->salutation()->sendTo($tenants, 'rent', fn (Message $message, Tenant $tenant) => $message->handler(new RentReminderHandler($tenant)));
```

`salutation()` opens each copy with that recipient's name. The callback gets the copy and its recipient, runs after the shared content (so what it adds goes at the end of the body), and returns `false` to skip the recipient. Tags are for the whole dispatch, so they are given to `sendTo()`. Eager load whatever the callback reads. `$messenger->message()` starts a framed message with no recipient for `sendTo()`. To build the items yourself, `MessageProcessed::dispatch(['sms' => $items], $tags)` still works.

## Gateways and drivers
A channel is a gateway extending the abstract `Gateway`, plus a fixed driver. The gateway implements `normalise()` (clean one message, or return null to drop it) and `driver()`; the driver implements `batches()`, `format()` and `deliver()` (throw on failure). `SmsGateway` uses `ConnectSmsDriver` and `EmailGateway` uses `ZeptoMailEmailDriver`. The drivers are `final`, created by the gateways, and neither read from config nor bound in the container.

Connect always receives a list of messages, even for one; `ConnectSmsDriver::deliver()` throws on an empty or non-list payload. Never post a single message object.

## Email
Build it on the same `Message`, set `->subject($subject)` and `->send()`. The recipient needs an `email` and a `name`. For the batch path finish with `->toEmail($subject)` and dispatch it under `email`.

```php
message()
    ->recipient($tenant)
    ->from('billing@acme.example', 'Acme Homes')   // optional, else messaging.email.from
    ->replyTo('help@acme.example')                 // optional, else messaging.email.reply_to, then the sender
    ->logo('https://acme.example/logo.png')        // optional, else messaging.email.logo
    ->organization('Acme Homes')                   // optional, else messaging.email.organization_name, then the app name
    ->emailOptions(['template_key' => '...'])      // optional, else ZEPTOMAIL_TEMPLATE_KEY
    ->salutation()
    ->text('Invoice INV-1042 is ready.')->newLine()
    ->action('https://acme.example/i/1042', 'View invoice:')
    ->subject('Your invoice')
    ->via('email')
    ->send('invoice');
```

- Branding is per message, so emails for different organisations can share one dispatch. The gateway fills what a message leaves out from `messaging.email`, and the driver groups by sender, reply-to and template key, 50 recipients to a request.
- The body is the shared one by default. The package turns it into the template's `lines`: a blank line is a break, a line with a link is a link labelled by the words before it (`Click Here` when there are none), the rest is text. For a different email body use `emailBody($text)`, or an `email()` method on the handler and `->handler($handler)->emailHandler($handler)`; a handler without `email()` keeps the shared body.
- The ZeptoMail template must show `subject`, `logo`, `organization_name`, `header`, `salutation`, `footer` and `lines`.
- Dropped silently: an invalid address, and email switched off. Thrown as a `LogicException` on dispatch: no sender anywhere, or no template key anywhere.

## Message handlers
A message type is a class that extends `MessageHandler` and implements `handle()`. Its inputs go in the constructor, and it fills `$this->message` with `text()`, `newLine()`, `action()`, `prepend()`, `append()` and `when()`. Keep it in the app, for example `app/Services/Messaging/Handlers`, and attach it with `Message::make()->handler(new ThingMessageHandler(...))`. To build a variant, add a protected method with no required arguments and pass its name as the second argument, `handler($handler, 'reminder')`. Any other method name throws an `InvalidArgumentException`.

```php
use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;

message()->recipient($user)->salutation()->handler(new CheckInOtpMessageHandler($code))->senderId('ACME')->send('otp');
```

For a user named Jane and the code 1234, the text sent is `Dear Jane, Your code is 1234.` (`salutation()` includes a salutation to the recipient's name; not calling it sends none, and `salutation(false)` removes it).

## Payload attributes
`recipient($model)` takes a model with a `phone_number`, and `toSMS($senderId = null)` returns the item: `scope_type`, `scope_id`, `client_type`, `client_id`, `sender_id`, `number`, `text` and `meta_data`. The recipient is the client, so Connect can trace the message to a record. A message has no scope (`scope_type` and `scope_id` are null) unless `scope($model)`, which must be a `MessageScope`, or a messenger sets one. Add extra meta data with `metaData([...])`; `app_url` is always sent. `toSMS()` and `toEmail()` throw a `LogicException` without a recipient.

## Messenger: switching a channel off per scope
`Messenger::for($project)` holds the channel switches and takes the header, footer and sender ID from the scope's `messageHeader()`, `messageFooter()` and `messageSenderId()` (each `?string`, null for none or the default sender, worked out once per messenger). The sender sent is the `toSMS('ACME')` argument, else the scope's, else the config default. The scope is required and must be a model implementing `MessageScope`, otherwise a `TypeError`; `Messenger::global()` is for no scope and starts messages with the scope, header and footer set: `$messenger->recipient($admin)->salutation()->handler(...)->toSMS('ACME')`. `salutation()` with no argument uses the recipient's `name`; it is never added on its own.

Implement `Nyumbani\Messaging\Contracts\MessageScope` (`isMessageChannelEnabled(MessageChannel $channel)`) on the model that owns the setting, such as a project. Ask `$messenger->canSend()`, `->smsEnabled()` or `->emailEnabled()` before building messages. The gate is the channel's global switch (`messaging.connect.sms.enabled`, `messaging.email.enabled`) and the scope's answer; a message scoped to a model that does not implement the interface (its own recipient, say) is never blocked by it. The listener drops messages whose scope has the channel off (one query per scope type), so start messages from the messenger and the project's switch is always the one that counts.

## Settings
Require `nyumbani/messaging`, then set `NYUMBANI_CONNECT_BASE_URL`, `NYUMBANI_CONNECT_TOKEN` and, optionally, `MESSAGING_SMS_SENDER_ID` in `.env`. The rest lives in `messaging.connect.sms`: `enabled`, `sender_id` and `uri`. `messaging.rate_limit_per_second` caps the speed. Publish the file with the `messaging-config` tag.

Email is off until `MESSAGING_EMAIL_ENABLED=true`. Set `ZEPTOMAIL_API_TOKEN` (the `Zoho-enczapikey` prefix is added when missing), `ZEPTOMAIL_TEMPLATE_KEY` and `MESSAGING_EMAIL_FROM_ADDRESS` (a domain verified in ZeptoMail), and optionally `MESSAGING_EMAIL_FROM_NAME`, `MESSAGING_EMAIL_REPLY_TO_ADDRESS`, `MESSAGING_EMAIL_LOGO` and `MESSAGING_EMAIL_ORGANIZATION_NAME`. They live in `messaging.email`; the ZeptoMail token, template and `uri` are in `messaging.email.zeptomail`.

## When nothing arrives
- On the `sync` queue the SMS is sent during the request and no worker is needed (a Connect failure then throws to the caller and is not retried). On any other queue connection a worker must be running.
- A number under nine digits is dropped silently.
- `MESSAGING_SMS_ENABLED=false` skips every SMS, and `MESSAGING_EMAIL_ENABLED` (off by default) gates every email.
- An email with an invalid address is dropped silently. ZeptoMail refuses a sender from an unverified domain or a wrong token (a 401), and the job fails like an SMS does.
- A failing Connect request is tried again after about 60 and 90 seconds, and after the third failure the job fails and logs `Message sending failed`. Check the failed jobs table.

## Testing
Call `Queue::fake()`, `send()` the message (or dispatch `MessageProcessed`), and assert `SendMessageJob` was pushed (`$job->payload[0]['recipient']` is the cleaned `254` number for SMS; for email `$job->payload['to'][0]['email_address']` and `$job->payload['template_key']`). To test delivery, use `Http::fake(['*' => ...])` and call `handle()` on the job. Never contact Connect or ZeptoMail from the suite.
