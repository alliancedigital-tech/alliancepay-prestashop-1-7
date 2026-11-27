<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Fiscalization;

use AlliancePay\Service\Fiscalization\FiscalizationService;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * Manual fiscalization buttons of the order page.
 */
class FiscalizationController extends FrameworkBundleAdminController
{
    public const TRANSLATION_DOMAIN = 'ModulesAlliancePayAdmin';

    public const CSRF_TOKEN_ID = 'alliancepay_fiscalization';

    /**
     * @AdminSecurity("is_granted('update', 'AdminOrders')", message="Access denied.")
     */
    public function createAction(Request $request, int $orderId, string $operationId): Response
    {
        return $this->run($request, $orderId, function (FiscalizationService $service) use ($orderId, $operationId) {
            return $service->fiscalize($this->get('doctrine.orm.entity_manager'), $orderId, $operationId);
        });
    }

    /**
     * @AdminSecurity("is_granted('update', 'AdminOrders')", message="Access denied.")
     */
    public function refreshAction(Request $request, int $orderId, string $operationId): Response
    {
        return $this->run($request, $orderId, function (FiscalizationService $service) use ($orderId, $operationId) {
            return $service->refresh($this->get('doctrine.orm.entity_manager'), $orderId, $operationId);
        });
    }

    private function run(Request $request, int $orderId, callable $action): Response
    {
        $token = new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf'));

        if (!$this->get('security.csrf.token_manager')->isTokenValid($token)) {
            $this->addFlash('error', $this->trans('Invalid security token.', self::TRANSLATION_DOMAIN));

            return $this->redirectToOrder($orderId);
        }

        /** @var FiscalizationService $service */
        $service = ServiceLocator::get(FiscalizationService::class);
        $result = $action($service);

        $text = $this->trans($result['message'], self::TRANSLATION_DOMAIN);
        if ($result['details'] !== []) {
            $text .= ' ' . implode('; ', array_map(function ($detail) {
                return $this->trans((string) $detail, self::TRANSLATION_DOMAIN);
            }, $result['details']));
        }

        $this->addFlash($result['success'] ? 'success' : 'error', $text);

        return $this->redirectToOrder($orderId);
    }

    private function redirectToOrder(int $orderId): Response
    {
        return $this->redirectToRoute('admin_orders_view', ['orderId' => $orderId]);
    }
}
