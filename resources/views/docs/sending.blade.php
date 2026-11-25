@extends('docs::layout')

@section('page', 'Sending')
@section('lead', 'Send one message with send(), many with sendTo(), and choose whether it goes out by SMS, email or both.')

@section('content')
    <x-docs::section id="one" title="1. One message: send()">
        <p>Build the message, then end the chain with <code>send()</code>. It takes one queue tag, or an array of them, which you can search for in Horizon, and it returns the message.</p>
        <x-docs::code>
            use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;

            message()
                ->scope($project)
                ->recipient($user)
                ->salutation()
                ->handler(new CheckInOtpMessageHandler($code))
                ->send('otp');
        </x-docs::code>
        <p><code>message()</code> is the same as <code>Message::make()</code>, and <code>$project</code> is the <a href="{{ $docs->url('scopes') }}" class="text-primary underline">scope</a> that owns the message. How the text is written is in <a href="{{ $docs->url('writing-messages') }}" class="text-primary underline">Writing a message</a> and <a href="{{ $docs->url('handlers') }}" class="text-primary underline">Message handlers</a>.</p>
    </x-docs::section>

    <x-docs::section id="channels" title="2. Which channels it goes out by">
        <p>A message goes out by SMS unless you say otherwise. Giving it a <code>subject()</code> adds email, and <code>via()</code> chooses the channels yourself.</p>
        <x-docs::table :columns="['You write', 'It goes out by']" :rows="[
            ['nothing extra', 'SMS'],
            ['subject(\'Rent reminder\')', 'SMS and email'],
            ['via(\'email\')', 'Email only (it needs a subject)'],
            ['via([\'sms\', \'email\'])', 'SMS and email'],
        ]" />
        <x-docs::code>
            message()
                ->scope($project)
                ->recipient($tenant)
                ->salutation()
                ->text('Your rent is due on Friday.')
                ->subject('Rent reminder')
                ->via(['sms', 'email'])
                ->send('rent');
        </x-docs::code>
        <p>A channel only goes out when it is on twice: globally (<code>MESSAGING_SMS_ENABLED</code>, <code>MESSAGING_EMAIL_ENABLED</code>) and for the scope. Email is off until you turn it on. See <a href="{{ $docs->url('messenger') }}" class="text-primary underline">The channel switches</a> and <a href="{{ $docs->url('sending-email') }}" class="text-primary underline">Sending email</a>.</p>
    </x-docs::section>

    <x-docs::section id="many" title="3. Many recipients: sendTo()">
        <p><code>send()</code> queues one job for one message. To message many people, build the shared message once and use <code>sendTo()</code>. It sends a copy to each recipient, in one event for every <code>messaging.chunk_size</code> recipients (500), so the gateways batch the requests.</p>
        <x-docs::code>
            message()
                ->scope($project)
                ->text('Rent is due Friday.')
                ->salutation()
                ->sendTo($tenants, 'rent');
        </x-docs::code>
        <p><code>salutation()</code> opens each copy with that recipient's name, so every tenant gets their own <code>Dear Jane,</code>. For something that differs per recipient, pass a callback. It receives the copy and its recipient, runs after the shared content, and returns <code>false</code> to skip the recipient:</p>
        <x-docs::code>
            use App\Models\Tenant;
            use App\Services\Messaging\Handlers\RentReminderHandler;
            use Nyumbani\Messaging\Message;

            message()
                ->scope($project)
                ->salutation()
                ->sendTo($tenants, 'rent', fn (Message $message, Tenant $tenant) => $message
                    ->handler(new RentReminderHandler($tenant)));
        </x-docs::code>
        <ul class="ml-5 list-disc space-y-1">
            <li>Tags go to the whole dispatch, so they are the second argument and not per recipient.</li>
            <li>It accepts any iterable, including <code>cursor()</code> and <code>lazy()</code>, so a long list is never held whole.</li>
            <li>Load whatever the callback reads before calling it, to avoid a query for each recipient.</li>
            <li><code>$messenger->message()</code> starts a message already scoped to the messenger's project, for <code>sendTo()</code>.</li>
        </ul>
    </x-docs::section>

    <x-docs::section id="details" title="4. Optional details">
        <x-docs::table :columns="['Call', 'What it does']" :rows="[
            ['senderId(\'ACME\')', 'The SMS sender ID, instead of the scope\'s or messaging.connect.sms.sender_id.'],
            ['category(\'payment-receipt\')', 'The label Nyumbani Connect files the SMS under, so its reports group messages by type. Up to 100 characters. Set it in the handler so every use of the message type shares it.'],
            ['metaData([\'invoice_id\' => 12])', 'Values Connect keeps with the SMS. app_url is always added.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="next" title="5. What happens next">
        <x-docs::steps :steps="[
            ['title' => 'Built', 'actor' => 'Your request', 'detail' => 'The payload item of each channel is built from the message and its scope. A mistake such as a missing subject, sender or logo throws here, whether or not the channel is on.'],
            ['title' => 'Checked', 'actor' => 'Your request', 'detail' => 'Each channel is checked against the scope it already holds, once per channel, and left out when it is off. If nothing is on, no event is dispatched.'],
            ['title' => 'Dispatched', 'actor' => 'Your request', 'detail' => 'The event named in messaging.event (MessageProcessed by default) is dispatched, marked as checked.'],
            ['title' => 'Queued and delivered', 'actor' => 'The package, then the queue', 'detail' => 'The listener hands the items to the gateways, which queue one job per batch. A worker sends them to Nyumbani Connect or ZeptoMail.'],
        ]" />
        <p>To use your own event, set <code>messaging.event</code> to a class that extends <code>MessageProcessed</code>. To send items you built yourself, dispatch <code>MessageProcessed::dispatch(['sms' => $items], $tags)</code>: the listener then checks each item's scope itself, so every item must carry <code>scope_type</code> and <code>scope_id</code>. The <a href="{{ $docs->url('gateways') }}" class="text-primary underline">Gateways and delivery</a> page follows an item to the provider.</p>
    </x-docs::section>

    <x-docs::section id="problems" title="When it does not send">
        <x-docs::table :columns="['Situation', 'What happens']" :rows="[
            ['The message has no scope', 'A LogicException, before anything is sent.'],
            ['The message has no recipient', 'A LogicException.'],
            ['An email has no subject, organization name, sender or logo', 'A LogicException, even where email is switched off.'],
            ['An SMS has no sender ID anywhere', 'A LogicException.'],
            ['An SMS has no organization name, on the message or the scope', 'A LogicException.'],
            ['The channel is off, globally or for the scope', 'Left out without an error. If every channel is off, no event is dispatched.'],
            ['A number is not nine digits (optionally led by 0 or 254), or an address is not valid', 'Dropped without an error.'],
        ]" />
    </x-docs::section>
@endsection
