<?php

namespace Nyumbani\Messaging\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class ConnectSmsDriver implements SmsDriver
{
    /**
     * The number of messages sent in one request
     */
    private int $chunkSize = 500;

    /**
     * Group the messages by sender, then split each group into batches
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function batches(Collection $messages): Collection
    {
        return $messages
            ->groupBy('sender_id')
            ->flatMap(fn (Collection $group) => $group->chunk($this->chunkSize));
    }

    /**
     * Build the request body for one chunk of normalised messages
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function format(array $messages): array
    {
        return collect($messages)->map(fn (array $message) => [
            'scope_type' => $message['scope_type'] ?? null,
            'scope_id' => $message['scope_id'] ?? null,
            'client_id' => $message['client_id'] ?? null,
            'client_type' => $message['client_type'] ?? null,
            'category' => $message['category'] ?? null,
            'recipient' => $message['number'],
            'message' => $message['text'],
            'sender_id' => $message['sender_id'],
            'meta_data' => $message['meta_data'] ?? null,
        ])->all();
    }

    /**
     * Send one list of messages to Nyumbani Connect, throwing when it fails
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function deliver(array $payload): void
    {
        //  Connect only accepts a list of messages, even for a single message
        if ($payload === [] || ! array_is_list($payload)) {
            throw new InvalidArgumentException('The payload sent to Connect must be a list of messages.');
        }

        //  Get the uri
        $uri = config('messaging.connect.sms.uri');

        //  Send the request
        Http::connect()->post($uri, $payload)->throw();
    }
}
