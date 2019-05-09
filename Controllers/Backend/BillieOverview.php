<?php

use BilliePayment\Models\Api;
use Shopware\Components\CSRFWhitelistAware;

/**
 * Backend Controller for lightweight backend module.
 * Manages Billie Order Details and States.
 * 
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 */
class Shopware_Controllers_Backend_BillieOverview extends Enlight_Controller_Action implements CSRFWhitelistAware
{

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    protected $entityManager = null;

    /**
     * @var \Shopware\Components\Model\QueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * @var \Shopware\Components\Logger
     */
    protected $logger = null;

    /**
     * Assign CSRF-Token to view.
     *
     * @return void
     */
    public function postDispatch()
    {
        $csrfToken = $this->container->get('BackendSession')->offsetGet('X-CSRF-Token');
        $this->View()->assign([ 'csrfToken' => $csrfToken ]);
    }

    /**
     * Index action displays list with orders paid with billie.io
     *
     * @return void
     */
    public function indexAction()
    {
        // Query Params
        $filters   = [
            ['property' => 'payment.name', 'value' => 'billie_payment_after_delivery']
        ];
        $sort    = [['property' => 'orders.orderTime', 'direction' => 'DESC']];
        $page    = intval($this->Request()->getParam('page', 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        // Load Orders
        $builder = $this->getQueryBuilder();
        $builder->select(['orders, attribute'])
            ->from(\Shopware\Models\Order\Order::class, 'orders')
            ->leftJoin('orders.attribute', 'attribute')
            ->leftJoin('orders.payment', 'payment')
            ->andWhere('orders.number IS NOT NULL')
            ->andWhere('orders.status != -1')
            ->addOrderBy($sort)
            ->addFilter($filters)
            ->setFirstResult($offset)
            ->setMaxResults($perPage);
        
        // Get Query and paginator
        $query = $builder->getQuery();
        $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        $paginator = $this->getEnityManager()->createPaginator($query);
        
        $this->View()->assign([
            'orders'        => $paginator->getIterator()->getArrayCopy(),
            'total'         => $paginator->count(),
            'totalPages'    => ceil($paginator->count()/$perPage),
            'page'          => $page,
            'perPage'       => $perPage,
            'statusClasses' =>  [
                'created'  => 'info',
                'declined' => 'danger',
                'shipped'  => 'success',
                'paid_out' => 'success',
                'late'     => 'warning',
                'complete' => 'success',
                'canceled' => 'danger',
            ]
        ]);
    }

    /**
     * Retrieves the current order status from billie.
     *
     * @return void
     */
    public function orderAction()
    {
        $order = $this->Request()->getParam('order_id');

        // TODO: Retrieve Order (GET /v1/order/{order_id})
        $this->getLogger()->info(sprintf('GET /v1/order/%s', $order));

        // Test data for now
        $data = [
            'state' => 'canceled',
            'order_id' => $order,
            'bank_account' => [
                'iban' => '12345678910',
                'bic'  => '12345678910'
            ],
            'debtor_company' => [
                'name' => 'John Doe',
                'address_house_number' => '42',
                'address_house_street' => 'XYZ',
                'address_house_city' => 'ABCDE',
                'address_house_postal_code' => '12345',
                'address_house_country' => 'USA',
            ]
        ];

        $this->View()->assign($data);
    }

    /**
     * Confirms direct payment by merchent.
     *
     * @return void
     */
    public function confirmPaymentAction()
    {
        // TODO: Call POST /v1/order/{order_id}/confirm-payment
        $order  = $this->Request()->getParam('order_id');
        $amount = floatval($this->Request()->getParam('amount'));

        $this->getLogger()->info(sprintf('POST /v1/order/%s/confirm-payment', $order));
        $this->Front()->Plugins()->Json()->setRenderer();
        $this->View()->assign(['success' => true, 'title' => 'Erfolgreich', 'data' => 'response reason']);
    }

    /**
     * Full Cancelation of an order on billie site.
     *
     * @return void
     */
    public function cancelOrderAction()
    {
        // TODO: run POST /v1/order/{order_id}/cancel
        $order  = $this->Request()->getParam('order_id');
        $this->getLogger()->info(sprintf('POST /v1/order/%s/cancel', $order));
        $this->Front()->Plugins()->Json()->setRenderer();
        $this->View()->assign(['success' => true, 'title' => 'Erfolgreich', 'data' => 'Cancelation Response']);
    }

    /**
     * Whitelisted CSRF actions
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return [ 'index', 'order' ];
    }

    /**
     * Internal helper function to get access to the query builder.
     *
     * @return \Shopware\Components\Model\QueryBuilder
     */
    private function getQueryBuilder()
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = $this->getEnityManager()->createQueryBuilder();
        }
        
        return $this->queryBuilder;
    }

    /**
     * Internal helper function to get access the Model Manager.
     *
     * @return \Shopware\Components\Model\ModelManager
     */
    private function getEnityManager()
    {
        if ($this->entityManager === null) {
            $this->entityManager = Shopware()->Container()->get('models');
        }
        
        return $this->entityManager;
    }

    /**
     * Internal helper function to get access the plugin logger
     *
     * @return \Shopware\Components\Logger
     */
    private function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = Shopware()->Container()->get('pluginlogger');
        }
        
        return $this->logger;
    }
}
