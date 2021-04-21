<?php

use Billie\Sdk\Exception\BillieException;
use BilliePayment\Components\Api\Api;
use BilliePayment\Components\Utils;
use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Helper\DocumentHelper;
use Doctrine\ORM\AbstractQuery;
use Monolog\Logger;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Models\Order\Order;

/**
 * Backend Controller for lightweight backend module.
 * Manages Billie Order Details and States.
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration
 * @phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 */
class Shopware_Controllers_Backend_BillieOverview extends Enlight_Controller_Action implements CSRFWhitelistAware
{

    /**
     * @var Api
     */
    private $api;

    /**
     * @var Shopware_Components_Snippet_Manager
     */
    private $snippetManager;

    /**
     * @var DocumentHelper
     */
    private $documentHelper;

    public function setContainer(Container $loader = null)
    {
        parent::setContainer($loader);

        $this->api = $this->container->get(Api::class);
        $this->snippetManager = $this->container->get('snippets');
        $this->documentHelper = $this->container->get(DocumentHelper::class);
    }

    /**
     * Index action displays list with orders paid with billie.io
     *
     * @return void
     */
    public function indexAction()
    {
        // Build Filters
        $filters = [];
        foreach (PaymentMethods::getNames() as $name) {
            $filters[] = ['property' => 'payment.name', 'value' => $name, 'operator' => 'or'];
        }
        unset($filters[0]['operator']);

        $sort = [['property' => 'orders.orderTime', 'direction' => 'DESC']];
        $currentPage = intval($this->Request()->getParam('page', 1));
        $maxPerPage = 25;
        // Load Orders
        $builder = $this->getModelManager()->createQueryBuilder();
        $builder->select(['orders, attribute', 'billing'])
            ->from(Order::class, 'orders')
            ->leftJoin('orders.attribute', 'attribute')
            ->leftJoin('orders.payment', 'payment')
            ->leftJoin('orders.billing', 'billing')
            ->addFilter($filters)
            ->andWhere('orders.number != 0')
            ->andWhere('orders.status != -1')
            ->addOrderBy($sort)
            ->setFirstResult(($currentPage - 1) * $maxPerPage)
            ->setMaxResults($maxPerPage);

        // Get Query and paginator
        $query = $builder->getQuery();
        $query->setHydrationMode(AbstractQuery::HYDRATE_ARRAY);
        $paginator = $this->getModelManager()->createPaginator($query);

        // Assign view data
        $this->View()->assign('errorCode', $this->Request()->getParam('errorCode'));
        $this->View()->assign([
            'orders' => $paginator->getIterator()->getArrayCopy(),
            'total' => $paginator->count(),
            'totalPages' => ceil($paginator->count() / $maxPerPage),
            'page' => $currentPage,
            'perPage' => $maxPerPage,
        ]);

        $this->View()->assign([
            'statusClasses' => [
                'created' => 'info',
                'declined' => 'danger',
                'shipped' => 'success',
                'paid_out' => 'success',
                'late' => 'warning',
                'complete' => 'success',
                'canceled' => 'danger',
            ],
        ]);
    }

    /**
     * Retrieves the current order status from billie.
     *
     * @return void
     */
    public function orderAction()
    {
        $orderId = $this->Request()->getParam('order_id');
        $shopwareOrder = $this->getModelManager()->find(Order::class, $orderId);

        if ($shopwareOrder === null) {
            $this->redirect(['controller' => 'BillieOverview', 'action' => 'index']);
            return;
        }

        try {
            $order = $this->api->getOrder($shopwareOrder);
        } catch (BillieException $e) {
            $this->redirect(['controller' => 'BillieOverview', 'action' => 'index', 'errorCode' => $e->getBillieCode()]);
            return;
        }

        $this->View()->assign('billieOrder', $order->toArray());
        $this->View()->assign('shopwareOrder', $shopwareOrder);
    }

    /**
     * Mark current order as shipped.
     *
     * @return void
     */
    public function shipOrderAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer();

        $orderId = $this->Request()->getParam('order_id');
        $invoiceNumber = $this->Request()->getParam('invoice_number', null);
        $invoiceUrl = $this->Request()->getParam('invoice_url', null);

        $order = $this->getModelManager()->find(Order::class, $orderId);
        if ($order === null) {
            $this->view->assign([
                'success' => false
            ]);
            return;
        }
        $invoiceUrl = !empty($invoiceUrl) ? $invoiceUrl : $this->documentHelper->getInvoiceUrlForOrder($order);
        $invoiceNumber = !empty($invoiceNumber) ? $invoiceNumber : $this->documentHelper->getInvoiceNumberForOrder($order);

        if ($invoiceNumber === null) {
            $this->View()->assign([
                'success' => false,
                'data' => 'MISSING_DOCUMENTS'
            ]);
            return;
        }

        $response = $this->api->shipOrder($order, $invoiceNumber, $invoiceUrl);

        $this->View()->assign([
            'success' => $response instanceof \Billie\Sdk\Model\Order,
            'message' => is_string($response) ? $this->snippetManager->getNamespace('backend/billie_overview/messages')
                ->get($response) : null
        ]);
    }

    public function confirmPaymentAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer();

        $amount = floatval(str_replace(',', '.', $this->Request()->getParam('amount')));
        $orderId = $this->Request()->getParam('order_id');

        $order = $this->getModelManager()->find(Order::class, $orderId);

        $response = $this->api->confirmPayment($order, $amount);

        $this->View()->assign([
            'success' => $response instanceof \Billie\Sdk\Model\Order,
            'message' => is_string($response) ? $this->snippetManager->getNamespace('backend/billie_overview/messages')
                ->get($response) : null
        ]);
    }

    public function refundOrderAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer();

        $orderId = $this->Request()->getParam('order_id');

        $amount = floatval(str_replace(',', '.', $this->Request()->getParam('amount')));
        $order = $this->getModelManager()->find(Order::class, $orderId);


        $response = $order ? $this->api->partlyRefund($order, $amount) : false;
        if ($response instanceof \Billie\Sdk\Model\Order) {
            $success = true;
        } else if ($response === true) {
            $success = true;
        } else {
            $success = false;
        }

        // Return result message
        $this->View()->assign([
            'success' => $success,
            'partly' => $response instanceof \Billie\Sdk\Model\Order,
            'message' => is_string($response) ? $this->snippetManager->getNamespace('backend/billie_overview/messages')
                ->get($response) : null
        ]);
    }

    /**
     * Full Cancelation of an order on billie site.
     *
     * @return void
     */
    public function cancelOrderAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer();

        $orderId = $this->Request()->getParam('order_id');
        $order = $this->getModelManager()->find(Order::class, $orderId);

        $response = $this->api->cancelOrder($order);

        $this->View()->assign([
            'success' => $response === true,
            'message' => is_string($response) ? $this->snippetManager->getNamespace('backend/billie_overview/messages')
                ->get($response) : null
        ]);
    }

    /**
     * Whitelisted CSRF actions
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return ['index', 'order'];
    }
}
