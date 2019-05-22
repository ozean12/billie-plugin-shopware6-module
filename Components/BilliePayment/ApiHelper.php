<?php

namespace BilliePayment\Components\BilliePayment;

use Shopware\Models\Order\Order;
use BilliePayment\Components\Utils;
use Billie\Exception\BillieException;
use Billie\Exception\InvalidCommandException;
use Billie\Exception\OrderDecline\OrderDeclinedException;
use Billie\Exception\OrderDecline\DebtorAddressException;
use Billie\Exception\OrderDecline\DebtorLimitExceededException;
use Billie\Exception\OrderDecline\DebtorNotIdentifiedException;
use Billie\Exception\OrderDecline\RiskPolicyDeclinedException;

/**
 * Helper Class to manage error messages and
 * the local state of orders.
 */
class ApiHelper
{
    /**
     * @var \BilliePayment\Components\Utils
     */
    protected $utils = null;

    /**
     * Set the utils helper via DI
     *
     * @param Utils $utils
     */
    public function __construct(Utils $utils)
    {
        $this->utils = $utils;
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
        $methods = ['state' => 'setBillieState', 'iban' => 'setBillieIban', 'bic' => 'setBillieBic', 'reference' => 'setBillieReferenceId'];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $methods) && method_exists($attr, $methods[$key])) {
                $attr->{$methods[$key]}($value);
            }
        }

        // save item
        $models = $this->utils->getEnityManager();
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
    public function getOrder($order)
    {
        $models = $this->utils->getEnityManager();
        $repo   = $models->getRepository(Order::class);
        return $repo->find($order);
    }

    /**
     * Get the order not found data message
     *
     * @param integer $order
     * @return array
     */
    public function orderNotFoundMessage($order)
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
    public function errorMessage(BillieException $exc, array $local = [])
    {
        $this->utils->getLogger()->error(sprintf('[%s]: %s', $exc->getBillieCode(), $exc->getBillieMessage()));
        return ['success' => false, 'data' => $exc->getBillieCode(), 'local' => $local];
    }

    /**
     * Generate Response based on declined order exception
     *
     * @param OrderDeclinedException $exc
     * @param array $local
     * @return array
     */
    public function declinedErrorMessage(OrderDeclinedException $exc, array $local = [])
    {
        // Get Code of OrderDecined child
        $code = $exc->getBillieCode();
        switch (get_class($exc)) {
            case DebtorAddressException::class:
                $code = 'DEBTOR_ADDRESS';
                break; 
            case DebtorNotIdentifiedException::class:
                $code = 'DEBTOR_NOT_IDENTIFIED';
                break; 
            case RiskPolicyDeclinedException::class:
                $code = 'RISK_POLICY';
                break; 
            case DebtorLimitExceededException::class:
                $code = 'DEBTOR_LIMIT_EXCEEDED';
                break;
        }

        $this->utils->getLogger()->error(sprintf('[%s]: %s', $code, $exc->getBillieMessage()));
        return ['success' => false, 'data' => $code, 'local' => $local];
    }

    /**
     * Generate InvalidCommand error message
     *
     * @param InvalidCommandException $exc
     * @param array $local
     * @return array
     */
    public function invalidCommandMessage(InvalidCommandException $exc, array $local = [])
    {
        $violations = $exc->getViolations();
        $this->utils->getLogger()->error('[InvalidCommandException]: ' . implode('; ', $violations));
        return ['success' => false, 'data' => 'InvalidCommandException', 'local' => $local];
    }
}
