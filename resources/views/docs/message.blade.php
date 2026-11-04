@extends('docs::layout')

@section('page', 'Message reference')
@section('lead', 'Every method of Message and the MessageHandler base class.')

@section('content')
    <x-docs::section id="overview" title="Message">
        <p><code>Nyumbani\Messaging\Message</code> builds one SMS or email. Every setter and body method returns the same message, so calls chain; only <code>toSMS()</code>, <code>toEmail()</code> and the getters (<code>getRecipient()</code>, <code>getBody()</code>) return something else. A message is made with <code>message()</code> or <code>Message::make()</code>, never with <code>new</code>, and one message is built for one recipient.</p>
        <p>The text of the SMS is put together in this order, with a blank line between the header and the rest, and between the body and the footer:</p>
        <x-docs::code lang="text" title="Layout of the text">
            {header}

            Dear {name}, {body}

            {footer}
        </x-docs::code>
        <p>A part that was not set is left out together with its blank line.</p>
    </x-docs::section>

    <x-docs::section id="all-methods" title="All methods">
        <p>Every public method of <code>Message</code>, grouped by what it is for. <strong>Works with</strong> says which channel the method matters to: <em>Both</em> for the shared parts of a message, <em>SMS</em> or <em>Email</em> for a method the other channel ignores. The setters and body methods return the same message, so calls chain; the last group returns something else, and <code>send()</code> and <code>sendTo()</code> return the message after dispatching.</p>
        <x-docs::table :columns="['Method', 'Works with', 'Group', 'Returns', 'What it does']" :rows="[
            ['make()', 'Both', 'Create', 'static', 'Returns a new message. The message() helper does the same. Never use new.'],
            ['recipient(Model $recipient)', 'Both', 'Who it is for', 'message', 'The model the message is sent to: a phone_number for SMS, an email and a name for email.'],
            ['scope((Model&MessageScope)|null $scope)', 'Both', 'Who it is for', 'message', 'The model that owns the message, which controls its channels for SMS and email. Sent to Connect as scope_type and scope_id, and not sent to ZeptoMail.'],
            ['header(?string $header)', 'Both', 'Frame', 'message', 'The line above the salutation. Null removes it.'],
            ['salutation(bool $include = true)', 'Both', 'Frame', 'message', 'Opens the body with Dear {recipient name},. Not calling it leaves it out, and salutation(false) removes it.'],
            ['footer(?string $footer)', 'Both', 'Frame', 'message', 'The line below the body. Null removes it.'],
            ['text(string $text = \'\')', 'Both', 'Body', 'message', 'Appends text, collapsing repeated spaces but keeping new lines.'],
            ['newLine(int $count = 1)', 'Both', 'Body', 'message', 'Appends new lines.'],
            ['action(string $link, string $action)', 'Both', 'Body', 'message', 'Appends a label and its link, such as Pay now: https://...'],
            ['prepend(string $text)', 'Both', 'Body', 'message', 'Puts text at the start of the body, after the salutation.'],
            ['append(string $text)', 'Both', 'Body', 'message', 'Puts text at the end of the body, as is.'],
            ['when(bool $condition, callable $then, ?callable $else = null)', 'Both', 'Body', 'message', 'Builds part of the message only when the condition holds.'],
            ['handler(MessageHandler $handler, string $method = \'handle\')', 'Both', 'Body', 'message', 'Builds the body with a handler. The method must be on the handler and need no arguments. The email shares this body unless it has its own.'],
            ['senderId(string $senderId)', 'SMS', 'SMS settings', 'message', 'The sender ID. Without it the scope sender or messaging.connect.sms.sender_id is used. Email ignores it.'],
            ['category(?string $category)', 'SMS', 'SMS settings', 'message', 'The category Connect files the SMS under. Up to 100 characters, and null or empty means none. Email ignores it.'],
            ['metaData(array $metaData)', 'SMS', 'SMS settings', 'message', 'Values Connect keeps with the SMS. Calls merge, and app_url is always added. ZeptoMail does not receive it.'],
            ['subject(?string $subject)', 'Email', 'Email settings', 'message', 'The email subject. Setting one adds the email channel unless via() chooses the channels.'],
            ['from(string $address, ?string $name = null)', 'Email', 'Email settings', 'message', 'The sender of the email. Defaults to messaging.email.from.'],
            ['replyTo(string $address, ?string $name = null)', 'Email', 'Email settings', 'message', 'Where replies go. Defaults to messaging.email.reply_to, then the sender.'],
            ['logo(?string $logo)', 'Email', 'Email settings', 'message', 'The logo the template shows. Defaults to messaging.email.logo.'],
            ['organization(?string $name)', 'Email', 'Email settings', 'message', 'The organization name the template shows. Defaults to messaging.email.organization_name, then the app name.'],
            ['emailOptions(array $options)', 'Email', 'Email settings', 'message', 'Options for the email driver, such as template_key to use another ZeptoMail template.'],
            ['emailBody(?string $body)', 'Email', 'Email settings', 'message', 'A body for the email alone. Null uses the shared body.'],
            ['emailHandler(MessageHandler $handler, string $method = \'email\')', 'Email', 'Email settings', 'message', 'Builds the email body with a handler method. A handler without an email() method keeps the shared body.'],
            ['via(MessageChannel|string|array $channels)', 'Both', 'Channels and sending', 'message', 'Chooses the channels, such as \'email\' or [\'sms\', \'email\']. Without it SMS goes out, plus email when there is a subject.'],
            ['send(array|string $tags = [])', 'Both', 'Channels and sending', 'message', 'Dispatches the configured event for this message on its channels and returns it. Tags are searchable in Horizon.'],
            ['sendTo(iterable $recipients, array|string $tags = [], ?callable $each = null)', 'Both', 'Channels and sending', 'message', 'Sends a copy to each recipient on its channels, one event for every messaging.chunk_size recipients. The callback gets the copy and the recipient, and returning false skips the recipient.'],
            ['toSMS(?string $senderId = null)', 'SMS', 'Payloads and getters', 'array', 'The SMS payload item. Throws when there is no recipient or no sender ID.'],
            ['toEmail(?string $subject = null)', 'Email', 'Payloads and getters', 'array', 'The email payload item. Throws when there is no recipient or no subject.'],
            ['getRecipient()', 'Both', 'Payloads and getters', '?Model', 'The recipient, which a handler may read.'],
            ['getBody()', 'Both', 'Payloads and getters', '?string', 'The body written so far, without the header, salutation or footer.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="recipient" title="Recipient">
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['recipient(Model $recipient)', 'Sets who the message is for: a model with a phone_number. Required before toSMS() and toEmail().'],
            ['getRecipient()', 'Returns the recipient, or null. A handler can read it as $this->message->getRecipient().'],
        ]" />
        <p>A <a href="{{ $docs->url('messenger') }}" class="text-primary underline">Messenger</a> starts a message with the recipient, scope, header and footer already set.</p>
    </x-docs::section>

    <x-docs::section id="frame" title="Header, salutation, footer">
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['header(?string $header)', 'Sets the line shown above everything, such as an organisation name. Null removes it.'],
            ['salutation(bool $include = true)', 'Opens the body with Dear {name}, , using the recipient\'s name, which is read when the payload is built, so it does not matter whether recipient() comes before or after. Not calling it leaves the salutation out, and salutation(false) removes it. A recipient with no name gets none.'],
            ['footer(?string $footer)', 'Sets the line shown below the body. Null removes it.'],
            ['senderId(string $senderId)', 'Sets the sender ID the message is sent from. Without it the configured default sender is used. A Messenger sets it from the scope when the scope has one.'],
        ]" />
        <x-docs::code title="Example">
            use Nyumbani\Messaging\Message;

            $message = Message::make()
                ->header('ACME HOMES')
                ->salutation()
                ->footer('Thank you');
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="body" title="Writing the body">
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['text(string $text)', 'Adds text to the end of the body. Runs of spaces become one space, and new lines are kept.'],
            ['newLine(int $count = 1)', 'Adds that many new lines. Use 2 for a blank line.'],
            ['action(string $link, string $action)', 'Adds the label and then the link, as Open: https://... Add a newLine() first, because nothing is put before it.'],
            ['prepend(string $text)', 'Puts text at the very start of the body.'],
            ['append(string $text)', 'Adds text to the end of the body exactly as given, without collapsing spaces.'],
            ['when(bool $condition, callable $then, ?callable $else = null)', 'Calls $then with the message when the condition is true, otherwise $else if one is given.'],
            ['getBody()', 'Returns the body written so far, without the header, salutation or footer. Null when nothing was written.'],
        ]" />
        <x-docs::code title="Example">
            use Nyumbani\Messaging\Message;

            $isOverdue = true;
            $link = 'https://app.test/i/1';

            $message
                ->text('Your invoice is due.')->newLine(2)
                ->action($link, 'View invoice:')
                ->when($isOverdue, fn (Message $message) => $message->prepend('OVERDUE: '));
        </x-docs::code>
        <p>With the header, salutation and footer from the example above, a link of <code>https://app.test/i/1</code> and an overdue invoice, <code>toSMS()['text']</code> is:</p>
        <x-docs::code lang="text" title="Text produced">
            ACME HOMES

            Dear Jane, OVERDUE: Your invoice is due.

            View invoice: https://app.test/i/1

            Thank you
        </x-docs::code>
        <p>Notice that <code>prepend()</code> puts its text at the start of the body, so it lands after the salutation, not before it.</p>
    </x-docs::section>

    <x-docs::section id="handler" title="Building with a handler">
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['handler(MessageHandler $handler, string $method = \'handle\')', 'Lets the handler write the body, using its handle() method or the protected method named. Throws an InvalidArgumentException for a method that cannot be used.'],
        ]" />
        <p>See <a href="{{ $docs->url('sending-with-handlers') }}" class="text-primary underline">Sending SMS with handlers</a> for how to write one.</p>
    </x-docs::section>

    <x-docs::section id="context" title="Scope, category and meta data">
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['scope((Model&MessageScope)|null $scope)', 'Sets the model that owns the message, such as a project or an organisation. It must implement MessageScope, or a TypeError is thrown. Connect receives its morph class and key as scope_type and scope_id. Without it, or with null, the message has no scope.'],
            ['category(?string $category)', 'Sets the category Nyumbani Connect files the SMS under, such as invoice or payment-receipt, so its reports group the messages. Up to 100 characters: a longer one throws a LogicException, because Connect would refuse the whole request. Null or an empty string means none. A handler usually sets it in handle(), and a sendTo() callback can override it for one recipient.'],
            ['metaData(array $metaData)', 'Adds values Connect keeps with the message. Calls merge, and a later value for the same key wins.'],
        ]" />
        <p><code>app_url</code> is always added to the meta data, taken from <code>config('app.url')</code>.</p>
    </x-docs::section>

    <x-docs::section id="to-sms" title="Turning it into a payload">
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['toSMS(?string $senderId = null)', 'Returns the payload item for the recipient. The sender is the argument, else senderId(), else the default. Throws a LogicException when no recipient was set.'],
        ]" />
        <x-docs::code title="Example">
            $item = $message->toSMS('ACME');
        </x-docs::code>
        <x-docs::table :columns="['Key', 'Value']" :rows="[
            ['scope_type, scope_id', 'The morph class and key of the scope set with scope(), or null when none was set.'],
            ['client_type, client_id', 'The morph class and key of the recipient.'],
            ['category', 'The category set with category(), or null when none was set.'],
            ['sender_id', 'The sender from the argument, senderId() or messaging.connect.sms.sender_id. toSMS() throws a LogicException when none of them is set.'],
            ['number', 'The recipient\'s phone_number, as stored. The gateway turns it into 254 and the last nine digits.'],
            ['text', 'The full text: header, salutation, body and footer.'],
            ['meta_data', 'app_url plus anything added with metaData().'],
        ]" />
        <p><code>toSMS()</code> does not queue anything. To send one message call <code>send()</code>, below. To send many at once, put the items in a <code>MessageProcessed</code> payload under <code>sms</code>.</p>
    </x-docs::section>

    <x-docs::section id="send" title="Sending">
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['send(array|string $tags = [])', 'Builds the payload of the message\'s channels and dispatches the event named in messaging.event (MessageProcessed by default). Returns the message.'],
            ['subject(?string $subject)', 'Sets the email subject. A message with a subject goes out by email as well as SMS.'],
            ['sendTo(iterable $recipients, array|string $tags = [], ?callable $each = null)', 'Sends a copy of the message to each recipient, one event for every messaging.chunk_size recipients. salutation() uses the recipient of each copy. The callback gets the copy and its recipient, runs after the shared content, and returning false skips the recipient. Accepts cursor() and lazy().'],
            ['via(MessageChannel|string|array $channels)', 'Chooses the channels, such as [\'sms\', \'email\'] or \'email\', instead of the default. An unknown channel throws a ValueError and an empty list a LogicException.'],
        ]" />
        <x-docs::code title="Example">
            message()
                ->recipient($user)
                ->salutation()
                ->text('Your rent is due on Friday.')
                ->subject('Rent reminder')
                ->via(['sms', 'email'])
                ->send('rent');

            message()->text('Rent is due Friday.')->salutation()->sendTo($tenants, 'rent');
        </x-docs::code>
        <p>Without <code>via()</code>, SMS always goes out and email only when the message has a subject. <code>send()</code> queues one job per message, so for many recipients use <code>sendTo()</code>, which batches them. Tags apply to the whole dispatch, so they are given to <code>sendTo()</code> and not per recipient. Set <code>messaging.event</code> to your own class to dispatch another event; it must extend <code>MessageProcessed</code>, and the package listens to it in place of the original.</p>
    </x-docs::section>

    <x-docs::section id="email" title="Email">
        <p>The header, salutation, body and footer are shared with the SMS. These methods only matter to an email. The recipient needs an <code>email</code> and a <code>name</code>.</p>
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['from(string $address, ?string $name = null)', 'Sets the sender of this email. Without it, messaging.email.from is used.'],
            ['replyTo(string $address, ?string $name = null)', 'Sets where replies go. Without it, messaging.email.reply_to is used, then the sender.'],
            ['logo(?string $logo)', 'Sets the logo URL the template shows. Without it, messaging.email.logo is used.'],
            ['organization(?string $name)', 'Sets the organization name the template shows. Without it, messaging.email.organization_name, then the app name.'],
            ['emailOptions(array $options)', 'Sets options the email driver reads. ZeptoMail reads template_key to use another template than the configured one. Calls merge.'],
            ['emailBody(?string $body)', 'Sets a body for the email alone. Null sends the shared body again.'],
            ['emailHandler(MessageHandler $handler, string $method = \'email\')', 'Builds the email-only body with the handler\'s email() method, or the protected method named, and leaves the SMS body alone. A handler with no email() method keeps the shared body.'],
            ['toEmail(string $subject)', 'Returns the email payload item for the recipient. Throws a LogicException when no recipient was set.'],
        ]" />
        <x-docs::table :columns="['Key', 'Value']" :rows="[
            ['scope_type, scope_id, client_type, client_id', 'As for an SMS.'],
            ['address, name', 'The recipient\'s email and name.'],
            ['subject', 'The argument of toEmail().'],
            ['from, reply_to, logo, organization_name', 'What the message set, or null so the gateway fills the configured value.'],
            ['header, salutation, footer', 'Trimmed, and null when not set. The salutation is Dear {name},.'],
            ['body', 'The email body, else the shared body. The driver turns it into the lines the template shows.'],
            ['options', 'What emailOptions() set.'],
            ['meta_data', 'app_url plus anything added with metaData().'],
        ]" />
        <p>Put the item in a <code>MessageProcessed</code> payload under <code>email</code> to send it. See <a href="{{ $docs->url('sending-email') }}" class="text-primary underline">Sending email</a>.</p>
    </x-docs::section>

    <x-docs::section id="handler-class" title="MessageHandler">
        <p><code>Nyumbani\Messaging\MessageHandler</code> is the base class of every handler.</p>
        <x-docs::table :columns="['Member', 'What it does']" :rows="[
            ['handle()', 'Abstract and protected. Write the default body here.'],
            ['$message', 'The Message being built. It is set before handle() runs and holds the last message after.'],
            ['build(Message $message, string $method = \'handle\')', 'Final. Checks the method, attaches the message, runs the method and returns the message. Message::handler() calls it; do not call it yourself.'],
        ]" />
        <p>A handler method is usable only when it belongs to the handler (not to this base class), is protected or public, is not static, is not a magic method and needs no arguments.</p>
    </x-docs::section>

    <x-docs::section id="other" title="The other classes">
        <x-docs::table :columns="['Class', 'What it does']" :rows="[
            ['MessageProcessed', 'The event. new MessageProcessed($payload, $tags) or MessageProcessed::dispatch($payload, $tags); the payload is keyed by channel and the tags are added to the queued jobs.'],
            ['SendMessageNotifications', 'The listener. Sends each channel\'s messages to its gateway unless the channel is switched off, globally or for the scope.'],
            ['SendMessageJob', 'Sends one request through the gateway\'s driver. Rate limited, and fails for good after 3 failed requests (waiting about 60, then 90 seconds), logging Message sending failed.'],
            ['Gateways\\Gateway, Gateways\\SmsGateway, Gateways\\EmailGateway', 'See Gateways.'],
        ]" />
    </x-docs::section>
@endsection
