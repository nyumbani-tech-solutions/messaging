<?php

namespace Nyumbani\Messaging\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MessageProcessed
{
    use Dispatchable;

    /**
     * Create a new event instance, with the payload keyed by channel such as `sms`
     *
     * `isGated` is true when send() or sendTo() already checked every message against its scope's channel switch, so the listener does not load the scopes again.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public array $payload = [],
        public array $tags = [],
        public bool $isGated = false,
    ) {}
}
