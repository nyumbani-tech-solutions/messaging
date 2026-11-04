@extends('docs::layout')

@section('page', 'Introduction')
@section('lead', 'Send an SMS or an email on behalf of a project, and let the package queue it, limit the speed, retry it and deliver it through its fixed provider.')

@section('content')
    <x-docs::section id="what-it-is" title="What Messaging is">
        <p>Messaging sends SMS through Nyumbani Connect and email through ZeptoMail. Your app never talks to a provider and cannot swap one for another: each channel\'s driver is fixed inside the package. You describe the message, and the package queues it, limits the speed and retries it when the provider fails.</p>
        <p>Three ideas are enough to start. Everything else in these docs is detail on one of them.</p>
        <x-docs::table :columns="['Idea', 'In one line', 'Read']" :rows="[
            ['The scope', 'The model every message belongs to, such as a project. It decides which channels are on and who the message is from (header, footer, sender, logo).', 'Scopes and identity'],
            ['The message', 'What you build: the scope, the recipient and the text, written inline or in a handler class.', 'Writing a message, Message handlers'],
            ['Sending', 'send() for one message, sendTo() for many, and the channels it goes out by.', 'Sending, Sending email'],
        ]" />
    </x-docs::section>

    <x-docs::section id="flow" title="The flow in four steps">
        <x-docs::steps :steps="[
            ['title' => 'Build', 'actor' => 'Your code', 'detail' => 'message() collects the scope, the recipient and the wording, and the optional subject, channels, sender and category.', 'code' => 'message()->recipient($user)->handler(...);'],
            ['title' => 'Send', 'actor' => 'Your code', 'detail' => 'send() dispatches a MessageProcessed event keyed by channel, and sendTo() does it for many recipients, one event for every chunk. The argument holds queue tags you can search for in Horizon.', 'code' => 'message()->...->send(\'otp\');'],
            ['title' => 'Queue', 'actor' => 'The package, in your request', 'detail' => 'The listener drops messages whose channel is switched off, The channel\'s gateway cleans the messages, groups them and queues one SendMessageJob per batch: up to 500 SMS or 50 emails.'],
            ['title' => 'Deliver', 'actor' => 'The queue: at once on sync, otherwise a worker', 'detail' => 'The job sends the batch to the provider: Nyumbani Connect for SMS, ZeptoMail for email. On the sync queue that happens during your request and a failure is thrown to your code. On a real queue a worker does it in the background, limits the speed, and retries a failure up to three times before parking it in the failed jobs table.'],
        ]" />
        <p>Steps 1 and 2 are all you write, and a single chained call does both. On a real queue a dispatch costs a few milliseconds, because the slow part, the HTTP call to Connect, waits for a worker in step 4. On the <code>sync</code> queue step 4 happens straight away, so the SMS is on its way as soon as the dispatch returns.</p>
    </x-docs::section>

    <x-docs::section id="quick" title="The smallest possible SMS">
        <p>After <a href="{{ $docs->url('installation') }}" class="text-primary underline">installing</a> this is a complete send, where <code>$project</code> is the model that owns the message (a project or an organisation that implements <code>MessageScope</code>). Every message needs a scope, because it decides which channels are on and who the message is from. It works on the <code>sync</code> queue as it is; on any other queue connection, run a worker as well:</p>
        <x-docs::code>
            message()
                ->scope($project)
                ->recipient($user)                       // a model with a phone_number
                ->salutation()                           // "Dear Jane, "
                ->text('Your rent is due on Friday.')
                ->send('rent');
        </x-docs::code>
        <x-docs::playground demo="message" title="Build your own SMS" :fields="[
            ['name' => 'name', 'label' => 'Recipient name', 'value' => 'Jane Wanjiru', 'placeholder' => 'e.g. Jane Wanjiru'],
            ['name' => 'phone', 'label' => 'Recipient phone_number', 'value' => '0712 345 678', 'placeholder' => 'e.g. 0712345678'],
            ['name' => 'salutation', 'label' => 'Salutation', 'type' => 'select', 'value' => 'yes', 'options' => ['yes' => 'salutation() - Dear {name}', 'no' => 'none']],
            ['name' => 'body', 'label' => 'Body', 'value' => 'Your rent is due on Friday.', 'placeholder' => 'e.g. Your rent is due on Friday.'],
            ['name' => 'header', 'label' => 'Header (optional)', 'value' => 'ACME HOMES', 'placeholder' => 'e.g. ACME HOMES'],
            ['name' => 'footer', 'label' => 'Footer (optional)', 'value' => 'Thank you', 'placeholder' => 'e.g. Thank you'],
            ['name' => 'sender', 'label' => 'Sender ID (optional)', 'value' => '', 'placeholder' => 'Empty uses the configured default'],
        ]" />
        <x-docs::playground demo="send" manual="Send the SMS" title="Send it for real" :fields="[
            ['name' => 'name', 'label' => 'Recipient name', 'value' => '', 'placeholder' => 'e.g. Jane Wanjiru'],
            ['name' => 'phone', 'label' => 'Recipient phone_number', 'value' => '', 'placeholder' => 'e.g. 0712345678'],
            ['name' => 'mode', 'label' => 'How to send it', 'type' => 'select', 'value' => 'now', 'options' => ['now' => 'Straight to Connect (shows its answer)', 'queue' => 'Through the queue, as an app does']],
            ['name' => 'salutation', 'label' => 'Salutation', 'type' => 'select', 'value' => 'yes', 'options' => ['yes' => 'salutation() - Dear {name}', 'no' => 'none']],
            ['name' => 'body', 'label' => 'Body', 'value' => 'This is a test message from the Nyumbani docs.', 'placeholder' => 'e.g. Your rent is due on Friday.'],
            ['name' => 'header', 'label' => 'Header (optional)', 'value' => '', 'placeholder' => 'e.g. ACME HOMES'],
            ['name' => 'footer', 'label' => 'Footer (optional)', 'value' => '', 'placeholder' => 'e.g. Thank you'],
            ['name' => 'sender', 'label' => 'Sender ID (optional)', 'value' => '', 'placeholder' => 'Empty uses the configured default'],
        ]" />
        <p>This sends a real SMS through Nyumbani Connect with the credentials in your <code>.env</code>. "Straight to Connect" skips the queue so you can read Connect's answer; "Through the queue" is exactly what <code>MessageProcessed::dispatch()</code> does: it sends at once when your queue connection is <code>sync</code>, and otherwise waits for a worker.</p>
        <p>Jane receives <code>Dear Jane, Your rent is due on Friday.</code> from the default sender. Move the wording into a <a href="{{ $docs->url('handlers') }}" class="text-primary underline">handler</a> as soon as the same message is sent from more than one place.</p>
    </x-docs::section>

    <x-docs::section id="explore" title="Read in this order">
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-docs::card title="1. Installation" href="{{ $docs->url('installation') }}">Require the package and set the Connect credentials.</x-docs::card>
            <x-docs::card title="2. Walkthrough" href="{{ $docs->url('walkthrough') }}">Issue an invoice and text two tenants, start to finish.</x-docs::card>
            <x-docs::card title="3. Scopes and identity" href="{{ $docs->url('scopes') }}">The model every message belongs to, and what it decides.</x-docs::card>
            <x-docs::card title="4. Writing a message" href="{{ $docs->url('writing-messages') }}">The body, salutation, header, footer, links and conditions.</x-docs::card>
            <x-docs::card title="5. Message handlers" href="{{ $docs->url('handlers') }}">Keep the wording of one message type in one class.</x-docs::card>
            <x-docs::card title="6. Sending" href="{{ $docs->url('sending') }}">send(), sendTo(), the channels, tags and category.</x-docs::card>
            <x-docs::card title="7. Sending email" href="{{ $docs->url('sending-email') }}">The template, the branding and the email body.</x-docs::card>
            <x-docs::card title="8. The channel switches" href="{{ $docs->url('messenger') }}">How a channel is switched off, and the Messenger.</x-docs::card>
            <x-docs::card title="Message reference" href="{{ $docs->url('message') }}">Every method of Message, grouped, and what each works with.</x-docs::card>
            <x-docs::card title="Configuration" href="{{ $docs->url('configuration') }}">Every key, and how failures and the rate limit behave.</x-docs::card>
            <x-docs::card title="Gateways and delivery" href="{{ $docs->url('gateways') }}">How requests are batched, the fixed drivers, and how to add a channel.</x-docs::card>
            <x-docs::card title="Testing and troubleshooting" href="{{ $docs->url('testing-and-troubleshooting') }}">Test a message, and find out why one did not arrive.</x-docs::card>
        </div>
    </x-docs::section>
@endsection
