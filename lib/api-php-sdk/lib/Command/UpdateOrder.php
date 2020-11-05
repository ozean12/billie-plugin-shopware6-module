<?php

namespace Billie\Command;

use Billie\Model\Amount;
use Shopware\Models\Order\Order;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class UpdateOrder
 *
 * @package Billie\Command
 * @author Marcel Barten <github@m-barten.de>
 */
class UpdateOrder
{

    /**
     * @var string
     */
    public $referenceId;
    /**
     * @var int
     */
    public $duration;
    /**
     * @var Amount
     */
    public $amount;
    /**
     * @var string
     */
    public $invoiceNumber;
    /**
     * @var string
     */
    public $invoiceUrl;
    /**
     * @var string
     */
    public $orderId;

    /**
     * @var Order
     */
    private $order;

    /**
     * @param Order $order
     * @param string $referenceId
     */
    public function __construct(Order $order, $referenceId)
    {
        $this->order = $order;
        $this->orderId = $order->getNumber();
        $this->referenceId = $referenceId;
    }

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraints('referenceId', [
            new Assert\Uuid(),
            new Assert\NotBlank()
        ]);

    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }
}
