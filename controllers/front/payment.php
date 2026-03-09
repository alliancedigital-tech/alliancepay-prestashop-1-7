<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use AlliancePay\Model\Payment\PaymentProcessorWrapper;
use AlliancePay\Model\Payment\PaymentProcessor;

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
                $entityManager = $this->container->get('doctrine.orm.entity_manager');
                $paymentResult = $this->paymentProcessorWrapper->initPaymentProcessor($this->context, $cart, $entityManager);

                if (!isset($paymentResult['redirectUrl'])) {
                    throw new Exception($this->module->l('Payment failed. Please try again.'));
                }

                Tools::redirect($paymentResult['redirectUrl']);
            } catch (Exception $exception) {
                $this->context->cookie->alliance_error = $exception->getMessage();
                $this->context->cookie->write();
                Tools::redirect('index.php?controller=order&step=1');
            }
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
