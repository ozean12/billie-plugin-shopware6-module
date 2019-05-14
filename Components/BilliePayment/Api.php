<?php

namespace BilliePayment\Components\BilliePayment;

use Exception;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Billie\HttpClient\BillieClient;
use Billie\Exception\BillieException;
use Billie\Exception\OrderDecline\OrderDeclinedException;
use Billie\Exception\InvalidCommandException;
use Billie\Exception\DebtorAddressException;
use Billie\Exception\OrderNotCancelledException;
use Billie\Exception\OrderNotShippedException;
use Billie\Exception\PostponeDueDateNotAllowedException;
use Billie\Command\CancelOrder;
use Billie\Command\ConfirmPayment;

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
     * @SuppressWarnings(PHPMD.StaticAccess)
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
        $this->client         = BillieClient::create($this->config['apikey'], $this->config['sandbox']);
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
     * @param ApiArguments $args
     * @return array
     */
    public function createOrder(ApiArguments $args)
    {
        $local  = [];

        // Call API Endpoint to create order -> POST /v1/order
        try {
            /** @var Billie\Model\Order $order */
            $order              = $this->client->createOrder($this->commandFactory->createOrderCommand($args, $this->config['duration']));
            $local['reference'] = $order->referenceId;
            $local['state']     = $order->state;
            $local['iban']      = $order->bankAccount->iban;
            $local['bic']       = $order->bankAccount->bic;
            $this->getLogger()->info('POST /v1/order');
        } 
        // Order Declined -> Billie User Error Message
        catch (OrderDeclinedException $exc) {
            return $this->errorMessage($exc, ['state' => 'declined']);
        }
        // Invalid Command -> Non-technical user error message
        catch(InvalidCommandException $exc) {
            return $this->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch(BillieException $exc) {
            return $this->errorMessage($exc, $local);
        }
 
        return ['success' => true, 'local' => $local];
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

        // run POST /v1/order/{order_id}/cancel
        try {
            $command = new CancelOrder($item->getAttribute()->getBillieReferenceId());
            $this->client->cancelOrder($command);
            $this->getLogger()->info(sprintf('POST /v1/order/%s/cancel', $order));
        } catch(InvalidCommandException $exception) {
            $violations = $exception->getViolations();
            $this->getLogger()->error('InvalidCommandException: ' . implode('; ', $violations));
            return ['success' => false, 'title' => 'Error', 'data' => $violations];
        } 
        catch (OrderNotCancelledException $exception) {
            $message = $exception->getBillieMessage();
            return ['success' => false, 'title' => 'Error', 'data' => $message];
        }

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

        // Already shipped/canceled
        $state = $item->getAttribute()->getBillieState();
        if ($state == 'shipped' || $state == 'canceled') {
            return;
        }

        // run POST /v1/order/{order_id}/ship
        try {
            /** @var Billie\Model\Order $order */
            $order          = $this->client->shipOrder($this->commandFactory->createShipCommand($item));
            $local['state'] = $order->state;
            $this->getLogger()->info(sprintf('POST /v1/order/{order_id}/ship', $order));
            // $dueDate = $order->invoice->dueDate;
        } catch (OrderNotShippedException $exception) {
            // $message    = $exception->getBillieMessage();
            // $messageKey = $exception->getBillieCode();
            $reason     = $exception->getReason();

            return ['success' => false, 'title' => 'Error', 'data' => $reason];
        } catch(InvalidCommandException $exception) {
            $violations = $exception->getViolations();
            $this->getLogger()->error('InvalidCommandException: ' . implode('; ', $violations));
            return ['success' => false, 'title' => 'Error', 'data' => $violations];
        } catch(Exception $exception) {
            $this->getLogger()->info('An exception occured: ' . $exception->getMessage());
            return ['success' => false, 'title' => 'Error', 'data' => $exception->getMessage()];
        }
        
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

        // Reduce Amount
        if (in_array('amount', $data)) {
            $command        = $this->commandFactory->createReduceAmountCommand($item, $data['amount']);
            $order          = $this->client->reduceOrderAmount($command);
            $local['state'] = $order->state;
            $this->getLogger()->info(sprintf('PATCH /v1/order/{order_id}', $item->getId()));
        }

        // Postpone Due Date
        if (in_array('duration', $data)) {
            $command = $this->commandFactory->createPostponeDueDateCommand($refId, $data['duration']);
        
            try {
                /** @var Billie\Model\Order $order */
                $order          = $this->client->postponeOrderDueDate($command);
                $local['state'] = $order->state;
                // $dueDate = $order->invoice->dueDate;
            } catch(InvalidCommandException $exception) {
                $violations = $exception->getViolations();
                $this->getLogger()->error('InvalidCommandException: ' . implode('; ', $violations));
                return ['success' => false, 'title' => 'Error', 'data' => $violations];
            }catch (PostponeDueDateNotAllowedException $exception) {
                $message    = $exception->getBillieMessage();
                // $messageKey = $exception->getBillieCode();
                return ['success' => false, 'title' => 'Error', 'data' => $message];
            }
        }

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

        // Call POST /v1/order/{order_id}/confirm-payment
        try {
            $command        = new ConfirmPayment($item->getAttribute()->getBillieReferenceId(), $amount * 100); // amount are in cents!
            $order          = $this->client->confirmPayment($command);
            $local['state'] = $order->state;
            $this->getLogger()->info(sprintf('POST /v1/order/%s/confirm-payment', $order));
        } catch(InvalidCommandException $exception) {
            $violations = $exception->getViolations();
            $this->getLogger()->error('InvalidCommandException: ' . implode('; ', $violations));
            return ['success' => false, 'title' => 'Error', 'data' => $violations];
        } catch (BillieException $exception) {
            $message    = $exception->getBillieMessage();
            // $messageKey = $exception->getBillieCode();
            return ['success' => false, 'title' => 'Error', 'data' => $message];
        }

        // Update local state
        if (($localUpdate = $this->updateLocal($item->getId(), $local)) !== true) {
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

        // GET /v1/order/{order_id}
        try {
            $response = $this->client->getOrder($item->getAttribute()->getBillieReferenceId());
            $this->getLogger()->info(sprintf('GET /v1/order/%s', $order));
            $local['state'] = $response->state; 
            $local['iban']  = $response->bankAccount->iban;
            $local['bic']   = $response->bankAccount->bic;
        } catch(InvalidCommandException $exception) {
            $violations = $exception->getViolations();
            $this->getLogger()->error('InvalidCommandException: ' . implode('; ', $violations));
            return ['success' => false, 'title' => 'Error', 'data' => $violations];
        }

        // Retrieved Data
        $response = [
            'success'      => true,
            'state'        => $response->state,
            'order_number' => $item->getNumber(),
            'order_id'     => $item->getId(),
            'bank_account' => [
                'iban' => $response->bankAccount->iban,
                'bic'  => $response->bankAccount->bic
            ],
            'debtor_company' => [
                'name'                      => $response->debtorCompany->name,
                'address_house_number'      => $response->debtorCompany->address->houseNumber,
                'address_house_street'      => $response->debtorCompany->address->street,
                'address_house_city'        => $response->debtorCompany->address->city,
                'address_house_postal_code' => $response->debtorCompany->address->postalCode,
                'address_house_country'     => $response->debtorCompany->address->countryCode
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
        $item = $order;
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
     * Generate Response based on exception
     *
     * @param BillieException $exc
     * @param array $local
     * @return array
     */
    private function errorMessage(BillieException $exc, array $local = [])
    {
        $this->getLogger()->error($exc->getBillieMessage());
        return ['success' => false, 'data' => $exc->getBillieCode(), 'local' => $local];
    }

    /**
     * Generate InvalidCommand error message
     *
     * @param InvalidCommandException $exc
     * @param array $local
     * @return array
     */
    private function invalidCommandMessage(InvalidCommandException $exc, array $local = [])
    {
        $violations = $exc->getViolations();
        $this->getLogger()->error('InvalidCommandException: ' . implode('; ', $violations));
        return ['success' => false, 'data' => 'InvalidCommandException', 'local' => $local];
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
