<?php

namespace Nyumbani\Messaging\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class SendMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted
     */
    public int $tries = 25;

    /**
     * The number of times the job may fail due to a genuine exception
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job for one request of a gateway
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public string $gateway,
        public array $payload,
        public array $tags = [],
    ) {}

    /**
     * Deliver the request, letting a failure throw so the queue retries it
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handle(): void
    {
        (new $this->gateway)->deliver($this->payload);
    }

    /**
     * Define the middleware for the job, which is the rate limit unless the job runs at once on the sync queue
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function middleware(): array
    {
        //  The sync queue runs the job in the request and cannot release it for later, so a limited job would be lost
        $connection = $this->connection ?? config('queue.default');

        if (config("queue.connections.{$connection}.driver") === 'sync') {
            return [];
        }

        return [(new RateLimited('messaging.rate-limit'))->releaseAfter(10)];
    }

    /**
     * Define the backoff strategy
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function backoff(): array
    {
        return [60, 90, 120];
    }

    /**
     * Get the tags that should be assigned to the job
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Log the failure once the job gives up
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function failed(Throwable $exception): void
    {
        logger()->error('Message sending failed', [
            'gateway' => $this->gateway,
            'message' => $exception->getMessage(),
        ]);
    }
}
