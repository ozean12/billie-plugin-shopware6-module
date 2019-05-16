<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use BilliePayment\Components\BilliePayment\Api;

/**
 * Subscriber to assign api messages to the checkout view
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class Checkout implements SubscriberInterface
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @param Api $api Api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => [ 'addApiMessagesToView', -1 ],
            'Enlight_Controller_Action_PreDispatch_Frontend_Address'   => 'extendAddressForm',
        ];
    }

    /**
     * Add Legalforms to address form
     * 
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function extendAddressForm(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();

        // Only valid actions
        if (!in_array($request->getActionName(), ['ajaxEditor', 'edit', 'create'])) {
            return;
        }

        $view->assign('legalForms', \Billie\Util\LegalFormProvider::all());
    }

    /**
     * Add API Messages to the Checkout View.
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function addApiMessagesToView(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware\Components\Logger $logger */
        $logger = Shopware()->Container()->get('pluginlogger');

        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();

        // Only valid actions
        if (!in_array($request->getActionName(), ['finish', 'payment', 'confirm'])) {
            return;
        }

        // Display error when legalform is missing
        $payment   = $view->sPayment['name'];
        $attrs     = $view->sUserData['billingaddress']['attributes'];
        $legalForm = in_array('billie_legalform', $attrs) ? $attrs['billie_legalform'] : $attrs['billieLegalform'];

        if ($payment === 'billie_payment_after_delivery' && (!isset($legalForm) || is_null($legalForm))) {
            $view->assign('invalidBillingAddress', true);
        }
        
        // Get API errors from the session and assign them to the view
        $view->assign('errorCode', $request->getParam('errorCode'));
        $logger->error("API-Error Code [{$request->getParam('errorCode')}]");
    }
}
