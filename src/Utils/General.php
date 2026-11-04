<?php

use Nyumbani\Messaging\Message;

//  General helper functions

if (! function_exists('message')) {
    /**
     * Start a new message, the same as Message::make()
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    function message(): Message
    {
        return Message::make();
    }
}
