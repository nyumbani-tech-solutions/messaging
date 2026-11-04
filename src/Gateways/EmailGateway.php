<?php

namespace Nyumbani\Messaging\Gateways;

use LogicException;
use Nyumbani\Messaging\Drivers\EmailDriver;
use Nyumbani\Messaging\Drivers\ZeptoMailEmailDriver;

class EmailGateway extends Gateway
{
    /**
     * Get the driver that delivers email, which is ZeptoMail and cannot be replaced by an app
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function driver(): EmailDriver
    {
        return new ZeptoMailEmailDriver;
    }

    /**
     * Fill the sender, reply-to, logo and organization from the config when the message has none, or return null when it has no valid address
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function normalise(array $message): ?array
    {
        //  Drop messages without a valid address
        $address = trim((string) ($message['address'] ?? ''));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        //  Get the sender, which every email needs
        $from = $this->party($message['from'] ?? null, config('messaging.email.from'));

        if (blank($from['address'])) {
            throw new LogicException('An email needs a sender: call from() on the message or set messaging.email.from.address.');
        }

        //  Return the message with its sender and branding filled
        return array_merge($message, [
            'address' => $address,
            'name' => trim((string) ($message['name'] ?? '')),
            'from' => $from,
            'reply_to' => $this->party($message['reply_to'] ?? null, config('messaging.email.reply_to'), $from),
            'logo' => ($message['logo'] ?? null) ?: config('messaging.email.logo'),
            'organization_name' => ($message['organization_name'] ?? null) ?: config('messaging.email.organization_name') ?: config('app.name'),
        ]);
    }

    /**
     * Resolve an address and name from the message, then the config, then the fallback
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function party(?array $given, ?array $default, ?array $fallback = null): array
    {
        //  Use the message's own address when it has one
        $party = filled($given['address'] ?? null) ? $given : $default;

        //  Use the fallback when there is still no address
        if (blank($party['address'] ?? null)) {
            $party = $fallback ?? ['address' => null];
        }

        //  Return the address with a name, which is the app name when none is set
        return [
            'address' => $party['address'],
            'name' => ($party['name'] ?? null) ?: ($default['name'] ?? null) ?: config('app.name'),
        ];
    }
}
