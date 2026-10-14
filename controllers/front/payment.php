<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use AlliancePay\Model\Payment\PaymentProcessorWrapper;
use AlliancePay\Model\Payment\PaymentProcessor;
use AlliancePay\Service\Subscription\Checkout\SubscriptionCheckoutService;

/**
 * Class AlliancePayRedirectProxyModuleFrontController.
 */
class AlliancePayPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @var mixed|object
     */
    private $paymentProcessorWrapper;

    /**
     * @return void
     * @throws Exception
     */
    public function postProcess()
    {
        if ($this->initPaymentProcessorWrapper()) {
            $cart = $this->context->cart;
            $entityManager = $this->container->get('doctrine.orm.entity_manager');
            $subscriptionCheckout = $this->initSubscriptionCheckout();
            $isSubscription = $subscriptionCheckout !== null
                && $subscriptionCheckout->isSubscriptionCart($entityManager, $cart);

            if ($isSubscription) {
                // Authoritative guard (no guest, no mixed cart) BEFORE the PS order exists.
                $customer = $this->context->customer;
                $errors = $subscriptionCheckout->validate(
                    $entityManager,
                    $cart,
                    Validate::isLoadedObject($customer) && $customer->isLogged() && !$customer->isGuest()
                );

                if (!empty($errors)) {
                    $this->context->cookie->alliance_error = implode(' ', $errors);
                    $this->context->cookie->write();
                    Tools::redirect('index.php?controller=cart&action=show');
                }
            }

            $validationResult = $this->module->validateOrder(
                $cart->id,
                (int) Configuration::get('PS_OS_PREPARATION'),
                $cart->getOrderTotal(),
                $this->module->displayName,
                null,
                [],
                null,
                false,
                $this->context->customer->secure_key
            );

            try {
                if (!$validationResult) {
                    throw new Exception(
                        $this->module->l(
                            'Your payment could not be processed. Order validation failed. Please try again.'
                        )
                    );
                }
                $paymentResult = $isSubscription
                    ? $subscriptionCheckout->createSubscription($this->context, $cart, $entityManager)
                    : $this->paymentProcessorWrapper->initPaymentProcessor($this->context, $cart, $entityManager);

                if (!isset($paymentResult['redirectUrl'])) {
                    throw new Exception($this->module->l('Payment failed. Please try again.'));
                }

                Tools::redirect($paymentResult['redirectUrl']);
            } catch (Throwable $exception) {
                $message = $exception->getMessage();

                if ($isSubscription) {
                    $message = $this->context->getTranslator()->trans($message, [], 'Modules.Alliancepay.Shop');
                }

                $this->context->cookie->alliance_error = $message;
                $this->context->cookie->write();
                Tools::redirect('index.php?controller=order&step=1');
            }
        }
    }

    /**
     * @return SubscriptionCheckoutService|null
     */
    private function initSubscriptionCheckout()
    {
        try {
            return ServiceLocator::get(SubscriptionCheckoutService::class);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @return bool
     */
    private function initPaymentProcessorWrapper()
    {
        try {
            $this->paymentProcessorWrapper = ServiceLocator::get('AlliancePay\Model\Payment\PaymentProcessorWrapper');
        } catch (Exception $exception) {
            $this->context->cookie->alliance_error = $this->module->l(
                'Your payment could not be processed. Please try again.'
            );
            $this->context->cookie->write();

            Tools::redirect('index.php?controller=order&step=1');
        }

        return $this->paymentProcessorWrapper instanceof PaymentProcessorWrapper;
    }
}
