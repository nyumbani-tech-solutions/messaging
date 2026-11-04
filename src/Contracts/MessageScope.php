<?php

namespace Nyumbani\Messaging\Contracts;

use Nyumbani\Messaging\Enums\MessageChannel;
use Nyumbani\Messaging\MessageIdentity;

interface MessageScope
{
    /**
     * Determine whether the channel is switched on for this model, such as a project or an organisation
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function isMessageChannelEnabled(MessageChannel $channel): bool;

    /**
     * Get who the messages of this model are from: its organization name, header, footer, SMS sender, and the logo and sender of its emails
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function messageIdentity(): MessageIdentity;
}
