<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Settings;

use AlliancePay\Config\Config;
use AlliancePay\Service\Fiscalization\FiscalSettingsValidator;
use Exception;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class AlliancePaySaveSettingsController.
 */
class AlliancePaySaveSettingsController extends FrameworkBundleAdminController
{

    /**
     * @var mixed
     */
    private $twig;

    /**
     * @var mixed
     */
    private $router;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        $twig,
        $router,
        Config $config,
        TranslatorInterface $translator
    ) {
        parent::__construct();
        $this->twig = $twig;
        $this->router = $router;
        $this->config = $config;
        $this->translator = $translator;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function saveAction(Request $request): Response
    {
        $parameters = $request->request->all();

        foreach (Config::PAYMENT_ALL_CONFIG_NAMES as $parameterName) {
            if (isset($parameters[$parameterName]) && $parameters[$parameterName] !== '') {
                $this->config->saveConfig(
                    $parameterName,
                    $parameters[$parameterName]
                );
            }
        }

        $fiscalErrors = $this->saveFiscalSettings($parameters);

        foreach ($fiscalErrors as $error) {
            $this->addFlash('error', $this->translator->trans($error, [], 'ModulesAlliancePayAdmin'));
        }

        $this->addFlash(
            'success',
            $this->translator->trans(
                'Settings saved.',
                [],
                'ModulesAlliancePayAdmin'
            )
        );

        return $this->redirect(
            $this->router->generate('alliance.admin.configure')
        );
    }

    /**
     * Fiscal settings are validated as a whole and, unlike the other settings, may be cleared.
     *
     * @param array $parameters
     * @return string[] validation errors, nothing is saved when there are any
     * @throws Exception
     */
    private function saveFiscalSettings(array $parameters): array
    {
        if (!isset($parameters[Config::FISCAL_ENABLED_CONFIG_NAME])) {
            return [];
        }

        $result = (new FiscalSettingsValidator())->validate(
            [
                'taxCode' => $parameters[Config::FISCAL_TAX_CODE_CONFIG_NAME] ?? '',
                'classificationCode' => $parameters[Config::FISCAL_CLASSIFICATION_CODE_CONFIG_NAME] ?? '',
                'exciseLabels' => $parameters[Config::FISCAL_EXCISE_LABELS_CONFIG_NAME] ?? '',
                'measurementCode' => $parameters[Config::FISCAL_MEASUREMENT_CODE_CONFIG_NAME] ?? '',
            ],
            true,
            true
        );

        if ($result['errors'] !== []) {
            return $result['errors'];
        }

        $values = $result['values'];
        $this->config->saveConfig(
            Config::FISCAL_ENABLED_CONFIG_NAME,
            empty($parameters[Config::FISCAL_ENABLED_CONFIG_NAME]) ? '0' : '1'
        );
        $this->config->saveConfig(
            Config::FISCAL_AUTO_CONFIG_NAME,
            empty($parameters[Config::FISCAL_AUTO_CONFIG_NAME]) ? '0' : '1'
        );
        $this->config->saveConfig(Config::FISCAL_TAX_CODE_CONFIG_NAME, (string) $values['taxCode']);
        $this->config->saveConfig(Config::FISCAL_CLASSIFICATION_CODE_CONFIG_NAME, (string) $values['classificationCode']);
        $this->config->saveConfig(Config::FISCAL_EXCISE_LABELS_CONFIG_NAME, implode(',', $values['exciseLabels']));
        $this->config->saveConfig(Config::FISCAL_MEASUREMENT_CODE_CONFIG_NAME, (string) $values['measurementCode']);

        return [];
    }
}
