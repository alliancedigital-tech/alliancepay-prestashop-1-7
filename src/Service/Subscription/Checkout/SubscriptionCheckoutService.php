<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Checkout;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\Factory\AllianceOrderFactory;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Service\Customer\CustomerDataBuilder;
use AlliancePay\Service\RequestIdentification\GenerateRequestIdentification;
use AlliancePay\Service\Subscription\Api\SubscriptionApiClient;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use AlliancePay\Service\Url\UrlProvider;
use Cart;
use Context;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Order;
use Throwable;

/**
 * Turns a subscription cart into a provider subscription order and a redirect to the Hosted Payment Page.
 */
class SubscriptionCheckoutService
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var SubscriptionApiClient
     */
    private $apiClient;

    /**
     * @var CartSubscriptionService
     */
    private $cartSubscriptionService;

    /**
     * @var CustomerDataBuilder
     */
    private $customerDataBuilder;

    /**
     * @var UrlProvider
     */
    private $urlProvider;

    /**
     * @var AllianceOrderFactory
     */
    private $allianceOrderFactory;

    /**
     * @var AuditLogger
     */
    private $auditLogger;

    /**
     * @var AllianceLogger
     */
    private $logger;

    public function __construct(
        Config $config,
        SubscriptionApiClient $apiClient,
        CartSubscriptionService $cartSubscriptionService,
        CustomerDataBuilder $customerDataBuilder,
        UrlProvider $urlProvider,
        AllianceOrderFactory $allianceOrderFactory,
        AuditLogger $auditLogger,
        AllianceLogger $logger
    ) {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->cartSubscriptionService = $cartSubscriptionService;
        $this->customerDataBuilder = $customerDataBuilder;
        $this->urlProvider = $urlProvider;
        $this->allianceOrderFactory = $allianceOrderFactory;
        $this->auditLogger = $auditLogger;
        $this->logger = $logger;
    }

    public function isSubscriptionCart(EntityManagerInterface $em, Cart $cart): bool
    {
        return $this->config->isSubscriptionsEnabled()
            && !empty($this->cartSubscriptionService->getIntents($em, (int) $cart->id));
    }

    /**
     * @return string[] translated error messages, empty when the cart may be paid
     */
    public function validate(EntityManagerInterface $em, Cart $cart, bool $isLoggedIn): array
    {
        return $this->cartSubscriptionService->translateErrors(
            $this->cartSubscriptionService->validateForPayment($em, $cart, $isLoggedIn)
        );
    }

    /**
     * Called after the PS order is created (same order of steps as the one-time flow).
     *
     * @param Context $context
     * @param Cart $cart
     * @param EntityManagerInterface $em
     * @return array{redirectUrl: string}
     * @throws Exception message is safe to show to the customer
     */
    public function createSubscription(Context $context, Cart $cart, EntityManagerInterface $em): array
    {
        $order = Order::getByCartId((int) $cart->id);
        $plan = $this->cartSubscriptionService->getPlanForCart($em, $cart);

        if (!$order || !$order->id || $plan === null) {
            throw new Exception('Your subscription could not be processed. Please try again.');
        }

        $customer = $order->getCustomer();
        $rules = $plan->getRules();
        $merchantRequestId = GenerateRequestIdentification::generateRequestId();
        $confirmationUrl = $this->urlProvider->getConfirmationUrl(
            (int) $order->id_cart,
            (int) $order->id,
            $customer->secure_key
        );

        $result = $this->apiClient->createSubscriptionOrder([
            'planId' => $plan->getProviderPlanId(),
            'merchantId' => $this->config->getMerchantId(),
            'merchantRequestId' => $merchantRequestId,
            'purpose' => 'Subscription: ' . $plan->getName(),
            'merchantComment' => 'Subscription for order #' . $order->id,
            'notificationUrl' => $this->urlProvider->getCallbackUrl(),
            'statusPageType' => $this->config->getStatusPageType(),
            'successUrl' => $confirmationUrl,
            'failUrl' => $confirmationUrl,
            'hppPaymentMethods' => SubscriptionConfig::HPP_PAYMENT_METHODS,
            'directType' => SubscriptionConfig::DIRECT_TYPE_REDIRECT,
            'customerData' => $this->customerDataBuilder->build($context, $order, $customer),
        ]);

        $hppOrderId = (string) $result->get('hppOrderId');
        $redirectUrl = (string) $result->get('redirectUrl');

        if (!$result->isSuccess() || $hppOrderId === '' || $redirectUrl === '') {
            $this->logger->error('Create subscription order failed: ' . (string) $result->getError());

            throw new Exception('Your subscription could not be processed. Please try again.');
        }

        $firstAmount = (int) ($rules[0]['coinAmount'] ?? 0);

        $subscription = new AllianceSubscription();
        $subscription->setOrderId((int) $order->id)
            ->setCustomerId((int) $order->id_customer)
            ->setPlanId((int) $plan->getId())
            ->setRulesSnapshot($rules)
            ->setProviderOrderId($hppOrderId)
            ->setMerchantRequestId($merchantRequestId)
            ->setStatus(SubscriptionConfig::SUBSCRIPTION_STATUS_PENDING);

        $allianceOrder = $this->allianceOrderFactory->create();
        $allianceOrder->setOrderId((int) $order->id);
        $allianceOrder->setMerchantRequestId($merchantRequestId);
        $allianceOrder->setHppOrderId($hppOrderId);
        $allianceOrder->setMerchantId($this->config->getMerchantId());
        $allianceOrder->setCoinAmount($firstAmount);
        $allianceOrder->setHppPayType(SubscriptionConfig::HPP_PAY_TYPE_SUBSCRIPTION);
        $allianceOrder->setOrderStatus((string) ($result->get('orderStatus') ?: 'CREATED'));
        $allianceOrder->setPaymentMethods(SubscriptionConfig::HPP_PAYMENT_METHODS);
        $allianceOrder->setEcomOrderId((string) $result->get('ecomOrderId'));
        $allianceOrder->setCurrencyCode((int) ($rules[0]['currency'] ?? 0) ?: null);

        try {
            $em->transactional(function (EntityManagerInterface $em) use ($subscription, $allianceOrder) {
                $em->persist($subscription);
                $em->persist($allianceOrder);
            });
        } catch (Throwable $e) {
            $this->logger->critical(
                'Subscription order created at the provider but not saved locally: hppOrderId=' . $hppOrderId
                . ', order=' . (int) $order->id . ': ' . $e->getMessage()
            );

            throw new Exception('Your subscription could not be processed. Please try again.');
        }

        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_SUBSCRIPTION_CREATED,
            ['provider_order_id' => $hppOrderId, 'order_id' => (int) $order->id],
            (int) $plan->getId(),
            $plan->getProductId(),
            (int) $subscription->getId()
        );

        return ['redirectUrl' => $redirectUrl];
    }
}
