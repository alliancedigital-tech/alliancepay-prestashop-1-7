<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Order;

use AlliancePay\Entity\AllianceOrder;
use AlliancePay\Model\AllianceOrder\UpdateAllianceOrder;
use AlliancePay\Service\Gateway\HttpClient;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class TransactionInfoController.
 */
class TransactionInfoController extends FrameworkBundleAdminController
{
    private $client;

    /**
     * @var UpdateAllianceOrder
     */
    private $updateAllianceOrder;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        HttpClient $client,
        UpdateAllianceOrder $updateAllianceOrder,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->client = $client;
        $this->updateAllianceOrder = $updateAllianceOrder;
        $this->entityManager = $entityManager;
    }

    /**
     * @param int $orderId
     * @return JsonResponse
     */
    public function syncAction(int $orderId): JsonResponse
    {
        try {
            $repository = $this->entityManager->getRepository(AllianceOrder::class);
            $order = $repository->findByOrderId((string) $orderId);
            $hppOrderId = $order->getHppOrderId();
            $result = $this->client->getOrderOperations($hppOrderId);

            if (!empty($result['operations'])) {
                $this->updateAllianceOrder->updateAllianceOrder(
                    $this->entityManager,
                    $result
                );
            }

            return $this->json([
                'success' => $result['success'] ?? true,
                'message' => $result['message'] ?? '',
                'items' => $result['operations'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}