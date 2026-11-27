<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Builds and validates the body of the fiscalization/create request (MerchantFiscalizationRequestDTO).
 */
class FiscalizationRequestBuilder
{
    /**
     * @var FiscalAmountDistributor
     */
    private $distributor;

    /**
     * @var FiscalAttributeResolver
     */
    private $resolver;

    public function __construct(FiscalAmountDistributor $distributor, FiscalAttributeResolver $resolver)
    {
        $this->distributor = $distributor;
        $this->resolver = $resolver;
    }

    /**
     * @param EntityManagerInterface $em
     * @param array $context merchantId, operationId, merchantRequestId, receiptComment, notificationUrl,
     *     lines (id, name, count, amount, productId, attributeId, service), targetKop, defaults
     * @return array{payload: array|null, errors: string[]}
     */
    public function build(EntityManagerInterface $em, array $context): array
    {
        $errors = [];

        foreach (['merchantId', 'operationId', 'merchantRequestId', 'notificationUrl'] as $field) {
            if (trim((string) ($context[$field] ?? '')) === '') {
                $errors[] = 'Required field is empty: ' . $field;
            }
        }

        try {
            $lines = $this->distributor->distribute($context['lines'] ?? [], (int) ($context['targetKop'] ?? 0));
        } catch (RuntimeException $e) {
            return ['payload' => null, 'errors' => array_merge($errors, [$e->getMessage()])];
        }

        $products = [];

        foreach ($lines as $line) {
            $fiscal = $this->resolveFiscal($em, $line, $context['defaults'] ?? []);
            $name = (string) $line['name'];

            if (trim((string) $line['id']) === '' || trim($name) === '') {
                $errors[] = 'An item has no id or name.';
            }

            if ($fiscal['measurementCode'] === null) {
                $errors[] = 'No measurement code for "' . $name . '".';
            }

            if (FiscalSettingsValidator::requiresClassification($fiscal['taxCode']) && $fiscal['classificationCode'] === null) {
                $errors[] = 'No UKTZED code for "' . $name . '" while its tax code is ' . $fiscal['taxCode'] . '.';
            }

            $product = [
                'id' => (string) $line['id'],
                'name' => $name,
                'count' => round((float) $line['count'], 3),
                'measurementCode' => $fiscal['measurementCode'],
                'unitPrice' => round($line['unitKop'] / 100, 2),
            ];

            if ($fiscal['taxCode'] !== null) {
                $product['taxCode'] = $fiscal['taxCode'];
            }
            if ($fiscal['classificationCode'] !== null) {
                $product['classificationCode'] = $fiscal['classificationCode'];
            }
            if ($fiscal['exciseLabels'] !== []) {
                $product['exciseLabels'] = $fiscal['exciseLabels'];
            }

            $products[] = $product;
        }

        if ($products === []) {
            $errors[] = 'There are no items to fiscalize.';
        }

        if ($errors !== []) {
            return ['payload' => null, 'errors' => $errors];
        }

        $payload = [
            'merchantRequestId' => (string) $context['merchantRequestId'],
            'merchantId' => (string) $context['merchantId'],
            'operationId' => (string) $context['operationId'],
        ];

        if (!empty($context['receiptComment'])) {
            $payload['receiptComment'] = (string) $context['receiptComment'];
        }

        $payload['notificationUrl'] = (string) $context['notificationUrl'];
        $payload['products'] = $products;

        return ['payload' => $payload, 'errors' => []];
    }

    private function resolveFiscal(EntityManagerInterface $em, array $line, array $defaults): array
    {
        // Services (shipping, wrapping) have no product record, only the module defaults apply.
        if (!empty($line['service']) || empty($line['productId'])) {
            return $this->resolver->resolveFromRows(null, null, $defaults);
        }

        return $this->resolver->resolve($em, (int) $line['productId'], (int) ($line['attributeId'] ?? 0), $defaults);
    }
}
