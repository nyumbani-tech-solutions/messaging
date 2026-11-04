<?php

namespace Nyumbani\Messaging\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use LogicException;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;
use Nyumbani\Messaging\Events\MessageProcessed;
use Nyumbani\Messaging\Gateways\EmailGateway;
use Nyumbani\Messaging\Gateways\Gateway;
use Nyumbani\Messaging\Gateways\SmsGateway;
use Nyumbani\Messaging\Messenger;

class SendMessageNotifications
{
    /**
     * Create the listener
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        protected SmsGateway $smsGateway,
        protected EmailGateway $emailGateway,
    ) {}

    /**
     * Send the processed messages through their gateways
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handle(MessageProcessed $event): void
    {
        //  Refuse the whole event before any channel is queued when a message has no scope
        $this->assertScoped($event->payload);

        //  Send each channel's messages through its gateway
        foreach (MessageChannel::cases() as $channel) {
            //  Get the payload
            $payload = $event->payload[$channel->value] ?? [];

            //  Keep the messages whose channel is on, unless send() or sendTo() already checked them against their scope
            $messages = $event->isGated ? $payload : $this->deliverable($channel, $payload);

            //  Send them
            if (count($messages) > 0) {
                $this->gateway($channel)->send($messages, $event->tags);
            }
        }
    }

    /**
     * Throw when any message of any channel has no scope, so that nothing is half sent
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function assertScoped(array $payload): void
    {
        foreach (MessageChannel::cases() as $channel) {
            foreach ($payload[$channel->value] ?? [] as $message) {
                if (blank($message['scope_type'] ?? null) || blank($message['scope_id'] ?? null)) {
                    throw new LogicException("A {$channel->value} message has no scope: every message needs a scope_type and a scope_id, which Message::scope() or Messenger::for() sets.");
                }
            }
        }
    }

    /**
     * Get the gateway that delivers a channel
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function gateway(MessageChannel $channel): Gateway
    {
        return match ($channel) {
            MessageChannel::SMS => $this->smsGateway,
            MessageChannel::EMAIL => $this->emailGateway,
        };
    }

    /**
     * Drop the messages whose channel is off, globally or for their scope
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function deliverable(MessageChannel $channel, array $messages): array
    {
        //  Send nothing when the channel is off globally, before any query
        if (! config($channel->enabledConfigKey())) {
            return [];
        }

        //  Load the scopes that control their channels
        $scopes = $this->scopes($messages);

        //  Keep the messages whose scope allows the channel
        return collect($messages)
            ->filter(fn (array $message) => $this->isDeliverable($channel, $scopes->get($message['scope_type'] ?? '')?->get($message['scope_id'] ?? '')))
            ->values()
            ->all();
    }

    /**
     * Determine whether the channel is on for the scope, which is false when the scope cannot be found
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function isDeliverable(MessageChannel $channel, (Model&MessageScope)|null $scope): bool
    {
        return $scope !== null && Messenger::for($scope)->isEnabled($channel);
    }

    /**
     * Load the scope models, one query per type, keyed by type and key
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function scopes(array $messages): Collection
    {
        return collect($messages)
            ->filter(fn (array $message) => filled($message['scope_type'] ?? null) && filled($message['scope_id'] ?? null))
            ->groupBy('scope_type')
            ->map(function (Collection $group, string $type) {
                //  Get the model class of the scope type
                $class = Relation::getMorphedModel($type) ?? $type;

                //  Refuse a scope that cannot control the channels, which is a mistake and not a missing record
                if (! is_subclass_of($class, MessageScope::class)) {
                    throw new LogicException("The scope type {$type} is not a model that implements MessageScope.");
                }

                //  Load the scopes in one query
                return $class::query()
                    ->whereKey($group->pluck('scope_id')->unique()->all())
                    ->get()
                    ->keyBy
                    ->getKey();
            });
    }
}
