@extends('essentials::docs.layout')

@section('page', 'Configuration')
@section('lead', 'Every key in config/messaging.php and the environment variable behind it.')

@section('content')
    <x-essentials::docs.section id="keys" title="Keys">
        <x-essentials::docs.table :columns="['Key', 'Environment', 'What it does']" :rows="[
            ['rate_limit_per_second', '', 'The most queued requests (not messages: one request holds up to 500) that go out each second, counted in your cache store across all workers. Defaults to 4.'],
            ['connect.base_url', 'NYUMBANI_CONNECT_BASE_URL', 'The address of Nyumbani Connect.'],
            ['connect.access_token', 'NYUMBANI_CONNECT_TOKEN', 'The bearer token Connect checks.'],
            ['connect.sms.enabled', 'MESSAGING_SMS_ENABLED', 'Switches every SMS off when false. Defaults to true.'],
            ['connect.sms.sender_id', 'MESSAGING_SMS_SENDER_ID', 'The sender used when a message has none. Defaults to NYUMBANI.'],
            ['connect.sms.uri', '', 'The Connect path SMS are posted to.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="failures" title="Failures">
        <p>A request that Connect refuses, or that cannot reach it, throws, and the queue tries again. A job is allowed <strong>three failures</strong>: it waits about 60 seconds after the first and about 90 after the second, and after the third it gives up and logs <code>Message sending failed</code> with the gateway and the error. Find it in the failed jobs table.</p>
        <p>Waiting for the rate limit is not a failure. When more requests are due than <code>rate_limit_per_second</code> allows, the job is put back for 10 seconds without using up one of its three failures, up to 25 attempts in all.</p>
        <x-essentials::docs.table :columns="['Setting', 'Value', 'Where']" :rows="[
            ['Failures allowed', '3', 'SendMessageJob::$maxExceptions'],
            ['Wait between failures', '60 then 90 seconds', 'SendMessageJob::backoff()'],
            ['Wait when rate limited', '10 seconds', 'SendMessageJob::middleware()'],
            ['Attempts in all', '25', 'SendMessageJob::$tries'],
            ['Messages per request', '500', 'Gateway::$chunkSize'],
        ]" />
    </x-essentials::docs.section>
@endsection
