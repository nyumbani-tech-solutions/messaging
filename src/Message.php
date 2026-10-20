<?php

namespace Nyumbani\Messaging;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Nyumbani\Messaging\Contracts\MessageScope;

class Message
{
    /**
     * The header line shown above the salutation
     */
    protected ?string $header = null;

    /**
     * The salutation that opens the body
     */
    protected ?string $salutation = null;

    /**
     * The message body
     */
    protected ?string $body = null;

    /**
     * The footer line shown below the body
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
     * The meta data sent along with the message
     */
    protected array $metaData = [];

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
     * Set the model the message is sent to, who must have a phone_number
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
     * Set the header
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
     * Address the message to a name, or to the recipient's name when none is given
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function salutation(?string $name = null): static
    {
        //  Use the recipient's name when called without an argument
        if (func_num_args() === 0) {
            $name = $this->requireRecipient('salutation()')->name;
        }

        //  Set the salutation
        $this->salutation = $name ? "Dear {$name}, " : null;

        //  Return the message
        return $this;
    }

    /**
     * Set the footer
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
     * Set the sender ID the message is sent from, null for the default
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
     * Set the model that owns the message, which controls its message channels
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function scope((Model&MessageScope)|null $scope): static
    {
        //  Set the scope
        $this->scope = $scope;

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
     * Get the SMS payload item for the recipient, from the sender given, the message's sender or the default
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function toSMS(?string $senderId = null): array
    {
        //  Get the recipient
        $recipient = $this->requireRecipient('toSMS()');

        //  Separate the header
        $header = $this->header ? "{$this->header}\n\n" : '';

        //  Separate the footer
        $footer = $this->footer ? "\n\n{$this->footer}" : '';

        //  Return the payload item
        return [
            'scope_type' => $this->scope?->getMorphClass(),
            'scope_id' => $this->scope?->getKey(),
            'client_id' => $recipient->getKey(),
            'client_type' => $recipient->getMorphClass(),
            'sender_id' => $senderId ?? $this->senderId,
            'number' => $recipient->phone_number,
            'text' => "{$header}{$this->salutation}{$this->body}{$footer}",
            'meta_data' => ['app_url' => config('app.url'), ...$this->metaData],
        ];
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
