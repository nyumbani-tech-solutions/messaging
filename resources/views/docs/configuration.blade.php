@extends('essentials::docs.layout')

@section('page', 'Configuration')
@section('lead', 'Every key in config/messaging.php and the environment variable behind it.')

@section('content')
    <x-essentials::docs.section id="keys" title="Keys">
        <x-essentials::docs.table :columns="['Key', 'Environment', 'What it does']" :rows="[
            ['rate_limit_per_second', '', 'The most queued requests that go out each second. Defaults to 4.'],
            ['connect.base_url', 'NYUMBANI_CONNECT_BASE_URL', 'The address of Nyumbani Connect.'],
            ['connect.access_token', 'NYUMBANI_CONNECT_TOKEN', 'The bearer token Connect checks.'],
            ['connect.sms.enabled', 'MESSAGING_SMS_ENABLED', 'Switches every SMS off when false. Defaults to true.'],
            ['connect.sms.sender_id', 'MESSAGING_SMS_SENDER_ID', 'The sender used when a message has none. Defaults to NYUMBANI.'],
            ['connect.sms.uri', '', 'The Connect path SMS are posted to.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="failures" title="Failures">
        <p>A failed request throws, so the queue retries it up to 25 times, waiting 60, 90 then 120 seconds. When it gives up, it logs <code>Message sending failed</code> with the error.</p>
    </x-essentials::docs.section>
@endsection
