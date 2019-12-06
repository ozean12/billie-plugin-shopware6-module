<?php

namespace BilliePayment\Subscriber\Frontend;

use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Helper\SessionHelper;
use Enlight\Event\SubscriberInterface;

class PaymentFilterSubscriber implements SubscriberInterface
{

    /**
     * @var SessionHelper
     */
    private $sessionHelper;

    public function __construct(SessionHelper $sessionHelper)
    {
        $this->sessionHelper = $sessionHelper;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter' => 'onFilterPayments',
        ];
    }

    public function onFilterPayments(\Enlight_Event_EventArgs $args)
    {
        $paymentMethods = $args->getReturn();

        $billingAddress = $this->sessionHelper->getBillingAddress();

        if($billingAddress && empty($billingAddress->getCompany())) {
            // remove all payment methods cause, the customer is not a B2B customer.
            foreach(PaymentMethods::getNames() as $name) {
                foreach($paymentMethods as $i => $paymentMethod) {
                    if($name == $paymentMethod['name']) {
                        unset($paymentMethods[$i]);
                    }
                }
            }
        }
        return $paymentMethods;
    }
}
