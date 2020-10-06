<?php

namespace BilliePayment\Services;

use ArrayObject;
use Billie\Model\Address;
use Billie\Model\DebtorCompany;
use Billie\Model\Order;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Country\Country;

class AddressService
{
    /**
     * @var ConfigService
     */
    private $configService;

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
        ConfigService $configService,
        SessionService $sessionService
    ) {
        $this->modelManager = $modelManager;
        $this->configService = $configService;
        $this->sessionService = $sessionService;
    }

    public function updateBillingAddress(Order $billiOrder, DebtorCompany $company)
    {
        $billingAddress = $this->sessionService->getBillingAddress();
        // write determined address to shopware order address
        $billingAddress->setCompany($company->name);
        $billingAddress->setStreet($company->addressStreet . ' ' . $company->addressHouseNumber);
        $billingAddress->setAdditionalAddressLine1($company->addressAddition);
        $billingAddress->setZipCode($company->addressPostalCode);
        $billingAddress->setCity($company->addressCity);
        $billingAddress->setVatId($billiOrder->debtorCompany->taxId);

        $country = $this->getCountry($company->addressCountry);
        if ($country) {
            $billingAddress->setCountry($country);
            $billingAddress->setCountry($country);
        }

        $this->modelManager->flush([$billingAddress]);
    }

    public function updateSessionAddress(Order $billiOrder, DebtorCompany $company)
    {
        /** @var ArrayObject $sOrderVariables */
        $sOrderVariables = Shopware()->Session()->sOrderVariables;
        $userData = $sOrderVariables->offsetGet('sUserData');

        $billingAddress = $userData['billingaddress'];
        $shippingAddress = $userData['shippingaddress'];

        $billingAddress['company'] = $company->name;
        $billingAddress['ustid'] = $billiOrder->debtorCompany->taxId;
        $billingAddress['street'] = $company->addressStreet . ' ' . $company->addressHouseNumber;
        $billingAddress['additionalAddressLine1'] = $company->addressAddition;
        $billingAddress['zipcode'] = $company->addressPostalCode;
        $billingAddress['city'] = $company->addressCity;
        $country = $this->getCountry($company->addressCountry);
        if ($country) {
            $billingAddress['country'] = $country->getId();
        }

        $billingAddress['attributes']['billie_registrationnumber'] = $billiOrder->debtorCompany->registrationNumber;
        $billingAddress['attributes']['billie_legalform'] = $billiOrder->debtorCompany->legalForm;

        $userData['billingaddress'] = $billingAddress;
        if ($billingAddress['id'] === $shippingAddress['id']) {
            $userData['shippingaddress'] = $userData['billingaddress'];
        }
        $sOrderVariables->offsetSet('sUserData', $userData);
    }

    /**
     * @param string $code
     *
     * @return Country
     */
    private function getCountry($code)
    {
        /* @var Country $country */
        return $country = $this->modelManager->getRepository(Country::class)
            ->findOneBy(['iso' => $code]);
    }
}
