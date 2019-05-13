<?php

namespace BilliePayment\Components\BilliePayment;

use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Billie\HttpClient\BillieClient;

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

    /**
     * @var BillieClient
     */
    protected $client = null;

    /**
     * @var CommandFactory
     */
    protected $commandFactory = null;

    /**
     * Load Plugin config
     */
    public function __construct()
    {
        $container = Shopware()->Container();
        $shop      = $container->initialized('shop')
            ? $container->get('shop')
            : $container->get('models')->getRepository(Shop::class)->getActiveDefault();

        $this->config = $container
            ->get('shopware.plugin.cached_config_reader')
            ->getByPluginName('BilliePayment', $shop);

        
        // initialize Billie Client
        $this->commandFactory = new CommandFactory();
        // $this->client = BillieClient::create($this->config['apikey'], $this->config['sandbox']);
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
        // Get Order from db
        $local  = [];
        $models = $this->getEnityManager();
        $repo   = $models->getRepository(Order::class);
        $order  = $repo->findOneBy(['number' => $orderNumber]);
        
        if (!$order) {
            return $this->orderNotFoundMessage($orderNumber);
        }

        // TODO: Call API Endpoint to create order -> POST /v1/order
        // try {
        //     /** @var Billie\Model\Orderr $order */
        //     $order = $this->client->createOrder($this->commandFactory->createOrderCommand($order, $this->config['duration']));
        //     $this->getLogger()->info('POST /v1/order');
        //     $local['referenceId'] = $order->referenceId;
        //     $local['state'] = $order->state;
        // } catch (Billie\Exception\OrderDeclinedException $exception) {
        //     $message = $exception->getBillieMessage();
        //     // for custom translation
        //     $messageKey = $exception->getBillieCode();
        //     $reason = $exception->getReason();
        //     $local['state'] = 'declined';
        //     return ['success' => false, 'title' => 'Error', 'data' => $reason];
        // }

        // Update local state
        if (($localUpdate = $this->updateLocal($order->getId(), $local)) !== true) {
            return $localUpdate;
        }

        return ['success' => true, 'messages' => 'OK'];
    }

    /**
     * Full Cancelation of an order on billie site.
     *
     * @param integer $orderf
     * @return array
     */
    public function cancelOrder($order)
    {
        // Get Order
        $local = ['state' => 'canceled'];
        $item  = $this->getOrder($order);
        
        if (!$item) {
            return $this->orderNotFoundMessage($order);
        }

        // TODO: run POST /v1/order/{order_id}/cancel
        // try {
        //     $command = new Billie\Command\CancelOrder($item->getAttribute()->getBillieReferenceId());
        //     $this->client->cancelOrder($command);
        //     $this->getLogger()->info(sprintf('POST /v1/order/%s/cancel', $order));
        // } catch (Billie\Exception\OrderNotCancelledException $exception) {
        //     $message = $exception->getBillieMessage();
        //     $messageKey = $exception->getBillieCode();
        //     // $local = ['state' => 'canceled'];

        //     return ['success' => false, 'title' => 'Error', 'data' => $message];
        // }

        // Update local state
        if (($localUpdate = $this->updateLocal($order, $local)) !== true) {
            return $localUpdate;
        }
        
        return ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Cancelation Response'];
    }

    /**
     * Mark order as shipped
     *
     * @param integer $order
     * @return array
     */
    public function shipOrder($order)
    {
        // Get Order
        $local = [];
        $item  = $this->getOrder($order);
        
        if (!$item) {
            return $this->orderNotFoundMessage($order);
        }

        // TODO: run POST /v1/order/{order_id}/ship
        // try {
        //     /** @var Billie\Model\Order $order */
        //     $order = $this->client->shipOrder($this->commandFactory->createShipCommand($item));
        //     $local['state'] = $order->state;
        //     $this->getLogger()->info(sprintf('POST /v1/order/{order_id}/ship', $order));
        //     $dueDate = $order->invoice->dueDate;
        // } catch (Billie\Exception\OrderNotShippedException $exception) {
        //     // TODO: Flag billie state as declined based on api response
        //     $message = $exception->getBillieMessage();
        //     $messageKey = $exception->getBillieCode();
        //     $reason = $exception->getReason();
        //     $local['state'] = 'declined';
        //
        //     return ['success' => false, 'title' => 'Error', 'data' => $reason];
        // }
        
        // Update local state
        if (($localUpdate = $this->updateLocal($order, $local)) !== true) {
            return $localUpdate;
        }

        return ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Ship Response'];
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
        // Get Order
        $local = [];
        $item  = $this->getOrder($order);
        
        if (!$item) {
            return $this->orderNotFoundMessage($order);
        }

        $refId = $item->getAttribute()->getBillieReferenceId();
        $state = $item->getAttribute()->getBillieState();

        // Reduce Amount
        // if (in_array('amount', $data)) {
        //     $command = $this->commandFactory->createReduceAmountCommand($refId, $state, $data['amount']);
        //     // TODO: run PATCH /v1/order/{order_id}
        //     $order = $this->client->reduceOrderAmount($command);
        //     $local['state'] = $order->state;
        //     $this->getLogger()->info(sprintf('PATCH /v1/order/{order_id}', $item->getId()));
        // }

        // // Postpone Due Date
        // if (in_array('duration', $data)) {
        //     $command = $this->commandFactory->createPostponeDueDateCommand($refId, $data['duration']);
        //
        //     try {
        //         /** @var Billie\Model\Orderr $order */
        //         $order = $this->client->postponeOrderDueDate($command);
        //         $local['state'] = $order->state;
        //         $dueDate = $order->invoice->dueDate;
        //     } catch (Billie\Exception\PostponeDueDateNotAllowedException $exception) {
        //         $message = $exception->getBillieMessage();
        //         $messageKey = $exception->getBillieCode();
        //         return ['success' => false, 'title' => 'Error', 'data' => $message];
        //     }
        // }

        // Update local state
        if (($localUpdate = $this->updateLocal($order, $local)) !== true) {
            return $localUpdate;
        }


        return ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Update Response'];
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
        // Get Order
        $local = [];
        $item  = $this->getOrder($order);
        
        if (!$item) {
            return $this->orderNotFoundMessage($order);
        }

        // // TODO: Call POST /v1/order/{order_id}/confirm-payment
        // try {
        //     $command = new Billie\Command\ConfirmPayment($item->getAttribute()->getBillieReferenceId(), $amount);
        //     $order = $this->client->confirmPayment($command);
        //     $local['state'] = $order->state;
        //     $this->getLogger()->info(sprintf('POST /v1/order/%s/confirm-payment', $order));
        // } catch (Billie\Exception\BillieException $exception) {
        //     $message = $exception->getBillieMessage();
        //     $messageKey = $exception->getBillieCode();
        //     return ['success' => false, 'title' => 'Error', 'data' => $message];
        // }

        // Update local state
        if (($localUpdate = $this->updateLocal($order, $local)) !== true) {
            return $localUpdate;
        }
        
        return ['success' => true, 'title' => 'Erfolgreich', 'data' => 'Confirm Payment Response'];
    }

    /**
     * Retrieve Order information
     *
     * @param integer $order
     * @return array
     */
    public function retrieveOrder($order)
    {
        // Get Order
        $local = [];
        $item  = $this->getOrder($order);
        
        if (!$item) {
            return $this->orderNotFoundMessage($order);
        }

        // TODO: run GET /v1/order/{order_id}
        // try {
        //     $response = $this->client->getOrder($item->getAttribute()->getBillieReferenceId());
        //     $this->getLogger()->info(sprintf('GET /v1/order/%s', $order));
        //     $local['state'] = $response->state; 
        //     $local['iban'] = $response->bankAccount->iban;
        //     $local['bic'] = $response->bankAccount->bic;
        // } catch(Billie\Exception\InvalidCommandException $exception) {
        //     return ['success' => false, 'title' => 'Error', 'data' => $exception];
        // }
        
        // TODO: Delete testdata
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

        // Update order details in database with new ones from billie
        if (($localUpdate = $this->updateLocal($order, $local)) !== true) {
            return $localUpdate;
        }
        
        return $response;
    }

    /**
     * Update local information
     *
     * @param Order|integer $order
     * @param array $data
     * @return bool|array
     */
    public function updateLocal($order, $data)
    {
        // Get Order if necessary.
        if (!$order instanceof Order) {
            $item = $this->getOrder($order);

            if (!$item) {
                return $this->orderNotFoundMessage($order);
            }
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

                case 'reference':
                    $attr->setBillieReferenceId($value);
                
                default:
                    break;
            }
        }

        // save item
        $models = $this->getEnityManager();
        $models->persist($attr);
        $models->flush($attr);

        return true;
    }

    /**
     * Get an order by id
     *
     * @param integer $order
     * @return Order
     */
    protected function getOrder($order)
    {
        $models = $this->getEnityManager();
        $repo   = $models->getRepository(Order::class);
        return $repo->find($order);
    }

    /**
     * Get the order not found data message
     *
     * @param integer $order
     * @return array
     */
    private function orderNotFoundMessage($order)
    {
        return [
            'success' => false,
            'title'   => 'Fehler',
            'data'    => sprintf('Bestellung mit ID %s konnte nicht gefunden werden', $order)
        ];
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
