<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;

/**
 * Order Cronjob to check order status.
 */
class Order implements SubscriberInterface
{
    /**
     * Canceled Order Code
     * @var integer
     */
    const ORDER_CANCELED = 4;


    /**
     * Shipped Order Code
     * @var integer
     */
    const ORDER_SHIPPED = 7;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onSaveOrder',
            'Enlight_Controller_Action_PreDispatch_Backend_Order'        => 'onBeforeSaveOrder'
        ];
    }

    /**
     * Send updates to billie if order is changed.
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function onBeforeSaveOrder(\Enlight_Event_EventArgs $args)
    {
        /** @var Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();

        if ($request->getActionName() == 'save') {
            $params = $request->getParams();
            $order  = Shopware()->Models()->find('Shopware\Models\Order\Order', $params['id']);
            
            // Update Amount if changed
            if ($order->getInvoiceAmount() != $params['invoiceAmount']) {
                // TODO: run PATCH /v1/order/{order_id}
                // TODO: print possible error message
                // exit('{"success": false, "message": "Dies ist eine Fehlernachricht"}');
            }
        }
    }

    /**
     * Sent updates to billie if order is changed.
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function onSaveOrder(\Enlight_Event_EventArgs $args)
    {
        /** @var Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();

        switch ($request->getActionName()) {
            // Batch Process orders.
            case 'batchProcess':
                $params = $request->getParams();

                foreach ($params['orders'] as $order) {
                    $this->processOrder($order);
                }
                break;

            // Process Single Order.
            case 'save':
                $this->processOrder($request->getParams());
                // $args->stop();
                break;

            // Extend order details overview.
            case 'load':
                $view->extendsTemplate('backend/billie_payment/view/detail/overview.js');
                break;
            default:
                break;
        }
    }

    /**
     * Process an order and call the respective api endpoints.
     *
     * @param array $order
     * @return void
     */
    protected function processOrder($order)
    {
        switch ($order['status']) {
            // Order is canceled.
            case self::ORDER_CANCELED:
                // TODO: run POST /v1/order/{order_1}/cancel
                // TODO: print possible error message
                $this->updateState($order['id'], 'canceled');
                // $view->assign(['success' => false, 'message' => 'Dies ist eine Fehlernachricht']);
                // exit('{"success": false, "message": "Dies ist eine Fehlernachricht"}');
                break;

            // Order is shipped
            case self::ORDER_SHIPPED:
                // TODO: run POST /v1/order/{order_id}/ship
                // TODO: Flag billie state as 'shipped' (or declined based on api response)
                $this->updateState($order['id'], 'shipped');
                break;

            default:
                break;
        }
    }

    /**
     * Update local api order state.
     *
     * @param integer $order
     * @param string $state
     * @return void
     */
    protected function updateState($order, $state)
    {
        // Save api state for order
        $models = Shopware()->Container()->get('models');
        $repo   = $models->getRepository(\Shopware\Models\Order\Order::class);
        $entry  = $repo->findOneBy(['order' => $order]);
        
        if ($entry) {
            $attr = $entry->getAttribute();
            $entry->setBillieState($state);
            $models->persist($attr);
            $models->flush($attr);
        }
    }
}
