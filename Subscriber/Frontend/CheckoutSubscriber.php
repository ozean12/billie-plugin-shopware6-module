<?php


namespace BilliePayment\Subscriber\Frontend;


use BilliePayment\Enum\PaymentMethods;
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

    public function __construct(
        Enlight_Components_Session_Namespace $session,
        WidgetService $widgetService
    ) {
        $this->session = $session;
        $this->widgetService = $widgetService;
    }


    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'addWidget'
        ];
    }

    public function addWidget(Enlight_Controller_ActionEventArgs $args)
    {
        /** @var Shopware_Controllers_Frontend_Checkout $subject */
        $subject = $args->getSubject();
        if ($subject->Request()->getActionName() !== 'confirm') {
            return;
        }

        $payment = $this->session->get('sOrderVariables')['sPayment'];
        if (PaymentMethods::exists($payment['name']) === false) {
            return;
        }

        $subject->View()->assign([
            'billiePayment' => [
                'widget' => $this->widgetService->getWidgetData((array) $this->session->get('sOrderVariables'))
            ]
        ]);

    }
}
