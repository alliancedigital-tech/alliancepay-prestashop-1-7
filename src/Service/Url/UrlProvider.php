<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Url;

use AlliancePay\Config\Config;
use Context;
use Link;

/**
 * Class UrlProvider.
 */
class UrlProvider
{
    /**
     * @return string
     */
    public function getCallbackUrl(): string
    {
        return $this->getLink()->getModuleLink(Config::MODULE_NAME, 'callback', [], true);
    }

    public function getConfirmationUrl(int $cartId, int $orderId, string $customerSecureKey): string
    {
        $link = $this->getLink();

        return $link->getPageLink(
            'order-confirmation',
            true,
            null,
            array(
                'id_cart' => $cartId,
                'id_module' => Config::MODULE_NAME,
                'id_order' => $orderId,
                'key' => $customerSecureKey
            )
        );
    }

    private function getLink(): Link
    {
        $context = Context::getContext();

        return $context->link;
    }
}
