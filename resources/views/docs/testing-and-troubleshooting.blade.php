@extends('essentials::docs.layout')

@section('page', 'Testing and troubleshooting')
@section('lead', 'Prove a message is built and queued without sending one, and find out why a real one did not arrive.')

@section('content')
    <x-essentials::docs.section id="levels" title="Three things to test">
        <x-essentials::docs.table :columns="['Question', 'Test it by', 'Needs']" :rows="[
            ['Is the wording right?', 'Building the message and reading toSMS()[\'text\'].', 'Nothing: no fake, no queue.'],
            ['Is the message queued?', 'Dispatching MessageProcessed under Queue::fake().', 'Queue::fake()'],
            ['Does Connect get the right request?', 'Running the job by hand under Http::fake().', 'Http::fake() and the Connect config'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="wording" title="1. The wording">
        <p>Build the message and read the text. This is the test most handlers need. See <a href="{{ $docs->url('sending-with-handlers') }}#test" class="text-primary underline">Test a handler</a>.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="queued" title="2. The message is queued">
        <p>The listener runs at once and the gateway dispatches a job, so a queue fake sees it without a worker.</p>
        <x-essentials::docs.code title="tests/Feature/CheckInOtpTest.php">
            use App\Models\User;
            use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
            use Illuminate\Support\Facades\Queue;
            use Nyumbani\Messaging\Events\MessageProcessed;
            use Nyumbani\Messaging\Jobs\SendMessageJob;
            use Nyumbani\Messaging\Message;

            it('queues the code', function () {
                Queue::fake();

                $user = User::factory()->create(['phone_number' => '0712 345 678']);

                $sms = Message::make()
                    ->recipient($user)
                    ->handler(new CheckInOtpMessageHandler('1234'))
                    ->toSMS('ACME');

                MessageProcessed::dispatch(['sms' => [$sms]], ['otp']);

                Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->payload[0]['recipient'] === '254712345678'
                    && $job->payload[0]['sender_id'] === 'ACME'
                    && $job->tags() === ['otp']);
            });

            it('queues nothing when sms is off', function () {
                Queue::fake();
                config(['messaging.connect.sms.enabled' => false]);

                // ... dispatch as above

                Queue::assertNothingPushed();
            });
        </x-essentials::docs.code>
        <p>The queued job's <code>payload</code> is the body Connect will receive, so you can assert on any field of it.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="delivered" title="3. The request to Connect">
        <p>Call <code>handle()</code> on the job yourself, with the HTTP client faked. This bypasses the queue and the rate limiter.</p>
        <x-essentials::docs.code title="tests/Feature/DeliveryTest.php">
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
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="troubleshooting" title="A message did not arrive">
        <p>Work down the list; the earlier checks are the cheaper ones.</p>
        <x-essentials::docs.table :columns="['Check', 'How', 'Fix']" :rows="[
            ['Does your queue need a worker?', 'config(\'queue.default\'). On sync there is nothing to run: the message is sent during the request. Otherwise php artisan queue:work, or the Horizon dashboard.', 'On a real queue, start a worker. Nothing is sent until one picks the job up.'],
            ['Is SMS switched off?', 'config(\'messaging.connect.sms.enabled\') and the scope\'s isMessageChannelEnabled().', 'Set MESSAGING_SMS_ENABLED=true, or turn it on for the project. A switched-off message is dropped without any error.'],
            ['Was the message dropped for its number?', 'The number needs at least nine digits.', 'Store the full number. A shorter one is dropped silently.'],
            ['Was a job queued?', 'Check the jobs table, or Horizon, for SendMessageJob.', 'No job means the message never left the listener: see the two checks above.'],
            ['Did the job fail?', 'php artisan queue:failed, and the log for Message sending failed.', 'The error shows Connect\'s answer. A 401 is a wrong NYUMBANI_CONNECT_TOKEN; a connection error is a wrong NYUMBANI_CONNECT_BASE_URL.'],
            ['Is it only slow?', 'Many requests at once wait for the rate limit.', 'Raise messaging.rate_limit_per_second, or wait: nothing is lost.'],
            ['Wrong sender?', 'toSMS() argument, then the scope, then the config default.', 'Set messageSenderId() on the scope or pass the sender to toSMS().'],
        ]" />
        <x-essentials::docs.callout type="tip" title="Read the exact request">
            Temporarily dump <code>$job->payload</code> from a queue fake, or run the job in <code>tinker</code> with <code>Http::fake()</code>. The payload is exactly the list Connect receives.
        </x-essentials::docs.callout>
    </x-essentials::docs.section>
@endsection
