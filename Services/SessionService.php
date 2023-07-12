<?php

namespace BilliePayment\Services;

use Billie\Sdk\Exception\BillieException;
use Billie\Sdk\Exception\GatewayException;
use Billie\Sdk\Model\Address;
use Billie\Sdk\Model\Address as BillieAddress;
use Billie\Sdk\Model\DebtorCompany;
use Billie\Sdk\Model\Request\CreateSessionRequestModel;
use Billie\Sdk\Service\Request\CreateSessionRequest;
use Billie\Sdk\Util\AddressHelper;
use BilliePayment\Components\Api\RequestServiceContainer;
use BilliePayment\Helper\BasketHelper;
use Monolog\Logger;
use Shopware\Bundle\StoreFrontBundle\Struct\Attribute;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Attribute\Payment;
use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address as ShopwareAddress;
use Shopware\Models\Customer\Customer;

class SessionService
{
    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var \BilliePayment\Components\Api\RequestServiceContainer
     */
    private $container;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * @var Customer|null
     */
    private $loadedCustomer;

    /**
     * @var ShopwareAddress|null
     */
    private $loadedBillingAddress;

    /**
     * @var ShopwareAddress|null
     */
    private $loadedShippingAddress;

    public function __construct(
        Logger $logger,
        \Enlight_Components_Session_Namespace $session,
        ModelManager $modelManager,
        RequestServiceContainer $container
    ) {
        $this->session = $session;
        $this->modelManager = $modelManager;
        $this->container = $container;
        $this->logger = $logger;
    }

    public function getCheckoutSessionId($createNew = false)
    {
        if ($createNew) {
            try {
                $sessionId = $this->container->get(CreateSessionRequest::class)->execute(
                    (new CreateSessionRequestModel())
                        ->setMerchantCustomerId($this->getCustomer()->getNumber())
                )->getCheckoutSessionId();
            } catch (BillieException $exception) {
                $context = [];
                if ($exception instanceof GatewayException) {
                    $requestData = $exception->getRequestData();
                    unset($requestData['client_id'], $requestData['client_secret']); // do not log credentials!

                    $context = array_merge($context, [
                        'code' => $exception->getBillieCode(),
                        'request' => $requestData,
                        'response' => $exception->getResponseData(),
                    ]);
                }
                $this->logger->error('Session ID can not be created, Exception: ' . $exception->getMessage(), $context);

                return null;
            }
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
            /** @var Payment|null $attribute */
            $attribute = $repo->findOneBy(['paymentId' => $payment['id']]);

            return $attribute ? $attribute->getBillieDuration() : 0;
        }

        return 0;
    }

    /**
     * @return \Billie\Sdk\Model\Amount
     */
    public function getTotalAmount()
    {
        $basket = $this->getOrderVariables()['sBasket'];

        return BasketHelper::getTotalAmount($basket);
    }

    /**
     * @internal
     */
    public function getCustomersBillingAddress(Customer $customer = null)
    {
        if ($this->loadedBillingAddress) {
            return $this->loadedBillingAddress;
        }

        $addressId = $this->session['checkoutBillingAddressId'];
        if ($addressId > 0) {
            $this->loadedBillingAddress = $this->modelManager->find(ShopwareAddress::class, $addressId);
        } elseif ($customer) {
            $this->loadedBillingAddress = $customer->getDefaultBillingAddress();
        } else {
            $customer = $this->getCustomer();
            if ($customer !== null) {
                $this->loadedBillingAddress = $customer->getDefaultBillingAddress();
            }
        }

        return $this->loadedBillingAddress;
    }

    /**
     * @internal
     */
    public function getCustomersShippingAddress(Customer $customer = null)
    {
        if ($this->loadedShippingAddress) {
            return $this->loadedShippingAddress;
        }

        $addressId = $this->session['checkoutShippingAddressId'];
        if ($addressId > 0) {
            $this->loadedShippingAddress = $this->modelManager->find(ShopwareAddress::class, $addressId);
        } elseif ($customer) {
            $this->loadedShippingAddress = $customer->getDefaultShippingAddress();
        } else {
            $customer = $this->getCustomer();
            if ($customer !== null) {
                $this->loadedShippingAddress = $customer->getDefaultBillingAddress();
            }
        }

        return $this->loadedShippingAddress;
    }

    /**
     * @return DebtorCompany|null
     */
    public function getDebtorCompany()
    {
        $billingAddress = $this->getCustomersBillingAddress();

        if ($billingAddress instanceof ShopwareAddress && !empty($billingAddress->getCompany())) {
            return (new DebtorCompany())
                ->setValidateOnSet(false)
                ->setName($billingAddress->getCompany())
                ->setAddress($this->getAddress($billingAddress));
        }

        return null;
    }

    /**
     * @return Address|null
     */
    public function getShippingAddress()
    {
        $shippingAddress = $this->getCustomersShippingAddress();

        if ($shippingAddress instanceof ShopwareAddress) {
            return $this->getAddress($shippingAddress);
        }

        return null;
    }

    public function getCustomer()
    {
        if ($this->loadedCustomer) {
            return $this->loadedCustomer;
        }

        $customerId = $this->session->get('sUserId');
        if (empty($customerId)) {
            return null;
        }

        return $this->loadedCustomer = $this->modelManager->find(Customer::class, $customerId);
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
     * Write the Billie shipping address to the shopware session (shipping address).
     */
    public function updateShippingAddress(BillieAddress $address)
    {
        $userData = $this->getUserData();

        $userData['shippingaddress'] = $this->updateAddress($userData['shippingaddress'], $address);

        $this->setUserData($userData);
    }

    /**
     * Write the Billie billing address to the shopware session (billing address).
     */
    public function updateBillingAddress(DebtorCompany $debtorCompany)
    {
        $userData = $this->getUserData();

        $userData['billingaddress']['company'] = $debtorCompany->getName();
        $userData['billingaddress'] = $this->updateAddress($userData['billingaddress'], $debtorCompany->getAddress());

        $this->setUserData($userData);
    }

    private function getAddress(ShopwareAddress $customerAddress)
    {
        return (new Address())
            ->setValidateOnSet(false)
            ->setStreet(AddressHelper::getStreetName($customerAddress->getStreet()))
            ->setHouseNumber(AddressHelper::getHouseNumber($customerAddress->getStreet()))
            ->setAddition($customerAddress->getAdditionalAddressLine1())
            ->setPostalCode($customerAddress->getZipcode())
            ->setCity($customerAddress->getCity())
            ->setCountryCode($customerAddress->getCountry()->getIso() ?: 'DE');
    }

    /**
     * @param array $shopwareAddress
     *
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
     * gets the country model by the iso code or the ID.
     *
     * @param string|int $identifier
     *
     * @return Country|null
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
     * @return \ArrayObject|null
     */
    private function getOrderVariables()
    {
        return Shopware()->Session()->offsetGet('sOrderVariables');
    }
}
