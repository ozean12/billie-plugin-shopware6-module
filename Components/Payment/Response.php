<?php

namespace BilliePayment\Components\Payment;

/**
 * Datastructure for the payment response.
 */
class Response
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
