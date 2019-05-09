<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Models\Order\Order;

/**
 * Subscriber to assign api messages to the checkout view
 */
class Checkout implements SubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend_Checkout' => 'onFrontendCheckout',
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
        /** @var \Shopware\Components\Model\ModelManager $entityManager */
        $models = Shopware()->Container()->get('models');
        $repo   = $models->getRepository(Order::class);

        // Save api state for order
        $order = $repo->find($args['orderId']);
        $attr  = $order->getAttribute();
        $attr->setBillieState(Shopware()->Session()->apiOrderState);

        $models->persist($attr);
        $models->flush($attr);

        // Clear api responses
        Shopware()->Session()->apiOrderState = null;
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
        $errors = $session->apiErrorMessages;
        if (isset($errors) && !empty($errors)) {
            $logger->error('Error on POST /v1/order: ' . json_encode($errors));
            $view->assign('apiErrorMessages', $errors);
            $session->apiErrorMessages = null;
        }
    }
}
