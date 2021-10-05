<?php

namespace BilliePayment\Subscriber\Frontend;

use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Services\ConfigService;
use BilliePayment\Services\WidgetService;
use Enlight\Event\SubscriberInterface;
use Enlight_Components_Session_Namespace;
use Enlight_Controller_ActionEventArgs;
use Shopware_Controllers_Frontend_Checkout;

class CheckoutSubscriber implements SubscriberInterface
{
    /**
     * @var Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @var WidgetService
     */
    private $widgetService;

    /**
     * @var ConfigService
     */
    private $configService;

    public function __construct(
        Enlight_Components_Session_Namespace $session,
        ConfigService $configService,
        WidgetService $widgetService
    ) {
        $this->session = $session;
        $this->widgetService = $widgetService;
        $this->configService = $configService;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onCheckout',
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter' => 'addDurationToPaymentMethodList'
        ];
    }

    public function addDurationToPaymentMethodList(\Enlight_Event_EventArgs $args)
    {
        $list = $args->getReturn();

        foreach ($list as &$item) {
            /** @var \Shopware\Bundle\StoreFrontBundle\Struct\Attribute $core */
            if (isset($item['attributes']['core']) && ($core = $item['attributes']['core']) instanceof \Shopware\Bundle\StoreFrontBundle\Struct\Attribute) {
                $item['billieDuration'] = $core->get('billie_duration');
            } else {
                // required for Shopware 5.5.x backward compatibility
                // we dont care about translations of the attribute, cause it is a global attribute (value)
                /** @var \Shopware\Models\Attribute\Payment $attribute */
                $attribute = Shopware()->Models()->getRepository(\Shopware\Models\Attribute\Payment::class)
                    ->findOneBy(['paymentId' => $item['id']]);

                if ($attribute) {
                    $item['billieDuration'] = $attribute->getBillieDuration();
                }
            }
        }
        return $list;
    }

    public function onCheckout(Enlight_Controller_ActionEventArgs $args)
    {
        /** @var Shopware_Controllers_Frontend_Checkout $subject */
        $subject = $args->getSubject();
        if ($subject->Request()->getActionName() === 'confirm') {
            $payment = $this->session->get('sOrderVariables')['sPayment'];
            if (PaymentMethods::exists($payment['name']) === false) {
                return;
            }

            $subject->View()->assign([
                'billiePayment' => [
                    'widget' => $this->widgetService->getWidgetData((array) $this->session->get('sOrderVariables')),
                ],
            ]);
        } elseif ($subject->Request()->getActionName() === 'shippingPayment') {
            $subject->View()->assign([
                'billiePayment' => [
                    'showPaymentIcon' => $this->configService->isShowPaymentIcon(),
                ],
            ]);
        }
    }
}
