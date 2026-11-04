@extends('docs::layout')

@section('page', 'Sending email')
@section('lead', 'Build an email on the same Message as an SMS, brand it, choose its template and send it through ZeptoMail.')

@section('content')
    <x-docs::section id="how" title="How an email is sent">
        <p>Email goes through ZeptoMail and a template you made in your ZeptoMail mail agent. The package does not send the body as HTML. It sends the template key and the pieces the template fills in: the subject, logo, organization name, header, salutation, footer and the body as <strong>lines</strong>. The provider is fixed, so an app cannot send email through another one.</p>
        <p>It is the same flow as an SMS: build a <code>Message</code>, call <code>send()</code> (which dispatches a <code>MessageProcessed</code> event), and the package queues it, limits the speed and retries a failure. Email is off until <code>MESSAGING_EMAIL_ENABLED</code> is true.</p>
    </x-docs::section>

    <x-docs::section id="quick" title="The smallest possible email">
        <p>After setting the ZeptoMail settings in <a href="{{ $docs->url('installation') }}" class="text-primary underline">Installation</a>, this is a complete send. The recipient is a model with an <code>email</code> and a <code>name</code>:</p>
        <x-docs::code>
            message()
                ->recipient($user)                       // a model with email and name
                ->salutation()                           // "Dear Jane,"
                ->text('Your rent is due on Friday.')
                ->subject('Rent reminder')
                ->via('email')                           // email only
                ->send('rent');
        </x-docs::code>
        <p>The sender, reply-to, logo and organization name come from <code>messaging.email</code>. A message with a <code>subject()</code> goes out by SMS and email unless <code>via()</code> chooses the channels: <code>via('email')</code> is email only and <code>via(['sms', 'email'])</code> is both. An email without a subject throws a <code>LogicException</code>. Each channel is switched on or off on its own.</p>
    </x-docs::section>

    <x-docs::section id="branding" title="Branding each message">
        <p>Branding belongs to the message, so emails for different organisations can go out in one <code>sendTo()</code> or dispatch without taking each other's sender or logo. Whatever a message does not set is filled from the config.</p>
        <x-docs::code>
            message()
                ->recipient($tenant)
                ->from('billing@acme.example', 'Acme Homes')
                ->replyTo('help@acme.example')
                ->logo('https://acme.example/logo.png')
                ->organization('Acme Homes')
                ->header('INVOICE')
                ->salutation()
                ->text('Invoice INV-1042 is ready.')->newLine()
                ->action('https://acme.example/i/1042', 'View invoice:')
                ->footer('Thank you')
                ->subject('Your invoice')
                ->via('email')
                ->send('invoice');
        </x-docs::code>
        <p>Emails are grouped by sender, reply-to and template before they are queued, so each request to ZeptoMail carries one brand, with up to 50 recipients.</p>
        <x-docs::callout type="warning" title="Verify the sender domain">
            ZeptoMail refuses a sender whose domain is not verified in the mail agent. The refusal fails the job, and the error is in the failed jobs table.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="template" title="Choosing the template">
        <p>The template named by <code>ZEPTOMAIL_TEMPLATE_KEY</code> is used for every email. A message can use another one with <code>emailOptions()</code>:</p>
        <x-docs::code>
            message()
                ->recipient($tenant)
                ->emailOptions(['template_key' => '2d6f.1c1637.k1.example'])
                ->text('Hello.')
                ->subject('Welcome')
                ->via('email')
                ->send('welcome');
        </x-docs::code>
        <p>An email with no template key anywhere throws a <code>LogicException</code> when it is sent, so a missing setting is loud rather than lost.</p>
        <p>The template must show these merge fields: <code>subject</code>, <code>logo</code>, <code>organization_name</code>, <code>header</code>, <code>salutation</code>, <code>footer</code> and <code>lines</code>. Each entry of <code>lines</code> is <code>{ text }</code>, <code>{ link: { action, url } }</code> or <code>{ break: true }</code>.</p>
    </x-docs::section>

    <x-docs::section id="body" title="One body, or one for email alone">
        <p>The body written with <code>text()</code>, <code>newLine()</code> and <code>action()</code> serves both channels. For email the package splits it into lines: a blank line is a break, a line with a link becomes a link labelled by the words before it (<code>Open:</code> becomes the label <code>Open</code>, and a bare link is labelled <code>Click Here</code>), and any other line is text.</p>
        <p>When the email should say something different, give it a body of its own:</p>
        <x-docs::code>
            $message->emailBody("Your rent is due on Friday.\n\nPay here: https://acme.example/pay");
        </x-docs::code>
        <p>With a handler, add an <code>email()</code> method next to <code>handle()</code> and call <code>emailHandler()</code>. A handler without an <code>email()</code> method keeps the shared body.</p>
        <x-docs::code title="app/Services/Messaging/Handlers/RentDueMessageHandler.php">
            use Nyumbani\Messaging\Message;
            use Nyumbani\Messaging\MessageHandler;

            class RentDueMessageHandler extends MessageHandler
            {
                protected function handle(): void
                {
                    $this->message->text('Rent is due Friday.');
                }

                protected function email(): void
                {
                    $this->message
                        ->text('Your rent is due on Friday.')->newLine(2)
                        ->action('https://acme.example/pay', 'Pay now:');
                }
            }

            $handler = new RentDueMessageHandler;

            message()
                ->recipient($tenant)
                ->handler($handler)         // the SMS body
                ->emailHandler($handler)    // the email body
                ->subject('Rent reminder')
                ->send('rent');
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="dropped" title="What is dropped or refused">
        <x-docs::table :columns="['Situation', 'What happens']" :rows="[
            ['The address is not a valid email', 'The message is dropped silently.'],
            ['Email is switched off, globally or for the scope', 'The message is dropped without any error.'],
            ['No sender in the message or in messaging.email.from', 'A LogicException is thrown when the event is dispatched.'],
            ['No template key in the message or the config', 'A LogicException is thrown when the event is dispatched.'],
            ['ZeptoMail refuses the request', 'The job throws, the queue retries it up to three times, then logs Message sending failed.'],
        ]" />
    </x-docs::section>
@endsection
