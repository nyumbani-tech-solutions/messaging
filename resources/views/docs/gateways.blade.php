@extends('docs::layout')

@section('page', 'Gateways')
@section('lead', 'How a channel cleans, batches and delivers its messages, and what adding a channel involves.')

@section('content')
    <x-docs::section id="journey" title="The journey of a payload">
        <p>You never call a gateway yourself. The package's listener does, after you dispatch <code>MessageProcessed</code>. Knowing the steps tells you where to look when a message does not arrive.</p>
        <x-docs::steps :steps="[
            ['title' => 'MessageProcessed is dispatched', 'actor' => 'Your code', 'detail' => 'The event carries the payload keyed by channel, and the queue tags.'],
            ['title' => 'The listener filters', 'actor' => 'SendMessageNotifications, at once', 'detail' => 'It drops messages when SMS is off globally, or off for the scope the message carries. One query per scope type loads the scopes.'],
            ['title' => 'The gateway cleans', 'actor' => 'SmsGateway::normalise()', 'detail' => 'Digits only, turned into 254 plus the last nine, the sender filled from config when missing. A message with fewer than nine digits is dropped silently.'],
            ['title' => 'The gateway batches', 'actor' => 'SmsGateway::batches() and format()', 'detail' => 'Messages are grouped by sender_id, split into chunks of 500, and each chunk is turned into the body Connect expects.'],
            ['title' => 'A job is queued per batch', 'actor' => 'SendMessageJob::dispatch()', 'detail' => 'Everything so far ran in the request that dispatched the event. On a real queue a worker takes over from here; on the sync queue the job runs straight away.'],
            ['title' => 'The job delivers', 'actor' => 'SmsGateway::deliver()', 'detail' => 'It posts the list to Connect with Http::connect() and throws on failure, so the queue retries it.'],
        ]" />
        <p>Everything up to <code>SendMessageJob::dispatch()</code> happens in the request that dispatched the event. Only the HTTP call to Connect waits for a worker, unless your queue is <code>sync</code>, where it runs in the same request too.</p>
    </x-docs::section>

    <x-docs::section id="gateway" title="The Gateway class">
        <p>Every channel extends the abstract <code>Gateway</code>. Its <code>send()</code> method is final and does the shared work; a gateway supplies the parts that differ.</p>
        <x-docs::table :columns="['Method', 'You write', 'Default']" :rows="[
            ['normalise(array $message)', 'Clean one message and return it, or return null to drop it.', 'Required.'],
            ['format(array $messages)', 'Turn one batch of clean messages into the request body.', 'Required.'],
            ['deliver(array $payload)', 'Send one request body to the provider. Throw on failure so the queue retries.', 'Required.'],
            ['batches(Collection $messages)', 'Split the messages into requests.', 'Chunks of $chunkSize (500).'],
        ]" />
    </x-docs::section>

    <x-docs::section id="sms" title="SmsGateway">
        <p>It is the only gateway today. This is what each step does to a message.</p>
        <x-docs::table :columns="['Step', 'What it does', 'Example']" :rows="[
            ['normalise', 'Keeps the digits of the number and turns them into 254 plus the last nine. Drops a message with fewer than nine digits, silently. Fills a missing sender_id from messaging.connect.sms.sender_id.', '0712 345 678 becomes 254712345678'],
            ['batches', 'Groups by sender_id, then chunks each group into requests of up to 500.', '600 messages from one sender: two requests, 500 and 100'],
            ['format', 'Renames fields to what Connect expects: number to recipient, text to message.', 'number: 254712345678 becomes recipient: 254712345678'],
            ['deliver', 'Posts the list to messaging.connect.sms.uri with Http::connect() and calls throw() on the answer. An empty or non-list payload is refused with an InvalidArgumentException.', 'POST {base_url}/api/sms/send'],
        ]" />
        <p><code>Http::connect()</code> is a macro the package adds: an HTTP client with the Connect base URL, JSON headers and the bearer token already set. Use it to call another Connect endpoint from your app rather than building a second client.</p>
    </x-docs::section>

    <x-docs::section id="new" title="Adding a channel">
        <p>The gateway is the easy part. The package currently wires only SMS, so a second channel also touches three other places. Write the gateway first:</p>
        <x-docs::code title="src/Gateways/WhatsAppGateway.php">
            use Illuminate\Support\Facades\Http;
            use Nyumbani\Messaging\Gateways\Gateway;

            class WhatsAppGateway extends Gateway
            {
                protected function normalise(array $message): ?array
                {
                    //  Drop a message with no number
                    return filled($message['number'] ?? null) ? $message : null;
                }

                protected function format(array $messages): array
                {
                    return collect($messages)->map(fn (array $message) => [
                        'recipient' => $message['number'],
                        'message' => $message['text'],
                    ])->all();
                }

                public function deliver(array $payload): void
                {
                    Http::connect()->post(config('messaging.connect.whatsapp.uri'), $payload)->throw();
                }
            }
        </x-docs::code>
        <p>Then, in the package:</p>
        <ol>
            <li>Add a case to <code>MessageChannel</code>, such as <code>WHATSAPP = 'whatsapp'</code>. Its value is the config key, so <code>Messenger::isEnabled()</code> reads <code>messaging.connect.whatsapp.enabled</code>.</li>
            <li>Add the <code>whatsapp</code> block to <code>config/messaging.php</code>, with <code>enabled</code> and <code>uri</code>.</li>
            <li>Handle the new channel in <code>SendMessageNotifications::handle()</code>, the way <code>sms</code> is, so a payload keyed <code>whatsapp</code> reaches the gateway.</li>
            <li>Give every <code>MessageScope::isMessageChannelEnabled()</code> a branch for the new case, because its <code>match</code> must now be exhaustive.</li>
        </ol>
        <x-docs::callout type="note" title="This is a package change">
            Adding a channel changes <code>nyumbani/messaging</code> itself, so it also needs a new docs page, an update to the <code>nyumbani-messaging</code> skill and a release. Do not fork a gateway inside one app.
        </x-docs::callout>
    </x-docs::section>
@endsection
