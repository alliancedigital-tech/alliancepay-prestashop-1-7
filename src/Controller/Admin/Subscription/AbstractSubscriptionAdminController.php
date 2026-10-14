<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Subscription;

use Context;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers of the subscription admin pages.
 */
abstract class AbstractSubscriptionAdminController extends FrameworkBundleAdminController
{
    protected const TRANSLATION_DOMAIN = 'ModulesAlliancePayAdmin';

    /**
     * Name of the tab (= _legacy_controller) used for the elevated-rights check.
     */
    abstract protected function getTabName(): string;

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->get('doctrine.orm.entity_manager');
    }

    /**
     * @param string $class
     * @return mixed|object
     * @throws CoreException
     */
    protected function getDomainService(string $class)
    {
        return ServiceLocator::get($class);
    }

    /**
     * @return int|null
     */
    protected function getEmployeeId(): ?int
    {
        $employee = Context::getContext()->employee;

        return $employee && $employee->id ? (int) $employee->id : null;
    }

    /**
     * @return bool
     */
    protected function hasElevatedRights(): bool
    {
        return $this->isGranted('delete', $this->getTabName());
    }

    /**
     * @param array $errors
     * @return void
     */
    protected function flashErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $this->addFlash('error', $this->translateMessage($error));
        }
    }

    /**
     * @param array $descriptor
     * @return string
     */
    protected function translateMessage(array $descriptor): string
    {
        return $this->trans($descriptor['message'], self::TRANSLATION_DOMAIN, $descriptor['params'] ?? []);
    }

    /**
     * @param array $errors
     * @return array
     */
    protected function translateErrors(array $errors): array
    {
        return array_map([$this, 'translateMessage'], $errors);
    }

    /**
     * @return void
     */
    protected function denyElevated(): void
    {
        $this->addFlash('error', $this->trans(
            'You do not have the rights to perform this action.',
            self::TRANSLATION_DOMAIN
        ));
    }

    /**
     * @param string $template
     * @param array $parameters
     * @return Response
     */
    protected function renderPage(string $template, array $parameters = []): Response
    {
        return $this->render(
            '@Modules/alliancepay/views/templates/admin/subscription/' . $template,
            $parameters + ['has_elevated_rights' => $this->hasElevatedRights()]
        );
    }

    /**
     * Reads a rules table posted as rules[i][field]; amounts are entered in major units ("199.00").
     *
     * @param Request $request
     * @param string $key
     * @return array raw rules (normalize before use)
     */
    protected function parseRules(Request $request, string $key = 'rules'): array
    {
        $rules = [];

        foreach ((array) $request->request->get($key, []) as $row) {
            if (!is_array($row) || count(array_filter($row, 'strlen')) === 0) {
                continue;
            }

            $amount = isset($row['coinAmount']) ? trim(str_replace(',', '.', (string) $row['coinAmount'])) : '';

            if ($amount !== '' && is_numeric($amount)) {
                $row['coinAmount'] = (int) round(((float) $amount) * 100);
            }

            $rules[] = $row;
        }

        return $rules;
    }
}
