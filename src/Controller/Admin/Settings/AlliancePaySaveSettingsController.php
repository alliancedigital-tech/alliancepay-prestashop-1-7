<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Settings;

use AlliancePay\Config\Config;
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

    /*public function __construct()
    {
        parent::__construct();
        $container = SymfonyContainer::getInstance();
        $this->twig = $container->get('twig');
        $this->router = $container->get('router');
        $this->config = $container->get('alliance.config');
    }*/

    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function saveAction(Request $request): Response
    {
        $parameters = $request->request->all();

        foreach (Config::PAYMENT_ALL_CONFIG_NAMES as $parameterName) {
            if (!empty($parameters[$parameterName])) {
                $this->config->saveConfig(
                    $parameterName,
                    $parameters[$parameterName]
                );
            }
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
}
