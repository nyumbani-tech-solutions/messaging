<?php

namespace Nyumbani\Messaging\Docs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;
use Nyumbani\Messaging\Events\MessageProcessed;
use Nyumbani\Messaging\Gateways\SmsGateway;
use Nyumbani\Messaging\Message;
use Nyumbani\Messaging\Messenger;
use ReflectionMethod;
use Throwable;

/**
 * The interactive demos of the Messaging docs, each building the real message, and the live one sending it
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class MessagingDemos
{
    /**
     * Build an SMS from the typed parts and show the payload item, the text and what Connect would receive
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function message(array $input): array
    {
        //  Build the payload item from the typed parts
        $item = $this->item($input);
        $gateway = new SmsGateway;
        $clean = (new ReflectionMethod($gateway, 'normalise'))->invoke($gateway, $item);

        //  A message without a usable number never leaves the gateway
        if ($clean === null) {
            return [$this->row('text', $item['text']), $this->row('Would be delivered', 'Dropped: the number has fewer than nine digits', false)];
        }

        return [
            $this->row('text', $item['text']),
            $this->row('number (as stored)', $item['number']),
            $this->row('recipient (to Connect)', $clean['number']),
            $this->row('sender_id (to Connect)', $clean['sender_id']),
            $this->row('client_type, client_id', "{$item['client_type']}, {$item['client_id']}"),
            $this->row('Would be delivered', 'Queued for Connect', true),
        ];
    }

    /**
     * Send the SMS for real: straight to Nyumbani Connect to see its answer, or through the queue as an app would
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function send(array $input): array
    {
        //  Connect must be configured
        if (blank(config('messaging.connect.base_url')) || blank(config('messaging.connect.access_token'))) {
            return [$this->row('Not configured', 'Set NYUMBANI_CONNECT_BASE_URL and NYUMBANI_CONNECT_TOKEN in .env', false)];
        }

        //  Build the message, then the same item the gateway would send
        $item = $this->item($input);
        $gateway = new SmsGateway;
        $clean = (new ReflectionMethod($gateway, 'normalise'))->invoke($gateway, $item);

        if ($clean === null) {
            return [$this->row('text', $item['text']), $this->row('Sent', 'No: the number has fewer than nine digits', false)];
        }

        //  Through the queue, exactly as an app sends it
        if (($input['mode'] ?? 'now') === 'queue') {
            MessageProcessed::dispatch(['sms' => [$item]], ['docs']);

            return [
                $this->row('text', $item['text']),
                $this->row('Queued', config('queue.default') === 'sync'
                    ? 'Yes, on the sync connection: it was sent during this request, no worker needed.'
                    : 'Yes, on the '.config('queue.default').' connection. A queue worker delivers it.', true),
            ];
        }

        //  Straight to Connect, so its answer can be shown
        try {
            $payload = (new ReflectionMethod($gateway, 'format'))->invoke($gateway, [$clean]);
            $response = Http::connect()->post(config('messaging.connect.sms.uri'), $payload);
        } catch (Throwable $exception) {
            return [$this->row('Failed', $exception->getMessage(), false)];
        }

        return [
            $this->row('text', $item['text']),
            $this->row('HTTP status', $response->status(), $response->successful()),
            $this->row('Request sent to Connect', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            $this->row('Response from Connect', json_encode($response->json() ?? $response->body(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
        ];
    }

    /**
     * Build the payload item of the SMS the reader described
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function item(array $input): array
    {
        //  Make up the recipient
        $recipient = (new DemoRecipient)->forceFill(['id' => 1, 'name' => $input['name'] ?? '', 'phone_number' => $input['phone'] ?? '']);

        //  Build the message the way an app would
        $message = Message::make()
            ->recipient($recipient)
            ->header(filled($input['header'] ?? null) ? $input['header'] : null)
            ->footer(filled($input['footer'] ?? null) ? $input['footer'] : null)
            ->text($input['body'] ?? '');

        //  Open with the recipient's name when asked
        if (($input['salutation'] ?? 'yes') === 'yes') {
            $message->salutation();
        }

        //  Turn it into the payload item, then into what the gateway sends
        $item = $message->toSMS(filled($input['sender'] ?? null) ? $input['sender'] : null);

        return $message->toSMS(filled($input['sender'] ?? null) ? $input['sender'] : null);
    }

    /**
     * Show whether a scope and the global switch let SMS out, and the frame the scope puts around every message
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function scope(array $input): array
    {
        //  Make up a project whose SMS switch is the typed one
        $isOn = ($input['scope'] ?? 'on') === 'on';
        $project = new class($isOn) extends Model implements MessageScope
        {
            public function __construct(protected bool $isOn = true)
            {
                parent::__construct();
            }

            public function isMessageChannelEnabled(MessageChannel $channel): bool
            {
                return $this->isOn;
            }

            public function messageHeader(): ?string
            {
                return 'ACME HOMES';
            }

            public function messageFooter(): ?string
            {
                return 'Thank you';
            }

            public function messageSenderId(): ?string
            {
                return 'ACME';
            }
        };

        //  Set the global switch for this check only
        $original = config('messaging.connect.sms.enabled');
        config(['messaging.connect.sms.enabled' => ($input['global'] ?? 'on') === 'on']);

        try {
            $messenger = ($input['scope'] ?? 'on') === 'none' ? Messenger::global() : Messenger::for($project);
            $enabled = $messenger->smsEnabled();
            $canSend = $messenger->canSend();
        } finally {
            config(['messaging.connect.sms.enabled' => $original]);
        }

        return [
            $this->row('smsEnabled()', $enabled ? 'true' : 'false', $enabled),
            $this->row('canSend()', $canSend ? 'true' : 'false', $canSend),
            $this->row('Why', match (true) {
                ($input['global'] ?? 'on') !== 'on' => 'The global switch is off, which wins over any scope.',
                ($input['scope'] ?? 'on') === 'none' => 'No scope, so only the global switch counts.',
                ! $isOn => 'The scope has SMS off, so its messages are dropped.',
                default => 'Both the global switch and the scope are on.',
            }),
        ];
    }

    /**
     * Build one row of an answer
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function row(string $label, mixed $value, ?bool $passes = null): array
    {
        return [
            'label' => $label,
            'value' => (string) $value,
            'state' => $passes === null ? null : ($passes ? 'pass' : 'fail'),
        ];
    }
}
