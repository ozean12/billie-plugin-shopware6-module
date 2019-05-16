<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use BilliePayment\Components\BilliePayment\Api;

/**
 * Order Cronjob to check order status.
 */
class Order implements SubscriberInterface
{
    /**
     * @var Api $api
     */
    private $api;
        
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
     * @param Api $api
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

            // Update Amount
            $response = $this->api->updateOrder($order->getId(), [
                'amount' => [
                    'net'   => $params['invoiceAmountNet'] + $params['invoiceShippingNet'],
                    'gross' => $params['invoiceAmount'] + $params['invoiceShipping'],
                    'currency' => $order->getCurrency(),
                ]
            ]);
            $controller->View()->assign(['success' => $response['success'], 'title' => $response['title'], 'message' => $response['data']]);
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
                $this->processOrder($request->getParams(), $view);
                // $args->stop();
                break;

            // Extend order details overview.
            case 'load':
                $view->extendsTemplate('backend/billie_payment/view/detail/overview.js');
                break;
        }
    }

    /**
     * Process an order and call the respective api endpoints.
     *
     * @param array $order
     * @return void
     */
    protected function processOrder($order, $view)
    {
        switch ($order['status']) {
            // Order is canceled.
            case self::ORDER_CANCELED:
                $response = $this->api->cancelOrder($order['id']);
                $view->assign(['success' => $response['success'], 'message' => $response['data']]);
                break;

            // Order is shipped
            case self::ORDER_SHIPPED:
                $response = $this->api->shipOrder($order['id']);
                $view->assign(['success' => $response['success'], 'title' => $response['title'], 'message' => $response['data']]);
                break;
        }
    }
}
