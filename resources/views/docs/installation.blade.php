@extends('essentials::docs.layout')

@section('page', 'Installation')
@section('lead', 'Require the package, set the Connect credentials and run a queue worker.')

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

    <x-essentials::docs.section id="queue" title="3. Run a queue worker">
        <p>Messages are queued, so nothing is sent until a worker runs, such as <code>php artisan queue:work</code> or Horizon.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="publish" title="4. Publish the config (optional)">
        <x-essentials::docs.code lang="bash" title="Terminal">
            php artisan vendor:publish --tag=messaging-config
        </x-essentials::docs.code>
    </x-essentials::docs.section>
@endsection
