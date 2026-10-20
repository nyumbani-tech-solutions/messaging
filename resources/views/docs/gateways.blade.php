@extends('essentials::docs.layout')

@section('page', 'Gateways')
@section('lead', 'How a channel batches and delivers its messages.')

@section('content')
    <x-essentials::docs.section id="gateway" title="The Gateway class">
        <p>Every channel extends the abstract <code>Gateway</code>, which cleans the messages, batches them and queues one <code>SendMessageJob</code> per batch. A gateway only supplies <code>normalise()</code> (clean one message, or return null to drop it), <code>format()</code> (the request body of a batch) and <code>deliver()</code> (send it, throwing on failure). It may override <code>batches()</code> to group messages first, as <code>SmsGateway</code> does by sender ID.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="sms" title="SmsGateway">
        <p>It posts to Connect's <code>api/sms/send</code> through the <code>Http::connect()</code> client, which carries the base URL and bearer token. The body is always a list of messages with <code>recipient</code>, <code>message</code>, <code>sender_id</code> and the scope, client and meta data fields.</p>
    </x-essentials::docs.section>
@endsection
