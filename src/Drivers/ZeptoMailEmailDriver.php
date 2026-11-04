<?php

namespace Nyumbani\Messaging\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use LogicException;
use Nyumbani\Messaging\Support\EmailLines;

final class ZeptoMailEmailDriver implements EmailDriver
{
    /**
     * The number of recipients sent in one request
     */
    private int $chunkSize = 50;

    /**
     * Group the messages by sender, reply-to and template, then split each group into batches
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function batches(Collection $messages): Collection
    {
        //  Get the callback
        $callback = fn (array $message) => implode('|', [
            $message['from']['address'],
            $message['from']['name'],
            $message['reply_to']['address'],
            $message['reply_to']['name'],
            $this->templateKey($message),
        ]);

        //  Group and chunk
        return $messages
            ->groupBy($callback)
            ->flatMap(fn (Collection $group) => $group->chunk($this->chunkSize));
    }

    /**
     * Build the template batch request for one chunk of normalised messages that share a sender and template
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function format(array $messages): array
    {
        //  The messages of a batch share their sender and template
        $first = $messages[0];

        return [
            'template_key' => $this->templateKey($first),
            'from' => $first['from'],
            'to' => collect($messages)->map(fn (array $message) => [
                'email_address' => [
                    'address' => $message['address'],
                    'name' => $message['name'],
                ],
                'merge_info' => [
                    'subject' => $message['subject'] ?? '',
                    'logo' => $message['logo'] ?? '',
                    'organization_name' => $message['organization_name'] ?? '',
                    'header' => $message['header'] ?? '',
                    'salutation' => $message['salutation'] ?? '',
                    'footer' => $message['footer'] ?? '',
                    'lines' => EmailLines::from($message['body'] ?? ''),
                ],
            ])->values()->all(),
            'reply_to' => $first['reply_to'],
        ];
    }

    /**
     * Send one template batch request to ZeptoMail, throwing when it fails
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function deliver(array $payload): void
    {
        Http::asJson()
            ->acceptJson()
            ->withHeader('Authorization', $this->token())
            ->post(config('messaging.email.zeptomail.uri'), $payload)
            ->throw();
    }

    /**
     * Get the template the message renders with: its own, else the configured one
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    private function templateKey(array $message): string
    {
        //  Get the message's template, else the configured one
        $key = $message['options']['template_key'] ?? config('messaging.email.zeptomail.template_key');

        return filled($key)
            ? $key
            : throw new LogicException('An email needs a template: call emailOptions() on the message or set messaging.email.zeptomail.template_key.');
    }

    /**
     * Get the Authorization header value, adding the ZeptoMail prefix when the token was set without it
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    private function token(): string
    {
        $token = trim((string) config('messaging.email.zeptomail.api_token'));

        return str_starts_with($token, 'Zoho-enczapikey') ? $token : "Zoho-enczapikey {$token}";
    }
}
