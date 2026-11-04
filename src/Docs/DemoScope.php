<?php

namespace Nyumbani\Messaging\Docs;

use Illuminate\Database\Eloquent\Model;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;
use Nyumbani\Messaging\MessageIdentity;

/**
 * A project made up for the interactive docs, never saved
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class DemoScope extends Model implements MessageScope
{
    /**
     * Create the project, with SMS switched on or off and the sender its messages go out from
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(protected bool $isOn = true, protected ?string $senderId = 'ACME')
    {
        parent::__construct();

        $this->forceFill(['id' => 1]);
    }

    /**
     * Get the morph name the payload shows
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function getMorphClass(): string
    {
        return 'project';
    }

    /**
     * Determine whether the channel is switched on for this project
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function isMessageChannelEnabled(MessageChannel $channel): bool
    {
        return $this->isOn;
    }

    /**
     * Get who the messages of this model are from
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function messageIdentity(): MessageIdentity
    {
        return new MessageIdentity(
            organizationName: 'Acme Homes',
            header: 'ACME HOMES',
            footer: 'Thank you',
            senderId: $this->senderId,
        );
    }
}
