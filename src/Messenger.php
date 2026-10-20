<?php

namespace Nyumbani\Messaging;

use Illuminate\Database\Eloquent\Model;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;

class Messenger
{
    /**
     * The scope's header, footer and sender ID, worked out once, and null until then
     */
    protected ?array $frame = null;

    /**
     * Create the messenger for a scope that controls its channels, or for the global switches alone
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        protected (Model&MessageScope)|null $scope = null
    ) {}

    /**
     * Return the messenger for a scope, which must be given and must control its channels
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function for(Model&MessageScope $scope): static
    {
        return new static($scope);
    }

    /**
     * Return the messenger for the global switches alone, when there is no scope
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function global(): static
    {
        return new static;
    }

    /**
     * Start a message to a recipient, scoped to the messenger's scope and framed by the scope's header, footer and sender ID
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function recipient(Model $recipient): Message
    {
        //  Work out the header, footer and sender ID once, for all the recipients
        [$header, $footer, $senderId] = $this->frame ??= [
            $this->scope?->messageHeader(),
            $this->scope?->messageFooter(),
            $this->scope?->messageSenderId(),
        ];

        //  Return the message
        return Message::make()
            ->recipient($recipient)
            ->scope($this->scope)
            ->header($header)
            ->footer($footer)
            ->senderId($senderId);
    }

    /**
     * Determine whether a channel is on globally and, when the scope controls its channels, for the scope too
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function isEnabled(MessageChannel $channel): bool
    {
        //  The global switch must be on
        if (! config("messaging.connect.{$channel->value}.enabled")) {
            return false;
        }

        //  The scope has the final say
        return $this->scope?->isMessageChannelEnabled($channel) ?? true;
    }

    /**
     * Determine whether SMS is on
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function smsEnabled(): bool
    {
        return $this->isEnabled(MessageChannel::SMS);
    }

    /**
     * Determine whether at least one channel is on
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function canSend(): bool
    {
        return collect(MessageChannel::cases())->contains(
            fn (MessageChannel $channel) => $this->isEnabled($channel)
        );
    }
}
