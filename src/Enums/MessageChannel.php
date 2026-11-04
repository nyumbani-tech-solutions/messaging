<?php

namespace Nyumbani\Messaging\Enums;

enum MessageChannel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';

    /**
     * Get the config key of the switch that turns the channel on or off globally
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function enabledConfigKey(): string
    {
        return match ($this) {
            self::SMS => 'messaging.connect.sms.enabled',
            self::EMAIL => 'messaging.email.enabled',
        };
    }
}
