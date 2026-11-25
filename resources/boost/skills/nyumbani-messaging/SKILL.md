---
name: nyumbani-messaging
description: "Invoke when a Nyumbani app sends an SMS or an email: building the text, a message handler, sending with `message()->...->send()`, the sender ID, email branding and template, the Nyumbani Connect or ZeptoMail settings, or a message that is not delivered. Skip for WhatsApp and Daraja callbacks."
metadata:
  author: nyumbani
---

# Nyumbani Messaging

SMS goes out through Nyumbani Connect and email through ZeptoMail. Do not call a provider such as Bunicom or ZeptoMail, or `Http`, from an app, and do not look for a way to choose a provider: each channel has one fixed driver inside the package, with no `driver` config key, and an app cannot replace it. SMS and email exist; WhatsApp is not available yet.

## The model in one minute
Three ideas cover almost everything. Learn them in this order.

1. **The scope** is the model every message belongs to (a project, an organisation, a gym). It decides which channels are on and who the message is from. A message without a scope throws.
2. **The message** is what you build: the scope, the recipient and the text, written inline or in a handler class.
3. **Sending** is `send()` for one message and `sendTo()` for many, on SMS, email or both.

```php
use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;

message()
    ->scope($project)                                  // 1. who it belongs to
    ->recipient($user)                                 // 2. who it is for
    ->salutation()
    ->handler(new CheckInOtpMessageHandler($code))     // 2. what it says
    ->send('otp');                                     // 3. send it, tagged otp
```

`message()` is the same as `Message::make()`, and `$project` is a model implementing `MessageScope`. `Messenger::for($project)->recipient($user)` is the same as `message()->scope($project)->recipient($user)`.

## 1. Scopes and identity
Implement `Nyumbani\Messaging\Contracts\MessageScope` on the model. It has two methods: `isMessageChannelEnabled(MessageChannel $channel)` and `messageIdentity(): MessageIdentity`.

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
            footer: $this->setting('message_footer'),
            senderId: $this->setting('sms_sender_id'),
            logo: $this->avatar,
            emailFrom: ['address' => $this->email, 'name' => $this->name],
        );
    }
}
```

Every `MessageIdentity` argument is optional and named, so a scope sets what it sends. The identity validates itself: a blank organization name, a logo that is not an absolute URL, or an address that is not an email throws an `InvalidArgumentException`.

| Argument | Used by | If left out |
| --- | --- | --- |
| `header`, `footer` | SMS and email | No header or footer |
| `senderId` | SMS | `messaging.connect.sms.sender_id` |
| `organizationName` | SMS and email | `toSMS()` and `toEmail()` throw, unless the message sets `organization()`. Connect always receives it as `organization_name` |
| `logo`, `emailFrom` | Email | `toEmail()` throws, unless the message sets `logo()` or `from()` |
| `emailReplyTo` | Email | Replies go to the sender |
| `metaData` | SMS and email | Only `app_url` and the message's own `metaData()`. Merges with the message's calls, and the message wins a shared key |

**What wins.** For each value the message comes first (`header()`, `footer()`, `senderId()`, `organization()`, `logo()`, `from()`, `replyTo()`), then the scope's identity, then the default. `meta_data` is the exception: `app_url`, then `organization_name` (always sent: the message's `organization()`, else the identity's; an SMS or email without one throws), then the identity's `metaData`, the message's `metaData()` calls merge, and the later one wins a shared key. The scope is read when the payload is built, so the order of calls does not matter and `scope($project)` and `Messenger::for($project)` give the same result.

**Not set, and none.** On every message setter, `null` means *not set*, the same as never calling it: it clears what was set before and the scope's value applies again. `withoutHeader()` and `withoutFooter()` are the only way to send with none when the scope has one. The scope is required and must implement `MessageScope`, otherwise a `TypeError`.

## 2. Writing a message
The text has up to four parts in this order: the header (the scope's, or `header()`), the salutation (`salutation()` gives `Dear {recipient name},`; not calling it sends none, and `salutation(false)` removes it), the body, and the footer (the scope's, or `footer()`). A blank line separates the header and the footer from the rest.

Write the body on the message with `text()`, `newLine()`, `action($link, $label)`, `prepend()`, `append()` and `when($condition, $then, $else)`, or in a handler. Use a handler once the same message is sent from more than one place.

A message type is a class that extends `MessageHandler` and implements `handle()`. Its inputs go in the constructor, and it fills `$this->message`. Keep it in the app, for example `app/Services/Messaging/Handlers`, and attach it with `->handler(new ThingMessageHandler(...))`. To build a variant, add a protected method with no required arguments and pass its name as the second argument, `handler($handler, 'reminder')`. Any other method name throws an `InvalidArgumentException`. For a user named Jane and the code 1234, the text sent is `Dear Jane, Your code is 1234.`

**Categories.** `category('payment-receipt')` sets the label Nyumbani Connect files the SMS under, so its reports group messages by type. Put it in the handler's `handle()` so every use of a message type shares it; a variant may set its own, and a `sendTo()` callback may override it per recipient. Use short kebab-case keys, reuse the ones other Nyumbani apps already use (`invoice`, `payment-receipt`, `arrears-reminder`, `new-tenant`), and keep it at 100 characters or fewer: a longer one throws a `LogicException`, because Connect would reject the whole request. The package sends exactly the string it is given, and email ignores it.

## 3. Sending
`send($tags = [])` takes one tag or an array and returns the message. It dispatches the event named in `messaging.event` (`MessageProcessed` by default; a custom class must extend it, and the package listens to whichever is configured). `senderId('ACME')` sets the SMS sender, and `metaData([...])` adds values Connect keeps (`app_url` is always sent).

**Channels.** SMS always goes out unless `via()` says otherwise. A message with a `subject()` also goes out by email. `via('email')` is email only and `via(['sms', 'email'])` is both. An email needs a subject, and a missing one throws.

**Many recipients.** Use `sendTo($recipients, $tags = [], $each = null)`, not a loop of `send()`, which queues one job per message. It sends a copy to each recipient with one event per `messaging.chunk_size` (500), and accepts `cursor()` and `lazy()`.

```php
use App\Models\Tenant;
use App\Services\Messaging\Handlers\RentReminderHandler;
use Nyumbani\Messaging\Message;

