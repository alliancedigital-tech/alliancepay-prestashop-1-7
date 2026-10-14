<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Subscription;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AlliancePlanTemplate;
use AlliancePay\Entity\AlliancePlanVersionHistory;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Service\Subscription\Plan\PlanRepricingService;
use AlliancePay\Service\Subscription\Plan\PlanRetirementService;
use AlliancePay\Service\Subscription\Plan\PlanService;
use Context;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Product;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tools;

/**
 * Class PlanController.
 */
class PlanController extends AbstractSubscriptionAdminController
{
    protected function getTabName(): string
    {
        return 'AlliancePayPlans';
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(): Response
    {
        $em = $this->getEntityManager();
        $languageId = (int) Context::getContext()->language->id;
        $plans = [];
        $runningCounts = $em->getRepository(AllianceSubscription::class)->countRunningByPlan();

        /** @var AllianceSubscriptionPlan $plan */
        foreach ($em->getRepository(AllianceSubscriptionPlan::class)->findBy([], ['id' => 'DESC']) as $plan) {
            $catalogCoin = (int) round(Tools::ps_round((float) Product::getPriceStatic($plan->getProductId()), 2) * 100);
            $amounts = array_filter(array_column($plan->getRules(), 'coinAmount'));

            $plans[] = [
                'entity' => $plan,
                'active_subscriptions' => $runningCounts[$plan->getId()] ?? 0,
                'product_name' => Product::getProductName($plan->getProductId(), null, $languageId) ?: ('#' . $plan->getProductId()),
                'catalog_price_differs' => !empty($amounts) && !in_array($catalogCoin, array_map('intval', $amounts), true),
            ];
        }

        /** @var PlanRetirementService $retirement */
        $retirement = $this->getDomainService(PlanRetirementService::class);

        return $this->renderPage('plans_list.html.twig', [
            'plans' => $plans,
            'archive_candidates' => array_map(function (AllianceSubscriptionPlan $plan) {
                return $plan->getId();
            }, $retirement->getArchiveCandidates($em)),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function newAction(Request $request): Response
    {
        $em = $this->getEntityManager();
        /** @var Config $config */
        $config = $this->getDomainService(Config::class);
        $currencyCode = (string) $config->getCurrencyCode($config->getShopCurrencyIsoCode());
        $data = [
            'productId' => '',
            'productLabel' => '',
            'templateId' => '',
            'name' => '',
            'description' => '',
            'categoryIndicator' => '',
            'appliesFrom' => '',
            'rules' => [],
        ];

        if ($request->isMethod('POST')) {
            $data = [
                'productId' => (int) $request->request->get('product_id'),
                'productLabel' => (string) $request->request->get('product_label'),
                'templateId' => (int) $request->request->get('template_id') ?: '',
                'name' => (string) $request->request->get('name'),
                'description' => (string) $request->request->get('description'),
                'categoryIndicator' => (string) $request->request->get('category_indicator'),
                'appliesFrom' => (string) $request->request->get('applies_from'),
                'rules' => $this->parseRules($request),
            ];

            $appliesFrom = null;

            if ($data['appliesFrom'] !== '') {
                try {
                    $appliesFrom = new DateTimeImmutable($data['appliesFrom'], new DateTimeZone('UTC'));
                } catch (Exception $e) {
                    $this->addFlash('error', $this->trans('Invalid "applies from" date.', self::TRANSLATION_DOMAIN));
                }
            }

            if ($data['appliesFrom'] === '' || $appliesFrom !== null) {
                /** @var PlanService $service */
                $service = $this->getDomainService(PlanService::class);
                $result = $service->create($em, array_merge($data, ['appliesFrom' => $appliesFrom]), $this->getEmployeeId());

                if ($result['success']) {
                    $this->addFlash('success', $this->trans('Plan created.', self::TRANSLATION_DOMAIN));

                    return $this->redirectToRoute(
                        'alliance.admin.subscription.plan',
                        ['id' => $result['plan']->getId()]
                    );
                }

                $this->flashErrors($result['errors']);

                if ($result['plan'] !== null) {
                    return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $result['plan']->getId()]);
                }
            }
        }

        $templates = [];

        /** @var AlliancePlanTemplate $template */
        foreach ($em->getRepository(AlliancePlanTemplate::class)->findBy(['status' => SubscriptionConfig::TEMPLATE_STATUS_ACTIVE]) as $template) {
            $templates[] = [
                'id' => $template->getId(),
                'name' => $template->getName(),
                'category' => $template->getCategoryIndicator(),
                'rows' => $template->getRulesTemplate(),
            ];
        }

        return $this->renderPage('plan_form.html.twig', [
            'data' => $data,
            'templates' => $templates,
            'currency_code' => $currencyCode,
            'currency_iso' => $config->getShopCurrencyIsoCode(),
            'interval_units' => SubscriptionConfig::INTERVAL_UNITS,
            'product_search_url' => $this->generateUrl('alliance.admin.subscription.product_search'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function showAction(int $id): Response
    {
        $em = $this->getEntityManager();
        /** @var AllianceSubscriptionPlan|null $plan */
        $plan = $em->find(AllianceSubscriptionPlan::class, $id);

        if ($plan === null) {
            $this->addFlash('error', $this->trans('Plan not found.', self::TRANSLATION_DOMAIN));

            return $this->redirectToRoute('alliance.admin.subscription.plans');
        }

        $languageId = (int) Context::getContext()->language->id;
        $activeSubscriptions = $em->getRepository(AllianceSubscription::class)->countRunningForPlan($plan->getId());

        return $this->renderPage('plan_show.html.twig', [
            'plan' => $plan,
            'active_subscriptions' => $activeSubscriptions,
            'product_name' => Product::getProductName($plan->getProductId(), null, $languageId) ?: ('#' . $plan->getProductId()),
            'history' => $em->getRepository(AlliancePlanVersionHistory::class)->findBy(['planId' => $id], ['id' => 'DESC']),
            'min_lead_days' => SubscriptionConfig::REPRICING_MIN_LEAD_DAYS,
            'min_date' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify('+' . SubscriptionConfig::REPRICING_MIN_LEAD_DAYS . ' days')->format('Y-m-d'),
            'interval_units' => SubscriptionConfig::INTERVAL_UNITS,
            'can_archive' => !$plan->isActive()
                && $activeSubscriptions === 0
                && $plan->getStatus() === SubscriptionConfig::PLAN_STATUS_ACTIVE,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function retrySyncAction(int $id): Response
    {
        $em = $this->getEntityManager();
        $plan = $em->find(AllianceSubscriptionPlan::class, $id);

        if ($plan !== null) {
            $result = $this->getDomainService(PlanService::class)->retryInfiniteSchedule($em, $plan, $this->getEmployeeId());

            if ($result['success']) {
                $this->addFlash('success', $this->trans('Infinite schedule set, the plan is active.', self::TRANSLATION_DOMAIN));
            } else {
                $this->flashErrors($result['errors']);
            }
        }

        return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $id]);
    }

    /**
     * Scenario "new plan only for new customers": stop offering the plan locally, keep it alive at the provider.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function toggleOfferedAction(int $id): Response
    {
        $em = $this->getEntityManager();
        $plan = $em->find(AllianceSubscriptionPlan::class, $id);

        if ($plan !== null) {
            $this->getDomainService(PlanRetirementService::class)
                ->setOfferedToNewCustomers($em, $plan, !$plan->isActive(), $this->getEmployeeId());
            $this->addFlash('success', $this->trans('Plan availability for new customers updated.', self::TRANSLATION_DOMAIN));
        }

        return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $id]);
    }

    /**
     * Scenario "mass repricing" for ALL current subscribers of the plan.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function repriceAction(Request $request, int $id): Response
    {
        if (!$this->hasElevatedRights()) {
            $this->denyElevated();

            return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $id]);
        }

        $em = $this->getEntityManager();
        $plan = $em->find(AllianceSubscriptionPlan::class, $id);

        if ($plan === null) {
            return $this->redirectToRoute('alliance.admin.subscription.plans');
        }

        try {
            $effectiveFrom = new DateTimeImmutable((string) $request->request->get('effective_from') . ' 00:00:00', new DateTimeZone('UTC'));
        } catch (Exception $e) {
            $this->addFlash('error', $this->trans('Invalid date.', self::TRANSLATION_DOMAIN));

            return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $id]);
        }

        $result = $this->getDomainService(PlanRepricingService::class)->reprice(
            $em,
            $plan,
            $this->parseRules($request),
            $effectiveFrom,
            (bool) $request->request->get('customers_notified'),
            trim((string) $request->request->get('reason')),
            $this->getEmployeeId()
        );

        if ($result['success']) {
            $this->addFlash('success', $this->trans('The new rules are scheduled for all subscribers of the plan.', self::TRANSLATION_DOMAIN));
        } else {
            $this->flashErrors($result['errors']);
        }

        return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $id]);
    }

    /**
     * Manual, confirmed archiving at the provider (never automatic).
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function archiveAction(int $id): Response
    {
        if (!$this->hasElevatedRights()) {
            $this->denyElevated();

            return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $id]);
        }

        $em = $this->getEntityManager();
        $plan = $em->find(AllianceSubscriptionPlan::class, $id);

        if ($plan !== null) {
            $result = $this->getDomainService(PlanRetirementService::class)->archiveAtProvider($em, $plan, $this->getEmployeeId());

            if ($result['success']) {
                $this->addFlash('success', $this->trans('The plan was archived at the provider.', self::TRANSLATION_DOMAIN));
            } else {
                $this->flashErrors($result['errors']);
            }
        }

        return $this->redirectToRoute('alliance.admin.subscription.plan', ['id' => $id]);
    }

    /**
     * Product lookup for the plan form: name and the catalog price used to prefill the rule amounts.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function productSearchAction(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q'));

        if (mb_strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $languageId = (int) Context::getContext()->language->id;
        $found = Product::searchByName($languageId, $query, null, 15) ?: [];
        $items = [];

        foreach ($found as $row) {
            $price = Tools::ps_round((float) Product::getPriceStatic((int) $row['id_product']), 2);
            $items[] = [
                'id' => (int) $row['id_product'],
                'name' => $row['name'],
                'price' => number_format($price, 2, '.', ''),
            ];
        }

        return new JsonResponse($items);
    }
}
