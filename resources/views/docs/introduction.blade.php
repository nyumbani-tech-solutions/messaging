@extends('essentials::docs.layout')

@section('page', 'Introduction')
@section('lead', 'Build an SMS once and let the package queue it through Nyumbani Connect.')

@section('content')
    <x-essentials::docs.section id="what-it-is" title="What Messaging is">
        <p>Every message goes out through Nyumbani Connect. You build the text with <code>Message</code>, put it in a <code>MessageProcessed</code> event, and the package queues it, limits the speed and retries it when Connect fails. Only SMS is available for now.</p>
        <x-essentials::docs.table :columns="['Piece', 'What it does']" :rows="[
            ['Message', 'The builder: header, salutation, body and footer, and the payload item for a recipient.'],
            ['MessageHandler', 'One class per message type, which writes the body.'],
            ['MessageProcessed', 'The event that carries the payload, keyed by channel such as sms.'],
            ['SmsGateway', 'Cleans the numbers, groups the messages by sender and queues the requests.'],
            ['SendMessageJob', 'Posts one request to Connect, rate limited and retried.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="explore" title="Explore by concept">
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-essentials::docs.card title="Installation" href="{{ $docs->url('installation') }}">Require the package and set the Connect credentials.</x-essentials::docs.card>
            <x-essentials::docs.card title="Sending an SMS" href="{{ $docs->url('sending') }}">Build a message with a handler and dispatch it.</x-essentials::docs.card>
            <x-essentials::docs.card title="Gateways" href="{{ $docs->url('gateways') }}">How requests are batched, and how to add a channel.</x-essentials::docs.card>
            <x-essentials::docs.card title="Configuration" href="{{ $docs->url('configuration') }}">Every key and what it does.</x-essentials::docs.card>
        </div>
    </x-essentials::docs.section>
@endsection
