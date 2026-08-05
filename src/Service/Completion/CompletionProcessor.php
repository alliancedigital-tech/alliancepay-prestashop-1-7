<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Completion;

use AlliancePay\Config\Config;
use AlliancePay\Entity\AllianceOrder;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Model\DateTime\DateTimeNormalizer;
use AlliancePay\Model\Payment\Processor\AbstractProcessor;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Service\Url\UrlProvider;
use Context;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Order;

/**
 * Class CompletionProcessor.
 */
class CompletionProcessor extends AbstractProcessor
{
    private $httpClient;

    private $config;

    private $urlProvider;

    private $dateTimeNormalizer;

    private $allianceLogger;

    public function __construct(
        HttpClient $httpClient,
        Config $config,
        UrlProvider $urlProvider,
        DateTimeNormalizer $dateTimeNormalizer,
        AllianceLogger $allianceLogger
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->urlProvider = $urlProvider;
        $this->dateTimeNormalizer = $dateTimeNormalizer;
        $this->allianceLogger = $allianceLogger;
    }

    /**
     * @param EntityManagerInterface $em
     * @param Order $psOrder
     * @return void
     * @throws Exception
     */
    public function processCompletion(EntityManagerInterface $em, Order $psOrder): void
    {
        /** @var AllianceOrder|null $allianceOrder */
        $allianceOrder = $em->getRepository(AllianceOrder::class)->findByOrderId((string)$psOrder->id);

        if (!$allianceOrder || $allianceOrder->getHppPayType() !== Config::HPP_PAY_TYPE_PREAUTH) {
            return;
        }

        $context = Context::getContext();
        $precision = $context->getComputingPrecision();
        $currentCoinAmount = $this->prepareCoinAmount((float) $psOrder->total_paid_tax_incl, $precision);
        $originalAmount = $allianceOrder->getOriginalAuthorizedAmount();

        if ($originalAmount === null) {
            $this->allianceLogger->error('Original authorized amount is not set for order #' . $psOrder->id);
            throw new Exception('Original authorized amount is not set for order #' . $psOrder->id);
        }

        $minAllowed = (int)round($originalAmount * 0.8);
        $maxAllowed = (int)round($originalAmount * 1.2);

        if ($currentCoinAmount < $minAllowed || $currentCoinAmount > $maxAllowed) {
            $this->allianceLogger->error(
                'Completion amount ' . $currentCoinAmount
                . ' is out of allowed range [' . $minAllowed . ', ' . $maxAllowed . ']'
            );
            throw new Exception(
                'Completion amount ' . $currentCoinAmount
                . ' is out of allowed range [' . $minAllowed . ', ' . $maxAllowed . ']'
            );
        }

        $completionData = [
            'merchantRequestId' => $this->generateMerchantRequestId(),
            'merchantId' => $this->config->getMerchantId(),
            'originalOperationId' => $allianceOrder->getOperationId(),
            'coinAmount' => (string)$currentCoinAmount,
            'date' => $this->dateTimeNormalizer->getRefundDate(),
            'notificationUrl' => $this->urlProvider->getCallbackUrl(),
        ];

        $result = $this->httpClient->executeCompletion($completionData);

        if (isset($result['success']) && $result['success'] === false) {
            $this->allianceLogger->error('Completion API error: ' . ($result['message'] ?? 'unknown error'));
            throw new Exception('Completion API error: ' . ($result['message'] ?? 'unknown error'));
        }
    }
}
