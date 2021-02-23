<?php


namespace BilliePayment\Components\Api;


use BilliePayment\Services\ConfigService;
use Shopware\Models\Shop\Shop;

class BillieClientFactory
{

    /**
     * @var ConfigService
     */
    private $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function createBillieClient(Shop $shop = null)
    {
        return \Billie\Sdk\Util\BillieClientFactory::getBillieClientInstance(
            $this->configService->getClientId($shop),
            $this->configService->getClientSecret($shop),
            $this->configService->isSandbox($shop)
        );
    }
}