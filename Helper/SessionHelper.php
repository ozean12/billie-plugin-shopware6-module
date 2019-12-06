<?php


namespace BilliePayment\Helper;


use Enlight_Components_Session_Namespace;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;

class SessionHelper
{

    /**
     * @var ModelManager
     */
    private $modelManager;
    /**
     * @var Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @var Customer
     */
    private $loadedCustomer;

    /**
     * @var Address
     */
    private $loadedBillingAddress;

    public function __construct(ModelManager $modelManager, Enlight_Components_Session_Namespace $session)
    {
        $this->modelManager = $modelManager;
        $this->session = $session;
    }

    public function getBillingAddress(Customer $customer = null)
    {
        if ($this->loadedBillingAddress) {
            return $this->loadedBillingAddress;
        }

        $addressId = $this->session['checkoutBillingAddressId'];
        if ($addressId > 0) {
            $this->loadedBillingAddress = $this->modelManager->find(Address::class, $addressId);
        } else if ($customer) {
            $this->loadedBillingAddress = $customer->getDefaultBillingAddress();
        } else {
            $customer = $this->getCustomer();
            if ($customer !== null) {
                $this->loadedBillingAddress = $customer->getDefaultBillingAddress();
            }
        }
        return $this->loadedBillingAddress;
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

}
