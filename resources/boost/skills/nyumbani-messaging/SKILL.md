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
2. Put it in a payload: `['sms' => [$message->toSMS($recipient, $senderId)]]`.
3. Dispatch `MessageProcessed::dispatch($payload, $tags)`.

The package listener queues the SMS through `SmsGateway`, which cleans the numbers, groups them by `sender_id`, chunks them by 1000 and queues one `SendMessageJob` per chunk.

## Gateways
A channel is a class extending the abstract `Gateway`, which cleans, batches and queues the messages. It implements `normalise()`, `format()` and `deliver()` (throw on failure), and may override `batches()`. `SmsGateway` is the only one today.

Connect always receives a list of messages, even for one; `SmsGateway::deliver()` throws on an empty or non-list payload. Never post a single message object.

## Message handlers
A message type is a class that extends `MessageHandler` and implements `handle()`. Its inputs go in the constructor, and it fills `$this->message` with `text()`, `newLine()`, `action()`, `prepend()`, `append()` and `when()`. Keep it in the app, for example `app/Services/Messaging/Handlers`, and attach it with `Message::make()->handler(new ThingMessageHandler(...))`. To build a variant, add a protected method with no required arguments and pass its name as the second argument, `handler($handler, 'reminder')`. Any other method name throws an `InvalidArgumentException`.

```php
use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
use Nyumbani\Messaging\Message;
use Nyumbani\Messaging\MessageProcessed;

$message = Message::make()->salutation($user->name)->handler(new CheckInOtpMessageHandler($code));

MessageProcessed::dispatch(['sms' => [$message->toSMS($user)]], ['otp']);
```

## Payload attributes
`toSMS($recipient, $senderId = null)` takes a model with a `phone_number` and returns the item: `scope_type`, `scope_id`, `client_type`, `client_id`, `sender_id`, `number`, `text` and `meta_data`. The recipient is the client, so Connect can trace the message to a record. Set the scope with `scope($model)` and extra meta data with `metaData([...])`; `app_url` is always sent.

## Settings
Require `nyumbani/messaging`, then set `NYUMBANI_CONNECT_BASE_URL`, `NYUMBANI_CONNECT_TOKEN` and, optionally, `MESSAGING_SMS_SENDER_ID` in `.env`. The rest lives in `messaging.connect.sms`: `enabled`, `sender_id` and `uri`. `messaging.rate_limit_per_second` caps the speed. Publish the file with the `messaging-config` tag.

## When nothing arrives
- A queue worker must be running; the sends are queued.
- A number under nine digits is dropped silently.
- `MESSAGING_SMS_ENABLED=false` skips every SMS.
- A failing Connect request retries 25 times and then logs `Message sending failed`.

## Testing
Call `Queue::fake()`, dispatch `MessageProcessed`, and assert `SendMessageJob` was pushed. To test delivery, use `Http::fake(['*' => ...])` and call `handle()` on the job.
