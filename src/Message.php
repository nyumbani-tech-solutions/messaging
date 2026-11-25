<?php

namespace Nyumbani\Messaging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use LogicException;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;
use Nyumbani\Messaging\Events\MessageProcessed;

class Message
{
    /**
     * The header line shown above the salutation, null to take the scope's, or an empty string for none
     */
    protected ?string $header = null;

    /**
     * Whether the body opens with a salutation to the recipient's name
     */
    protected bool $salutation = false;

    /**
     * The message body
     */
    protected ?string $body = null;

    /**
     * The footer line shown below the body, null to take the scope's, or an empty string for none
     */
    protected ?string $footer = null;

    /**
     * The sender ID the message is sent from, or null for the default
     */
    protected ?string $senderId = null;

    /**
     * The model the message is sent to
     */
    protected ?Model $recipient = null;

    /**
     * The model that owns the message, such as a project or an organization
     */
    protected (Model&MessageScope)|null $scope = null;

    /**
     * The category Nyumbani Connect files the SMS under, such as invoice or payment-receipt
     */
    protected ?string $category = null;

    /**
     * The meta data sent along with the message
     */
    protected array $metaData = [];

    /**
     * The body of the email when it should differ from the SMS body, or null to use the body
     */
    protected ?string $emailBody = null;

    /**
     * The address and name the email is sent from, or null for the configured sender
     */
    protected ?array $from = null;

    /**
     * The address and name replies go to, or null for the configured reply-to
     */
    protected ?array $replyTo = null;

    /**
     * The logo shown in the email, or null for the configured one
     */
    protected ?string $logo = null;

    /**
     * The organization name shown in the email, or null for the configured one
     */
    protected ?string $organizationName = null;

    /**
     * The subject of the email, which turns the email channel on unless via() chooses the channels
     */
    protected ?string $subject = null;

    /**
     * The channels the message goes out on, or null to send SMS and, when it has a subject, email
     */
    protected ?array $channels = null;

    /**
     * The options the email driver reads, such as the template_key of the ZeptoMail template
     */
    protected array $emailOptions = [];

