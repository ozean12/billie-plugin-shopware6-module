<?php

namespace BilliePayment\Components\BilliePayment;

/**
 * Datastructure for the payment response.
 */
class PaymentResponse
{
    /**
     * @var int
     */
    public $transactionId;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $status;

    /**
     * @var string
     */
    public $signature;
}
