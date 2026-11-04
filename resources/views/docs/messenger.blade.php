@extends('docs::layout')

@section('page', 'The channel switches')
@section('lead', 'A channel goes out only when it is on globally and for the scope. The Messenger asks both, and starts messages for a scope.')

@section('content')
    <x-docs::section id="what" title="1. Two switches, one answer">
        <p>SMS and email each have a global switch (<code>MESSAGING_SMS_ENABLED</code>, <code>MESSAGING_EMAIL_ENABLED</code>) and a switch per <a href="{{ $docs->url('scopes') }}" class="text-primary underline">scope</a>, answered by the scope's <code>isMessageChannelEnabled()</code>. A channel is on only when both are on.</p>
        <x-docs::table :columns="['Global switch', 'Scope says', 'Channel is']" :rows="[
            ['off', 'anything', 'off'],
            ['on', 'off', 'off'],
            ['on', 'on', 'on'],
        ]" />
        <x-docs::playground demo="scope" title="Try the switches" :fields="[
            ['name' => 'global', 'label' => 'Global switch (MESSAGING_SMS_ENABLED)', 'type' => 'select', 'value' => 'on', 'options' => ['on' => 'on', 'off' => 'off']],
            ['name' => 'scope', 'label' => 'The scope', 'type' => 'select', 'value' => 'on', 'options' => ['on' => 'SMS on for the project', 'off' => 'SMS off for the project']],
        ]" />
        <p><code>Messenger::for($scope)</code> asks both switches for you, and starts messages that already carry the scope. There is no messenger without a scope.</p>
        <x-docs::table :columns="['Method', 'What it does']" :rows="[
            ['Messenger::for($scope)', 'Makes the messenger for a scope, which must be a model that implements MessageScope, or a TypeError is thrown.'],
            ['recipient(Model $recipient)', 'Starts a Message for the recipient, scoped to the messenger. It is the same as message()->scope($scope)->recipient($recipient).'],
            ['message()', 'Starts a scoped Message with no recipient yet, for sendTo().'],
            ['isEnabled(MessageChannel $channel), smsEnabled(), emailEnabled()', 'True when the channel is on globally and for the scope.'],
            ['canSend()', 'True when at least one channel is on.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="flow" title="2. Stop early, then message each recipient">
        <x-docs::code title="app/Services/AdminNotificationService.php">
            use App\Services\Messaging\Handlers\BudgetReachedMessageHandler;
            use Nyumbani\Messaging\Messenger;

            $messenger = Messenger::for($this->project);

            if (! $messenger->canSend()) {
                return;
            }

            $messenger
                ->message()
                ->salutation()
                ->handler(new BudgetReachedMessageHandler($categoryRows))
                ->sendTo($admins, 'budget');
        </x-docs::code>
        <p><code>message()</code> starts a message scoped to the project, taking its header, footer, sender and email identity from the project, with no recipient yet. <code>sendTo()</code> sends a copy to each admin in one event per <code>messaging.chunk_size</code> recipients, and <code>salutation()</code> addresses each copy to that admin. For a single recipient, <code>$messenger->recipient($admin)->...->send('budget')</code> does the same for one message.</p>
        <p>For an admin named Jane, in a project called ACME HOMES, the text is:</p>
        <x-docs::code lang="text" title="Text produced">
            ACME HOMES

            Dear Jane, Budget reached for Cement and Steel.

            Thank you
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="enforced" title="3. Where it is enforced">
        <p><code>send()</code> and <code>sendTo()</code> check each message against the scope it already holds, once per scope and channel, and leave out the messages whose channel is off. They then dispatch the event marked as checked, so the listener does not look the scopes up again. A scope that is not saved yet works, and sending costs no extra query. If every channel is off, no event is dispatched.</p>
        <p>An event you dispatch yourself with items you built (<code>MessageProcessed::dispatch(['sms' => $items])</code>) is not marked, so the listener checks it. It reads the scope each item carries (<code>scope_type</code> and <code>scope_id</code>) with one query per model type, so a payload for a thousand recipients of one project costs one query, and it needs the scope to be saved. An item with no <code>scope_type</code> and <code>scope_id</code>, or whose scope type is not a model that implements <code>MessageScope</code>, makes the whole event throw a <code>LogicException</code> before anything is queued. An item whose scope row no longer exists is dropped, because its switch cannot be read.</p>
    </x-docs::section>
@endsection
