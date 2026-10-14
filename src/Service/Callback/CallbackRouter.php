<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Callback;

use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Service\Subscription\Callback\SubscriptionCallbackProcessor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sends a verified callback either to the subscription flow or to the regular one-time payment flow.
 * The subscription is resolved by the provider's unique order id only, never by product or plan.
 */
class CallbackRouter
{
    /**
     * @var CallbackProcessor
     */
    private $orderCallbackProcessor;

    /**
     * @var SubscriptionCallbackProcessor
     */
    private $subscriptionCallbackProcessor;

    public function __construct(
        CallbackProcessor $orderCallbackProcessor,
        SubscriptionCallbackProcessor $subscriptionCallbackProcessor
    ) {
        $this->orderCallbackProcessor = $orderCallbackProcessor;
        $this->subscriptionCallbackProcessor = $subscriptionCallbackProcessor;
    }

    public function route(EntityManagerInterface $em, array $callbackData): void
    {
        $hppOrderId = $callbackData['hppOrderId'] ?? null;

        if (!empty($hppOrderId)) {
            /** @var AllianceSubscription|null $subscription */
            $subscription = $em->getRepository(AllianceSubscription::class)
                ->findOneBy(['providerOrderId' => (string) $hppOrderId]);

            if ($subscription !== null) {
                $this->subscriptionCallbackProcessor->process($em, (int) $subscription->getId(), $callbackData);

                return;
            }
        }

        $this->orderCallbackProcessor->processCallback($em, $callbackData);
    }
}
