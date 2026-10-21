@extends('docs::layout')

@section('page', 'Sending SMS without handlers')
@section('lead', 'Write the text straight on the message, for a quick one-off.')

@section('content')
    <x-docs::section id="when" title="When to skip the handler">
        <p>A handler is not required. The body methods of <code>Message</code> work on their own, so a short message can be written where it is sent. Use this for a one-off, such as a console command or a script. A message type your app sends again and again belongs in a <a href="{{ $docs->url('sending-with-handlers') }}" class="text-primary underline">handler</a>, which keeps its wording in one place and is easy to test. The shipped Nyumbani rule asks apps to use handlers for their message types.</p>
    </x-docs::section>

    <x-docs::section id="send" title="Build and send">
        <x-docs::code title="Anywhere in the app">
            use Nyumbani\Messaging\Message;
            use Nyumbani\Messaging\Events\MessageProcessed;

            $sms = Message::make()
                ->recipient($user)
                ->salutation()
                ->text("Your code is {$code}.")->newLine()
                ->text('It expires in 5 minutes.')
                ->toSMS('ACME');

            MessageProcessed::dispatch(['sms' => [$sms]], ['otp']);
        </x-docs::code>
        <p>For a user named Jane and the code 1234, the text of the SMS is:</p>
        <x-docs::code lang="text" title="Text produced">
            Dear Jane, Your code is 1234.
            It expires in 5 minutes.
        </x-docs::code>
        <p>Everything after the message is built is the same as with a handler: <code>toSMS()</code> returns the payload item, <code>MessageProcessed</code> queues it, and Connect receives a list of messages.</p>
    </x-docs::section>

    <x-docs::section id="more" title="Header, links and conditions">
        <x-docs::code title="A fuller message">
            use Nyumbani\Messaging\Message;

            $isOverdue = true;

            $message = Message::make()
                ->header('ACME HOMES')
                ->salutation('Jane')
                ->text('Your rent is due.')->newLine(2)
                ->action('https://acme.test/pay', 'Pay now:')
                ->when($isOverdue, fn (Message $message) => $message->prepend('OVERDUE: '));
        </x-docs::code>
        <p>For an overdue invoice, <code>toSMS()['text']</code> is:</p>
        <x-docs::code lang="text" title="Text produced">
            ACME HOMES

            Dear Jane, OVERDUE: Your rent is due.

            Pay now: https://acme.test/pay
        </x-docs::code>
        <p>The <a href="{{ $docs->url('message') }}" class="text-primary underline">Message reference</a> lists every method you can call.</p>
    </x-docs::section>
@endsection
