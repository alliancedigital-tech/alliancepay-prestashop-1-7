<?php

declare(strict_types=1);

namespace AlliancePay\Controller\Admin\Authorization;

use AlliancePay\Config\Config;
use AlliancePay\Service\Authorization\AuthorizationService;
use Exception;
use AlliancePay\Logger\AllianceLogger as Logger;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\Translation\TranslatorInterface;

class AlliancePayAuthorizeByVirtualDeviceController extends FrameworkBundleAdminController
{
    /**
     * @var object|null
     */
    private $twig;

    /**
     * @var object|null
     */
    private $router;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AuthorizationService
     */
    private $authService;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var TraslatorInterface
     */
    private $translator;

    public function __construct(
        AuthorizationService $authService, 
        Logger $logger,
        TranslatorInterface $translator
    ) {
        parent::__construct();
        $container = SymfonyContainer::getInstance();
        $this->twig = $container->get('twig');
        $this->router = $container->get('router');
        $this->authService = $authService;
        $this->logger = $logger;
        $this->translator = $translator;
    }

    public function authorizeAction(): RedirectResponse
    {
        try {
            $authResult = $this->authService->authorize();

            if (!$authResult) {
                $this->translator->trans(
                    'Failed to authorize.',
                    [],
                    'ModulesAlliancePayAdmin'
                );
                throw new Exception('Failed to authorize.');
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            $message = $this->translator->trans(
                'Authorization failed. Please try again.',
                [],
                'ModulesAlliancePayAdmin'
            );
            $this->addFlash('error',  $message);
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
