@extends('docs::layout')

@section('page', 'Testing and troubleshooting')
@section('lead', 'Prove a message is built and queued without sending one, and find out why a real one did not arrive.')

@section('content')
    <x-docs::section id="levels" title="Three things to test">
        <x-docs::table :columns="['Question', 'Test it by', 'Needs']" :rows="[
            ['Is the wording right?', 'Building the message and reading toSMS()[\'text\'].', 'Nothing: no fake, no queue.'],
            ['Is the message queued?', 'Calling send() (or dispatching MessageProcessed) under Queue::fake().', 'Queue::fake()'],
            ['Does Connect get the right request?', 'Running the job by hand under Http::fake().', 'Http::fake() and the Connect config'],
        ]" />
    </x-docs::section>

    <x-docs::section id="wording" title="1. The wording">
        <p>Build the message and read the text. This is the test most handlers need. See <a href="{{ $docs->url('handlers') }}#test" class="text-primary underline">Test a handler</a>.</p>
    </x-docs::section>

    <x-docs::section id="queued" title="2. The message is queued">
        <p>The listener runs at once and the gateway dispatches a job, so a queue fake sees it without a worker.</p>
        <x-docs::code title="tests/Feature/CheckInOtpTest.php">
            use App\Models\User;
            use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
            use Illuminate\Support\Facades\Queue;
            use Nyumbani\Messaging\Jobs\SendMessageJob;

            it('queues the code', function () {
                Queue::fake();

                $user = User::factory()->create(['phone_number' => '0712 345 678']);

                message()
                    ->scope($project)
                    ->recipient($user)
                    ->handler(new CheckInOtpMessageHandler('1234'))
                    ->senderId('ACME')
                    ->send('otp');

                Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->payload[0]['recipient'] === '254712345678'
                    && $job->payload[0]['sender_id'] === 'ACME'
                    && $job->tags() === ['otp']);
            });

            it('queues nothing when sms is off', function () {
                Queue::fake();
                config(['messaging.connect.sms.enabled' => false]);

                // ... send as above

                Queue::assertNothingPushed();
            });
        </x-docs::code>
        <p>The queued job's <code>payload</code> is the body Connect will receive, so you can assert on any field of it. To prove the event itself, use <code>Event::fake([MessageProcessed::class])</code> and assert on its <code>payload</code> and <code>tags</code>; this also works for <code>sendTo()</code>, where one event carries up to <code>messaging.chunk_size</code> recipients. If you set <code>messaging.event</code>, fake and assert your own class.</p>
    </x-docs::section>

    <x-docs::section id="delivered" title="3. The request to Connect">
        <p>Call <code>handle()</code> on the job yourself, with the HTTP client faked. This bypasses the queue and the rate limiter.</p>
        <x-docs::code title="tests/Feature/DeliveryTest.php">
            use Illuminate\Http\Client\RequestException;
            use Illuminate\Support\Facades\Http;
            use Nyumbani\Messaging\Gateways\SmsGateway;
            use Nyumbani\Messaging\Jobs\SendMessageJob;

            it('posts the list to connect', function () {
                config([
                    'messaging.connect.base_url' => 'https://connect.test',
                    'messaging.connect.access_token' => 'secret',
                ]);
                Http::fake(['*' => Http::response([], 202)]);

                (new SendMessageJob(SmsGateway::class, [[
                    'recipient' => '254712345678',
                    'message' => 'Hello',
                    'sender_id' => 'ACME',
                ]]))->handle();

                Http::assertSent(fn ($request) => $request->url() === 'https://connect.test/api/sms/send'
                    && $request->hasHeader('Authorization', 'Bearer secret'));
            });

            it('throws when connect refuses', function () {
                config(['messaging.connect.base_url' => 'https://connect.test']);
                Http::fake(['*' => Http::response([], 500)]);

                (new SendMessageJob(SmsGateway::class, [['recipient' => '254712345678', 'message' => 'Hi', 'sender_id' => 'ACME']]))->handle();
            })->throws(RequestException::class);
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="email-tests" title="Testing an email">
        <p>The same three levels apply. Read <code>subject('Subject')->toEmail()['body']</code> for the wording, fake the queue for the batch, and run the job by hand for the ZeptoMail request.</p>
        <x-docs::code title="tests/Feature/InvoiceEmailTest.php">
            use Illuminate\Support\Facades\Http;
            use Illuminate\Support\Facades\Queue;
            use Nyumbani\Messaging\Gateways\EmailGateway;
            use Nyumbani\Messaging\Jobs\SendMessageJob;

            beforeEach(function () {
                config([
                    'messaging.email.enabled' => true,
                    'messaging.email.zeptomail.uri' => 'https://zepto.test/send',
                    'messaging.email.zeptomail.api_token' => 'abc123',
                    'messaging.email.zeptomail.template_key' => 'tpl-default',
                ]);
            });

            it('queues the email', function () {
                Queue::fake();

                message()->scope($project)->recipient($user)->salutation()->text('Hi.')->subject('Hello')->via('email')->send('invoice');

                Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->payload['template_key'] === 'tpl-default'
                    && $job->payload['to'][0]['email_address']['address'] === $user->email);
            });

            it('posts the template batch to zeptomail', function () {
                Http::fake(['zepto.test/*' => Http::response([], 201)]);

                (new SendMessageJob(EmailGateway::class, $payload))->handle();

                Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Zoho-enczapikey abc123'));
            });
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="troubleshooting" title="A message did not arrive">
        <p>Work down the list; the earlier checks are the cheaper ones.</p>
        <x-docs::table :columns="['Check', 'How', 'Fix']" :rows="[
            ['Does your queue need a worker?', 'config(\'queue.default\'). On sync there is nothing to run: the message is sent during the request. Otherwise php artisan queue:work, or the Horizon dashboard.', 'On a real queue, start a worker. Nothing is sent until one picks the job up.'],
            ['Is SMS switched off?', 'config(\'messaging.connect.sms.enabled\') and the scope\'s isMessageChannelEnabled().', 'Set MESSAGING_SMS_ENABLED=true, or turn it on for the project. A switched-off message is dropped without any error.'],
            ['Was the message dropped for its number?', 'The number needs at least nine digits.', 'Store the full number. A shorter one is dropped silently.'],
            ['Was a job queued?', 'Check the jobs table, or Horizon, for SendMessageJob.', 'No job means the message never left the listener: see the two checks above.'],
            ['Did the job fail?', 'php artisan queue:failed, and the log for Message sending failed.', 'The error shows Connect\'s answer. A 401 is a wrong NYUMBANI_CONNECT_TOKEN; a connection error is a wrong NYUMBANI_CONNECT_BASE_URL.'],
            ['Is it only slow?', 'Many requests at once wait for the rate limit.', 'Raise messaging.rate_limit_per_second, or wait: nothing is lost.'],
            ['Wrong sender?', 'senderId() on the message, then the scope, then the config default.', 'Call senderId() on the message, set senderId in the scope\'s messageIdentity() or set MESSAGING_SMS_SENDER_ID. With none of them toSMS() throws.'],
            ['Is email switched off?', 'config(\'messaging.email.enabled\') and the scope\'s isMessageChannelEnabled().', 'Set MESSAGING_EMAIL_ENABLED=true. Email is off by default, and a switched-off message is dropped without any error.'],
            ['Was the email dropped for its address?', 'The address must pass a normal email check.', 'Store a valid email. An invalid one is dropped silently.'],
            ['Did sending an email throw?', 'A LogicException says what is missing.', 'Call subject() (every email needs one), make sure the scope\'s messageIdentity() sets emailFrom and logo (or call from() and logo()), and set ZEPTOMAIL_TEMPLATE_KEY (or emailOptions()).'],
            ['Did ZeptoMail refuse it?', 'php artisan queue:failed and the log for Message sending failed.', 'The error shows ZeptoMail\'s answer. A 401 is a wrong ZEPTOMAIL_API_TOKEN; a sender from an unverified domain is refused.'],
        ]" />
        <x-docs::callout type="tip" title="Read the exact request">
            Temporarily dump <code>$job->payload</code> from a queue fake, or run the job in <code>tinker</code> with <code>Http::fake()</code>. The payload is exactly the body the provider receives: the list for Connect, the template batch for ZeptoMail.
        </x-docs::callout>
    </x-docs::section>
@endsection
