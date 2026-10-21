@extends('docs::layout')

@section('page', 'Introduction')
@section('lead', 'Build an SMS once and let the package queue it through Nyumbani Connect.')

@section('content')
    <x-docs::section id="what-it-is" title="What Messaging is">
        <p>Every message goes out through Nyumbani Connect. You build the text with <code>Message</code>, put it in a <code>MessageProcessed</code> event, and the package queues it, limits the speed and retries it when Connect fails. Only SMS is available for now. Your app never talks to an SMS provider.</p>
        <x-docs::table :columns="['Piece', 'What it does']" :rows="[
            ['Messenger', 'One per project or organisation: the channel switches, the header and footer the scope supplies, and the messages it starts.'],
            ['Message', 'The builder: recipient, header, salutation, body and footer, and the payload item.'],
            ['MessageHandler', 'One class per message type, which writes the body.'],
            ['MessageProcessed', 'The event that carries the payload, keyed by channel such as sms.'],
            ['SmsGateway', 'Cleans the numbers, groups the messages by sender and queues the requests.'],
            ['SendMessageJob', 'Posts one request to Connect, rate limited and retried.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="flow" title="The flow in four steps">
        <x-docs::steps :steps="[
            ['title' => 'Build', 'actor' => 'Your code', 'detail' => 'Message::make() collects the recipient, the wording and the optional header, footer and sender. toSMS() turns it into one payload item.', 'code' => 'Message::make()->recipient($user)->handler(...)->toSMS();'],
            ['title' => 'Dispatch', 'actor' => 'Your code', 'detail' => 'Put the items in a MessageProcessed event, keyed by channel. The second argument holds queue tags you can search for in Horizon.', 'code' => 'MessageProcessed::dispatch([\'sms\' => [$sms]], [\'otp\']);'],
            ['title' => 'Queue', 'actor' => 'The package, in your request', 'detail' => 'The listener drops messages whose channel is switched off, SmsGateway cleans the numbers, groups by sender and queues one SendMessageJob per batch of up to 500.'],
            ['title' => 'Deliver', 'actor' => 'The queue: at once on sync, otherwise a worker', 'detail' => 'The job posts the list to Nyumbani Connect. On the sync queue that happens during your request and a failure is thrown to your code. On a real queue a worker does it in the background, limits the speed, and retries a failure up to three times before parking it in the failed jobs table.'],
        ]" />
        <p>Steps 1 and 2 are all you write. On a real queue a dispatch costs a few milliseconds, because the slow part, the HTTP call to Connect, waits for a worker in step 4. On the <code>sync</code> queue step 4 happens straight away, so the SMS is on its way as soon as the dispatch returns.</p>
    </x-docs::section>

    <x-docs::section id="quick" title="The smallest possible SMS">
        <p>After <a href="{{ $docs->url('installation') }}" class="text-primary underline">installing</a> this is a complete send. It works on the <code>sync</code> queue as it is; on any other queue connection, run a worker as well:</p>
        <x-docs::code>
            use Nyumbani\Messaging\Events\MessageProcessed;
            use Nyumbani\Messaging\Message;

            $sms = Message::make()
                ->recipient($user)                       // a model with a phone_number
                ->salutation()                           // "Dear Jane, "
                ->text('Your rent is due on Friday.')
                ->toSMS();

            MessageProcessed::dispatch(['sms' => [$sms]]);
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
        <p>Jane receives <code>Dear Jane, Your rent is due on Friday.</code> from the default sender. Move the wording into a <a href="{{ $docs->url('sending-with-handlers') }}" class="text-primary underline">handler</a> as soon as the same message is sent from more than one place.</p>
    </x-docs::section>

    <x-docs::section id="explore" title="Read in this order">
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-docs::card title="1. Installation" href="{{ $docs->url('installation') }}">Require the package and set the Connect credentials.</x-docs::card>
            <x-docs::card title="2. Configuration" href="{{ $docs->url('configuration') }}">Every key, and how failures and the rate limit behave.</x-docs::card>
            <x-docs::card title="3. Walkthrough" href="{{ $docs->url('walkthrough') }}">Issue an invoice and text two tenants, start to finish.</x-docs::card>
            <x-docs::card title="4. Sending SMS with handlers" href="{{ $docs->url('sending-with-handlers') }}">Write a handler, its variants and tests, then dispatch the message.</x-docs::card>
            <x-docs::card title="5. Sending SMS without handlers" href="{{ $docs->url('sending-without-handlers') }}">Write the text inline on the message.</x-docs::card>
            <x-docs::card title="6. Messenger" href="{{ $docs->url('messenger') }}">One object per project: its switches, header and footer, and every message.</x-docs::card>
            <x-docs::card title="7. Gateways" href="{{ $docs->url('gateways') }}">How requests are batched, and how to add a channel.</x-docs::card>
            <x-docs::card title="8. Testing and troubleshooting" href="{{ $docs->url('testing-and-troubleshooting') }}">Test a message, and find out why one did not arrive.</x-docs::card>
            <x-docs::card title="Message reference" href="{{ $docs->url('message') }}">Every method of Message and the handler base class.</x-docs::card>
        </div>
    </x-docs::section>
@endsection
