<?php


namespace BilliePayment\Helper;


use Billie\Sdk\Model\DebtorCompany;
use Shopware\Models\Customer\Address;
use Billie\Sdk\Util\AddressHelper as SdkHelper;

class AddressHelper
{

    public static function createDebtorCompany(Address $address, $validateOnSet = true)
    {
        return (new DebtorCompany())
            ->setValidateOnSet($validateOnSet)
            ->setAddress(self::createAddress($address, $validateOnSet))
            ->setName($address->getCompany());
    }

    public static function createAddress(Address $address, $validateOnSet = true)
    {
        $addressModel = (new \Billie\Sdk\Model\Address())
            ->setValidateOnSet($validateOnSet)
            ->setStreet(SdkHelper::getStreetName($address->getStreet()))
            ->setHouseNumber(SdkHelper::getHouseNumber($address->getStreet()))
            ->setPostalCode($address->getZipcode())
            ->setCity($address->getCity())
            ->setCountryCode($address->getCountry()->getIso());

        if (!empty($addition1 = trim($address->getAdditionalAddressLine1()))) {
            $addressModel->setAddition($addition1);
        }

        if (!empty($addition2 = trim($address->getAdditionalAddressLine2()))) {
            $addressModel->setAddition(
                (!empty($addition1) ? $addition1 . ', ' : null) . $addition2
            );
        }
        return $addressModel;
    }

}