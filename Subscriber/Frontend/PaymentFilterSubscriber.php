<?php

namespace BilliePayment\Subscriber\Frontend;

use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Services\SessionService;
use Enlight\Event\SubscriberInterface;

class PaymentFilterSubscriber implements SubscriberInterface
{

    /**
     * @var SessionService
     */
    private $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
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

        $billingAddress = $this->sessionService->getBillingAddress();

        if ($billingAddress) {
            if(empty($billingAddress->getCompany())) {
                // remove all payment methods cause, the customer is not a B2B customer.
                foreach (PaymentMethods::getNames() as $name) {
                    foreach ($paymentMethods as $i => $paymentMethod) {
                        if ($name == $paymentMethod['name']) {
                            unset($paymentMethods[$i]);
                        }
                    }
                }
            } else {
                foreach($paymentMethods as $i => $paymentMethod) {
                    if($billieMethod = PaymentMethods::getMethod($paymentMethod['name'])) {
                        if(in_array($billingAddress->getCountry()->getIso(), $billieMethod['billie_config']['allowed_in_countries']) == false) {
                            unset($paymentMethods[$i]);
                        }
                    }
                }
            }
        }
        return $paymentMethods;
    }
}
