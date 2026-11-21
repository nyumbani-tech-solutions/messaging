<?php

namespace Nyumbani\Messaging\Gateways;

use Nyumbani\Messaging\Drivers\ConnectSmsDriver;
use Nyumbani\Messaging\Drivers\SmsDriver;

class SmsGateway extends Gateway
{
    /**
     * Get the driver that delivers SMS, which is Nyumbani Connect and cannot be replaced by an app
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function driver(): SmsDriver
    {
        return new ConnectSmsDriver;
    }

    /**
     * Turn a message into a Kenyan number and sender, or null when it is not a valid Kenyan number
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function normalise(array $message): ?array
    {
        //  Keep digits only
        $digits = preg_replace('/\D/', '', (string) ($message['number'] ?? ''));

        //  Drop messages whose number is not a Kenyan one: nine digits, optionally led by 0 or 254
        if (! preg_match('/^(?:254|0)?(\d{9})$/', $digits, $matches)) {
            return null;
        }

        //  Get the sender id
        $senderId = $message['sender_id']
            ?? config('messaging.connect.sms.sender_id');

        //  Return the message with an international number and a sender
        return array_merge($message, [
            'number' => '254'.$matches[1],
            'sender_id' => $senderId,
        ]);
    }
}
