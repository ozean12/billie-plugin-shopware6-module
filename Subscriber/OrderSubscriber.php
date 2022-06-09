<?php

namespace BilliePayment\Subscriber;

use BilliePayment\Components\Api\Api;
use BilliePayment\Components\Payment\Service;
use BilliePayment\Components\Utils;
use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Helper\DocumentHelper;
use BilliePayment\Services\ConfigService;
use DateInterval;
use DateTime;
use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use Enlight_View_Default;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

/**
 * Order Subscriber which calls the billie api when an
 * order is saved to send the updated informations.
 */
class OrderSubscriber implements SubscriberInterface
{
    /**
     * Clarification required order code.
     *
     * @deprecated
     *
     * @var int
     */
    const ORDER_STATE_CLARIFICATION_REQUIRED = 8; // TODO config

    /**
     * @var Utils
     */
    private $utils;

    /**
     * @var Service
     */
    private $service;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var DocumentHelper
     */
    private $documentHelper;

    /**
     * @var \BilliePayment\Services\ConfigService
     */
    private $configService;

    public function __construct(
        ModelManager $modelManager,
        Api $api,
        Utils $utils,
        Service $service,
        DocumentHelper $documentHelper,
        ConfigService $configService
    ) {
        $this->modelManager = $modelManager;
        $this->api = $api;
        $this->utils = $utils;
        $this->service = $service;
        $this->documentHelper = $documentHelper;
        $this->configService = $configService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onSaveOrder',
            'Enlight_Controller_Action_PreDispatch_Backend_Order' => 'onDocumentCreate',
            'Shopware_Modules_Order_SendMail_FilterVariables' => 'filterOrderMailVariables',
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
     * Calcuate Duration.
     *
     * @return void
     */
    public function onDocumentCreate(Enlight_Controller_ActionEventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $params = $request->getParams();

        if ($request->getActionName() === 'createDocument') {
            /** @var Order|null $order */
            $order = $this->modelManager->find(Order::class, $params['orderId']);

            if (!$order || !$this->service->isBilliePayment(['id' => $order->getPayment()->getId()])) {
                return;
            }

            $duration = $order->getPayment()->getAttribute()->getBillieDuration();
            $attrs = $order->getAttribute();
            $date = array_key_exists('displayDate', $params) ? new DateTime($params['displayDate']) : new DateTime();
            $date->add(new DateInterval('P' . $duration . 'D'));

            $attrs->setBillieDuration($duration);
            $attrs->setBillieDurationDate($date->format('d.m.Y'));

            $this->modelManager->flush($attrs);
        }
    }

    /**
     * Sent updates to billie if order is changed.
     *
     * @return void
     */
    public function onSaveOrder(Enlight_Controller_ActionEventArgs $args)
    {
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
     * @return void
     */
    protected function processOrder(Enlight_Controller_Request_RequestHttp $request, array $orderArray, Enlight_View_Default $view)
    {
        /** @var Service $service */
        $service = Shopware()->Container()->get(Service::class);
        if (!$service->isBilliePayment(['id' => $orderArray['paymentId']])) {
            return;
        }
        /** @var Order|null $order */
        $order = $this->modelManager->find(Order::class, $orderArray['id']);
        if ($order === null) {
            return;
        }

        switch ($orderArray['status']) {
            case $this->configService->getOrderStatusForAutoProcessing('cancel'):
                $response = $this->api->cancelOrder($order);
                $view->assign([
                    'success' => $response === true,
                    'message' => $this->utils->getSnippet(
                        'backend/billie_overview/messages',
                        is_string($response) ? $response : null,
                        is_string($response) ? $response : null
                    ),
                ]);
                break;

            case $this->configService->getOrderStatusForAutoProcessing('ship'):
                $invoiceNumber = $this->documentHelper->getInvoiceNumberForOrder($order);
                if ($invoiceNumber) {
                    $invoiceUrl = $this->documentHelper->getInvoiceUrlForOrder($order);
                    $response = $this->api->shipOrder($order, $invoiceNumber, $invoiceUrl);
                    $view->assign([
                        'success' => $response instanceof \Billie\Sdk\Model\Order,
                        'message' => $this->utils->getSnippet(
                            'backend/billie_overview/messages',
                            is_string($response) ? $response : null,
                            is_string($response) ? $response : null
                        ),
                    ]);

                    // Set Status to clarification required in api error
                    if ($response instanceof \Billie\Sdk\Model\Order === false) {
                        $status = $this->modelManager->getRepository(Status::class)->find(self::ORDER_STATE_CLARIFICATION_REQUIRED);

                        if ($status !== null) {
                            $order->setOrderStatus($status);
                            $this->modelManager->flush($order);
                        }

                        $view->assign('status', self::ORDER_STATE_CLARIFICATION_REQUIRED);
                    }
                }
                break;
            default:
                if ($request->getActionName() === 'save') {
                    $response = $this->api->updateAmount(
                        $order,
                        $request->getParam('invoiceAmount'),
                        $request->getParam('invoiceAmountNet')
                    );

                    $view->assign([
                        'success' => $response instanceof \Billie\Sdk\Model\Order,
                        'message' => $this->utils->getSnippet(
                            'backend/billie_overview/messages',
                            is_string($response) ? $response : null,
                            is_string($response) ? $response : null
                        ),
                    ]);
                }
        }
    }
}
