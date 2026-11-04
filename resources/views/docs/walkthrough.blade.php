@extends('docs::layout')

@section('page', 'Walkthrough')
@section('lead', 'Issue an invoice and text both tenants, from the first command to the delivered SMS.')

@section('content')
    <x-docs::section id="install" title="1. Install and configure">
        <x-docs::code lang="bash" title="Terminal">
            composer require nyumbani/messaging
            php artisan queue:work
        </x-docs::code>
        <p>Set <code>NYUMBANI_CONNECT_BASE_URL</code> and <code>NYUMBANI_CONNECT_TOKEN</code> in <code>.env</code> as described under <a href="{{ $docs->url('installation') }}" class="text-primary underline">Installation</a>. With <code>QUEUE_CONNECTION=sync</code> messages go out during the request and nothing else is needed. On any other connection, keep a queue worker running, because nothing is sent until it picks the job up.</p>
    </x-docs::section>

    <x-docs::section id="recipient" title="2. Give the recipient a phone number">
        <p>The package reads <code>phone_number</code> from the model it sends to. If your column is called something else, add an accessor.</p>
        <x-docs::code title="app/Models/Tenant.php">
            use Illuminate\Database\Eloquent\Casts\Attribute;
            use Illuminate\Database\Eloquent\Model;

            class Tenant extends Model
            {
                protected function phoneNumber(): Attribute
                {
                    return Attribute::make(get: fn () => $this->phone);
                }
            }
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="handler" title="3. Write the handler">
        <x-docs::code title="app/Services/Messaging/Handlers/InvoiceIssuedMessageHandler.php">
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
                        ->action($this->payLink, 'Pay now:')
                        ->category('invoice');
                }
            }
        </x-docs::code>
        <p><code>category()</code> is the label Nyumbani Connect files the SMS under, so every invoice message is reported together. See <a href="{{ $docs->url('handlers') }}" class="text-primary underline">Message handlers</a> for the rules.</p>
    </x-docs::section>

    <x-docs::section id="send" title="4. Send one message to every recipient">
        <p>The invoice has two tenants. A <a href="{{ $docs->url('messenger') }}" class="text-primary underline">The channel switches</a> scoped to the invoice's property gets the header, footer and sender from it once. <code>sendTo()</code> then sends a copy to each tenant, with the salutation addressed to that tenant, in one event.</p>
        <x-docs::code title="app/Http/Controllers/InvoiceController.php">
            use App\Models\Invoice;
            use App\Models\Tenant;
            use App\Services\Messaging\Handlers\InvoiceIssuedMessageHandler;
            use Illuminate\Http\RedirectResponse;
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

                    $messenger
                        ->message()
                        ->salutation()
                        ->handler($handler)
                        ->sendTo($invoice->tenants, 'invoice-issued');

                    return back();
                }
            }
        </x-docs::code>
        <p>The handler is built once and reused for both tenants. To add something per tenant, pass a callback as the third argument of <code>sendTo()</code>. Load <code>tenants</code> and <code>property</code> with the invoice so no query runs per tenant.</p>
    </x-docs::section>

    <x-docs::section id="item" title="5. What the payload item holds">
        <p>This is the item for the first tenant. The scope is the property, set by <code>Messenger::for($invoice->property)</code>; <code>property</code> and <code>tenant</code> are the names in your app's morph map, which is why <code>scope_type</code> and <code>client_type</code> are not class names. The <code>Property</code> model implements <code>MessageScope</code>: its <code>messageIdentity()</code> gives the header <code>ACME HOMES</code>, the footer <code>Thank you</code> and the sender <code>ACME</code>.</p>
        <x-docs::code lang="json" title="First payload item">
            {
                "scope_type": "property",
                "scope_id": 5,
                "client_id": 12,
                "client_type": "tenant",
                "category": "invoice",
                "sender_id": "ACME",
                "number": "0712 345 678",
                "text": "ACME HOMES\n\nDear Jane Wanjiru, Invoice INV-1042 is due on 5 November 2026.\n\nPay now: https://acme.test/invoices/42/pay\n\nThank you",
                "meta_data": {
                    "app_url": "https://acme.test"
                }
            }
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="connect" title="6. What Connect receives">
        <p>The listener hands the two items to the SMS gateway. Both use the sender <code>ACME</code>, so they travel together: one queued job, one request, with the numbers turned into <code>254</code> plus the last nine digits.</p>
        <x-docs::code lang="text" title="The request">
            POST {NYUMBANI_CONNECT_BASE_URL}/api/sms/send
            Authorization: Bearer {token}
        </x-docs::code>
        <x-docs::code lang="json" title="The body, always a list of messages">
            [
                {
                    "scope_type": "property",
                    "scope_id": 5,
                    "client_id": 12,
                    "client_type": "tenant",
                    "category": "invoice",
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
                    "category": "invoice",
                    "recipient": "254722000111",
                    "message": "ACME HOMES\n\nDear Peter Otieno, Invoice INV-1042 is due on 5 November 2026.\n\nPay now: https://acme.test/invoices/42/pay\n\nThank you",
                    "sender_id": "ACME",
                    "meta_data": {
                        "app_url": "https://acme.test"
                    }
                }
            ]
        </x-docs::code>
        <p>Had the tenants been given different sender IDs, there would be one request per sender.</p>
    </x-docs::section>

    <x-docs::section id="text" title="7. The two SMS">
        <x-docs::code lang="text" title="Jane Wanjiru, 0712 345 678">
            ACME HOMES

            Dear Jane Wanjiru, Invoice INV-1042 is due on 5 November 2026.

            Pay now: https://acme.test/invoices/42/pay

            Thank you
        </x-docs::code>
        <x-docs::code lang="text" title="Peter Otieno, 0722 000 111">
            ACME HOMES

            Dear Peter Otieno, Invoice INV-1042 is due on 5 November 2026.

            Pay now: https://acme.test/invoices/42/pay

            Thank you
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="deliver" title="8. Delivery">
        <p>The job posts the request, at once on the sync queue or from a worker otherwise. Any successful answer from Connect ends the job and Connect takes over delivery. Any error answer, or no answer, throws. On a real queue the job is tried again after about 60 seconds, then about 90, and after the third failure <code>Message sending failed</code> is logged and the job lands in the failed jobs table. Watch the jobs in Horizon, or with <code>php artisan queue:failed</code>.</p>
    </x-docs::section>

    <x-docs::section id="next" title="Where to go next">
        <ul class="ml-5 list-disc space-y-1">
            <li><a href="{{ $docs->url('scopes') }}" class="text-primary underline">Scopes and identity</a>: what the property did for the header, footer and sender.</li>
            <li><a href="{{ $docs->url('sending') }}" class="text-primary underline">Sending</a>: <code>send()</code>, <code>sendTo()</code> and the channels.</li>
            <li><a href="{{ $docs->url('testing-and-troubleshooting') }}" class="text-primary underline">Testing and troubleshooting</a>: prove it works without sending a real SMS.</li>
        </ul>
    </x-docs::section>
@endsection
