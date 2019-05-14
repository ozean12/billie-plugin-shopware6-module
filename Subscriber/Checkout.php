<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use BilliePayment\Components\BilliePayment\Api;

/**
 * Subscriber to assign api messages to the checkout view
 */
class Checkout implements SubscriberInterface
{
    /**
     * @var $api Api
     */
    private $api;

    /**
     * @param $api Api
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
            'Enlight_Controller_Action_PreDispatch_Frontend_Checkout' => 'onFrontendCheckout',
            'Enlight_Controller_Action_PreDispatch_Frontend_Address'  => 'onFrontendAddress',
            'Shopware_Modules_Order_SaveOrder_ProcessDetails'         => 'onSaveOrder'
        ];
    }

    /**
     * Save API State information after order is created.
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function onSaveOrder(\Enlight_Event_EventArgs $args)
    {
        $session = Shopware()->Session();
        $this->api->updateLocal($args['orderId'], ['state' => $session->apiOrderState]);
        $session->apiOrderState = null;
    }

    /**
     * Add Legalforms to address form
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function onFrontendAddress(\Enlight_Event_EventArgs $args)
    {
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
    public function onFrontendCheckout(\Enlight_Event_EventArgs $args)
    {
        /** @var $controller \Enlight_Controller_Action */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();
        $session    = Shopware()->Session();
        $logger     = Shopware()->Container()->get('pluginlogger');

        // Only valid actions
        if (!in_array($request->getActionName(), ['finish', 'payment', 'confirm'])) {
            return;
        }
        
        // Get API errors from the session and assign them to the view
        $view->assign('errorCode', $request->getParam('errorCode'));
        $errors = $session->apiErrorMessages;
        if (isset($errors) && !empty($errors)) {
            $errors = is_array($errors) ? $errors : [$errors];
            $logger->error('Error on POST /v1/order: ' . json_encode($errors));
            $view->assign('apiErrorMessages', $errors);
            unset($session->apiErrorMessages);
        }
    }
}