message()->scope($project)->text('Rent is due Friday.')->salutation()->sendTo($tenants, 'rent');

message()->scope($project)->salutation()->sendTo($tenants, 'rent', fn (Message $message, Tenant $tenant) => $message->handler(new RentReminderHandler($tenant)));
```

`salutation()` opens each copy with that recipient's name. The callback gets the copy and its recipient, runs after the shared content (so what it adds goes at the end of the body), and returns `false` to skip the recipient. Tags are for the whole dispatch, so they are given to `sendTo()`. Eager load whatever the callback reads. `$messenger->message()` starts a scoped message with no recipient for `sendTo()`.

**What happens next.** `send()` and `sendTo()` build each channel's item (a mistake such as a missing sender throws here, even where the channel is off), check each channel against the scope they already hold, leave out the ones that are off, and dispatch the event marked as checked. If nothing is on, no event is dispatched. The listener then hands the items to the gateways: `SmsGateway` cleans the numbers, groups them by `sender_id`, chunks them by 500 and queues one `SendMessageJob` per chunk, and email follows the same flow through `EmailGateway`. To send items you built yourself, `MessageProcessed::dispatch(['sms' => $items], $tags)` still works, but the listener then checks every item's scope itself, so each item needs a `scope_type` and `scope_id` (one without throws before anything is queued) and the scope must be saved.

## 4. Email
Build it on the same `Message`, set `->subject($subject)` and `->send()`. The recipient needs an `email` and a `name`. An email needs an organization name, a sender and a logo, from the message or the scope's identity, or `toEmail()` throws; a hand-built item without them throws in the gateway.

```php
message()
    ->scope($project)
    ->recipient($tenant)
    ->from('billing@acme.example', 'Acme Homes')   // else the scope's emailFrom; required
    ->replyTo('help@acme.example')                 // else the scope's emailReplyTo, then the sender
    ->logo('https://acme.example/logo.png')        // else the scope's logo; required
    ->organization('Acme Homes')                   // else the scope's organizationName; required
    ->emailOptions(['template_key' => '...'])      // optional, else ZEPTOMAIL_TEMPLATE_KEY
    ->salutation()
    ->text('Invoice INV-1042 is ready.')->newLine()
    ->action('https://acme.example/i/1042', 'View invoice:')
    ->subject('Your invoice')
    ->via('email')
    ->send('invoice');
