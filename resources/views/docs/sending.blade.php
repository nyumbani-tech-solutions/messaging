@extends('essentials::docs.layout')

@section('page', 'Sending an SMS')
@section('lead', 'Write a handler, build the message and dispatch it.')

@section('content')
    <x-essentials::docs.section id="build" title="Build a message">
        <x-essentials::docs.code title="app/Services/Messaging/Handlers/CheckInOtpMessageHandler.php">
            use Nyumbani\Messaging\MessageHandler;

            class CheckInOtpMessageHandler extends MessageHandler
            {
                public function __construct(private readonly string $code) {}

                protected function handle(): void
                {
                    $this->message->text("Your code is {$this->code}.");
                }
            }
        </x-essentials::docs.code>
        <p>A handler takes its inputs in the constructor and fills <code>$this->message</code>. To build another version of the message, add a protected method and pass its name: <code>handler($handler, 'reminder')</code>. A method that does not exist, is private, is static, needs arguments or belongs to the base class throws an <code>InvalidArgumentException</code>.</p>
        <p>The builder also has <code>newLine()</code>, <code>action()</code>, <code>prepend()</code>, <code>append()</code> and <code>when()</code>, and the header, salutation and footer wrap the body.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="send" title="Send it">
        <x-essentials::docs.code title="Anywhere in the app">
            use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
            use Nyumbani\Messaging\Message;
            use Nyumbani\Messaging\MessageProcessed;

            $message = Message::make()
                ->salutation($user->name)
                ->handler(new CheckInOtpMessageHandler($code));

            MessageProcessed::dispatch(['sms' => [
                $message->toSMS($user, 'ACME'),
            ]], ['otp']);
        </x-essentials::docs.code>
        <p><code>toSMS()</code> takes the recipient model, which needs a <code>phone_number</code>, and an optional sender ID. It returns the payload item with <code>scope_type</code>, <code>scope_id</code>, <code>client_type</code>, <code>client_id</code>, <code>sender_id</code>, <code>number</code>, <code>text</code> and <code>meta_data</code>. Set the scope with <code>scope($model)</code> and add meta data with <code>metaData([...])</code>; <code>app_url</code> is always included.</p>
        <p>Numbers are turned into <code>254</code> plus the last nine digits, and a message without a usable number is dropped. Connect always receives a list of messages, even for one, and the package refuses an empty or non-list payload. Messages are grouped by <code>sender_id</code> (the config value when it is null) and sent in requests of up to 1000. The second argument of the dispatch holds the queue tags.</p>
    </x-essentials::docs.section>
@endsection
