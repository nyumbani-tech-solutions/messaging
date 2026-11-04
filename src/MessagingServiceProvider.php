<?php

namespace Nyumbani\Messaging;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nyumbani\Docs\Docs;
use Nyumbani\Docs\DocsRegistry;
use Nyumbani\Messaging\Docs\MessagingDemos;
use Nyumbani\Messaging\Events\MessageProcessed;
use Nyumbani\Messaging\Listeners\SendMessageNotifications;

class MessagingServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function register(): void
    {
        //  Load helpers
        $this->loadHelpersFrom(__DIR__.'/Utils');

        //  Load the configuration file
        $this->mergeConfigFrom(__DIR__.'/../config/messaging.php', 'messaging');
    }

    /**
     * Bootstrap the package services.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function boot(): void
    {
        //  Publish the configuration file
        $this->publishes([
            __DIR__.'/../config/messaging.php' => config_path('messaging.php'),
        ], 'messaging-config');

        //  Define the connect client
        $this->handleConnectClient();

        //  Define the rate limiter
        $this->handleRateLimiter();

        //  Define the listener
        $this->handleListener();

        //  Add the docs, which Nyumbani Docs serves, when it is installed
        if (class_exists(DocsRegistry::class)) {
            $this->registerDocs();
        }
    }

    /**
     * Add the pre-authenticated Nyumbani Connect HTTP client
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function handleConnectClient(): void
    {
        //  Define the macro, reading the config when the client is built
        Http::macro('connect', fn () => Http::baseUrl(config('messaging.connect.base_url'))
            ->asJson()
            ->acceptJson()
            ->withToken(config('messaging.connect.access_token')));
    }

    /**
     * Limit how fast queued messages go out
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function handleRateLimiter(): void
    {
        //  Define the rate
        $rate = config('messaging.rate_limit_per_second');

        //  Define the callback
        $callback = fn () => Limit::perSecond($rate)->by('messages');

        //  Define the rate limiter
        RateLimiter::for('messaging.rate-limit', $callback);
    }

    /**
     * Send processed messages through their gateways
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function handleListener(): void
    {
        //  Get the event, which an app may replace with a class that extends MessageProcessed
        $event = config('messaging.event');

        if (! is_a($event, MessageProcessed::class, true)) {
            throw new InvalidArgumentException('messaging.event must be '.MessageProcessed::class.' or a class that extends it.');
        }

        Event::listen($event, SendMessageNotifications::class);
    }

    /**
     * Add the Messaging docs to the Nyumbani docs, which Nyumbani Docs serves
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function registerDocs(): void
    {
        //  Load the views of the pages
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'messaging');

        //  Describe the pages
        $demos = new MessagingDemos;

        $this->app->make(DocsRegistry::class)->register((new Docs('nyumbani-messaging', 'Messaging', [
            ['title' => 'Getting started', 'pages' => [
                'introduction' => 'Introduction',
                'installation' => 'Installation',
                'configuration' => 'Configuration',
                'walkthrough' => 'Walkthrough',
            ]],
            ['title' => 'Using Messaging', 'pages' => [
                'sending-with-handlers' => 'Sending SMS with handlers',
                'sending-without-handlers' => 'Sending SMS without handlers',
                'sending-email' => 'Sending email',
                'messenger' => 'Messenger',
                'gateways' => 'Gateways',
                'testing-and-troubleshooting' => 'Testing and troubleshooting',
            ]],
            ['title' => 'Reference', 'pages' => [
                'message' => 'Message reference',
            ]],
        ], 'messaging::docs'))
            ->demo('message', $demos->message(...))
            ->demo('scope', $demos->scope(...))
            ->demo('send', $demos->send(...)));
    }

    /**
     * Load every helper file in a folder.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function loadHelpersFrom(string $path): void
    {
        foreach (glob("$path/*.php") as $helper) {
            require_once $helper;
        }
    }
}