```

- The driver groups by sender, reply-to and template key, 50 recipients to a request, so emails for different organisations never share a request.
- The body is the shared one by default. The package turns it into the template's `lines`: a blank line is a break, a line with a link is a link labelled by the words before it (`Click Here` when there are none), the rest is text. For a different email body use `emailBody($text)`, or an `email()` method on the handler and `->handler($handler)->emailHandler($handler)`; a handler without `email()` keeps the shared body.
- The ZeptoMail template must show `subject`, `logo`, `organization_name`, `header`, `salutation`, `footer` and `lines`.
- Dropped silently: an invalid address, and email switched off. Thrown as a `LogicException`: no subject, organization name, sender or logo, and no template key anywhere.

## 5. The channel switches
A channel goes out only when it is on globally (`messaging.connect.sms.enabled`, `messaging.email.enabled`) and for the scope (`isMessageChannelEnabled()`). `Messenger::for($project)` asks both: `canSend()`, `smsEnabled()`, `emailEnabled()` and `isEnabled($channel)`, and it starts scoped messages with `recipient($model)` and `message()`. Ask `$messenger->canSend()` before building messages. There is no messenger without a scope.

The check happens in the request for `send()` and `sendTo()`, using the scope the message holds, so an unsaved scope works and no query runs. An event you dispatch yourself is checked by the listener, with one query per scope type. An item whose scope row no longer exists is dropped, because its switch cannot be read.

## All Message methods
On every message-level setter, `null` means *not set*: it clears what was set before and the scope's value applies again. `withoutHeader()` and `withoutFooter()` are the only way to send with none.

Every public method, grouped, with the channel it matters to: Both for the shared parts, SMS or Email for a method the other channel ignores. All of them return the message (`static`) unless the Returns column says otherwise.

| Method | Works with | Returns | What it does |
| --- | --- | --- | --- |
| **Create** | | | |
| `make()` | Both | `static` | Returns a new message. The message() helper does the same. Never use new. |
| **Who it is for** | | | |
| `recipient(Model $recipient)` | Both | message | The model the message is sent to: a phone_number for SMS, an email and a name for email. |
| `scope(Model&MessageScope $scope)` | Both | message | Required. The model that owns the message, which controls its channels and defines who it is from. Sent to Connect as scope_type and scope_id. toSMS(), toEmail(), send() and sendTo() throw a LogicException without one. |
| **Frame** | | | |
| `header(?string $header)` | Both | message | The line above the salutation. Without it, the scope's identity header. |
| `withoutHeader()` | Both | message | Sends the message with no header, even when the scope has one. |
| `salutation(bool $include = true)` | Both | message | Opens the body with Dear {recipient name},. Not calling it leaves it out, and salutation(false) removes it. |
| `footer(?string $footer)` | Both | message | The line below the body. Without it, the scope's identity footer. |
| `withoutFooter()` | Both | message | Sends the message with no footer, even when the scope has one. |
| **Body** | | | |
| `text(string $text = '')` | Both | message | Appends text, collapsing repeated spaces but keeping new lines. |
| `newLine(int $count = 1)` | Both | message | Appends new lines. |
| `action(string $link, string $action)` | Both | message | Appends a label and its link, such as Pay now: https://... |
| `prepend(string $text)` | Both | message | Puts text at the start of the body, after the salutation. |
| `append(string $text)` | Both | message | Puts text at the end of the body, as is. |
| `when(bool $condition, callable $then, ?callable $else = null)` | Both | message | Builds part of the message only when the condition holds. |
| `handler(MessageHandler $handler, string $method = 'handle')` | Both | message | Builds the body with a handler. The method must be on the handler and need no arguments. The email shares this body unless it has its own. |
| **SMS settings** | | | |
| `senderId(?string $senderId)` | SMS | message | The sender ID. Without it the scope sender or messaging.connect.sms.sender_id is used. Email ignores it. |
| `category(?string $category)` | SMS | message | The category Connect files the SMS under. Up to 100 characters, and null or empty means none. Email ignores it. |
| `metaData(array $metaData)` | SMS | message | Values Connect keeps with the SMS. Calls merge, and app_url is always added. ZeptoMail does not receive it. |
| **Email settings** | | | |
| `subject(?string $subject)` | Email | message | The email subject. Setting one adds the email channel unless via() chooses the channels. |
| `from(?string $address, ?string $name = null)` | Email | message | The sender of the email. Defaults to the scope's identity emailFrom; an email with neither throws. |
| `replyTo(?string $address, ?string $name = null)` | Email | message | Where replies go. Defaults to the scope's identity emailReplyTo, then the sender. |
| `logo(?string $logo)` | Email | message | The logo the template shows. Defaults to the scope's identity logo; an email with neither throws. |
| `organization(?string $name)` | Email | message | The organization name the template shows. Defaults to the scope's identity organizationName; an email with neither throws. |
| `emailOptions(array $options)` | Email | message | Options for the email driver, such as template_key to use another ZeptoMail template. |
| `emailBody(?string $body)` | Email | message | A body for the email alone. Null uses the shared body. |
| `emailHandler(MessageHandler $handler, string $method = 'email')` | Email | message | Builds the email body with a handler method. A handler without an email() method keeps the shared body. |
| **Channels and sending** | | | |
| `via(MessageChannel\|string\|array $channels)` | Both | message | Chooses the channels, such as 'email' or ['sms', 'email']. Without it SMS goes out, plus email when there is a subject. |
| `send(array\|string $tags = [])` | Both | message | Dispatches the configured event for this message on its channels and returns it. Tags are searchable in Horizon. |
| `sendTo(iterable $recipients, array\|string $tags = [], ?callable $each = null)` | Both | message | Sends a copy to each recipient on its channels, one event for every messaging.chunk_size recipients. The callback gets the copy and the recipient, and returning false skips the recipient. |
| **Payloads and getters** | | | |
| `toSMS()` | SMS | `array` | The SMS payload item. Throws when there is no recipient or no sender ID. |
| `toEmail(?string $subject = null)` | Email | `array` | The email payload item. Throws when there is no recipient or no subject. |
| `getRecipient()` | Both | `?Model` | The recipient, which a handler may read. |
| `getBody()` | Both | `?string` | The body written so far, without the header, salutation or footer. |

## Payload attributes
`recipient($model)` takes a model with a `phone_number` (SMS) or an `email` and `name` (email). `toSMS()` returns the item: `scope_type`, `scope_id`, `client_type`, `client_id`, `category`, `sender_id`, `number`, `text` and `meta_data`. The recipient is the client, and the scope is the owner, so Connect can trace the message to both. `toSMS()` and `toEmail()` throw a `LogicException` without a recipient or a scope.

## Settings
Require `nyumbani/messaging`, then set `NYUMBANI_CONNECT_BASE_URL`, `NYUMBANI_CONNECT_TOKEN` and, optionally, `MESSAGING_SMS_SENDER_ID` in `.env`. The rest lives in `messaging.connect.sms`: `enabled`, `sender_id` and `uri`. `messaging.rate_limit_per_second` caps the speed. Publish the file with the `messaging-config` tag.

Email is off until `MESSAGING_EMAIL_ENABLED=true`. Set `ZEPTOMAIL_API_TOKEN` (the `Zoho-enczapikey` prefix is added when missing), and `ZEPTOMAIL_TEMPLATE_KEY`. The sender (a domain verified in ZeptoMail), reply-to, logo and organization name are not configured: they come from the scope. The switch lives in `messaging.email`; the ZeptoMail token, template and `uri` are in `messaging.email.zeptomail`.

## Gateways and drivers
A channel is a gateway extending the abstract `Gateway`, plus a fixed driver. The gateway implements `normalise()` (clean one message, or return null to drop it) and `driver()`; the driver implements `batches()`, `format()` and `deliver()` (throw on failure). `SmsGateway` uses `ConnectSmsDriver` and `EmailGateway` uses `ZeptoMailEmailDriver`. The drivers are `final`, created by the gateways, and neither read from config nor bound in the container.

Connect always receives a list of messages, even for one; `ConnectSmsDriver::deliver()` throws on an empty or non-list payload. Never post a single message object.

## When nothing arrives
- On the `sync` queue the SMS is sent during the request and no worker is needed (a Connect failure then throws to the caller and is not retried). On any other queue connection a worker must be running.
- A number that is not nine digits (optionally led by 0 or 254) is dropped silently, so the too-long fake numbers from `HasKenyans` never reach Connect.
- `MESSAGING_SMS_ENABLED=false` skips every SMS, and `MESSAGING_EMAIL_ENABLED` (off by default) gates every email.
- An email with an invalid address is dropped silently. ZeptoMail refuses a sender from an unverified domain or a wrong token (a 401), and the job fails like an SMS does.
- A failing Connect request is tried again after about 60 and 90 seconds, and after the third failure the job fails and logs `Message sending failed`. Check the failed jobs table.

## Testing
Call `Queue::fake()`, `send()` the message (or dispatch `MessageProcessed`), and assert `SendMessageJob` was pushed (`$job->payload[0]['recipient']` is the cleaned `254` number for SMS; for email `$job->payload['to'][0]['email_address']` and `$job->payload['template_key']`). To test delivery, use `Http::fake(['*' => ...])` and call `handle()` on the job. Never contact Connect or ZeptoMail from the suite.
