<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Illuminate\Container\Container;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;

class Subscription
{
    /**
     * Broadcast subscription to client(s).
     *
     * @throws \InvalidArgumentException
     */
    public static function broadcast(string $subscriptionField, $root, ?bool $shouldQueue = null): void
    {
        // Ensure we have a schema and registered subscription fields
        // in the event we are calling this method in code.
        $schemaBuilder = Container::getInstance()->make(SchemaBuilder::class);
        $schemaBuilder->schema();

        $registry = Container::getInstance()->make(SubscriptionRegistry::class);
        if (! $registry->has($subscriptionField)) {
            throw new \InvalidArgumentException("No subscription field registered for {$subscriptionField}");
        }

        // Default to the configuration setting if not specified
        if (null === $shouldQueue) {
            $shouldQueue = config('lighthouse.subscriptions.queue_broadcasts', false);
        }

        $subscription = $registry->subscription($subscriptionField);
        $broadcaster = Container::getInstance()->make(BroadcastsSubscriptions::class);

        try {
            if ($shouldQueue) {
                $broadcaster->queueBroadcast($subscription, $subscriptionField, $root);
            } else {
                $broadcaster->broadcast($subscription, $subscriptionField, $root);
            }
        } catch (\Throwable $e) {
            $exceptionHandler = Container::getInstance()->make(SubscriptionExceptionHandler::class);
            $exceptionHandler->handleBroadcastError($e);
        }
    }
}
