@extends('docs::layout')

@section('page', 'Configuration')
@section('lead', 'Every key in config/messaging.php and the environment variable behind it.')

@section('content')
    <x-docs::section id="keys" title="Keys">
        <x-docs::table :columns="['Key', 'Environment', 'What it does']" :rows="[
            ['event', '', 'The event send() and sendTo() dispatch, and the one the package listens to. Defaults to Nyumbani\\Messaging\\Events\\MessageProcessed. A custom class must extend it, otherwise the provider throws an InvalidArgumentException.'],
            ['chunk_size', '', 'The most recipients sendTo() puts in one event. Defaults to 500, the size of an SMS request.'],
            ['rate_limit_per_second', '', 'The most queued requests (not messages: one request holds up to 500) that go out each second, counted in your cache store across all workers. Defaults to 4.'],
            ['connect.base_url', 'NYUMBANI_CONNECT_BASE_URL', 'The address of Nyumbani Connect.'],
            ['connect.access_token', 'NYUMBANI_CONNECT_TOKEN', 'The bearer token Connect checks.'],
            ['connect.sms.enabled', 'MESSAGING_SMS_ENABLED', 'Switches every SMS off when false. Defaults to true.'],
            ['connect.sms.sender_id', 'MESSAGING_SMS_SENDER_ID', 'The sender used when a message has none. Defaults to NYUMBANI. An SMS with no sender anywhere makes toSMS() throw a LogicException.'],
            ['connect.sms.uri', '', 'The Connect path SMS are posted to.'],
            ['email.enabled', 'MESSAGING_EMAIL_ENABLED', 'Switches email on. Defaults to false, so no email is sent until you turn it on.'],
            ['email.from.address, email.from.name', 'MESSAGING_EMAIL_FROM_ADDRESS, MESSAGING_EMAIL_FROM_NAME', 'The sender used when a message has none. The name falls back to the app name. A message with no sender anywhere throws a LogicException.'],
            ['email.reply_to.address, email.reply_to.name', 'MESSAGING_EMAIL_REPLY_TO_ADDRESS, MESSAGING_EMAIL_REPLY_TO_NAME', 'Where replies go when a message has none. Without it, replies go to the sender.'],
            ['email.logo', 'MESSAGING_EMAIL_LOGO', 'The logo URL shown by the template when a message has none.'],
            ['email.organization_name', 'MESSAGING_EMAIL_ORGANIZATION_NAME', 'The organization name shown by the template when a message has none. Defaults to the app name.'],
            ['email.zeptomail.uri', '', 'The ZeptoMail template batch endpoint. Change it only for another ZeptoMail region.'],
            ['email.zeptomail.api_token', 'ZEPTOMAIL_API_TOKEN', 'The Send Mail token. The Zoho-enczapikey prefix is added when you leave it out.'],
            ['email.zeptomail.template_key', 'ZEPTOMAIL_TEMPLATE_KEY', 'The template used when a message does not name its own with emailOptions().'],
        ]" />
        <x-docs::callout type="note" title="There is no driver key">
            SMS always goes through Nyumbani Connect and email through ZeptoMail. The package fixes each driver, and an app cannot choose or replace it from the config or the container.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="failures" title="Failures">
        <p>A request that the provider refuses, or that cannot reach it, throws, and the queue tries again. A job is allowed <strong>three failures</strong>: it waits about 60 seconds after the first and about 90 after the second, and after the third it gives up and logs <code>Message sending failed</code> with the gateway and the error. Find it in the failed jobs table.</p>
        <p>Waiting for the rate limit is not a failure. When more requests are due than <code>rate_limit_per_second</code> allows, the job is put back for 10 seconds without using up one of its three failures, up to 25 attempts in all.</p>
        <x-docs::table :columns="['Setting', 'Value', 'Where']" :rows="[
            ['Failures allowed', '3', 'SendMessageJob::$maxExceptions'],
            ['Wait between failures', '60 then 90 seconds', 'SendMessageJob::backoff()'],
            ['Wait when rate limited', '10 seconds', 'SendMessageJob::middleware()'],
            ['Attempts in all', '25', 'SendMessageJob::$tries'],
            ['Messages per request', '500 SMS, 50 emails', 'ConnectSmsDriver and ZeptoMailEmailDriver'],
        ]" />
    </x-docs::section>
@endsection
