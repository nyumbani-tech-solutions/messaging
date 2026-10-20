<?php

namespace Nyumbani\Messaging\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Nyumbani\Messaging\Contracts\MessageScope;
use Nyumbani\Messaging\Enums\MessageChannel;
use Nyumbani\Messaging\Events\MessageProcessed;
use Nyumbani\Messaging\Gateways\SmsGateway;
use Nyumbani\Messaging\Messenger;

class SendMessageNotifications
{
    /**
     * Create the listener
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(protected SmsGateway $smsGateway) {}

    /**
     * Send the processed messages through their gateways
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handle(MessageProcessed $event): void
    {
        //  Keep the SMS messages whose channel is on
        $sms = $this->deliverable(MessageChannel::SMS, $event->payload['sms'] ?? []);

        //  Send them
        if (count($sms) > 0) {
            $this->smsGateway->send($sms, $event->tags);
        }
    }

    /**
     * Drop the messages whose channel is off, globally or for their scope
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function deliverable(MessageChannel $channel, array $messages): array
    {
        //  Send nothing when the channel is off globally, before any query
        if (! Messenger::global()->isEnabled($channel)) {
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
     * Determine whether the channel is on for the scope, or globally when the message has no scope that controls its channels
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function isDeliverable(MessageChannel $channel, ?Model $scope): bool
    {
        return ($scope ? Messenger::for($scope) : Messenger::global())->isEnabled($channel);
    }

    /**
     * Load the scope models that control their channels, one query per type, keyed by type and key
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

                //  Skip scopes that do not control their channels
                if (! is_subclass_of($class, MessageScope::class)) {
                    return collect();
                }

                //  Load the scopes in one query
                return $class::query()->whereKey($group->pluck('scope_id')->unique()->all())->get()->keyBy->getKey();
            });
    }
}
