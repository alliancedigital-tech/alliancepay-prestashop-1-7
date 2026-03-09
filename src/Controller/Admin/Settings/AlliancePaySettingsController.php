<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Settings;

use AlliancePay\Config\Config;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AlliancePaySettingsController.
 */
class AlliancePaySettingsController extends FrameworkBundleAdminController
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

    public function __construct()
    {
        parent::__construct();
        $container = SymfonyContainer::getInstance();
        $this->twig = $container->get('twig');
        $this->router = $container->get('router');
        if (class_exists(\AlliancePay\Config\Config::class)) {
            $this->config = \PrestaShop\PrestaShop\Adapter\ServiceLocator::get(
                \AlliancePay\Config\Config::class
            );
        }
    }

    public function indexAction(Request $request): Response
    {
        $config = $this->config->getAllSettings();

        return $this->render('@Modules/alliancepay/views/templates/admin/configure.html.twig',
            [
                'config' => $config,
                'order_states' => $this->config->getOrderStates(),
                'status_page_types' => $this->config->getStatusPageTypes(),
                'save_url' => $this->router->generate('alliance.save.config', [], true),
                'auth_url' => $this->router->generate('alliance.authorize.by.virtual.device', [], true)
            ]
        );
    }
}
