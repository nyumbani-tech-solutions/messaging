@extends('docs::layout')

@section('page', 'Writing a message')
@section('lead', 'Write the text on the message: salutation, body, links, conditions, header and footer. For a message type you send again and again, use a handler.')

@section('content')
    <x-docs::section id="parts" title="The parts of a message">
        <p>The text of an SMS has up to four parts, in this order. The header and footer come from the <a href="{{ $docs->url('scopes') }}" class="text-primary underline">scope</a> unless the message sets its own; the salutation and the body are yours.</p>
        <x-docs::table :columns="['Part', 'Where it comes from', 'Left out when']" :rows="[
            ['Header', 'The scope, or header() on the message', 'Neither has one, or withoutHeader()'],
            ['Salutation', 'salutation(): Dear {the recipient\'s name},', 'You do not call salutation()'],
            ['Body', 'text(), newLine(), action(), prepend(), append(), when(), or a handler', 'Never: it is the message'],
            ['Footer', 'The scope, or footer() on the message', 'Neither has one, or withoutFooter()'],
        ]" />
        <p>You can write the body straight on the message, as below, or move it into a <a href="{{ $docs->url('handlers') }}" class="text-primary underline">handler</a>. Use a handler once the same message is sent from more than one place: it keeps the wording in one class and is easy to test. Inline text suits a one-off, such as a console command or a script. The shipped body methods are the same either way.</p>
    </x-docs::section>

    <x-docs::section id="send" title="Build and send">
        <x-docs::code title="Anywhere in the app">
            message()
                ->scope($project)
                ->recipient($user)
                ->salutation()
                ->text("Your code is {$code}.")->newLine()
                ->text('It expires in 5 minutes.')
                ->senderId('ACME')
                ->send('otp');
        </x-docs::code>
        <p>For a user named Jane and the code 1234, the text of the SMS is:</p>
        <x-docs::code lang="text" title="Text produced">
            Dear Jane, Your code is 1234.
            It expires in 5 minutes.
        </x-docs::code>
        <p><code>message()</code> is the same as <code>Message::make()</code>. <code>send()</code> takes one queue tag or an array of them and returns the message. Everything after that is the same as with a handler: the <code>MessageProcessed</code> event queues the SMS and Connect receives a list of messages. Without <code>senderId()</code> the sender is <code>messaging.connect.sms.sender_id</code>.</p>
    </x-docs::section>

    <x-docs::section id="more" title="Header, links and conditions">
        <x-docs::code title="A fuller message">
            use Nyumbani\Messaging\Message;

            $isOverdue = true;

            $message = message()
                ->scope($project)
                ->recipient($user)
                ->header('ACME HOMES')
                ->salutation()
                ->text('Your rent is due.')->newLine(2)
                ->action('https://acme.test/pay', 'Pay now:')
                ->when($isOverdue, fn (Message $message) => $message->prepend('OVERDUE: '))
                ->send('rent');
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
