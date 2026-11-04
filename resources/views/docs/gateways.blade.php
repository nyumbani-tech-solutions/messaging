@extends('docs::layout')

@section('page', 'Gateways and delivery')
@section('lead', 'How a channel cleans, batches and delivers its messages, why its driver is fixed, and what adding a channel involves.')

@section('content')
    <x-docs::section id="journey" title="The journey of a payload">
        <p>You never call a gateway yourself. The package's listener does, after <code>send()</code> or <code>sendTo()</code> dispatches the event (<code>MessageProcessed</code>, or the class set in <code>messaging.event</code>). Knowing the steps tells you where to look when a message does not arrive.</p>
        <x-docs::steps :steps="[
            ['title' => 'The event is dispatched', 'actor' => 'send(), sendTo() or your code', 'detail' => 'The event carries the payload keyed by channel, and the queue tags. send() puts one message in it and sendTo() up to messaging.chunk_size.'],
            ['title' => 'The listener filters', 'actor' => 'SendMessageNotifications, at once', 'detail' => 'For each channel it drops messages when the channel is off globally, or off for the scope the message carries. One query per scope type loads the scopes.'],
            ['title' => 'The gateway cleans', 'actor' => 'normalise()', 'detail' => 'SMS: digits only, turned into 254 plus the last nine, the sender filled from config for items built by hand. Email: the address checked, the sender, reply-to, logo and organization filled from config. A message with an unusable number or address is dropped silently.'],
            ['title' => 'The driver batches', 'actor' => 'The driver\'s batches() and format()', 'detail' => 'SMS are grouped by sender_id and split into chunks of 500. Emails are grouped by sender, reply-to and template and split into chunks of 50. Each chunk becomes the body the provider expects.'],
            ['title' => 'A job is queued per batch', 'actor' => 'SendMessageJob::dispatch()', 'detail' => 'Everything so far ran in the request that dispatched the event. On a real queue a worker takes over from here; on the sync queue the job runs straight away.'],
            ['title' => 'The job delivers', 'actor' => 'The driver\'s deliver()', 'detail' => 'It sends the body to Connect or ZeptoMail and throws on failure, so the queue retries it.'],
        ]" />
        <p>Everything up to <code>SendMessageJob::dispatch()</code> happens in the request that dispatched the event. Only the HTTP call to the provider waits for a worker, unless your queue is <code>sync</code>, where it runs in the same request too.</p>
    </x-docs::section>

    <x-docs::section id="gateway" title="The Gateway class">
        <p>Every channel extends the abstract <code>Gateway</code>. Its <code>send()</code> method is final and does the shared work. A gateway cleans the messages of its channel and names its driver; the driver talks to the provider.</p>
        <x-docs::table :columns="['Method', 'You write', 'Default']" :rows="[
            ['normalise(array $message)', 'Clean one message and return it, or return null to drop it.', 'Required.'],
            ['driver()', 'Return the driver of the channel.', 'Required.'],
            ['batches(), format(), deliver()', 'Nothing: they hand over to the driver.', 'The driver\'s own methods.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="drivers" title="Drivers are fixed">
        <p>Each channel has exactly one driver, chosen by the package. An app cannot pick another one, and there is no <code>driver</code> key in the config.</p>
        <x-docs::table :columns="['Channel', 'Gateway', 'Driver', 'Provider']" :rows="[
            ['SMS', 'SmsGateway', 'ConnectSmsDriver', 'Nyumbani Connect'],
            ['Email', 'EmailGateway', 'ZeptoMailEmailDriver', 'ZeptoMail'],
        ]" />
        <p>The drivers are <code>final</code> classes that the gateways create themselves. They are not read from the config and not bound in the container, so an app cannot replace them, not even by rebinding. Moving to another provider is a change to the package and a release for every app.</p>
        <x-docs::table :columns="['Driver method', 'What it does']" :rows="[
            ['batches(Collection $messages)', 'Splits the clean messages into the groups that each become one request.'],
            ['format(array $messages)', 'Builds the request body for one batch.'],
            ['deliver(array $payload)', 'Sends one request body to the provider and throws on failure.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="sms" title="SmsGateway and ConnectSmsDriver">
        <p>This is what each step does to an SMS.</p>
        <x-docs::table :columns="['Step', 'What it does', 'Example']" :rows="[
            ['normalise', 'Keeps the digits of the number and turns them into 254 plus the last nine. Drops a message with fewer than nine digits, silently. Fills a missing sender_id from messaging.connect.sms.sender_id.', '0712 345 678 becomes 254712345678'],
            ['batches', 'Groups by sender_id, then chunks each group into requests of up to 500.', '600 messages from one sender: two requests, 500 and 100'],
            ['format', 'Renames fields to what Connect expects: number to recipient, text to message.', 'number: 254712345678 becomes recipient: 254712345678'],
            ['deliver', 'Posts the list to messaging.connect.sms.uri with Http::connect() and calls throw() on the answer. An empty or non-list payload is refused with an InvalidArgumentException.', 'POST {base_url}/api/sms/send'],
        ]" />
        <p><code>Http::connect()</code> is a macro the package adds: an HTTP client with the Connect base URL, JSON headers and the bearer token already set. Use it to call another Connect endpoint from your app rather than building a second client.</p>
    </x-docs::section>

    <x-docs::section id="email" title="EmailGateway and ZeptoMailEmailDriver">
        <p>This is what each step does to an email. See <a href="{{ $docs->url('sending-email') }}" class="text-primary underline">Sending email</a> for how to build one.</p>
        <x-docs::table :columns="['Step', 'What it does', 'Example']" :rows="[
            ['normalise', 'Drops a message whose address is not a valid email, silently. Fills the sender, reply-to, logo and organization from messaging.email when the message has none. Throws a LogicException when there is no sender at all.', 'from: null becomes the configured sender'],
            ['batches', 'Groups by sender, reply-to and template key, then chunks each group into requests of up to 50. Different branding never shares a request.', '60 emails from one sender and one from another: three requests, 50, 10 and 1'],
            ['format', 'Builds the template batch body: template_key, from, reply_to and a to entry per recipient with the merge_info the template shows (subject, logo, organization_name, header, salutation, footer and the body as lines). The body becomes text, link and break lines; a link is labelled by the words before it.', 'Pay here: https://x.test becomes a link line labelled Pay here'],
            ['deliver', 'Posts the body to messaging.email.zeptomail.uri with the token in the Authorization header, adding the Zoho-enczapikey prefix when it is missing, and calls throw() on the answer. A missing template key throws a LogicException.', 'POST https://api.zeptomail.com/v1.1/email/template/batch'],
        ]" />
    </x-docs::section>

    <x-docs::section id="new" title="Adding a channel">
        <p>A channel is a gateway and a driver. The package currently wires SMS and email, so a third channel also touches three other places. Write the driver first, as a <code>final</code> class, then the gateway:</p>
        <x-docs::code title="src/Drivers/WhatsAppDriver.php">
            use Illuminate\Support\Collection;
            use Illuminate\Support\Facades\Http;
            use Nyumbani\Messaging\Drivers\Driver;

            final class WhatsAppDriver implements Driver
            {
                public function batches(Collection $messages): Collection
                {
                    return $messages->chunk(100);
                }

                public function format(array $messages): array
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
        <x-docs::code title="src/Gateways/WhatsAppGateway.php">
            use Nyumbani\Messaging\Drivers\Driver;
            use Nyumbani\Messaging\Drivers\WhatsAppDriver;
            use Nyumbani\Messaging\Gateways\Gateway;

            class WhatsAppGateway extends Gateway
            {
                protected function driver(): Driver
                {
                    return new WhatsAppDriver;
                }

                protected function normalise(array $message): ?array
                {
                    //  Drop a message with no number
                    return filled($message['number'] ?? null) ? $message : null;
                }
            }
        </x-docs::code>
        <p>Then, in the package:</p>
        <ol>
            <li>Add a case to <code>MessageChannel</code>, such as <code>WHATSAPP = 'whatsapp'</code>, and its config key in <code>enabledConfigKey()</code>, which <code>Messenger::isEnabled()</code> reads.</li>
            <li>Add the block to <code>config/messaging.php</code>, with <code>enabled</code> and the provider settings.</li>
            <li>Inject the gateway into <code>SendMessageNotifications</code> and return it from its <code>gateway()</code> method, so a payload keyed <code>whatsapp</code> reaches it.</li>
            <li>Give every <code>MessageScope::isMessageChannelEnabled()</code> a branch for the new case, if its <code>match</code> must be exhaustive.</li>
        </ol>
        <x-docs::callout type="note" title="This is a package change">
            Adding a channel changes <code>nyumbani/messaging</code> itself, so it also needs a new docs page, an update to the <code>nyumbani-messaging</code> skill and a release. Do not fork a gateway inside one app.
        </x-docs::callout>
    </x-docs::section>
@endsection
