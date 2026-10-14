<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Subscription;

use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AlliancePlanTemplate;
use AlliancePay\Service\Subscription\Plan\TemplateService;
use AlliancePay\Service\Subscription\Rules\TemplateRulesApplier;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan templates: schedule structure without amounts.
 */
class TemplateController extends AbstractSubscriptionAdminController
{
    protected function getTabName(): string
    {
        return 'AlliancePayTemplates';
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(): Response
    {
        $em = $this->getEntityManager();
        /** @var TemplateService $service */
        $service = $this->getDomainService(TemplateService::class);
        $templates = [];

        /** @var AlliancePlanTemplate $template */
        foreach ($em->getRepository(AlliancePlanTemplate::class)->findBy([], ['id' => 'DESC']) as $template) {
            $templates[] = ['entity' => $template, 'used' => $service->isUsed($em, $template)];
        }

        return $this->renderPage('templates_list.html.twig', ['templates' => $templates]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function formAction(Request $request, $id = null): Response
    {
        $em = $this->getEntityManager();
        /** @var TemplateService $service */
        $service = $this->getDomainService(TemplateService::class);
        $template = $id !== null ? $em->find(AlliancePlanTemplate::class, (int) $id) : null;

        if ($id !== null && $template === null) {
            $this->addFlash('error', $this->trans('Template not found.', self::TRANSLATION_DOMAIN));

            return $this->redirectToRoute('alliance.admin.subscription.templates');
        }

        $locked = $template !== null && $service->isUsed($em, $template);
        $data = [
            'code' => $template ? $template->getCode() : '',
            'name' => $template ? $template->getName() : '',
            'categoryIndicator' => $template ? $template->getCategoryIndicator() : '',
            'rows' => $template ? $template->getRulesTemplate() : [],
        ];

        if ($request->isMethod('POST')) {
            $data = [
                'code' => (string) $request->request->get('code'),
                'name' => (string) $request->request->get('name'),
                'categoryIndicator' => (string) $request->request->get('category_indicator'),
                'rows' => array_values(array_filter((array) $request->request->get('rows', []), 'is_array')),
            ];
            $result = $service->save($em, $template, $data, $this->getEmployeeId());

            if ($result['success']) {
                $this->addFlash('success', $this->trans('Template saved.', self::TRANSLATION_DOMAIN));

                return $this->redirectToRoute('alliance.admin.subscription.templates');
            }

            $this->flashErrors($result['errors']);
        }

        return $this->renderPage('template_form.html.twig', [
            'template' => $template,
            'data' => $data,
            'locked' => $locked,
            'stage_types' => TemplateRulesApplier::STAGE_TYPES,
            'interval_units' => SubscriptionConfig::INTERVAL_UNITS,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function archiveAction(int $id): Response
    {
        $em = $this->getEntityManager();
        $template = $em->find(AlliancePlanTemplate::class, $id);

        if ($template !== null) {
            $this->getDomainService(TemplateService::class)->archive($em, $template, $this->getEmployeeId());
            $this->addFlash('success', $this->trans('Template archived.', self::TRANSLATION_DOMAIN));
        }

        return $this->redirectToRoute('alliance.admin.subscription.templates');
    }
}
