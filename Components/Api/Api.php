<?php

namespace BilliePayment\Components\Api;

use Billie\Command\CheckoutSessionConfirm;
use Billie\Mapper\CreateOrderMapper;
use BilliePayment\Components\Utils;
use BilliePayment\Components\MissingDocumentsException;
use BilliePayment\Services\ConfigService;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;
use Billie\HttpClient\BillieClient;
use Billie\Exception\BillieException;
use Billie\Exception\OrderDecline\OrderDeclinedException;
use Billie\Exception\InvalidCommandException;
use BilliePayment\Components\MissingLegalFormException;

/**
 * Service Wrapper for billie API sdk
 */
class Api
{
    /**
     * @var ConfigService
     */
    protected $config = [];

    /**
     * @var BillieClient
     */
    protected $client = null;

    /**
     * @var CommandFactory
     */
    protected $factory = null;

    /**
     * @var Helper
     */
    public $helper = null;

    /**
     * @var Utils
     */
    protected $utils = null;

    /**
     * Load Plugin config
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @param Helper $helper
     * @param Utils $utils
     * @param CommandFactory $factory
     * @param ConfigService $config
     */
    public function __construct(
        Helper $helper,
        Utils $utils,
        CommandFactory $factory,
        ConfigService $config
)
    {
        // initialize Billie Client
        $this->config = $config;
        $this->helper  = $helper;
        $this->factory = $factory;
        $this->utils   = $utils;
        $this->client  = BillieClient::create($config->getClientId(), $config->getClientSecret(), $config->isSandbox());
    }

    public function createCheckoutSession(Customer $customer) {
        return $this->client->checkoutSessionCreate($customer->getId());
    }

    public function confirmCheckoutSession(CheckoutSessionConfirm $confirmModel)
    {
        return $this->client->checkoutSessionConfirm($confirmModel); // TODO does not work - 2020-01-10
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
        $builder = $this->utils->getQueryBuilder();
        $builder->select(['orders, attribute'])
            ->from(Order::class, 'orders')
            ->leftJoin('orders.attribute', 'attribute')
            ->leftJoin('orders.payment', 'payment')
            ->addFilter($filters)
            ->andWhere('orders.number != 0')
            ->andWhere('orders.status != -1')
            ->addOrderBy($sorting)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        // Get Query and paginator
        $query = $builder->getQuery();
        $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        $paginator = $this->utils->getEnityManager()->createPaginator($query);

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
        $local = [];

        // Call API Endpoint to create order -> POST /v1/order
        try {
            /** @var \Billie\Model\Order $order */
            $order              = $this->client->createOrder($this->factory->createOrderCommand($args));
            $local['reference'] = $order->referenceId;
            $local['state']     = $order->state;
            $local['iban']      = $order->bankAccount->iban;
            $local['bic']       = $order->bankAccount->bic;
            $this->utils->getLogger()->info('POST /v1/order');
        }
        // Missing Legal Form attributes
        catch (MissingLegalFormException $exc) {
            return [
                'success' => false,
                'title'   => $this->utils->getSnippet('backend/billie_overview/errors', 'error'),
                'data'    => $exc->getErrorCodes()[0]
            ];
        }
        // Order Declined -> Billie User Error Message
        catch (OrderDeclinedException $exc) {
            return $this->helper->declinedErrorMessage($exc, ['state' => 'declined']);
        }
        // Invalid Command -> Non-technical user error message
        catch (InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch (BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }

        return ['success' => true, 'local' => $local];
    }

    /**
     * Full Cancelation of an order on billie site.
     *
     * @param integer $order
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
            $this->client->cancelOrder(
                $this->factory->createCancelCommand($item->getAttribute()->getBillieReferenceid())
            );
            $this->utils->getLogger()->info("POST /v1/order/{$order}/cancel");
            $local['state'] = 'canceled';
        }
        // Invalid Command -> Non-technical user error message
        catch (InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch (BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }

        // Update local state
        if (($localUpdate = $this->helper->updateLocal($item, $local)) !== true) {
            return $localUpdate;
        }

        return ['success' => true];
    }

    /**
     * Mark order as shipped
     *
     * @param integer $order
     * @param string|null $invoice
     * @param string|null $url
     * @return array
     */
    public function shipOrder($order, $invoice = null, $url = null)
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
            return ['success' => false, 'data' => 'ORDER_CANNOT_BE_SHIPPED'];
        }

        // run POST /v1/order/{order_id}/ship
        try {
            /** @var \Billie\Model\Order $response */
            $response       = $this->client->shipOrder($this->factory->createShipCommand($item, $invoice, $url));
            $local['state'] = $response->state;
            $this->utils->getLogger()->info("POST /v1/order/{$order}/ship");
            // $dueDate = $order->invoice->dueDate;
        }
        // Missing Documents
        catch (MissingDocumentsException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }
        // Invalid Command -> Non-technical user error message
        catch (InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch (BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }

        // Update local state
        if (($localUpdate = $this->helper->updateLocal($item, $local)) !== true) {
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
            $command        = $this->factory->createReduceAmountCommand($item, $data['amount']);
            $order          = $this->client->reduceOrderAmount($command);
            $local['state'] = $order->state;
            $this->utils->getLogger()->info("PATCH /v1/order/{$order}");
        }

        // Postpone Due Date
        if (in_array('duration', $data)) {
            $command = $this->factory->createPostponeDueDateCommand($refId, $data['duration']);

            try {
                /** @var \Billie\Model\Order $response */
                $response       = $this->client->postponeOrderDueDate($command);
                $local['state'] = $response->state;
                // $dueDate = $order->invoice->dueDate;
            }
            // Invalid Command -> Non-technical user error message
            catch (InvalidCommandException $exc) {
                return $this->helper->invalidCommandMessage($exc);
            }
            // Catch other billie exceptions
            catch (BillieException $exc) {
                return $this->helper->errorMessage($exc, $local);
            }
        }

        // Update local state
        if (($localUpdate = $this->helper->updateLocal($item, $local)) !== true) {
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
            $refId          = $item->getAttribute()->getBillieReferenceid();
            $command        = $this->factory->createConfirmPaymentCommand($refId, $amount);
            $order          = $this->client->confirmPayment($command);
            $local['state'] = $order->state;
            $this->utils->getLogger()->info("POST /v1/order/{$order}/confirm-payment");
        }
        // Invalid Command -> Non-technical user error message
        catch (InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch (BillieException $exc) {
            return $this->helper->errorMessage($exc, $local);
        }

        // Update local state
        if (($localUpdate = $this->helper->updateLocal($item, $local)) !== true) {
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
            $this->utils->getLogger()->info("GET /v1/order/{$order}");
            $response       = $this->client->getOrder($item->getAttribute()->getBillieReferenceId());
            $local['state'] = $response->state;
            $local['iban']  = $response->bankAccount->iban;
            $local['bic']   = $response->bankAccount->bic;
        }
        // Invalid Command -> Non-technical user error message
        catch (InvalidCommandException $exc) {
            return $this->helper->invalidCommandMessage($exc);
        }
        // Catch other billie exceptions
        catch (BillieException $exc) {
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
            ],
            'amount' => $response->amount,
            'amountNet' => $response->amountNet
        ];

        // Update order details in database with new ones from billie
        if (($localUpdate = $this->helper->updateLocal($item, $local)) !== true) {
            return $localUpdate;
        }

        return $response;
    }

    /**
     * @return BillieClient
     */
    public function getClient()
    {
        return $this->client;
    }
}
