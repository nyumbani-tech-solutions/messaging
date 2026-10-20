@extends('essentials::docs.layout')

@section('page', 'Message reference')
@section('lead', 'Every method of Message and the MessageHandler base class.')

@section('content')
    <x-essentials::docs.section id="overview" title="Message">
        <p><code>Nyumbani\Messaging\Message</code> builds one SMS. Every method except <code>toSMS()</code> returns the same message, so calls chain. A message is made with <code>Message::make()</code>, never with <code>new</code>, and one message is built for one recipient.</p>
        <p>The text of the SMS is put together in this order, with a blank line between the header and the rest, and between the body and the footer:</p>
        <x-essentials::docs.code lang="text" title="Layout of the text">
            {header}

            Dear {name}, {body}

            {footer}
        </x-essentials::docs.code>
        <p>A part that was not set is left out together with its blank line.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="recipient" title="Recipient">
        <x-essentials::docs.table :columns="['Method', 'What it does']" :rows="[
            ['recipient(Model $recipient)', 'Sets who the message is for: a model with a phone_number. Required before salutation() without a name and before toSMS().'],
            ['getRecipient()', 'Returns the recipient, or null. A handler can read it as $this->message->getRecipient().'],
        ]" />
        <p>A <a href="{{ $docs->url('messenger') }}" class="text-primary underline">Messenger</a> starts a message with the recipient, scope, header and footer already set.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="frame" title="Header, salutation, footer">
        <x-essentials::docs.table :columns="['Method', 'What it does']" :rows="[
            ['header(?string $header)', 'Sets the line shown above everything, such as an organisation name. Null removes it.'],
            ['salutation(?string $name = null)', 'With a name, opens the body with Dear {name}, . With no argument, uses the recipient\'s name and needs recipient() first. Null removes it.'],
            ['footer(?string $footer)', 'Sets the line shown below the body. Null removes it.'],
            ['senderId(?string $senderId)', 'Sets the sender ID the message is sent from. Null means the default sender. A Messenger sets it from the scope.'],
        ]" />
        <x-essentials::docs.code title="Example">
            use Nyumbani\Messaging\Message;

            $message = Message::make()
                ->header('ACME HOMES')
                ->salutation('Jane')
                ->footer('Thank you');
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="body" title="Writing the body">
        <x-essentials::docs.table :columns="['Method', 'What it does']" :rows="[
            ['text(string $text)', 'Adds text to the end of the body. Runs of spaces become one space, and new lines are kept.'],
            ['newLine(int $count = 1)', 'Adds that many new lines. Use 2 for a blank line.'],
            ['action(string $link, string $action)', 'Adds the label and then the link, as Open: https://... Add a newLine() first, because nothing is put before it.'],
            ['prepend(string $text)', 'Puts text at the very start of the body.'],
            ['append(string $text)', 'Adds text to the end of the body exactly as given, without collapsing spaces.'],
            ['when(bool $condition, callable $then, ?callable $else = null)', 'Calls $then with the message when the condition is true, otherwise $else if one is given.'],
            ['getBody()', 'Returns the body written so far, without the header, salutation or footer. Null when nothing was written.'],
        ]" />
        <x-essentials::docs.code title="Example">
            use Nyumbani\Messaging\Message;

            $isOverdue = true;
            $link = 'https://app.test/i/1';

            $message
                ->text('Your invoice is due.')->newLine(2)
                ->action($link, 'View invoice:')
                ->when($isOverdue, fn (Message $message) => $message->prepend('OVERDUE: '));
        </x-essentials::docs.code>
        <p>With the header, salutation and footer from the example above, a link of <code>https://app.test/i/1</code> and an overdue invoice, <code>toSMS()['text']</code> is:</p>
        <x-essentials::docs.code lang="text" title="Text produced">
            ACME HOMES

            Dear Jane, OVERDUE: Your invoice is due.

            View invoice: https://app.test/i/1

            Thank you
        </x-essentials::docs.code>
        <p>Notice that <code>prepend()</code> puts its text at the start of the body, so it lands after the salutation, not before it.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="handler" title="Building with a handler">
        <x-essentials::docs.table :columns="['Method', 'What it does']" :rows="[
            ['handler(MessageHandler $handler, string $method = \'handle\')', 'Lets the handler write the body, using its handle() method or the protected method named. Throws an InvalidArgumentException for a method that cannot be used.'],
        ]" />
        <p>See <a href="{{ $docs->url('sending-with-handlers') }}" class="text-primary underline">Sending SMS with handlers</a> for how to write one.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="context" title="Scope and meta data">
        <x-essentials::docs.table :columns="['Method', 'What it does']" :rows="[
            ['scope((Model&MessageScope)|null $scope)', 'Sets the model that owns the message, such as a project or an organisation. It must implement MessageScope, or a TypeError is thrown. Connect receives its morph class and key as scope_type and scope_id. Without it, or with null, the message has no scope.'],
            ['metaData(array $metaData)', 'Adds values Connect keeps with the message. Calls merge, and a later value for the same key wins.'],
        ]" />
        <p><code>app_url</code> is always added to the meta data, taken from <code>config('app.url')</code>.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="to-sms" title="Turning it into a payload">
        <x-essentials::docs.table :columns="['Method', 'What it does']" :rows="[
            ['toSMS(?string $senderId = null)', 'Returns the payload item for the recipient. The sender is the argument, else senderId(), else the default. Throws a LogicException when no recipient was set.'],
        ]" />
        <x-essentials::docs.code title="Example">
            $item = $message->toSMS('ACME');
        </x-essentials::docs.code>
        <x-essentials::docs.table :columns="['Key', 'Value']" :rows="[
            ['scope_type, scope_id', 'The morph class and key of the scope set with scope(), or null when none was set.'],
            ['client_type, client_id', 'The morph class and key of the recipient.'],
            ['sender_id', 'The sender from the argument or senderId(), or null so the configured default sender is used.'],
            ['number', 'The recipient\'s phone_number, as stored. The gateway turns it into 254 and the last nine digits.'],
            ['text', 'The full text: header, salutation, body and footer.'],
            ['meta_data', 'app_url plus anything added with metaData().'],
        ]" />
        <p>Put the item in a <code>MessageProcessed</code> payload under <code>sms</code> to send it. The message is not queued by <code>toSMS()</code> itself.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="handler-class" title="MessageHandler">
        <p><code>Nyumbani\Messaging\MessageHandler</code> is the base class of every handler.</p>
        <x-essentials::docs.table :columns="['Member', 'What it does']" :rows="[
            ['handle()', 'Abstract and protected. Write the default body here.'],
            ['$message', 'The Message being built. It is set before handle() runs and holds the last message after.'],
            ['build(Message $message, string $method = \'handle\')', 'Final. Checks the method, attaches the message, runs the method and returns the message. Message::handler() calls it; do not call it yourself.'],
        ]" />
        <p>A handler method is usable only when it belongs to the handler (not to this base class), is protected or public, is not static, is not a magic method and needs no arguments.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="other" title="The other classes">
        <x-essentials::docs.table :columns="['Class', 'What it does']" :rows="[
            ['MessageProcessed', 'The event. new MessageProcessed($payload, $tags) or MessageProcessed::dispatch($payload, $tags); the payload is keyed by channel and the tags are added to the queued jobs.'],
            ['SendMessageNotifications', 'The listener. Sends the sms messages to SmsGateway unless SMS is switched off.'],
            ['SendMessageJob', 'Posts one request to Connect. Rate limited, 25 tries, retried after 60, 90 and 120 seconds, and logs when it gives up.'],
            ['Gateways\\Gateway, Gateways\\SmsGateway', 'See Gateways.'],
        ]" />
    </x-essentials::docs.section>
@endsection
