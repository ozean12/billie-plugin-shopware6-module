<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use BilliePayment\Components\Api\Api;
use BilliePayment\Components\Utils;
use Shopware\Models\Order\Order as OrderModel;
use Shopware\Models\Order\Status;

/**
 * Order Subscriber which calls the billie api when an
 * order is saved to send the updated informations.
 */
class Order implements SubscriberInterface
{
    /**
     * @var Api $api
     */
    private $api;

    /**
     * @var Utils $utils
     */
    private $utils;

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
     * Clarification required order code
     * @var integer
     */
    const ORDER_STATE_CLARIFICATION_REQUIRED = 8;
    
    /**
     * @param Api $api
     * @param Utils $utils
     */
    public function __construct(Api $api, Utils $utils)
    {
        $this->api   = $api;
        $this->utils = $utils;
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
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $params     = $request->getParams();
        $view       = $controller->View();

        // Update Amount.
        if ($request->getActionName() == 'save') {
            /** @var \Shopware\Models\Order\Order $order */
            $order    = Shopware()->Models()->find('Shopware\Models\Order\Order', $params['id']);
            $response = $this->api->updateOrder($order->getId(), [
                'amount' => [
                    'net'      => $params['invoiceAmountNet'] + $params['invoiceShippingNet'],
                    'gross'    => $params['invoiceAmount'] + $params['invoiceShipping'],
                    'currency' => $order->getCurrency(),
                ]
            ]);

            $view->assign([
                'success' => $response['success'],
                'title'   => $response['title'],
                'message' => $response['data']
            ]);
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
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();
        $params     = $request->getParams();

        switch ($request->getActionName()) {
            // Batch Process orders.
            case 'batchProcess':
                foreach ($params['orders'] as $order) {
                    $this->processOrder($order, $view);
                }
                break;

            // Process Single Order.
            case 'save':
                $this->processOrder($params, $view);
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
     * @param \Enlight_View_Default $view
     * @return void
     */
    protected function processOrder(array $order, \Enlight_View_Default $view)
    {
        /** @var \BilliePayment\Components\Payment\Service $service */
        $service = Shopware()->Container()->get('billie_payment.payment_service');
        if (!$service->isBilliePayment(['id' => $order['paymentId']])) {
            return;
        }

        switch ($order['status']) {
            // Order is canceled.
            case self::ORDER_CANCELED:
                $response = $this->api->cancelOrder($order['id']);
                $view->assign([
                    'success' => $response['success'],
                    'message' => $this->utils->getSnippet(
                        'backend/billie_overview/errors',
                        $response['data'],
                        $response['data']
                    )
                ]);
                break;

            // Order is shipped
            case self::ORDER_SHIPPED:
                $response = $this->api->shipOrder($order['id']);
                $view->assign([
                    'success' => $response['success'],
                    'title'   => $response['title'],
                    'message' => $this->utils->getSnippet(
                        'backend/billie_overview/errors',
                        $response['data'],
                        $response['data']
                    )
                ]);

                // Set Satus to clarification required in api error
                if ($view->success == false) {
                    $models = $this->utils->getEnityManager();
                    $status = $models->getRepository(Status::class)->find(self::ORDER_STATE_CLARIFICATION_REQUIRED);
                    $_order = $models->getRepository(OrderModel::class)->find($order['id']);

                    if ($status !== null) {
                        $_order->setOrderStatus($status);
                        $models->persist($_order);
                        $models->flush($_order);
                    }

                    $view->data['status'] = self::ORDER_STATE_CLARIFICATION_REQUIRED;
                }

                break;
        }
    }
}
