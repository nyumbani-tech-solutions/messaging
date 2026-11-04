<?php

namespace Nyumbani\Messaging\Drivers;

use Illuminate\Support\Collection;

interface Driver
{
    /**
     * Split the clean messages into the batches that each become one request
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function batches(Collection $messages): Collection;

    /**
     * Build the request body for one batch of clean messages
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function format(array $messages): array;

    /**
     * Send one request body to the provider, throwing when it fails
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function deliver(array $payload): void;
}
