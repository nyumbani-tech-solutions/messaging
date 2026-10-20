<?php

namespace Nyumbani\Messaging\Contracts;

use Nyumbani\Messaging\Enums\MessageChannel;

interface MessageScope
{
    /**
     * Determine whether the channel is switched on for this model, such as a project or an organisation
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function isMessageChannelEnabled(MessageChannel $channel): bool;

    /**
     * Get the line every message of this model starts with, or null for none
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function messageHeader(): ?string;

    /**
     * Get the line every message of this model ends with, or null for none
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function messageFooter(): ?string;
}
