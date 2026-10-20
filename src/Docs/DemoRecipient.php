<?php

namespace Nyumbani\Messaging\Docs;

use Illuminate\Database\Eloquent\Model;

/**
 * A recipient made up for the interactive docs, never saved
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class DemoRecipient extends Model
{
    /**
     * Get the morph name the payload shows
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function getMorphClass(): string
    {
        return 'recipient';
    }
}
