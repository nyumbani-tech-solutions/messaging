<?php

namespace Nyumbani\Messaging\Gateways;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SmsGateway extends Gateway
{
    /**
     * Group the messages by sender, then split each group into batches
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function batches(Collection $messages): Collection
    {
        return $messages
            ->groupBy('sender_id')
            ->flatMap(fn (Collection $group) => $group->chunk($this->chunkSize));
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

    /**
     * Turn a message into a Kenyan number and sender, or null when it has no usable number
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function normalise(array $message): ?array
    {
        //  Keep digits only
        $digits = preg_replace('/\D/', '', (string) ($message['number'] ?? ''));

        //  Drop messages without a full number
        if (strlen($digits) < 9) {
            return null;
        }

        //  Get the sender id
        $senderId = $message['sender_id']
            ?? config('messaging.connect.sms.sender_id');

        //  Return the message with an international number and a sender
        return array_merge($message, [
            'number' => '254'.substr($digits, -9),
            'sender_id' => $senderId,
        ]);
    }

    /**
     * Build the request body for one chunk of normalised messages
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function format(array $messages): array
    {
        return collect($messages)->map(fn (array $message) => [
            'scope_type' => $message['scope_type'] ?? null,
            'scope_id' => $message['scope_id'] ?? null,
            'client_id' => $message['client_id'] ?? null,
            'client_type' => $message['client_type'] ?? null,
            'recipient' => $message['number'],
            'message' => $message['text'],
            'sender_id' => $message['sender_id'],
            'meta_data' => $message['meta_data'] ?? null,
        ])->all();
    }
}
