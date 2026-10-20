@extends('essentials::docs.layout')

@section('page', 'Installation')
@section('lead', 'Require the package, set the Connect credentials and pick how messages are sent.')

@section('content')
    <x-essentials::docs.section id="require" title="1. Require the package">
        <x-essentials::docs.code lang="bash" title="Terminal">
            composer require nyumbani/messaging
        </x-essentials::docs.code>
        <p>Laravel discovers the service provider on its own.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="environment" title="2. Set the environment">
        <x-essentials::docs.code lang="bash" title=".env">
            NYUMBANI_CONNECT_BASE_URL=
            NYUMBANI_CONNECT_TOKEN=
            MESSAGING_SMS_SENDER_ID=NYUMBANI
        </x-essentials::docs.code>
        <x-essentials::docs.callout type="warning" title="The token is a secret">
            Keep it in the git-ignored <code>.env</code> only, never in code or <code>.env.example</code>.
        </x-essentials::docs.callout>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="queue" title="3. Choose how messages are sent">
        <p>Messages go through your app's queue, so what happens next follows <code>QUEUE_CONNECTION</code>. Either way works.</p>
        <x-essentials::docs.table :columns="['QUEUE_CONNECTION', 'What happens', 'You need']" :rows="[
            ['sync', 'The message is sent during the request that dispatched it, then the code carries on. A failure is thrown to that code and is not retried.', 'Nothing more. This is the simplest setup, and fine for local development and low volume.'],
            ['database, redis, sqs...', 'The request only queues the message and finishes at once. A worker sends it, retries a failure, and spreads the load with the rate limit.', 'A queue worker, such as php artisan queue:work or Horizon.'],
        ]" />
        <x-essentials::docs.callout type="tip" title="Which to use">
            Use <code>sync</code> to get going. Move to a real queue when a slow Connect should not slow your pages, or when you send many messages at once.
        </x-essentials::docs.callout>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="publish" title="4. Publish the config (optional)">
        <x-essentials::docs.code lang="bash" title="Terminal">
            php artisan vendor:publish --tag=messaging-config
        </x-essentials::docs.code>
    </x-essentials::docs.section>
@endsection
