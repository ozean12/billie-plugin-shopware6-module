<?php

namespace BilliePayment\Components\BilliePayment;

/**
 * Datastructure for the api arguments.
 */
class ApiArguments
{
    /**
     * Paid Tax Amount
     *
     * @var float
     */
    public $taxAmount;

    /**
     * Paid net amount
     *
     * @var float
     */
    public $amountNet;

    /**
     * Billing Information
     *
     * @var array
     */
    public $billing;

    /**
     * Customer Email
     *
     * @var string
     */
    public $customerEmail;

    /**
     * Currency string, e.g. EUR
     *
     * @var string
     */ 
    public $currency;

    /**
     * Country information, e.g. name, iso, etc.
     *
     * @var array
     */
    public $country;
}