    /**
     * Return a new message
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set the model the message is sent to, who must have a phone_number for SMS and an email for email
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function recipient(Model $recipient): static
    {
        //  Set the recipient
        $this->recipient = $recipient;

        //  Return the message
        return $this;
    }

    /**
     * Set the header, which beats the scope's. Null clears it, so the scope's applies again
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function header(?string $header): static
    {
        //  Set the header
        $this->header = $header;

        //  Return the message
        return $this;
    }

    /**
     * Send the message with no header, even when the scope has one
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function withoutHeader(): static
    {
        return $this->header('');
    }

    /**
     * Open the body with a salutation to the recipient's name, or pass false to leave it out
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function salutation(bool $include = true): static
    {
        //  Set whether the salutation is included
        $this->salutation = $include;

        //  Return the message
        return $this;
    }

    /**
     * Set the footer, which beats the scope's. Null clears it, so the scope's applies again
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function footer(?string $footer): static
    {
        //  Set the footer
        $this->footer = $footer;

        //  Return the message
        return $this;
    }

    /**
     * Send the message with no footer, even when the scope has one
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function withoutFooter(): static
    {
        return $this->footer('');
    }

    /**
     * Set the sender ID the message is sent from. Null clears it, so the scope's applies again
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function senderId(?string $senderId): static
    {
        //  Set the sender ID
        $this->senderId = $senderId;

        //  Return the message
        return $this;
    }

    /**
     * Set the model that owns the message, which controls its message channels and defines who it is from. Every message needs one
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function scope(Model&MessageScope $scope): static
    {
        //  Set the scope
        $this->scope = $scope;

        //  Return the message
        return $this;
    }

    /**
     * Set the category Nyumbani Connect files the SMS under, up to 100 characters, or null for none
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function category(?string $category): static
    {
        //  Connect rejects the whole request when a category is longer than this
        if ($category !== null && mb_strlen($category) > 100) {
            throw new LogicException('A category can be at most 100 characters.');
        }

        //  Set the category
        $this->category = filled($category) ? $category : null;

        //  Return the message
        return $this;
    }

    /**
     * Add meta data to send along with the message
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function metaData(array $metaData): static
    {
        //  Merge the meta data
        $this->metaData = [...$this->metaData, ...$metaData];

        //  Return the message
        return $this;
    }

    /**
     * Set the address and name the email is sent from. A null address clears it, so the scope's applies again
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function from(?string $address, ?string $name = null): static
    {
        //  Set the sender, where no address clears it so the scope's applies again
        $this->from = filled($address) ? ['address' => $address, 'name' => $name] : null;

        //  Return the message
        return $this;
    }

    /**
     * Set the address and name replies to the email go to. A null address clears it, so the scope's applies again
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function replyTo(?string $address, ?string $name = null): static
    {
        //  Set the reply-to, where no address clears it so the scope's applies again
        $this->replyTo = filled($address) ? ['address' => $address, 'name' => $name] : null;

        //  Return the message
        return $this;
    }

    /**
     * Set the logo shown in the email. Null clears it, so the scope's applies again
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function logo(?string $logo): static
    {
        //  Set the logo
        $this->logo = $logo;

        //  Return the message
        return $this;
    }

    /**
     * Set the organization name shown in the email. Null clears it, so the scope's applies again
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function organization(?string $name): static
    {
        //  Set the organization name
        $this->organizationName = $name;

        //  Return the message
        return $this;
    }

    /**
     * Prefix the organization name with the app name when the app runs locally, so Connect shows which local app sent it, such as Zaka:PCEA Milele
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function labelForEnvironment(string $organizationName): string
    {
        //  Leave the name alone outside the local environment
        if (! app()->environment('local')) {
            return $organizationName;
        }

        //  Prefix the app name
        return config('app.name').':'.$organizationName;
    }

    /**
     * Set the subject of the email
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function subject(?string $subject): static
    {
        //  Set the subject
        $this->subject = $subject;

        //  Return the message
        return $this;
    }

    /**
     * Choose the channels the message goes out on, such as ['sms', 'email'], instead of the default
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function via(MessageChannel|string|array $channels): static
    {
        //  Define the channels
        $channels = collect(is_array($channels) ? $channels : [$channels]);

        //  Define the callback
        $callback = fn (MessageChannel|string $channel) => ! ($channel instanceof MessageChannel)
            ? MessageChannel::from($channel)
            : $channel;

        //  Turn each channel into its enum
        $channels = $channels
            ->map($callback)
            ->unique()
            ->values()
            ->all();

        //  Sending on no channel sends nothing, which is a mistake
        if ($channels === []) {
            throw new LogicException('via() needs at least one channel.');
        }

        //  Set the channels
        $this->channels = $channels;

        //  Return the message
        return $this;
    }

    /**
     * Set the options the email driver reads, such as the template_key of the ZeptoMail template
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function emailOptions(array $options): static
    {
        //  Merge the options
        $this->emailOptions = [...$this->emailOptions, ...$options];

        //  Return the message
        return $this;
    }

    /**
     * Set a body for the email alone, or null to send the body of the SMS
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function emailBody(?string $body): static
    {
        //  Set the email body
        $this->emailBody = $body;

        //  Return the message
        return $this;
    }

    /**
     * Build the email body with a handler method, leaving the SMS body alone. A handler without an email() method keeps the shared body
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function emailHandler(MessageHandler $handler, string $method = 'email'): static
    {
        //  The default method is optional
        if ($method === 'email' && ! method_exists($handler, 'email')) {
            return $this;
        }

        //  Get the email
        $email = new static;

        //  Get the recipient
        $email->recipient = $this->recipient;

        //  Get the scope
        $email->scope = $this->scope;

        //  Set the email body
        $this->emailBody = $handler->build($email, $method)->getBody();

        //  Return the message
        return $this;
    }

    /**
     * Append some text to the body, collapsing repeated spaces but keeping new lines
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function text(string $text = ''): static
    {
        //  Remove extra white spaces but ignore new lines and carriage returns
        $text = preg_replace('/[^\S\r\n]+/', ' ', $text);

        //  Append the text
        $this->body .= $text;

        //  Return the message
        return $this;
    }

    /**
     * Append new lines to the body
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function newLine(int $count = 1): static
    {
        //  Append the new lines
        $this->body .= str_repeat("\n", $count);

        //  Return the message
        return $this;
    }

    /**
     * Append an action label and its link to the body
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function action(string $link, string $action): static
    {
        //  Append the action
        $this->body .= "{$action} {$link}";

        //  Return the message
        return $this;
    }

    /**
     * Put some text at the start of the body
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function prepend(string $text): static
    {
        //  Prepend the text
        $this->body = "{$text}{$this->body}";

        //  Return the message
        return $this;
    }

    /**
     * Put some text at the end of the body
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function append(string $text): static
    {
        //  Append the text
        $this->body .= $text;

        //  Return the message
        return $this;
    }

    /**
     * Build part of the message only when a condition holds
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function when(bool $condition, callable $then, ?callable $else = null): static
    {
        //  Run the matching callback
        $condition ? $then($this) : ($else ? $else($this) : null);

        //  Return the message
        return $this;
    }

    /**
     * Build the body with a handler
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handler(MessageHandler $handler, string $method = 'handle'): static
    {
        return $handler->build($this, $method);
    }

    /**
     * Get the model the message is sent to, which a handler may read
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function getRecipient(): ?Model
    {
        return $this->recipient;
    }

    /**
     * Get the message body
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Get the SMS payload item for the recipient, from the message's sender, the scope's or the configured default, throwing when there is none
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function toSMS(): array
    {
        //  Get the recipient
        $recipient = $this->requireRecipient('toSMS()');

        //  Get the scope
        $scope = $this->requireScope('toSMS()');

        //  Declare the identity
        $identity = $scope->messageIdentity();

        //  Separate the header
        $header = ($line = $this->header ?? $identity->header) ? "{$line}\n\n" : '';

        //  Separate the footer
        $footer = ($line = $this->footer ?? $identity->footer) ? "\n\n{$line}" : '';

        //  Get the text
        $text = "{$header}{$this->greeting($recipient)}{$this->body}{$footer}";

        //  Get the sender ID, which every SMS needs
        $senderId = ($this->senderId ?? $identity->senderId) ?: config('messaging.connect.sms.sender_id');

        //  Throw when there is no sender ID
        if (blank($senderId)) {
            throw new LogicException('An SMS needs a sender ID: call senderId() on the message or set messaging.connect.sms.sender_id.');
        }

        //  Get the organization name, which Connect always receives
        $organizationName = $this->labelForEnvironment($this->organizationName
            ?? $identity->organizationName
            ?? throw new LogicException('An SMS needs an organization name: call organization() on the message or set organizationName in the scope\'s messageIdentity().'));

        //  Return the payload item
        return [
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => (string) $scope->getKey(),
            'client_id' => $recipient->getKey(),
            'client_type' => $recipient->getMorphClass(),
            'category' => $this->category,
            'sender_id' => $senderId,
            'number' => $recipient->phone_number,
            'text' => $text,
            'meta_data' => [
                'app_url' => config('app.url'),
                'organization_name' => $organizationName,
                ...$identity->metaData,
                ...$this->metaData,
            ],
        ];
    }

    /**
     * Get the email payload item for the recipient, who must have an email and a name, with the subject given or the message's own, and a sender and logo from the message or its scope
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function toEmail(?string $subject = null): array
    {
        //  Get the recipient
        $recipient = $this->requireRecipient('toEmail()');

        //  Get the scope, with the identity it gives the message
        $scope = $this->requireScope('toEmail()');

        //  Declare the identity
        $identity = $scope->messageIdentity();

        //  Get the subject, which every email needs
        $subject ??= $this->subject ?? throw new LogicException('An email needs a subject: call subject() first.');

        //  Get the sender, which every email needs
        $from = $this->from ?? $identity->emailFrom ?? throw new LogicException('An email needs a sender: call from() on the message or set emailFrom in the scope\'s messageIdentity().');

        //  Get the logo, which every email needs
        $logo = $this->logo ?? $identity->logo ?? throw new LogicException('An email needs a logo: call logo() on the message or set logo in the scope\'s messageIdentity().');

        //  Get where replies go, which is the sender when none is set
        $replyTo = $this->replyTo ?? $identity->emailReplyTo;

        //  Get the organization name, which every email needs
        $organizationName = $this->labelForEnvironment($this->organizationName
            ?? $identity->organizationName
            ?? throw new LogicException('An email needs an organization name: call organization() on the message or set organizationName in the scope\'s messageIdentity().'));

        //  Get the header
        $header = ($line = $this->header ?? $identity->header) ? trim($line) : null;

        //  Get the salutation
        $salutation = ($line = $this->greeting($recipient)) ? trim($line) : null;

        //  Get the footer
        $footer = ($line = $this->footer ?? $identity->footer) ? trim($line) : null;

        //  Return the payload item
        return [
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => (string) $scope->getKey(),
            'client_id' => $recipient->getKey(),
            'client_type' => $recipient->getMorphClass(),
            'address' => $recipient->email,
            'name' => (string) $recipient->name,
            'subject' => $subject,
            'from' => $from,
            'reply_to' => $replyTo,
            'logo' => $logo,
            'organization_name' => $organizationName,
            'header' => $header,
            'salutation' => $salutation,
            'body' => $this->emailBody ?? $this->body,
            'footer' => $footer,
            'options' => $this->emailOptions,
            'meta_data' => [
                'app_url' => config('app.url'),
                'organization_name' => $organizationName,
                ...$identity->metaData,
                ...$this->metaData,
            ],
        ];
    }

    /**
     * Send the message on its channels by dispatching the configured event, and return the message
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function send(array|string $tags = []): static
    {
        //  Dispatch the one message
        $this->dispatch([$this], $tags);

        //  Return the message
        return $this;
    }

    /**
     * Send a copy of the message to each recipient, one event for every chunk of recipients, and return the message
     *
     * The callback receives the copy and its recipient to add what is theirs, and returning false skips the recipient.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function sendTo(iterable $recipients, array|string $tags = [], ?callable $each = null): static
    {
        //  Refuse before anything is built when the message has no scope
        $this->requireScope('sendTo()');

        //  Build the callback
        $callback = function (Model $recipient) use ($each) {
            //  Copy the shared message for the recipient
            $copy = (clone $this)->recipient($recipient);

            //  Let the callback add what is the recipient's, where false skips them
            return ($each ? $each($copy, $recipient) : $copy) === false ? null : $copy;
        };

        //  Dispatch the chunks
        LazyCollection::make($recipients)
            ->map($callback)
            ->filter()
            ->chunk(max(1, (int) config('messaging.chunk_size', 500)))
            ->each(fn (LazyCollection $chunk) => $this->dispatch($chunk->all(), $tags));

        //  Return the message
        return $this;
    }

    /**
     * Dispatch the event the app chose, which is MessageProcessed by default, with the messages of each channel the message's scope has on
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function dispatch(array $messages, array|string $tags): void
    {
        //  Build the payload, keyed by channel, from the channels each message's scope has on
        $payload = [];

        //  Ask each scope once per channel, since the copies of one message share their scope
        $isOn = [];

        foreach ($messages as $message) {
            foreach ($message->resolveChannels() as $channel) {
                //  Build the item first, so a mistake such as a missing subject throws whether or not the channel is on
                $item = match ($channel) {
                    MessageChannel::SMS => $message->toSMS(),
                    MessageChannel::EMAIL => $message->toEmail(),
                };

                //  Leave out the channel when the scope or the global switch has it off
                $scope = $message->requireScope('send()');

                $isOn[spl_object_id($scope)][$channel->value] ??= Messenger::for($scope)->isEnabled($channel);

                if ($isOn[spl_object_id($scope)][$channel->value]) {
                    $payload[$channel->value][] = $item;
                }
            }
        }

        //  Nothing is on, so there is nothing to send
        if ($payload === []) {
            return;
        }

        //  Dispatch the event, marked as checked so the listener does not load the scopes again
        $event = config('messaging.event', MessageProcessed::class);

        $event::dispatch($payload, (array) $tags, true);
    }

    /**
     * Get the channels the message goes out on: those chosen with via(), otherwise SMS and, when there is a subject, email
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function resolveChannels(): array
    {
        return $this->channels ?? array_filter([
            MessageChannel::SMS,
            $this->subject ? MessageChannel::EMAIL : null,
        ]);
    }

    /**
     * Get the salutation to the recipient's name, or an empty string when there is none or the recipient has no name
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function greeting(Model $recipient): string
    {
        return $this->salutation && filled($recipient->name)
            ? "Dear {$recipient->name}, "
            : '';
    }

    /**
     * Get the scope, or throw when none was set
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function requireScope(string $method): Model&MessageScope
    {
        return $this->scope
            ?? throw new LogicException("{$method} needs a scope: call scope() first, or start the message with Messenger::for(\$scope).");
    }

    /**
     * Get the recipient, or throw when none was set
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function requireRecipient(string $method): Model
    {
        return $this->recipient
            ?? throw new LogicException("{$method} needs a recipient: call recipient() first.");
    }
}
