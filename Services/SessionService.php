<?php

namespace BilliePayment\Services;

use Billie\Command\CheckoutSessionConfirm;
use Billie\Model\DebtorCompany;
use BilliePayment\Components\Api\Api;
use BilliePayment\Helper\BasketHelper;
use Enlight_Components_Session_Namespace;
use Shopware\Bundle\StoreFrontBundle\Struct\Attribute;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Attribute\Payment;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use stdClass;

class SessionService
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @var Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @var ModelManager
     */
    private $modelManager;

    public function __construct(Enlight_Components_Session_Namespace $session, ModelManager $modelManager, Api $api)
    {
        $this->session = $session;
        $this->modelManager = $modelManager;
        $this->api = $api;
    }

    public function getSessionConfirmModel()
    {
        $model = new CheckoutSessionConfirm($this->getCheckoutSessionId(false) ?: false);
        $model->duration = $this->getBillieDurationForPaymentMethod();
        $amount = $this->getTotalAmount();
        $model->amount = new stdClass();
        $model->amount->grossAmount = $amount['gross'] * 100;
        $model->amount->netAmount = $amount['net'] * 100;
        $model->amount->taxAmount = $amount['tax'] * 100;

        return $model;
    }

    public function getCheckoutSessionId($createNew = false)
    {
        if ($createNew) {
            $sessionId = $this->api->createCheckoutSession($this->getCustomer());
            $this->setData('checkoutSessionId', $sessionId);

            return $sessionId;
        }

        return $this->getData('checkoutSessionId');
    }

    public function getBillieDurationForPaymentMethod()
    {
        $payment = $this->session->get('sOrderVariables')['sPayment'];
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

    public function getTotalAmount($key = null)
    {
        $basket = $this->session->get('sOrderVariables')['sBasket'];
        $totals = BasketHelper::getTotalAmount($basket);

        return $key ? $totals[$key] : $totals;
    }

    public function getBillingAddress()
    {
        $addressId = $this->session->get('checkoutBillingAddressId');
        if ($addressId === null) {
            $customer = $this->getCustomer();

            return $customer ? $customer->getDefaultBillingAddress() : null;
        }

        return $this->modelManager->find(Address::class, $addressId);
    }

    public function getShippingAddress()
    {
        $addressId = $this->session->get('checkoutShippingAddressId');
        if ($addressId === null) {
            $customer = $this->getCustomer();

            return $customer ? $customer->getDefaultShippingAddress() : null;
        }

        return $this->modelManager->find(Address::class, $addressId);
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

    public function getData($key)
    {
        $session = $this->session->get('BilliePayment');

        return isset($session[$key]) ? $session[$key] : null;
    }

    public function getSession()
    {
        return $this->session;
    }

    /**
     * @param DebtorCompany|array $address
     */
    public function setApprovedAddress($address)
    {
        if ($address instanceof DebtorCompany) {
            $address = [
                'name' => $address->name,
                'address_street' => $address->addressStreet,
                'address_house_number' => $address->addressHouseNumber,
                'address_addition' => $address->addressAddition,
                'address_postal_code' => $address->addressPostalCode,
                'address_city' => $address->addressCity,
                'address_country' => $address->addressCountry,
            ];
        }
        $this->setData('approved_address', $address);
    }

    /**
     * @return DebtorCompany
     */
    public function getApprovedAddress()
    {
        $address = null;
        if ($sessionAddress = $this->getData('approved_address')) {
            $address = new DebtorCompany();
            $address->name = $sessionAddress['name'];
            $address->addressStreet = $sessionAddress['address_street'];
            $address->addressHouseNumber = $sessionAddress['address_house_number'];
            $address->addressAddition = $sessionAddress['address_addition'];
            $address->addressPostalCode = $sessionAddress['address_postal_code'];
            $address->addressCity = $sessionAddress['address_city'];
            $address->addressCountry = $sessionAddress['address_country'];
        }

        return $address;
    }
}
