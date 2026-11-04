@extends('docs::layout')

@section('page', 'Scopes and identity')
@section('lead', 'Every message belongs to a scope: the model that owns it. The scope decides which channels are on and who the message is from.')

@section('content')
    <x-docs::section id="what" title="What a scope is">
        <p>A scope is the model a message belongs to, such as a project, an organisation or a gym. Nyumbani apps send messages on behalf of something, and that something has its own settings: whether SMS is on for it, the line its messages start with, the sender its customers recognise, the logo on its emails. Instead of repeating those on every message, you define them once on the scope, and every message that belongs to it takes them from there.</p>
        <x-docs::table :columns="['The scope decides', 'Through', 'Example']" :rows="[
            ['Whether a channel is on', 'isMessageChannelEnabled($channel)', 'SMS is switched off for this project'],
            ['Who its messages are from', 'messageIdentity()', 'Header, footer, sender ID, organization name, logo, email sender and reply-to'],
        ]" />
        <x-docs::callout type="note" title="A scope is not optional">
            A message with no scope throws a <code>LogicException</code> when it is built or sent. This keeps who a message is from in one place, so two messages of the same project can never disagree.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="implement" title="1. Make a model a scope">
        <p>Implement <code>MessageScope</code> on the model. It has two methods, and the second returns a <code>MessageIdentity</code>.</p>
        <x-docs::code title="app/Models/Project.php">
            use Illuminate\Database\Eloquent\Model;
            use Nyumbani\Messaging\Contracts\MessageScope;
            use Nyumbani\Messaging\Enums\MessageChannel;
            use Nyumbani\Messaging\MessageIdentity;

            class Project extends Model implements MessageScope
            {
                public function isMessageChannelEnabled(MessageChannel $channel): bool
                {
                    return match ($channel) {
                        MessageChannel::SMS => (bool) $this->setting('notify_via_sms', true),
                        MessageChannel::EMAIL => (bool) $this->setting('notify_via_email', false),
                    };
                }

                public function messageIdentity(): MessageIdentity
                {
                    return new MessageIdentity(
                        organizationName: $this->name,
                        header: $this->name,
                        footer: $this->setting('message_footer'),
                        senderId: $this->setting('sms_sender_id'),
                        logo: $this->avatar,
                        emailFrom: ['address' => $this->email, 'name' => $this->name],
                    );
                }
            }
        </x-docs::code>
        <p>Every value can be a computation, and every argument of <code>MessageIdentity</code> is optional, so a scope that only sends SMS sets just the SMS values.</p>
    </x-docs::section>

    <x-docs::section id="identity" title="2. The identity">
        <x-docs::table :columns="['Argument', 'Used by', 'What it is', 'If you leave it out']" :rows="[
            ['header', 'SMS and email', 'The line every message starts with, such as the project name.', 'No header.'],
            ['footer', 'SMS and email', 'The line every message ends with, such as Thank you.', 'No footer.'],
            ['senderId', 'SMS', 'The sender ID the SMS goes out as.', 'messaging.connect.sms.sender_id.'],
            ['organizationName', 'Email', 'The name the email template shows.', 'An email throws, unless the message sets organization().'],
            ['logo', 'Email', 'An absolute URL of the logo the template shows.', 'An email throws, unless the message sets logo().'],
            ['emailFrom', 'Email', 'The sender, as [\'address\' => ..., \'name\' => ...], on a domain verified in ZeptoMail.', 'An email throws, unless the message sets from().'],
            ['emailReplyTo', 'Email', 'Where replies go, in the same shape.', 'Replies go to the sender.'],
        ]" />
        <p>The identity checks itself: a blank organization name, a logo that is not an absolute URL, or an address that is not an email throws an <code>InvalidArgumentException</code> where the scope is written, not when a customer is texted.</p>
    </x-docs::section>

    <x-docs::section id="wins" title="3. What wins">
        <p>A message can set any of these itself. For each value the message comes first, then the scope, then the default. Where nothing is found, the message either goes without or throws.</p>
        <x-docs::table :columns="['Value', 'On the message', 'From the scope', 'Last resort']" :rows="[
            ['Header', 'header()', 'header', 'None'],
            ['Footer', 'footer()', 'footer', 'None'],
            ['SMS sender ID', 'senderId()', 'senderId', 'messaging.connect.sms.sender_id, else toSMS() throws'],
            ['Organization name', 'organization()', 'organizationName', 'toEmail() throws'],
            ['Logo', 'logo()', 'logo', 'toEmail() throws'],
            ['Email sender', 'from()', 'emailFrom', 'toEmail() throws'],
            ['Reply-to', 'replyTo()', 'emailReplyTo', 'The sender'],
        ]" />
        <p>Because the scope is read when the payload is built, the order of the calls does not matter, and a message scoped with <code>scope($project)</code> gives exactly the same result as one started from <code>Messenger::for($project)</code>.</p>
    </x-docs::section>

    <x-docs::section id="null" title="4. Not set, and none">
        <p>On every message setter, <strong>null means not set</strong>, the same as never calling it: the scope's value applies. To send a message with no header or footer at all, even though the scope has one, say so with a method of its own.</p>
        <x-docs::table :columns="['On the message', 'The header is']" :rows="[
            ['header() never called', 'The scope\'s header'],
            ['header(null)', 'The scope\'s header (it clears anything set before)'],
            ['header(\'OVERDUE\')', 'OVERDUE'],
            ['withoutHeader()', 'None'],
        ]" />
        <p><code>footer()</code> and <code>withoutFooter()</code> work the same way. The other setters, <code>senderId()</code>, <code>organization()</code>, <code>logo()</code>, <code>from()</code> and <code>replyTo()</code>, follow the null rule too.</p>
    </x-docs::section>

    <x-docs::section id="next" title="Where to go next">
        <ul class="ml-5 list-disc space-y-1">
            <li><a href="{{ $docs->url('writing-messages') }}" class="text-primary underline">Writing a message</a> puts the scope to work: <code>message()->scope($project)->...</code>.</li>
            <li><a href="{{ $docs->url('messenger') }}" class="text-primary underline">The channel switches</a> explains how the scope switches a channel off and where that is enforced.</li>
            <li><a href="{{ $docs->url('message') }}" class="text-primary underline">The Message reference</a> lists every setter.</li>
        </ul>
    </x-docs::section>
@endsection
