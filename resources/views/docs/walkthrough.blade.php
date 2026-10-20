@extends('essentials::docs.layout')

@section('page', 'Walkthrough')
@section('lead', 'Issue an invoice and text both tenants, from the first command to the delivered SMS.')

@section('content')
    <x-essentials::docs.section id="install" title="1. Install and configure">
        <x-essentials::docs.code lang="bash" title="Terminal">
            composer require nyumbani/messaging
            php artisan queue:work
        </x-essentials::docs.code>
        <p>Set <code>NYUMBANI_CONNECT_BASE_URL</code> and <code>NYUMBANI_CONNECT_TOKEN</code> in <code>.env</code> as described under <a href="{{ $docs->url('installation') }}" class="text-primary underline">Installation</a>. The queue worker must stay running, because nothing is sent until it picks the job up.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="recipient" title="2. Give the recipient a phone number">
        <p>The package reads <code>phone_number</code> from the model it sends to. If your column is called something else, add an accessor.</p>
        <x-essentials::docs.code title="app/Models/Tenant.php">
            use Illuminate\Database\Eloquent\Casts\Attribute;
            use Illuminate\Database\Eloquent\Model;

            class Tenant extends Model
            {
                protected function phoneNumber(): Attribute
                {
                    return Attribute::make(get: fn () => $this->phone);
                }
            }
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="handler" title="3. Write the handler">
        <x-essentials::docs.code title="app/Services/Messaging/Handlers/InvoiceIssuedMessageHandler.php">
            use App\Models\Invoice;
            use Nyumbani\Messaging\MessageHandler;

            class InvoiceIssuedMessageHandler extends MessageHandler
            {
                public function __construct(
                    private readonly Invoice $invoice,
                    private readonly string $payLink,
                ) {}

                protected function handle(): void
                {
                    $due = $this->invoice->due_date->format('j F Y');

                    $this->message
                        ->text("Invoice {$this->invoice->number} is due on {$due}.")->newLine(2)
                        ->action($this->payLink, 'Pay now:');
                }
            }
        </x-essentials::docs.code>
        <p>See <a href="{{ $docs->url('sending-with-handlers') }}" class="text-primary underline">Sending SMS with handlers</a> for the rules.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="send" title="4. Build one message per recipient and send them together">
        <p>The invoice has two tenants. A <a href="{{ $docs->url('messenger') }}" class="text-primary underline">Messenger</a> scoped to the invoice's property gets the header and footer from it once, and each tenant gets their own message from it, because the salutation differs. Both go into one payload.</p>
        <x-essentials::docs.code title="app/Http/Controllers/InvoiceController.php">
            use App\Models\Invoice;
            use App\Models\Tenant;
            use App\Services\Messaging\Handlers\InvoiceIssuedMessageHandler;
            use Illuminate\Http\RedirectResponse;
            use Nyumbani\Messaging\Events\MessageProcessed;
            use Nyumbani\Messaging\Messenger;

            class InvoiceController
            {
                public function issue(Invoice $invoice): RedirectResponse
                {
                    $messenger = Messenger::for($invoice->property);

                    if (! $messenger->canSend()) {
                        return back();
                    }

                    $handler = new InvoiceIssuedMessageHandler($invoice, route('invoices.pay', ['invoice' => $invoice]));

                    $messages = $invoice->tenants->map(fn (Tenant $tenant) => $messenger
                        ->recipient($tenant)
                        ->salutation()
                        ->handler($handler)
                        ->toSMS())->all();

                    MessageProcessed::dispatch(['sms' => $messages], ['invoice-issued']);

                    return back();
                }
            }
        </x-essentials::docs.code>
        <p>The handler is built once and reused for both tenants. Load <code>tenants</code> and <code>property</code> with the invoice so no query runs per tenant.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="item" title="5. What toSMS() returns">
        <p>This is the item for the first tenant. The scope is the property, set by <code>Messenger::for($invoice->property)</code>. The <code>Property</code> model implements <code>MessageScope</code>: it answers <code>ACME HOMES</code> from <code>messageHeader()</code> <code>Thank you</code> from <code>messageFooter()</code> and the sender <code>ACME</code> from <code>messageSenderId()</code>.</p>
        <x-essentials::docs.code lang="json" title="First payload item">
            {
                "scope_type": "property",
                "scope_id": 5,
                "client_id": 12,
                "client_type": "tenant",
                "sender_id": "ACME",
                "number": "0712 345 678",
                "text": "ACME HOMES\n\nDear Jane Wanjiru, Invoice INV-1042 is due on 5 November 2026.\n\nPay now: https://acme.test/invoices/42/pay\n\nThank you",
                "meta_data": {
                    "app_url": "https://acme.test"
                }
            }
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="connect" title="6. What Connect receives">
        <p>The listener hands the two items to the SMS gateway. Both use the sender <code>ACME</code>, so they travel together: one queued job, one request, with the numbers turned into <code>254</code> plus the last nine digits.</p>
        <x-essentials::docs.code lang="text" title="The request">
            POST https://connect.nyumbani.ke/api/sms/send
            Authorization: Bearer {token}
        </x-essentials::docs.code>
        <x-essentials::docs.code lang="json" title="The body, always a list of messages">
            [
                {
                    "scope_type": "property",
                    "scope_id": 5,
                    "client_id": 12,
                    "client_type": "tenant",
                    "recipient": "254712345678",
                    "message": "ACME HOMES\n\nDear Jane Wanjiru, Invoice INV-1042 is due on 5 November 2026.\n\nPay now: https://acme.test/invoices/42/pay\n\nThank you",
                    "sender_id": "ACME",
                    "meta_data": {
                        "app_url": "https://acme.test"
                    }
                },
                {
                    "scope_type": "property",
                    "scope_id": 5,
                    "client_id": 13,
                    "client_type": "tenant",
                    "recipient": "254722000111",
                    "message": "ACME HOMES\n\nDear Peter Otieno, Invoice INV-1042 is due on 5 November 2026.\n\nPay now: https://acme.test/invoices/42/pay\n\nThank you",
                    "sender_id": "ACME",
                    "meta_data": {
                        "app_url": "https://acme.test"
                    }
                }
            ]
        </x-essentials::docs.code>
        <p>Had the tenants been given different sender IDs, there would be one request per sender.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="text" title="7. The two SMS">
        <x-essentials::docs.code lang="text" title="Jane Wanjiru, 0712 345 678">
            ACME HOMES

            Dear Jane Wanjiru, Invoice INV-1042 is due on 5 November 2026.

            Pay now: https://acme.test/invoices/42/pay

            Thank you
        </x-essentials::docs.code>
        <x-essentials::docs.code lang="text" title="Peter Otieno, 0722 000 111">
            ACME HOMES

            Dear Peter Otieno, Invoice INV-1042 is due on 5 November 2026.

            Pay now: https://acme.test/invoices/42/pay

            Thank you
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="deliver" title="8. Delivery">
        <p>The worker posts the request. Connect answers <code>202</code> with <code>Messages queued successfully</code> and takes over from there. Any other answer throws, so the job is retried up to 25 times, after 60, 90 and then 120 seconds, and <code>Message sending failed</code> is logged if it never succeeds. Watch the jobs in Horizon, or in the failed jobs table.</p>
    </x-essentials::docs.section>
@endsection
