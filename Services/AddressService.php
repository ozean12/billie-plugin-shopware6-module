<?php

namespace BilliePayment\Services;

use Billie\Sdk\Model\Address;
use Billie\Sdk\Model\DebtorCompany;
use Billie\Sdk\Model\Order;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address as ShopwareAddress;

class AddressService
{
    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var ModelManager
     */
    private $modelManager;

    public function __construct(
        ModelManager $modelManager,
        SessionService $sessionService
    ) {
        $this->modelManager = $modelManager;
        $this->sessionService = $sessionService;
    }

    /**
     * writes the Billie-DebtorCompany Model to the Shopware billing address.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateBillingAddress(Order $billieOrder)
    {
        $shopwareAddress = $this->sessionService->getCustomersBillingAddress();
        if ($shopwareAddress) {
            $shopwareAddress->setCompany($billieOrder->getCompany()->getName());
            $shopwareAddress = $this->updateAddress($shopwareAddress, $billieOrder->getBillingAddress());
            $this->modelManager->flush([$shopwareAddress]);
        }
    }

    /**
     * writes a Billie-Address Model to the Shopware shipping address.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateShippingAddress(Address $billieAddress)
    {
        $shopwareAddress = $this->sessionService->getCustomersShippingAddress();
        if ($shopwareAddress) {
            $shopwareAddress = $this->updateAddress($shopwareAddress, $billieAddress);
            $this->modelManager->flush([$shopwareAddress]);
        }
    }

    protected function updateAddress(ShopwareAddress $shopwareAddress, Address $billieAddress)
    {
        $shopwareAddress->setStreet($billieAddress->getStreet() . ' ' . $billieAddress->getHouseNumber());
        $shopwareAddress->setAdditionalAddressLine1($billieAddress->getAddition());
        $shopwareAddress->setZipCode($billieAddress->getPostalCode());
        $shopwareAddress->setCity($billieAddress->getCity());
        if ($country = $this->getCountry($billieAddress->getCountryCode())) {
            $shopwareAddress->setCountry($country);
        }

        return $shopwareAddress;
    }

    /**
     * gets the country model by the iso code.
     *
     * @param string $code
     *
     * @return Country|null
     */
    private function getCountry($code)
    {
        /* @var Country $country */
        return $this->modelManager->getRepository(Country::class)->findOneBy(['iso' => $code]);
    }
}
