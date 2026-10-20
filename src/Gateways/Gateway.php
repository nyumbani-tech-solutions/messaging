<?php

namespace Nyumbani\Messaging\Gateways;

use Illuminate\Support\Collection;
use Nyumbani\Messaging\SendMessageJob;

abstract class Gateway
{
    /**
     * The number of messages sent in one request
     */
    protected int $chunkSize = 500;

    /**
     * Queue the messages, one job per request
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    final public function send(array $messages, array $tags = []): void
    {
        //  Clean the messages, dropping the ones that cannot be delivered
        $messages = collect($messages)
            ->map(fn (array $message) => $this->normalise($message))
            ->filter();

        //  Queue one job per batch
        $this->batches($messages)->each(fn (Collection $batch) => SendMessageJob::dispatch(
            gateway: static::class,
            payload: $this->format($batch->values()->all()),
            tags: $tags,
        ));
    }

    /**
     * Split the clean messages into the batches that each become one request
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function batches(Collection $messages): Collection
    {
        return $messages->chunk($this->chunkSize);
    }

    /**
     * Clean a message, or return null when it cannot be delivered
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    abstract protected function normalise(array $message): ?array;

    /**
     * Build the request body for one batch of clean messages
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    abstract protected function format(array $messages): array;

    /**
     * Send one request body to the provider, throwing when it fails
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    abstract public function deliver(array $payload): void;
}
