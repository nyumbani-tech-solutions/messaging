<?php

namespace Nyumbani\Messaging;

use Illuminate\Database\Eloquent\Model;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;

class Messenger
{
    /**
     * Create the messenger for the scope that owns the messages and controls their channels
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        protected Model&MessageScope $scope,
    ) {}

    /**
     * Return the messenger for a scope, which every message needs and which controls its channels
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function for(Model&MessageScope $scope): static
    {
        return new static($scope);
    }

    /**
     * Start a message with no recipient yet, scoped to the messenger's scope, which supplies its header, footer, sender and email identity, ready for sendTo()
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function message(): Message
    {
        return Message::make()->scope($this->scope);
    }

    /**
     * Start a message to a recipient, scoped to the messenger's scope, which supplies its header, footer, sender and email identity
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function recipient(Model $recipient): Message
    {
        return $this->message()->recipient($recipient);
    }

    /**
     * Determine whether a channel is on globally and for the scope too
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function isEnabled(MessageChannel $channel): bool
    {
        //  The global switch must be on
        if (! config($channel->enabledConfigKey())) {
            return false;
        }

        //  The scope has the final say
        return $this->scope->isMessageChannelEnabled($channel);
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
     * Determine whether email is on
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function emailEnabled(): bool
    {
        return $this->isEnabled(MessageChannel::EMAIL);
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
