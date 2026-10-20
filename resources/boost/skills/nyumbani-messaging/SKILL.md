---
name: nyumbani-messaging
description: "Invoke when a Nyumbani app sends an SMS: building the text, a message handler, dispatching MessageProcessed, the sender ID, the Nyumbani Connect settings, or a message that is not delivered. Skip for email, WhatsApp and Daraja callbacks."
metadata:
  author: nyumbani
---

# Nyumbani Messaging

Every message goes out through Nyumbani Connect. Do not call a provider such as Bunicom, or `Http`, from an app. Only SMS exists for now; email and WhatsApp are not available yet.

## Flow
1. Build the text with `Nyumbani\Messaging\Message`.
2. Start with `->recipient($model)` (or `$messenger->recipient($model)`), and finish with `->toSMS($senderId)` right after the handler (or the last body call). Put the result in a payload: `['sms' => [$sms]]`.
3. Dispatch `MessageProcessed::dispatch($payload, $tags)`.

The package listener queues the SMS through `SmsGateway`, which cleans the numbers, groups them by `sender_id`, chunks them by 500 and queues one `SendMessageJob` per chunk.

## Gateways
A channel is a class extending the abstract `Gateway`, which cleans, batches and queues the messages. It implements `normalise()`, `format()` and `deliver()` (throw on failure), and may override `batches()`. `SmsGateway` is the only one today.

Connect always receives a list of messages, even for one; `SmsGateway::deliver()` throws on an empty or non-list payload. Never post a single message object.

## Message handlers
A message type is a class that extends `MessageHandler` and implements `handle()`. Its inputs go in the constructor, and it fills `$this->message` with `text()`, `newLine()`, `action()`, `prepend()`, `append()` and `when()`. Keep it in the app, for example `app/Services/Messaging/Handlers`, and attach it with `Message::make()->handler(new ThingMessageHandler(...))`. To build a variant, add a protected method with no required arguments and pass its name as the second argument, `handler($handler, 'reminder')`. Any other method name throws an `InvalidArgumentException`.

```php
use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
use Nyumbani\Messaging\Message;
use Nyumbani\Messaging\Events\MessageProcessed;

$sms = Message::make()->recipient($user)->salutation()->handler(new CheckInOtpMessageHandler($code))->toSMS('ACME');

MessageProcessed::dispatch(['sms' => [$sms]], ['otp']);
```

For a user named Jane and the code 1234, the text sent is `Dear Jane, Your code is 1234.` (`salutation()` with no argument uses the recipient's name).

## Payload attributes
`recipient($model)` takes a model with a `phone_number`, and `toSMS($senderId = null)` returns the item: `scope_type`, `scope_id`, `client_type`, `client_id`, `sender_id`, `number`, `text` and `meta_data`. The recipient is the client, so Connect can trace the message to a record. A message has no scope (`scope_type` and `scope_id` are null) unless `scope($model)`, which must be a `MessageScope`, or a messenger sets one. Add extra meta data with `metaData([...])`; `app_url` is always sent. `toSMS()` and `salutation()` (no argument) throw a `LogicException` without a recipient.

## Messenger: switching SMS off per scope
`Messenger::for($project)` holds the channel switches and takes the header, footer and sender ID from the scope's `messageHeader()`, `messageFooter()` and `messageSenderId()` (each `?string`, null for none or the default sender, worked out once per messenger). The sender sent is the `toSMS('ACME')` argument, else the scope's, else the config default. The scope is required and must be a model implementing `MessageScope`, otherwise a `TypeError`; `Messenger::global()` is for no scope and starts messages with the scope, header and footer set: `$messenger->recipient($admin)->salutation()->handler(...)->toSMS('ACME')`. `salutation()` with no argument uses the recipient's `name`; it is never added on its own.

Implement `Nyumbani\Messaging\Contracts\MessageScope` (`isMessageChannelEnabled(MessageChannel $channel)`) on the model that owns the setting, such as a project. Ask `$messenger->canSend()` or `->smsEnabled()` before building messages. The gate is the global `messaging.connect.sms.enabled` switch and the scope's answer; a message scoped to a model that does not implement the interface (its own recipient, say) is never blocked by it. The listener drops messages whose scope has the channel off (one query per scope type), so start messages from the messenger and the project's switch is always the one that counts.

## Settings
Require `nyumbani/messaging`, then set `NYUMBANI_CONNECT_BASE_URL`, `NYUMBANI_CONNECT_TOKEN` and, optionally, `MESSAGING_SMS_SENDER_ID` in `.env`. The rest lives in `messaging.connect.sms`: `enabled`, `sender_id` and `uri`. `messaging.rate_limit_per_second` caps the speed. Publish the file with the `messaging-config` tag.

## When nothing arrives
- On the `sync` queue the SMS is sent during the request and no worker is needed (a Connect failure then throws to the caller and is not retried). On any other queue connection a worker must be running.
- A number under nine digits is dropped silently.
- `MESSAGING_SMS_ENABLED=false` skips every SMS.
- A failing Connect request is tried again after about 60 and 90 seconds, and after the third failure the job fails and logs `Message sending failed`. Check the failed jobs table.

## Testing
Call `Queue::fake()`, dispatch `MessageProcessed`, and assert `SendMessageJob` was pushed. To test delivery, use `Http::fake(['*' => ...])` and call `handle()` on the job.
