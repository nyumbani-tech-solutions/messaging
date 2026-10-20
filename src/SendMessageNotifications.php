<?php

namespace Nyumbani\Messaging;

use Nyumbani\Messaging\Gateways\SmsGateway;

class SendMessageNotifications
{
    /**
     * Create the listener
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(protected SmsGateway $smsGateway) {}

    /**
     * Send the processed messages through their gateways
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handle(MessageProcessed $event): void
    {
        //  Get the SMS messages
        $sms = $event->payload['sms'] ?? [];

        //  Send them when SMS is switched on
        if (config('messaging.connect.sms.enabled') && count($sms) > 0) {
            $this->smsGateway->send($sms, $event->tags);
        }
    }
}
