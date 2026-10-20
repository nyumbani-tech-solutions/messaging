<?php

namespace Nyumbani\Messaging;

use Illuminate\Database\Eloquent\Model;
use Nyumbani\Messaging\Contracts\MessageScope;

class Messenger
{
    /**
     * The header the scope gave, worked out once
     */
    protected ?string $header = null;

    /**
     * The footer the scope gave, worked out once
     */
    protected ?string $footer = null;

    /**
     * Whether the header and footer were worked out yet
     */
    protected bool $isFramed = false;

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
     * Start a message to a recipient, scoped to the messenger's scope and framed by the scope's header and footer
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function recipient(Model $recipient): Message
    {
        //  Work out the header and footer, once for all the recipients
        $this->frame();

        return Message::make()
            ->recipient($recipient)
            ->scope($this->scope)
            ->header($this->header)
            ->footer($this->footer);
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

    /**
     * Ask the scope for its header and footer the first time, which may be costly
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function frame(): void
    {
        //  Do it only once
        if ($this->isFramed) {
            return;
        }

        //  Ask the scope, which may answer null for none
        $this->header = $this->scope?->messageHeader();
        $this->footer = $this->scope?->messageFooter();

        //  Remember that it was done
        $this->isFramed = true;
    }
}
