<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Subscription;

use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceScheduledPayment;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Repository\ScheduledPaymentRepository;
use AlliancePay\Service\Subscription\ManualRecurringTrigger;
use AlliancePay\Service\Subscription\SubscriptionCancelService;
use AlliancePay\Service\Subscription\SubscriptionDiagnostics;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer subscriptions: list, support diagnostics, cancellation, manual recurring trigger.
 */
class SubscriptionController extends AbstractSubscriptionAdminController
{
    protected function getTabName(): string
    {
        return 'AlliancePaySubscriptions';
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request): Response
    {
        $em = $this->getEntityManager();
        $status = strtoupper((string) $request->query->get('status'));
        $criteria = $status !== '' ? ['status' => $status] : [];
        $rows = [];

        /** @var AllianceSubscription $subscription */
        foreach ($em->getRepository(AllianceSubscription::class)->findBy($criteria, ['id' => 'DESC'], 200) as $subscription) {
            $plan = $em->find(AllianceSubscriptionPlan::class, $subscription->getPlanId());
            $rows[] = ['entity' => $subscription, 'plan' => $plan];
        }

        return $this->renderPage('subscriptions_list.html.twig', [
            'subscriptions' => $rows,
            'status' => $status,
            'statuses' => [
                SubscriptionConfig::SUBSCRIPTION_STATUS_PENDING,
                SubscriptionConfig::SUBSCRIPTION_STATUS_ACTIVE,
                SubscriptionConfig::SUBSCRIPTION_STATUS_CANCELED,
                SubscriptionConfig::SUBSCRIPTION_STATUS_COMPLETED,
                SubscriptionConfig::SUBSCRIPTION_STATUS_FAILED,
            ],
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function showAction(int $id): Response
    {
        $em = $this->getEntityManager();
        /** @var AllianceSubscription|null $subscription */
        $subscription = $em->find(AllianceSubscription::class, $id);

        if ($subscription === null) {
            $this->addFlash('error', $this->trans('Subscription not found.', self::TRANSLATION_DOMAIN));

            return $this->redirectToRoute('alliance.admin.subscription.subscriptions');
        }

        /** @var ScheduledPaymentRepository $paymentRepository */
        $paymentRepository = $em->getRepository(AllianceScheduledPayment::class);
        /** @var SubscriptionDiagnostics $diagnostics */
        $diagnostics = $this->getDomainService(SubscriptionDiagnostics::class);

        // Always a fresh provider request: the local callback copy may be partial or stale.
        $live = $diagnostics->fetchOperations($subscription);

        return $this->renderPage('subscription_show.html.twig', [
            'subscription' => $subscription,
            'plan' => $em->find(AllianceSubscriptionPlan::class, $subscription->getPlanId()),
            'payments' => $paymentRepository->findBySubscription($id),
            'operations' => $live['operations'],
            'operations_warning' => $live['warning'],
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function cancelAction(int $id): Response
    {
        if (!$this->hasElevatedRights()) {
            $this->denyElevated();

            return $this->redirectToRoute('alliance.admin.subscription.subscription', ['id' => $id]);
        }

        $em = $this->getEntityManager();
        $subscription = $em->find(AllianceSubscription::class, $id);

        if ($subscription !== null) {
            /** @var SubscriptionCancelService $service */
            $service = $this->getDomainService(SubscriptionCancelService::class);
            $result = $service->cancel($em, $subscription->getProviderOrderId(), null, $this->getEmployeeId());

            if ($result['success']) {
                $this->addFlash('success', $this->trans('The subscription was canceled.', self::TRANSLATION_DOMAIN));
            } else {
                $this->flashErrors($result['errors']);
            }
        }

        return $this->redirectToRoute('alliance.admin.subscription.subscription', ['id' => $id]);
    }

    /**
     * "Run now": asks the provider to execute a scheduled recurring payment immediately.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function runNowAction(int $paymentId): Response
    {
        $em = $this->getEntityManager();
        /** @var AllianceScheduledPayment|null $payment */
        $payment = $em->find(AllianceScheduledPayment::class, $paymentId);

        if ($payment === null) {
            return $this->redirectToRoute('alliance.admin.subscription.subscriptions');
        }

        if (!$this->hasElevatedRights()) {
            $this->denyElevated();
        } else {
            /** @var ManualRecurringTrigger $trigger */
            $trigger = $this->getDomainService(ManualRecurringTrigger::class);
            $result = $trigger->trigger($em, $payment, (int) $this->getEmployeeId());

            if ($result['success']) {
                $this->addFlash('success', $this->trans('The payment was requested at the provider, the result arrives via callback.', self::TRANSLATION_DOMAIN));
            } else {
                $this->flashErrors($result['errors']);
            }
        }

        return $this->redirectToRoute('alliance.admin.subscription.subscription', ['id' => $payment->getSubscriptionId()]);
    }
}
