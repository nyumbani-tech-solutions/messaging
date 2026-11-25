<?php

namespace Nyumbani\Messaging;

use InvalidArgumentException;

/**
 * Who a scope's messages are from: the frame around the text, and what its SMS and emails go out as
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
final readonly class MessageIdentity
{
    /**
     * Describe the identity
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public ?string $organizationName = null,
        public ?string $header = null,
        public ?string $footer = null,
        public ?string $senderId = null,
        public ?string $logo = null,
        public ?array $emailFrom = null,
        public ?array $emailReplyTo = null,
        public array $metaData = [],
    ) {
        //  A blank name would leave the email without one, so leave it out instead
        if ($organizationName !== null && blank($organizationName)) {
            throw new InvalidArgumentException('The organization name cannot be blank: leave it out for none.');
        }

        //  The template shows the logo as an image
        if (filled($logo) && filter_var($logo, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("The logo must be an absolute URL, [{$logo}] given.");
        }

        //  Both addresses need a valid email
        foreach (['emailFrom' => $emailFrom, 'emailReplyTo' => $emailReplyTo] as $key => $party) {
            if ($party !== null && filter_var($party['address'] ?? null, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("{$key} needs a valid ['address' => ..., 'name' => ...].");
            }
        }
    }
}
