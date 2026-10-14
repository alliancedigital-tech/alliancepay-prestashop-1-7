<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Subscription;

use AlliancePay\Entity\AllianceSubscriptionAuditLog;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administrative actions journal for support investigations.
 */
class AuditController extends AbstractSubscriptionAdminController
{
    protected function getTabName(): string
    {
        return 'AlliancePaySubscriptionAudit';
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request): Response
    {
        $action = trim((string) $request->query->get('action'));
        $criteria = $action !== '' ? ['action' => $action] : [];

        return $this->renderPage('audit_list.html.twig', [
            'entries' => $this->getEntityManager()
                ->getRepository(AllianceSubscriptionAuditLog::class)
                ->findBy($criteria, ['id' => 'DESC'], 300),
            'action_filter' => $action,
        ]);
    }
}
