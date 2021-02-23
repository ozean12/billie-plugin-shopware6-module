<?php

namespace BilliePayment\Services;

use ArrayObject;
use Billie\Sdk\Model\Address;
use Billie\Sdk\Model\Address as BillieAddress;
use Billie\Sdk\Model\Amount;
use Billie\Sdk\Model\DebtorCompany;
use Billie\Sdk\Model\Request\CreateSessionRequestModel;
use Billie\Sdk\Service\Request\CreateSessionRequest;
use Billie\Sdk\Util\AddressHelper;
use BilliePayment\Helper\BasketHelper;
use Enlight_Components_Session_Namespace;
use Shopware\Bundle\StoreFrontBundle\Struct\Attribute;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Attribute\Payment;
use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address as ShopwareAddress;
use Shopware\Models\Customer\Customer;

class SessionService
{

    /**
     * @var Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var CreateSessionRequest
     */
    private $createSessionRequest;

    public function __construct(
        Enlight_Components_Session_Namespace $session,
        ModelManager $modelManager,
        CreateSessionRequest $createSessionRequest
    )
    {
        $this->session = $session;
        $this->modelManager = $modelManager;
        $this->createSessionRequest = $createSessionRequest;
    }

    public function getCheckoutSessionId($createNew = false)
    {
        if ($createNew) {
            $sessionId = $this->createSessionRequest->execute(
                (new CreateSessionRequestModel())
                    ->setMerchantCustomerId($this->getCustomer()->getNumber())
            )->getCheckoutSessionId();
            $this->setData('checkoutSessionId', $sessionId);
            return $sessionId;
        }

        return $this->getData('checkoutSessionId');
    }

    public function getBillieDurationForPaymentMethod()
    {
        $payment = $this->getOrderVariables()['sPayment'];
        if (isset($payment['attribute']['billie_duration'])) {
            return intval($payment['attribute']['billie_duration']);
        } elseif (isset($payment['attributes']['core'])) {
            /** @var Attribute $attributeStruct */
            $attributeStruct = $payment['attributes']['core'];
            return intval($attributeStruct->get('billie_duration'));
        } elseif (isset($payment['id'])) {
            $repo = $this->modelManager->getRepository(Payment::class);
            /** @var Payment $attribute */
            $attribute = $repo->findOneBy(['paymentId' => $payment['id']]);
            return $attribute ? $attribute->getBillieDuration() : 0;
        }
        return 0;
    }

    public function getTotalAmount(): Amount
    {
        $basket = $this->getOrderVariables()['sBasket'];
        return BasketHelper::getTotalAmount($basket);
    }

    /**
     * @interal
     */
    public function getShopwareBillingAddress()
    {
        $addressId = $this->session->get('checkoutBillingAddressId');
        if ($addressId === null) {
            $customer = $this->getCustomer();

            return $customer ? $customer->getDefaultBillingAddress() : null;
        }

        return $this->modelManager->find(ShopwareAddress::class, $addressId);
    }

    /**
     * @interal
     */
    public function getShopwareShippingAddress()
    {
        $addressId = $this->session->get('checkoutShippingAddressId');
        if ($addressId === null) {
            $customer = $this->getCustomer();

            return $customer ? $customer->getDefaultShippingAddress() : null;
        }

        return $this->modelManager->find(ShopwareAddress::class, $addressId);
    }

    /**
     * @return DebtorCompany|null
     */
    public function getDebtorCompany()
    {
        $userData = $this->getUserData();

        if (isset($userData['billingaddress'])) {
            return (new DebtorCompany())
                ->setValidateOnSet(false)
                ->setName($userData['billingaddress']['company'])
                ->setAddress($this->getAddress($userData['billingaddress']));
        }
        return null;
    }

    /**
     * @return Address|null
     */
    public function getShippingAddress()
    {
        $userData = $this->getUserData();

        if (isset($userData['shippingaddress'])) {
            return $this->getAddress($userData['shippingaddress']);
        }
        return null;
    }

