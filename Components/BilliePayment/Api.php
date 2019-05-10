<?php

namespace BilliePayment\Components\BilliePayment;

use Shopware\Models\Order\Order;

/**
 * Service Wrapper for billie API sdk
 */
class Api
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    protected $entityManager = null;

    /**
     * @var \Shopware\Components\Logger
     */
    protected $logger = null;

    /**
     * @var \Shopware\Components\Model\QueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * @var array
     */
    protected $config = [];

    public function __construct()
    {
        // TODO: get infos from $user, $basket and from plugin config
        // $config = Shopware()->Plugins()->Frontend()->BilliePayment()->Config();
        $this->config = Shopware()->Container()->get('shopware.plugin.cached_config_reader')->getByPluginName('BilliePayment', Shopware()->Shop());
    }

    /**
     * Load orders paid with billie.
     *
     * @param integer $page
     * @param integer $perPage
     * @param mixed $filters
     * @param mixed $sorting
     * @return array
     */
    public function getList($page = 1, $perPage = 25, $filters = null, $sorting = null)
    {
        // Load Orders
        $builder = $this->getQueryBuilder();
        $builder->select(['orders, attribute'])
            ->from(Order::class, 'orders')
            ->leftJoin('orders.attribute', 'attribute')
            ->leftJoin('orders.payment', 'payment')
            ->andWhere('orders.number IS NOT NULL')
            ->andWhere('orders.status != -1')
            ->addOrderBy($sorting)
            ->addFilter($filters)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);
        
        // Get Query and paginator
        $query = $builder->getQuery();
        $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        $paginator = $this->getEnityManager()->createPaginator($query);

        // Return result
        return [
            'orders'     => $paginator->getIterator()->getArrayCopy(),
            'total'      => $paginator->count(),
            'totalPages' => ceil($paginator->count()/$perPage),
            'page'       => $page,
            'perPage'    => $perPage
        ];
    }

    /**
     * Tell billie about newly created order
     *
     * @param integer $orderNumber
     * @return array
     */
    public function createOrder($orderNumber)
    {
        $this->getLogger()->info('POST /v1/order');

        // Get Order from db
        $models = $this->getEnityManager();
        $repo   = $models->getRepository(Order::class);
        $order  = $repo->findOneBy(['number' => $orderNumber]);

        if ($order) {
            // Prepare data
            $amountNet = $order->getInvoiceAmountNet() + $order->getInvoiceShippingNet();
            $amountGross = $order->getInvoiceAmount() + $order->getInvoiceShipping();

            $data = [
                'debtor_person' => [
                    'salutation' => $order->getBilling()->getSalutation(),
                    'last_name' => $order->getBilling()->getLastName(),
                    'first_name' => $order->getBilling()->getFirstName(),
                    'phone_number' => $order->getBilling()->getPhone(),
                    'email' => $order->getCustomer()->getEmail(),
                ],
                'amount' => [
                    'net' => $amountNet,
                    'gross' => $amountGross,
                    'tax' => $amountGross - $amountNet,
                ],
                'order_id' => $order->getId()
            ];

            // TODO: Call API Endpoint to create order -> POST /v1/order
            // TODO: Update API order status to either 'declined' or 'created' depending on api return state
            return ['success' => true, 'messages' => 'OK'];
        }
    }

    /**
     * Full Cancelation of an order on billie site.
     *
     * @param integer $order
     * @return array
     */
    public function cancelOrder($order)
    {
        // TODO: run POST /v1/order/{order_id}/cancel
        $this->getLogger()->info(sprintf('POST /v1/order/%s/cancel', $order));
        $response = ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Cancelation Response'];

        // Update local state
        if (($localUpdate = $this->updateLocal($order, ['state' => 'canceled'])) !== true) {
            return $localUpdate;
        }
        
        return $response;
    }

    /**
     * Mark order as shipped
     *
     * @param integer $order
     * @return array
     */
    public function shipOrder($order)
    {
        // TODO: run POST /v1/order/{order_id}/ship
        // TODO: Flag billie state as 'shipped' (or declined based on api response)
        $this->getLogger()->info(sprintf('POST /v1/order/{order_id}/ship', $order));
        $response = ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Ship Response'];

        return $response;
    }

    /**
     * Update an order
     *
     * @param integer $order
     * @param array $data
     * @return array
     */
    public function updateOrder($order, array $data)
    {
        // TODO: run PATCH /v1/order/{order_id}
        $this->getLogger()->info(sprintf('PATCH /v1/order/{order_id}', $order));
        $response = ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Update Response'];
        
        return $response;
    }

    /**
     * Full cancelation of an order on billie site.
     *
     * @param integer $order
     * @param float $amount
     * @return array
     */
    public function confirmPayment($order, $amount)
    {
        $this->getLogger()->info(sprintf('POST /v1/order/%s/confirm-payment', $order));
        // TODO: Call POST /v1/order/{order_id}/confirm-payment
        $response = ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Confirm Payment Response'];

        // TODO: Only if api call was
        // Update local state
        if (($localUpdate = $this->updateLocal($order, ['state' => 'completed'])) !== true) {
            return $localUpdate;
        }

        return $response;
    }

    /**
     * Retrieve Order information
     *
     * @param integer $order
     * @return array
     */
    public function retrieveOrder($order)
    {
        // TODO: run GET /v1/order/{order_id}
        $this->getLogger()->info(sprintf('GET /v1/order/%s', $order));
        $models = $this->getEnityManager();
        $repo   = $models->getRepository(Order::class);
        $item   = $repo->find($order);
        $response = [
            'state' => $item->getAttribute()->getBillieState(),
            'order_id' => $item->getId(),
            'bank_account' => [
                'iban' => $item->getAttribute()->getBillieIban(),
                'bic'  => $item->getAttribute()->getBillieBic()
            ],
            'debtor_company' => [
                'name' => $item->getBilling()->getFirstname() . ' ' . $item->getBilling()->getLastname(),
                'address_house_number' => $item->getBilling()->getZipCode(),
                'address_house_street' => $item->getBilling()->getStreet(),
                'address_house_city' => $item->getBilling()->getCity(),
                'address_house_postal_code' => $item->getBilling()->getZipCode(),
                'address_house_country' => $item->getBilling()->getCountry()->getName()
            ]
        ];


        // TODO: Update order details in database with new ones from billie
        // if (($localUpdate = $this->updateLocal($order, ['state' => 'canceled'])) !== true) {
        //     return $localUpdate;
        // }
        
        return $response;
    }

    /**
     * Update local information
     *
     * @param integer $order
     * @param array $data
     * @return bool|array
     */
    public function updateLocal($order, $data)
    {
        // Get order
        $models = $this->getEnityManager();
        $repo   = $models->getRepository(Order::class);
        $item   = $repo->find($order);

        // Order not found
        if (!$item) {
            return [
                'success' => false,
                'title'   => 'Fehler',
                'data'    => sprintf('Bestellung mit ID %s konnte nicht gefunden werden', $order)
            ];
        }

        // set values
        $attr = $item->getAttribute();
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'state':
                    $attr->setBillieState($value);
                    break;

                case 'iban':
                    $attr->setBillieIban($value);
                    break;

                case 'bic':
                    $attr->setBillieBic($value);
                    break;
                
                default:
                    break;
            }
        }

        // save item
        $models->persist($attr);
        $models->flush($attr);

        return true;
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