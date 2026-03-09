<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\Payment;

use Context;
use Exception;
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;

/**
 * Class PaymentProcessorWrapper.
 */
class PaymentProcessorWrapper
{
    /**
     * @var mixed|object
     */
    private $paymentProcessor;

    /**
     * @var mixed
     */
    private $em;

    /**
     * @param Context $context
     * @param $cart
     * @param $entityManager
     * @return array
     * @throws CoreException
     */
    public function initPaymentProcessor(Context $context, $cart, $entityManager): array
    {
        $isInitDependencies = $this->initDependencies();

        if (!$isInitDependencies) {
            throw new Exception('Your payment could not be processed. Please try again.');
        }

        return $this->paymentProcessor->processPayment($context, $cart, $entityManager);
    }

    /**
     * @return bool
     * @throws CoreException
     */
    private function initDependencies(): bool
    {
        $this->paymentProcessor = ServiceLocator::get('AlliancePay\Model\Payment\PaymentProcessor');

        return $this->paymentProcessor instanceof PaymentProcessor;
    }
}
