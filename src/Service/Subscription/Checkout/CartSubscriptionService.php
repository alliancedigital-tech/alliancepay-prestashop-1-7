<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Checkout;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceCartSubscription;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Repository\CartSubscriptionRepository;
use AlliancePay\Repository\SubscriptionPlanRepository;
use Cart;
use Context;
use Currency;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Product;

/**
 * The customer's intent "buy this product as a subscription (plan X)" and the checkout guard rules:
 * no guest subscriptions, no mixed carts, no two subscriptions at once.
 *
 * PrestaShop's cart has no custom line fields, so the intent lives in alliance_cart_subscription.
 * The guard applies ONLY when the customer explicitly chose a subscription; a product that merely has
 * a plan is still a normal one-time purchase by default.
 */
class CartSubscriptionService
{
    public const REQUEST_PURCHASE_TYPE = 'alliance_purchase_type';
    public const REQUEST_PLAN_ID = 'alliance_plan_id';
    public const PURCHASE_TYPE_SUBSCRIPTION = 'subscription';

    private const TRANSLATION_DOMAIN = 'Modules.Alliancepay.Shop';

    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Plans a customer can pick for a product in the given cart currency.
     *
     * @param EntityManagerInterface $em
     * @param int $productId
     * @param string|null $currencyIso ISO alpha code of the cart currency, null = no currency filter
     * @return AllianceSubscriptionPlan[]
     */
    public function getOfferedPlans(EntityManagerInterface $em, int $productId, ?string $currencyIso = null): array
    {
        if (!$this->config->isSubscriptionsEnabled()) {
            return [];
        }

        /** @var SubscriptionPlanRepository $repository */
        $repository = $em->getRepository(AllianceSubscriptionPlan::class);
        $plans = $repository->findOfferedByProduct($productId);

        if ($currencyIso === null) {
            return $plans;
        }

        try {
            $numericCode = (string) $this->config->getCurrencyCode(strtoupper($currencyIso));
        } catch (InvalidArgumentException $e) {
            return [];
        }

        return array_values(array_filter($plans, function (AllianceSubscriptionPlan $plan) use ($numericCode) {
            return $plan->getCurrencyCode() === $numericCode;
        }));
    }

    /**
     * @return AllianceCartSubscription[]
     */
    public function getIntents(EntityManagerInterface $em, int $cartId): array
    {
        /** @var CartSubscriptionRepository $repository */
        $repository = $em->getRepository(AllianceCartSubscription::class);

        return $repository->findByCart($cartId);
    }

    public function getPlanForCart(EntityManagerInterface $em, Cart $cart): ?AllianceSubscriptionPlan
    {
        $intents = $this->getIntents($em, (int) $cart->id);

        if (count($intents) !== 1) {
            return null;
        }

        return $em->find(AllianceSubscriptionPlan::class, $intents[0]->getPlanId());
    }

    public function removeIntent(EntityManagerInterface $em, int $cartId, int $productId, int $attributeId): void
    {
        $intent = $em->getRepository(AllianceCartSubscription::class)->findOneBy([
            'cartId' => $cartId,
            'productId' => $productId,
            'productAttributeId' => $attributeId,
        ]);

        if ($intent !== null) {
            $em->remove($intent);
            $em->flush();
        }
    }

