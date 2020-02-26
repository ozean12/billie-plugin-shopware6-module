<?php

namespace BilliePayment\Components\Api;

use Billie\Command\UpdateOrder;
use Billie\Exception\BillieException;
use Billie\Exception\InvalidCommandException;
use Billie\Exception\InvalidRequestException;
use Billie\Exception\OrderDecline\OrderDeclinedException;
use Billie\HttpClient\BillieClient;
use Billie\Mapper\RetrieveOrderMapper;
use Billie\Model\Amount;
use Billie\Model\DebtorCompany;
use BilliePayment\Components\MissingDocumentsException;
use BilliePayment\Components\MissingLegalFormException;
use BilliePayment\Components\Utils;
use BilliePayment\Services\BankService;
use BilliePayment\Services\ConfigService;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;
use Shopware\Models\Payment\Payment;

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
     * @var BankService
     */
    private $bankService;

    /**
     * @var ModelManager
     */
    protected $modelManager;

    /**
     * Load Plugin config
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @param Helper $helper
     * @param Utils $utils
     * @param CommandFactory $factory
     * @param ConfigService $config
     * @param BankService $bankService
     */
    public function __construct(
        Helper $helper,
        Utils $utils,
        CommandFactory $factory,
        ConfigService $config,
        BankService $bankService
)
    {
        // initialize Billie Client
        $this->config = $config;
        $this->helper  = $helper;
        $this->factory = $factory;
        $this->utils   = $utils;
        $this->client  = BillieClient::create($config->getClientId(), $config->getClientSecret(), $config->isSandbox());
        $this->bankService = $bankService;
        $this->modelManager = $this->utils->getEnityManager(); // TODO move to DI
    }

    public function createCheckoutSession(Customer $customer) {
        return $this->client->checkoutSessionCreate($customer->getId());
    }

    /**
     * @param $refId
     * @param Address|DebtorCompany $address
     * @param Payment $paymentMethod
     * @param $amount
     * @param $currency
     * @return \Billie\Model\Order
     * @throws InvalidCommandException
     */
    public function confirmCheckoutSession($refId, $address, Payment $paymentMethod, $amount, $currency)
    {
        $amount['currency'] = $currency;

        $model = $this->factory->createConfirmCheckoutSessionCommand(
            $refId,
            $address instanceof Address ? $this->factory->createDebtorCompany($address) : $address,
            $amount,
            $paymentMethod->getAttribute()->getBillieDuration()
        );
        $response = $this->client->checkoutSessionConfirm($model);
        if(is_array($response)) {
            return RetrieveOrderMapper::orderObjectFromArray($response);
        } else {
            return $response;
        }
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
        $builder->select(['orders, attribute', 'billing'])
            ->from(Order::class, 'orders')
            ->leftJoin('orders.attribute', 'attribute')
            ->leftJoin('orders.payment', 'payment')
            ->leftJoin('orders.billing', 'billing')
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
     * refunds partly or completly the order. if the amount is greater or equal than the actual open amount on
     * billie PaD, the order will canceled
     * @param $orderId
     * @param $amount
     * @return array
     * @throws BillieException
     * @throws InvalidCommandException
     */
    public function partlyRefund($orderId, $amount)
    {
        $shopwareOrder = $this->utils->getEnityManager()->find(Order::class, $orderId);
        $order = $this->client->getOrder($shopwareOrder->getTransactionId());
        if ($amount >= $order->amount) {
            $return = $this->cancelOrder($shopwareOrder->getId());
            $partly = false;
        } else {
            $newAmount = ($order->amount - $amount) * 100;
            $taxRate = round(($order->amount / $order->amountNet), 2);

            $model = $this->factory->createReduceAmountCommand($shopwareOrder, [
                'net' => round($newAmount / $taxRate, 0),
                'gross' => round($newAmount, 0),
                'currency' => $shopwareOrder->getCurrency(),
            ]);
            try {
                $return = $this->client->reduceOrderAmount($model);
                $partly = true;
            } catch (InvalidCommandException | BillieException $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage() ? : implode('', $e->getViolations())
                ];
            }
        }
        if (is_array($return)) {
            if (isset($return['success'])) {
                $return['partly'] = $partly;
                return $return;
            } else if (isset($return['uuid'])) {
                $order = $this->getClient()->getOrder($return['uuid']);
                return [
                    'success' => true,
                    'partly' => $order->state !== \Billie\Model\Order::STATE_CANCELLED
                ];
            }
        } else if ($return instanceof \Billie\Model\Order) {
            return [
                'success' => true,
                'partly' => $return->state !== \Billie\Model\Order::STATE_CANCELLED
            ];
        }
        return [
            'success' => false,
            'error' => 'Unknown error.'
        ];
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

    public function updateOrderAmount($order, $net, $gross)
    {
        if (is_numeric($order)) {
            $order = $this->modelManager->find(Order::class, $order);
        }

        try {
            $command = $this->factory->createReduceAmountCommand($order, [
                'net' => floatval($net) * 100,
                'gross' => floatval($gross) * 100,
                'currency' => $order->getCurrency()
            ]);
            $billieOrder = $this->client->reduceOrderAmount($command);
            $order->getAttribute()->setBillieState($billieOrder->state);
            $this->modelManager->flush($order);
            return true;
        } catch (\Exception $e) {
            $this->utils->getLogger()->info("Error during `updateOrderAmount`. Message: ".$e->getMessage());
            return false;
        }
    }

    /**
     * Update an order
     *
     * @param Order $order
     * @param \Billie\Model\Order $billieOrder
     * @return \Billie\Model\Order|boolean
     */
    public function updateOrder(Order $order, \Billie\Model\Order $billieOrder)
    {
        try {
            $updateOrder = new UpdateOrder($billieOrder->referenceId);
            $updateOrder->orderId = $billieOrder->orderId;
            $response = $this->client->updateOrder($updateOrder);

            if ($response instanceof \Billie\Model\Order) {
                $order->getAttribute()->setBillieState($response->state);
                $this->modelManager->flush($order);
                return $billieOrder;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $this->utils->getLogger()->info("Error during `updateOrderAmount`. Message: ".$e->getMessage());
            return false;
        }
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
                'bic'  => $response->bankAccount->bic,
                'bank' => $this->bankService->getBankData($item, $response)['name']
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
