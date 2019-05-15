<?php

namespace BilliePayment\Components\BilliePayment;

use Shopware\Models\Order\Order;
use Billie\Exception\BillieException;
use Billie\Exception\InvalidCommandException;

/**
 * Helper Class to manage error messages and
 * the local state of orders.
 */
class ApiHelper
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
    public function getOrder($order)
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
    public function invalidCommandMessage(InvalidCommandException $exc, array $local = [])
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
    public function getQueryBuilder()
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
    public function getEnityManager()
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
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = Shopware()->Container()->get('pluginlogger');
        }
        
        return $this->logger;
    }
}
