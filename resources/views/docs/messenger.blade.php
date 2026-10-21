@extends('docs::layout')

@section('page', 'Messenger')
@section('lead', 'One object per project or organisation: the scope supplies the on/off switches, the header, footer and sender ID, and the messenger starts every message.')

@section('content')
    <x-docs::section id="what" title="What the messenger is">
        <p><code>Messenger::for($scope)</code> stands for the model the messages belong to, such as a project. It answers whether a channel is on, and it starts messages that already carry the recipient, the scope, the header and the footer. Because the gate and the message share one scope, the project's own switch cannot be skipped by accident. A scope that forgets to implement <code>MessageScope</code>, or a variable that turns out to be null, fails at once with a <code>TypeError</code> instead of quietly skipping the switch. An app with no per-scope settings uses <code>Messenger::global()</code>.</p>
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['Messenger::for($scope)', 'Makes the messenger for a scope. The scope is required and must be a model that implements MessageScope, or a TypeError is thrown.'],
            ['Messenger::global()', 'Makes the messenger for no scope: only the global switches apply, and its messages have no scope.'],
            ['recipient(Model $recipient)', 'Starts a Message for the recipient, scoped to the messenger and framed by the scope\'s messageHeader(), messageFooter() and messageSenderId(). The scope is asked once per messenger, not once per recipient.'],
            ['isEnabled(MessageChannel $channel), smsEnabled()', 'True when the channel is on globally and for the scope.'],
            ['canSend()', 'True when at least one channel is on.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="implement" title="1. Let the scope answer">
        <p>Implement <code>MessageScope</code> on the model that owns the setting. It is asked about one <code>MessageChannel</code> at a time, and for the header, footer and sender ID of its messages. Any of those can be a computation, and null means none (the default sender for the sender ID).</p>
        <x-docs::code title="app/Models/Project.php">
            use Illuminate\Database\Eloquent\Model;
            use Nyumbani\Messaging\Contracts\MessageScope;
            use Nyumbani\Messaging\Enums\MessageChannel;

            class Project extends Model implements MessageScope
            {
                public function isMessageChannelEnabled(MessageChannel $channel): bool
                {
                    return match ($channel) {
                        MessageChannel::SMS => (bool) $this->setting('notify_via_sms', true),
                    };
                }

                public function messageHeader(): ?string
                {
                    return $this->name;
                }

                public function messageFooter(): ?string
                {
                    return $this->setting('message_footer');
                }

                public function messageSenderId(): ?string
                {
                    return $this->setting('sms_sender_id');
                }
            }
        </x-docs::code>
        <x-docs::table :columns="['Global switch', 'Scope says', 'Channel is']" :rows="[
            ['off', 'anything', 'off'],
            ['on', 'on, or the scope does not control its channels', 'on'],
            ['on', 'off', 'off'],
        ]" />
            <x-docs::playground demo="scope" title="Try the switches" :fields="[
            ['name' => 'global', 'label' => 'Global switch (MESSAGING_SMS_ENABLED)', 'type' => 'select', 'value' => 'on', 'options' => ['on' => 'on', 'off' => 'off']],
            ['name' => 'scope', 'label' => 'The scope', 'type' => 'select', 'value' => 'on', 'options' => ['on' => 'SMS on for the project', 'off' => 'SMS off for the project', 'none' => 'No scope (Messenger::global())']],
        ]" />
    </x-docs::section>

    <x-docs::section id="flow" title="2. Stop early, then message each recipient">
        <x-docs::code title="app/Services/AdminNotificationService.php">
            use App\Services\Messaging\Handlers\BudgetReachedMessageHandler;
            use Nyumbani\Messaging\Events\MessageProcessed;
            use Nyumbani\Messaging\Messenger;

            $messenger = Messenger::for($this->project);

            if (! $messenger->canSend()) {
                return;
            }

            $sms = $admins->map(fn ($admin) => $messenger
                ->recipient($admin)
                ->salutation()
                ->handler(new BudgetReachedMessageHandler($categoryRows))
                ->toSMS())->all();

            MessageProcessed::dispatch(['sms' => $sms], ['budget']);
        </x-docs::code>
        <p>For an admin named Jane, in a project called ACME HOMES, the text is:</p>
        <x-docs::code lang="text" title="Text produced">
            ACME HOMES

            Dear Jane, Budget reached for Cement and Steel.

            Thank you
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="frame" title="Header, footer and salutation">
        <ul class="ml-5 list-disc space-y-1">
            <li>The scope's <code>messageHeader()</code> and <code>messageFooter()</code> are worked out once, the first time <code>recipient()</code> is called, and every message copies them. A null answer leaves the header or footer out together with its blank line.</li>
            <li>On one message, <code>header()</code> or <code>footer()</code> replaces what the scope gave, and null drops it.</li>
            <li>The sender goes to Connect from the first of these that is set: the argument of <code>toSMS('ACME')</code>, the scope's <code>messageSenderId()</code> (or <code>senderId()</code> on the message), then the <code>messaging.connect.sms.sender_id</code> default.</li>
            <li><code>Messenger::global()</code> has no scope, so its messages have no header or footer unless you set them on the message.</li>
            <li><code>salutation()</code> with no argument uses the recipient's <code>name</code>. <code>salutation('Mr Otieno')</code> sets it by hand, and <code>salutation(null)</code> removes it. It is never added on its own.</li>
            <li>The order of the calls does not matter: the text is put together when <code>toSMS()</code> runs.</li>
        </ul>
        <x-docs::callout type="warning" title="A recipient is required">
            <code>toSMS()</code> and <code>salutation()</code> without a name throw a <code>LogicException</code> when no recipient was set.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="enforced" title="3. The listener enforces it too">
        <p>If a message slips through, the listener checks again before queueing. It looks at the scope each message carries (<code>scope_type</code> and <code>scope_id</code>) and drops the messages whose scope has the channel off. Messages started by a messenger carry the messenger's scope, so a project with SMS off never has its messages queued, even for a recipient that would allow them.</p>
        <p>Scopes are loaded with one query per model type, and only for types that implement <code>MessageScope</code>, so a payload for a thousand recipients of one project costs one query. A message whose scope no longer exists, or that has no scope, is checked against the global switch only.</p>
    </x-docs::section>
@endsection
