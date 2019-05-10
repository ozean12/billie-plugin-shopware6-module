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
     * @var $api Api
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
                // TODO: print possible error message
                $response = $this->api->updateOrder($order->getId(), [
                    'amount' => [
                        'net'   => $params['invoiceAmountNet'] + $params['invoiceShippingNet'],
                        'gross' => $params['invoiceAmount'] + $params['invoiceShipping'],
                        'currency' => 'EUR', //TODO: Fetch correct currency
                    ]
                ]);
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
                // TODO: print possible error message
                $response = $this->api->cancelOrder($order['id']);
                // $view->assign(['success' => false, 'message' => 'Dies ist eine Fehlernachricht']);
                // exit('{"success": false, "message": "Dies ist eine Fehlernachricht"}');
                break;

            // Order is shipped
            case self::ORDER_SHIPPED:
                $response = $this->api->shipOrder($order['id']);
                break;

            default:
                break;
        }
    }
}
