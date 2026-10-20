@extends('essentials::docs.layout')

@section('page', 'Sending SMS with handlers')
@section('lead', 'Write a handler for the message type, build the message and dispatch it.')

@section('content')
    <x-essentials::docs.section id="what" title="What a handler is">
        <p>A handler owns the wording of one kind of message, such as a check-in code or an invoice reminder. Keeping it in its own class means the text lives in one place instead of being assembled in controllers, models or jobs. The handler writes only the body; the <code>Message</code> adds the header, salutation and footer around it. To write the text inline instead, see <a href="{{ $docs->url('sending-without-handlers') }}" class="text-primary underline">Sending SMS without handlers</a>.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="write" title="1. Write a handler">
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
        <p>The rules are short:</p>
        <ul class="ml-5 list-disc space-y-1">
            <li>Take everything the text needs in the constructor, as promoted properties.</li>
            <li>Implement <code>handle()</code>, which is abstract, and fill <code>$this->message</code> from it.</li>
            <li>Do not send anything or touch the database. A handler only builds text.</li>
            <li>Keep handlers in the app, for example in <code>app/Services/Messaging/Handlers</code>, and end the class name with <code>MessageHandler</code>.</li>
        </ul>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="send" title="2. Attach it and send">
        <x-essentials::docs.code title="Anywhere in the app">
            use App\Services\Messaging\Handlers\CheckInOtpMessageHandler;
            use Nyumbani\Messaging\Message;
            use Nyumbani\Messaging\MessageProcessed;

            $sms = Message::make()
                ->recipient($user)
                ->salutation()
                ->handler(new CheckInOtpMessageHandler($code))
                ->toSMS('ACME');

            MessageProcessed::dispatch(['sms' => [$sms]], ['otp']);
        </x-essentials::docs.code>
        <p>For a user named Jane and the code 1234, the text of the SMS is:</p>
        <x-essentials::docs.code lang="text" title="Text produced">
            Dear Jane, Your code is 1234.
        </x-essentials::docs.code>
        <p><code>handler()</code> hands the message to the handler, which fills the body, and returns the same message, so <code>toSMS()</code> can follow it directly. A handler instance can be reused for several messages, because each call attaches the message it is given.</p>
        <p><code>recipient()</code> says who the message is for: a model with a <code>phone_number</code>. <code>salutation()</code> with no argument opens the body with <code>Dear {the recipient's name}, </code>. <code>toSMS()</code> takes an optional sender ID and returns the payload item with <code>scope_type</code>, <code>scope_id</code>, <code>client_type</code>, <code>client_id</code>, <code>sender_id</code>, <code>number</code>, <code>text</code> and <code>meta_data</code>. A message has no scope unless you set one with <code>scope($model)</code>, a model that implements <code>MessageScope</code>, or start from a <a href="{{ $docs->url('messenger') }}" class="text-primary underline">Messenger</a>, which sets it for you. Add meta data with <code>metaData([...])</code>; <code>app_url</code> is always included.</p>
        <p>Numbers are turned into <code>254</code> plus the last nine digits, and a message without a usable number is dropped. Connect always receives a list of messages, even for one, and the package refuses an empty or non-list payload. Messages are grouped by <code>sender_id</code> (the config value when it is null) and sent in requests of up to 500. The second argument of the dispatch holds the queue tags.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="conditions" title="3. Links and conditions in the body">
        <x-essentials::docs.code title="app/Services/Messaging/Handlers/InvoiceReminderMessageHandler.php">
            use App\Models\Invoice;
            use Nyumbani\Messaging\MessageHandler;

            class InvoiceReminderMessageHandler extends MessageHandler
            {
                public function __construct(private readonly Invoice $invoice) {}

                protected function handle(): void
                {
                    $this->message
                        ->text("Invoice {$this->invoice->number} of {$this->invoice->amount} is due.")
                        ->when($this->invoice->isOverdue(), fn ($message) => $message->newLine()->text('It is now overdue.'))
                        ->newLine(2)
                        ->action(route('invoices.show', ['invoice' => $this->invoice]), 'View invoice:');
                }
            }
        </x-essentials::docs.code>
        <p>For invoice <code>INV-1</code> of <code>2500.00</code> that is not overdue, sent to a tenant named Jane, the text is:</p>
        <x-essentials::docs.code lang="text" title="Text produced">
            Dear Jane, Invoice INV-1 of 2500.00 is due.

            View invoice: https://app.test/invoices/1
        </x-essentials::docs.code>
        <p>When the invoice is overdue, the <code>when()</code> branch adds a line:</p>
        <x-essentials::docs.code lang="text" title="Text produced when overdue">
            Dear Jane, Invoice INV-1 of 2500.00 is due.
            It is now overdue.

            View invoice: https://app.test/invoices/1
        </x-essentials::docs.code>
        <p>These are the methods a handler can call on the message:</p>
        <x-essentials::docs.table :columns="['Method', 'What it does']" :rows="[
            ['text($text)', 'Adds text to the body. Repeated spaces are collapsed and new lines are kept.'],
            ['newLine($count)', 'Adds one or more new lines.'],
            ['action($link, $label)', 'Adds a label and a link, such as View invoice: https://...'],
            ['prepend($text), append($text)', 'Puts text at the start or the end of the body.'],
            ['when($condition, $then, $else)', 'Builds part of the body only when the condition holds.'],
        ]" />
        <p>The header, salutation, footer, scope and meta data are set on the <code>Message</code> by the caller, not by the handler, so the same handler works for any sender or recipient.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="variants" title="4. Variants of one message">
        <p>When one message comes in several versions, such as a first notice and a reminder, add a protected method for each and pass its name as the second argument.</p>
        <x-essentials::docs.code title="Two versions in one handler">
            use App\Models\Invoice;
            use Nyumbani\Messaging\Message;
            use Nyumbani\Messaging\MessageHandler;

            class InvoiceMessageHandler extends MessageHandler
            {
                public function __construct(private readonly Invoice $invoice) {}

                protected function handle(): void
                {
                    $this->message->text("Invoice {$this->invoice->number} was issued.");
                }

                protected function reminder(): void
                {
                    $this->message->text("Reminder: invoice {$this->invoice->number} is unpaid.");
                }
            }

            $message = Message::make()->handler(new InvoiceMessageHandler($invoice), 'reminder');
        </x-essentials::docs.code>
        <p>For invoice <code>INV-1</code>, the two versions produce:</p>
        <x-essentials::docs.table :columns="['Call', 'Text produced']" :rows="[
            ['handler(new InvoiceMessageHandler($invoice))', 'Invoice INV-1 was issued.'],
            ['handler(new InvoiceMessageHandler($invoice), \'reminder\')', 'Reminder: invoice INV-1 is unpaid.'],
        ]" />
        <p>The method named must belong to the handler, be protected or public, be non-static and need no arguments. Anything else, such as a missing method, a private one, <code>build</code> or a magic method, throws an <code>InvalidArgumentException</code> that names the class and method.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="test" title="5. Test a handler">
        <x-essentials::docs.code title="tests/Feature/InvoiceMessageHandlerTest.php">
            use App\Models\Invoice;
            use App\Models\User;
            use App\Services\Messaging\Handlers\InvoiceMessageHandler;
            use Nyumbani\Messaging\Message;

            it('writes the reminder', function () {
                $user = new User;
                $user->setAttribute('phone_number', '0712345678');
                $invoice = new Invoice(['number' => 'INV-1']);

                $text = Message::make()
                    ->recipient($user)
                    ->handler(new InvoiceMessageHandler($invoice), 'reminder')
                    ->toSMS()['text'];

                expect($text)->toBe('Reminder: invoice INV-1 is unpaid.');
            });
        </x-essentials::docs.code>
        <p>Nothing is queued by this, so no fake is needed. <code>toSMS()['text']</code> is the full text, header and footer included.</p>
    </x-essentials::docs.section>
@endsection
