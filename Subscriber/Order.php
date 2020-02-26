<?php

namespace BilliePayment\Subscriber;

use BilliePayment\Components\Api\Api;
use BilliePayment\Components\Payment\Service;
use BilliePayment\Components\Utils;
use BilliePayment\Enum\PaymentMethods;
use DateInterval;
use DateTime;
use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use Enlight_View_Default;
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
     * @var Service $service
     */
    private $service;

    /**
     * Canceled Order Code
     * @var integer
     */
    const ORDER_CANCELED = 4; // TODO config

    /**
     * Shipped Order Code
     * @var integer
     */
    const ORDER_SHIPPED = 7; // TODO config

    /**
     * Clarification required order code
     * @var integer
     */
    const ORDER_STATE_CLARIFICATION_REQUIRED = 8; // TODO config

    /**
     * @param Api $api
     * @param Utils $utils
     * @param Service $service
     */
    public function __construct(Api $api, Utils $utils, Service $service)
    {
        $this->api = $api;
        $this->utils = $utils;
        $this->service = $service;

    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onSaveOrder',
            'Shopware_Modules_Order_SaveOrder_FilterAttributes' => 'onBeforeSendMail',
            'Enlight_Controller_Action_PreDispatch_Backend_Order' => 'onDocumentCreate',
            'Shopware_Modules_Order_SendMail_FilterVariables' => 'filterOrderMailVariables'
        ];
    }

    public function filterOrderMailVariables(Enlight_Event_EventArgs $args)
    {
        $data = $args->getReturn();
        if (PaymentMethods::exists($data['additional']['payment']['name'])) {
            // BILLSWPL-31: remove payment method description in order mail
            $data['additional']['payment']['additionaldescription'] = null;
        }
        return $data;
    }

    /**
     * Calcuate Duration
     *
     * @param Enlight_Event_EventArgs $args
     * @return void
     */
    public function onDocumentCreate(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $params = $request->getParams();

        if ($request->getActionName() == 'createDocument') {
            /** @var OrderModel $order */
            $order = Shopware()->Models()->find('Shopware\Models\Order\Order', $params['orderId']);

            if (empty($order) || !$this->service->isBilliePayment(['id' => $order->getPayment()->getId()])) {
                return;
            }

            $duration = $order->getPayment()->getAttribute()->getBillieDuration();
            $attrs = $order->getAttribute();
            $date = array_key_exists('displayDate', $params) ? new DateTime($params['displayDate']) : new DateTime();
            $date->add(new DateInterval('P' . $duration . 'D'));

            $attrs->setBillieDuration($duration);
            $attrs->setBillieDurationDate($date->format('d.m.Y'));

            $models = $this->utils->getEnityManager();
            $models->persist($attrs);
            $models->flush([$attrs]);
        }
    }

    /**
     * Filter to add billie api response to order attributes before mail is send.
     *
     * @param Enlight_Event_EventArgs $args
     * @return void
     */
    public function onBeforeSendMail(Enlight_Event_EventArgs $args)
    {
        /** @var OrderModel $order */
        $session = Shopware()->Session()['billie_api_response'];
        $value = $args->getReturn();

        if (!empty($session) && $session['success']) {
            $value['billie_state'] = $session['local']['state'];
            $value['billie_referenceid'] = $session['local']['reference'];
            $value['billie_iban'] = $session['local']['iban'];
            $value['billie_bic'] = $session['local']['bic'];
            $value['billie_duration'] = $session['local']['duration'];
            $value['billie_duration_date'] = $session['local']['duration_date'];
        }

        unset($session);
        return $value;
    }

    /**
     * Sent updates to billie if order is changed.
     *
     * @param Enlight_Event_EventArgs $args
     * @return void
     */
    public function onSaveOrder(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $view = $controller->View();
        $params = $request->getParams();

        switch ($request->getActionName()) {
            // Batch Process orders.
            case 'batchProcess':
                foreach ($params['orders'] as $order) {
                    $this->processOrder($request, $order, $view);
                }
                break;

            // Process Single Order.
            case 'save':
                $this->processOrder($request, $params, $view);
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
     * @param Enlight_View_Default $view
     * @return void
     */
    protected function processOrder(Enlight_Controller_Request_RequestHttp $request, array $order, Enlight_View_Default $view)
    {
        /** @var Service $service */
        $service = Shopware()->Container()->get('billie_payment.payment_service');
        if (!$service->isBilliePayment(['id' => $order['paymentId']])) {
            return;
        }

        if ($request->getActionName() == 'save') {
            $this->api->updateOrderAmount(
                $request->getParam('id'),
                $request->getParam('invoiceAmountNet') + $request->getParam('invoiceShippingNet'),
                $request->getParam('invoiceAmount') + $request->getParam('invoiceShipping')
            );
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
                    'title' => $response['title'],
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
