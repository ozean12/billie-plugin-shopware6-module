<?php


namespace BilliePayment\Services;


use ArrayObject;
use Billie\Model\Address;
use Billie\Model\Company;
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

    )
    {
        $this->modelManager = $modelManager;
        $this->configService = $configService;
        $this->sessionService = $sessionService;
    }

    public function updateBillingAddress(Company $company)
    {
        $billingAddress = $this->sessionService->getBillingAddress();
        // write determined address to shopware order address
        $billingAddress->setCompany($company->name);
        $billingAddress->setStreet($company->address->street . ' '.$company->address->houseNumber);
        $billingAddress->setAdditionalAddressLine1($company->address->addition);
        $billingAddress->setZipCode($company->address->postalCode);
        $billingAddress->setCity($company->address->city);
        $billingAddress->setVatId($company->taxId);

        $country = $this->getCountry($company->address->countryCode);
        if ($country) {
            $billingAddress->setCountry($country);
            $billingAddress->setCountry($country);
        }

        $addressAttribute = $billingAddress->getAttribute();
        $addressAttribute->setBillieRegistrationnumber($company->registrationNumber);
        $addressAttribute->setBillieLegalform($company->legalForm);



        $this->modelManager->flush([$billingAddress, $addressAttribute]);
    }

    public function updateSessionAddress(Company $company){
        /** @var ArrayObject $sOrderVariables */
        $sOrderVariables = Shopware()->Session()->sOrderVariables;
        $userData = $sOrderVariables->offsetGet('sUserData');

        $billingAddress = $userData['billingaddress'];
        $shippingAddress = $userData['shippingaddress'];

        $billingAddress['company'] = $company->name;
        $billingAddress['ustid'] = $company->taxId;
        $billingAddress['street'] = $company->address->street . ' '. $company->address->houseNumber;
        $billingAddress['additionalAddressLine1'] = $company->address->addition;
        $billingAddress['zipcode'] = $company->address->postalCode;
        $billingAddress['city'] = $company->address->city;
        $country = $this->getCountry($company->address->countryCode);
        if ($country) {
            $billingAddress['country'] = $country->getId();
        }

        $billingAddress['attributes']['billie_registrationnumber'] = $company->registrationNumber;
        $billingAddress['attributes']['billie_legalform'] = $company->legalForm;

        $userData['billingaddress'] = $billingAddress;
        if($billingAddress['id'] === $shippingAddress['id']) {
            $userData['shippingaddress'] = $userData['billingaddress'];
        }
        $sOrderVariables->offsetSet('sUserData', $userData);
    }

    /**
     * @param string $code
     * @return Country
     */
    private function getCountry($code) {
        /** @var Country $country */
        return $country = $this->modelManager->getRepository(Country::class)
            ->findOneBy(['iso' => $code]);
    }
}
