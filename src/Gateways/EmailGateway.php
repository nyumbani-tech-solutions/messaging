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

        //  Get the sender, logo and organization name, which every email needs and a message gets from itself or its scope
        $from = $this->party($message['from'] ?? null);

        if (blank($from['address'])) {
            throw new LogicException('An email needs a sender: call from() on the message or set emailFrom in the scope\'s messageIdentity().');
        }

        if (blank($message['logo'] ?? null)) {
            throw new LogicException('An email needs a logo: call logo() on the message or set logo in the scope\'s messageIdentity().');
        }

        if (blank($message['organization_name'] ?? null)) {
            throw new LogicException('An email needs an organization name: call organization() on the message or set organizationName in the scope\'s messageIdentity().');
        }

        //  Return the message with its sender and branding filled
        return array_merge($message, [
            'address' => $address,
            'name' => trim((string) ($message['name'] ?? '')),
            'from' => $from,
            'reply_to' => $this->party($message['reply_to'] ?? null, $from),
        ]);
    }

    /**
     * Resolve an address and name from the message, then the fallback, using the app name when no name is set
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function party(?array $given, ?array $fallback = null): array
    {
        //  Use the message's own address when it has one, else the fallback
        $party = filled($given['address'] ?? null) ? $given : ($fallback ?? ['address' => null]);

        //  Return the address with a name, which is the app name when none is set
        return [
            'address' => $party['address'],
            'name' => ($party['name'] ?? null) ?: config('app.name'),
        ];
    }
}
