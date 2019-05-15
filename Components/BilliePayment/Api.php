<?php

namespace BilliePayment\Components\BilliePayment;

use Exception;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Billie\HttpClient\BillieClient;
use Billie\Exception\BillieException;
use Billie\Exception\OrderDecline\OrderDeclinedException;
use Billie\Exception\InvalidCommandException;
use Billie\Command\CancelOrder;
use Billie\Command\ConfirmPayment;

/**
 * Service Wrapper for billie API sdk
 */
class Api
{
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
     * @var ApiHelper
     */
    public $helper = null;

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
        $this->helper         = new ApiHelper();
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
        $builder = $this->helper->getQueryBuilder();
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
        $paginator = $this->helper->getEnityManager()->createPaginator($query);

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
            $this->helper->getLogger()->info('POST /v1/order');
        } 
        // Order Declined -> Billie User Error Message
        catch (OrderDeclinedException $exc) {
            return $this->helper->declinedErrorMessage($exc, ['state' => 'declined']);
        }
        // Invalid Command -> Non-technical user error message
        catch(InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch(BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
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
        $local = [];
        $item  = $this->helper->getOrder($order);
        
        if (!$item) {
            return $this->helper->orderNotFoundMessage($order);
        }

        // run POST /v1/order/{order_id}/cancel
        try {
            $this->client->cancelOrder(new CancelOrder($item->getAttribute()->getBillieReferenceId()));
            $this->helper->getLogger()->info(sprintf('POST /v1/order/%s/cancel', $order));
            $local['state'] = 'canceled';
        }
        // Invalid Command -> Non-technical user error message
        catch(InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch(BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }
        
        // Update local state
        if (($localUpdate = $this->helper->updateLocal($order, $local)) !== true) {
            return $localUpdate;
        }
        
        return ['success' => true];
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
        $item  = $this->helper->getOrder($order);
        
        if (!$item) {
            return $this->helper->orderNotFoundMessage($order);
        }

        // Already shipped/canceled
        $state = $item->getAttribute()->getBillieState();
        if ($state == 'shipped' || $state == 'canceled') {
            return;
        }

        // run POST /v1/order/{order_id}/ship
        try {
            /** @var Billie\Model\Order $response */
            $response       = $this->client->shipOrder($this->commandFactory->createShipCommand($item));
            $local['state'] = $response->state;
            $this->helper->getLogger()->info(sprintf('POST /v1/order/{order_id}/ship', $order));
            // $dueDate = $order->invoice->dueDate;
        } 
        // Invalid Command -> Non-technical user error message
        catch(InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch(BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }

        // Update local state
        if (($localUpdate = $this->helper->updateLocal($order, $local)) !== true) {
            return $localUpdate;
        }

        return ['success' => true];
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
        $item  = $this->helper->getOrder($order);
        
        if (!$item) {
            return $this->helper->orderNotFoundMessage($order);
        }

        $refId = $item->getAttribute()->getBillieReferenceId();

        // Reduce Amount
        if (in_array('amount', $data)) {
            $command        = $this->commandFactory->createReduceAmountCommand($item, $data['amount']);
            $order          = $this->client->reduceOrderAmount($command);
            $local['state'] = $order->state;
            $this->helper->getLogger()->info(sprintf('PATCH /v1/order/{order_id}', $item->getId()));
        }

        // Postpone Due Date
        if (in_array('duration', $data)) {
            $command = $this->commandFactory->createPostponeDueDateCommand($refId, $data['duration']);
        
            try {
                /** @var Billie\Model\Order $response */
                $response       = $this->client->postponeOrderDueDate($command);
                $local['state'] = $response->state;
                // $dueDate = $order->invoice->dueDate;
            } 
            // Invalid Command -> Non-technical user error message
            catch(InvalidCommandException $exc) {
                return $this->helper->invalidCommandMessage($exc);
            }
            // Catch other billie exceptions
            catch(BillieException $exc) {
                return $this->helper->errorMessage($exc, $local);
            }
        }

        // Update local state
        if (($localUpdate = $this->helper->updateLocal($order, $local)) !== true) {
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
        $item  = $this->helper->getOrder($order);
        
        if (!$item) {
            return $this->helper->orderNotFoundMessage($order);
        }

        // Call POST /v1/order/{order_id}/confirm-payment
        try {
            $command        = new ConfirmPayment($item->getAttribute()->getBillieReferenceId(), $amount * 100); // amount are in cents!
            $order          = $this->client->confirmPayment($command);
            $local['state'] = $order->state;
            $this->helper->getLogger()->info(sprintf('POST /v1/order/%s/confirm-payment', $order));
        } 
        // Invalid Command -> Non-technical user error message
        catch(InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch(BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }

        // Update local state
        if (($localUpdate = $this->helper->updateLocal($item->getId(), $local)) !== true) {
            return $localUpdate;
        }
        
        return ['success' => true];
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
        $item  = $this->helper->getOrder($order);
        
        if (!$item) {
            return $this->helper->orderNotFoundMessage($order);
        }

        // GET /v1/order/{order_id}
        try {
            $this->helper->getLogger()->info(sprintf('GET /v1/order/%s', $order));
            $response       = $this->client->getOrder($item->getAttribute()->getBillieReferenceId());
            $local['state'] = $response->state; 
            $local['iban']  = $response->bankAccount->iban;
            $local['bic']   = $response->bankAccount->bic;
        } 
        // Invalid Command -> Non-technical user error message
        catch(InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch(BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
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
        if (($localUpdate = $this->helper->updateLocal($order, $local)) !== true) {
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
            $item = $this->helper->getOrder($order);

            if (!$item) {
                return $this->helper->orderNotFoundMessage($order);
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
}