    /**
     * Guard for adding/changing a cart line. Saves the intent when a subscription is chosen and allowed.
     *
     * @param EntityManagerInterface $em
     * @param Cart $cart
     * @param int $productId
     * @param int $attributeId
     * @param int $quantity
     * @param string $operator 'up' or 'down'
     * @param bool $isLoggedIn authenticated (non-guest) customer
     * @param string|null $purchaseType value of the request field, null/other = one-time
     * @param int $planId requested plan
     * @param bool $saveIntent persist the intent when the change is allowed (false for a dry-run check)
     * @return array error descriptors, empty when the change is allowed
     */
    public function guardCartChange(
        EntityManagerInterface $em,
        Cart $cart,
        int $productId,
        int $attributeId,
        int $quantity,
        string $operator,
        bool $isLoggedIn,
        ?string $purchaseType,
        int $planId,
        bool $saveIntent = true
    ): array {
        if (!$this->config->isSubscriptionsEnabled() || $quantity <= 0 || $operator === 'down') {
            return [];
        }

        if ($attributeId === 0 && $productId > 0) {
            // The storefront may send no combination: compare against the one PrestaShop will actually use.
            $attributeId = (int) Product::getDefaultAttribute($productId);
        }

        $intents = $cart->id ? $this->getIntents($em, (int) $cart->id) : [];
        $wantsSubscription = $purchaseType === self::PURCHASE_TYPE_SUBSCRIPTION;
        $sameLine = null;

        foreach ($intents as $intent) {
            if ($intent->getProductId() === $productId && $intent->getProductAttributeId() === $attributeId) {
                $sameLine = $intent;
            }
        }

        if (!$wantsSubscription) {
            if (empty($intents)) {
                return [];
            }

            return [$sameLine !== null
                ? ['message' => 'The quantity of a subscription cannot be changed.', 'params' => []]
                : ['message' => 'A subscription cannot be combined with other products. Complete or remove the subscription first.', 'params' => []],
            ];
        }

        if (!$isLoggedIn) {
            return [['message' => 'Please sign in to subscribe. Guest checkout is not available for subscriptions.', 'params' => []]];
        }

        $plan = $planId > 0 ? $em->find(AllianceSubscriptionPlan::class, $planId) : null;

        if ($plan === null || !$plan->isActive() || $plan->getProductId() !== $productId
            || $plan->getStatus() !== SubscriptionConfig::PLAN_STATUS_ACTIVE
        ) {
            return [['message' => 'The selected subscription plan is not available.', 'params' => []]];
        }

        if ($cart->id) {
            $currencyIso = Currency::getIsoCodeById((int) $cart->id_currency);
            $offered = array_filter(
                $this->getOfferedPlans($em, $productId, (string) $currencyIso),
                function (AllianceSubscriptionPlan $offeredPlan) use ($plan) {
                    return $offeredPlan->getId() === $plan->getId();
                }
            );

            if (empty($offered)) {
                return [['message' => 'The selected plan is not available in the cart currency.', 'params' => []]];
            }
        }

        if ($quantity > 1) {
            return [['message' => 'A subscription can only be bought in a quantity of one.', 'params' => []]];
        }

        $products = $cart->id ? $cart->getProducts(true) : [];

        if (!empty($products)) {
            $onlyThisLine = count($products) === 1
                && (int) $products[0]['id_product'] === $productId
                && (int) $products[0]['id_product_attribute'] === $attributeId;

            return [$onlyThisLine
                ? ['message' => 'This subscription is already in your cart.', 'params' => []]
                : ['message' => 'A subscription cannot be combined with other products in one order.', 'params' => []],
            ];
        }

        if (!empty($intents)) {
            return [['message' => 'Only one subscription can be purchased at a time.', 'params' => []]];
        }

        if ($saveIntent) {
            $this->saveIntent($em, (int) $cart->id, $productId, $attributeId, (int) $plan->getId());
        }

        return [];
    }

    /**
     * Final validation right before the order is created.
     *
     * @return array error descriptors
     */
    public function validateForPayment(EntityManagerInterface $em, Cart $cart, bool $isLoggedIn): array
    {
        $intents = $this->getIntents($em, (int) $cart->id);

        if (empty($intents)) {
            return [];
        }

        if (!$isLoggedIn) {
            return [['message' => 'Please sign in to subscribe. Guest checkout is not available for subscriptions.', 'params' => []]];
        }

        if (count($intents) > 1) {
            return [['message' => 'Only one subscription can be purchased at a time.', 'params' => []]];
        }

        $products = $cart->getProducts(true);

        if (count($products) !== 1 || (int) $products[0]['cart_quantity'] !== 1
            || (int) $products[0]['id_product'] !== $intents[0]->getProductId()
        ) {
            return [['message' => 'A subscription cannot be combined with other products in one order.', 'params' => []]];
        }

        $plan = $em->find(AllianceSubscriptionPlan::class, $intents[0]->getPlanId());

        if ($plan === null || $plan->getStatus() !== SubscriptionConfig::PLAN_STATUS_ACTIVE) {
            return [['message' => 'The selected subscription plan is not available.', 'params' => []]];
        }

        return [];
    }

    /**
     * @param array $errors error descriptors
     * @return string[] translated
     */
    public function translateErrors(array $errors): array
    {
        $translator = Context::getContext()->getTranslator();
        $result = [];

        foreach ($errors as $error) {
            $result[] = $translator->trans($error['message'], $error['params'] ?? [], self::TRANSLATION_DOMAIN);
        }

        return $result;
    }

    private function saveIntent(
        EntityManagerInterface $em,
        int $cartId,
        int $productId,
        int $attributeId,
        int $planId
    ): void {
        $intent = $em->getRepository(AllianceCartSubscription::class)->findOneBy([
            'cartId' => $cartId,
            'productId' => $productId,
            'productAttributeId' => $attributeId,
        ]);

        if ($intent === null) {
            $intent = new AllianceCartSubscription();
            $intent->setCartId($cartId)->setProductId($productId)->setProductAttributeId($attributeId);
            $em->persist($intent);
        }

        $intent->setPlanId($planId);
        $em->flush();
    }
}
