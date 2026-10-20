<?php

namespace Nyumbani\Messaging\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MessageProcessed
{
    use Dispatchable;

    /**
     * Create a new event instance, with the payload keyed by channel such as `sms`
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public array $payload = [],
        public array $tags = [],
    ) {}
}