    private function getAddress($addressDataFromSession)
    {
        return (new Address())
            ->setValidateOnSet(false)
            ->setStreet(AddressHelper::getStreetName($addressDataFromSession['street']))
            ->setHouseNumber(AddressHelper::getHouseNumber($addressDataFromSession['street']))
            ->setAddition($addressDataFromSession['additional_address_line1'])
            ->setPostalCode($addressDataFromSession['zipcode'])
            ->setCity($addressDataFromSession['city'])
            ->setCountryCode($this->getCountry($addressDataFromSession['countryID'])->getIso() ?: 'DE');
    }


    public function getCustomer()
    {
        $userId = $this->session->get('sUserId');

        return $userId ? $this->modelManager->find(Customer::class, $userId) : null;
    }

    public function clearData()
    {
        $this->session->offsetUnset('BilliePayment');
    }

    final public function setData($key, $value = null)
    {
        $session = $this->session->get('BilliePayment', []);
        if ($value) {
            $session[$key] = $value;
        } else {
            unset($session[$key]);
        }
        $this->session->offsetSet('BilliePayment', $session);
    }

    public function getData($key, $default = null)
    {
        $session = $this->session->get('BilliePayment');

        return isset($session[$key]) ? $session[$key] : $default;
    }

    public function getSession()
    {
        return $this->session;
    }

    /**
     * Write the Billie shipping address to the shopware session (shipping address)
     *
     * @param BillieAddress $address
     */
    public function updateShippingAddress(BillieAddress $address)
    {
        $userData = $this->getUserData();

        $userData['shippingaddress'] = $this->updateAddress($userData['shippingaddress'], $address);

        $this->setUserData($userData);
    }

    /**
     * Write the Billie billing address to the shopware session (billing address)
     *
     * @param DebtorCompany $debtorCompany
     */
    public function updateBillingAddress(DebtorCompany $debtorCompany)
    {
        $userData = $this->getUserData();

        $userData['billingaddress']['company'] = $debtorCompany->getName();
        $userData['billingaddress'] = $this->updateAddress($userData['billingaddress'], $debtorCompany->getAddress());

        $this->setUserData($userData);
    }

    /**
     * @param array $shopwareAddress
     * @param BillieAddress $billieAddress
     * @return array
     */
    private function updateAddress($shopwareAddress, BillieAddress $billieAddress)
    {
        $shopwareAddress['street'] = $billieAddress->getStreet() . ' ' . $billieAddress->getHouseNumber();
        $shopwareAddress['additionalAddressLine1'] = $billieAddress->getAddition();
        $shopwareAddress['zipcode'] = $billieAddress->getPostalCode();
        $shopwareAddress['city'] = $billieAddress->getCity();
        $country = $this->getCountry($billieAddress->getCountryCode());
        if ($country) {
            $shopwareAddress['country'] = $country->getId();
        }
        return $shopwareAddress;
    }

    /**
     * gets the country model by the iso code or the ID
     *
     * @param string|int $identifier
     * @return Country
     */
    private function getCountry($identifier)
    {
        if (is_numeric($identifier)) {
            $filter = ['id' => $identifier];
        } else {
            $filter = ['iso' => $identifier];
        }
        /* @var Country $country */
        return $this->modelManager->getRepository(Country::class)->findOneBy($filter);
    }

    private function getUserData()
    {
        $sOrderVariables = $this->getOrderVariables();
        return $sOrderVariables ? $sOrderVariables->offsetGet('sUserData') : [];
    }

    private function setUserData($userData)
    {
        $sOrderVariables = $this->getOrderVariables();
        $sOrderVariables->offsetSet('sUserData', $userData);
    }

    /**
     * @return ArrayObject
     */
    private function getOrderVariables()
    {
        return Shopware()->Session()->offsetGet('sOrderVariables');
    }
}
