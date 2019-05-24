<?php

namespace BilliePayment\Components\Api;

use BilliePayment\Components\Utils;
use Doctrine\Common\Collections\Criteria;
use Shopware\Models\Order\Order;
use Billie\Model\Address;
use Billie\Model\Amount;
use Billie\Model\Company;
use Billie\Model\Person;
use Billie\Command\CancelOrder;
use Billie\Command\CreateOrder;
use Billie\Command\ConfirmPayment;
use Billie\Command\PostponeOrderDueDate;
use Billie\Command\ReduceOrderAmount;
use Billie\Command\ShipOrder;

/**
 * Factory class to create billie sdk commands
 * fillied with the required parameters.
 */
class CommandFactory
{
    /**
     * Document Type for invoices
     * @var integer
     */
    const INVOICE_TYPE = 1;

    /**
     * @var Utils
     */
    protected $utils = null;

    /**
     * Set Utils via DI.
     *
     * @param Utils $utils
     */
    public function __construct(Utils $utils)
    {
        $this->utils = $utils;
    }

    /**
     * Factory method for the CreateOrder Command
     *
     * @param ApiArguments $args
     * @param int $duration
     * @return CreateOrder
     */
    public function createOrderCommand(ApiArguments $args, $duration)
    {
        // Create command and fill it, prepend id wiht 'CUSTOMER_ID_' or api will deny it!
        $customer = isset($args->billing['customer']) && array_key_exists('id', $args->billing['customer']) ? 'CUSTOMER_ID_' . $args->billing['customer']['id'] : null;
        $address  = $this->createAddress($args->billing, $args->country);
        $command  = new CreateOrder();

        // Company information, whereas CUSTOMER_ID is the merchant's customer id (null for guest orders)
        $command->debtorCompany                     = new Company($customer, $args->billing['company'], $address);
        $command->debtorCompany->legalForm          = $args->billing['attributes']['billieLegalform'];
        $command->debtorCompany->registrationNumber = $args->billing['attributes']['billieRegistrationnumber'];

        // Debtor Person data
        $command->debtorPerson            = new Person($args->customerEmail);
        $command->debtorPerson->salution  = $args->billing['salutation'] === 'mr' ? 'm' : 'f'; // m or f
        $command->debtorPerson->firstname = $args->billing['firstname'];
        $command->debtorPerson->lastname  = $args->billing['lastname'];
        $command->debtorPerson->phone     = $args->billing['phone'];

        // amounts are in cent!
        $command->amount          = new Amount($args->amountNet * 100, $args->currency, $args->taxAmount * 100);
        $command->deliveryAddress = $address; // or: new \Billie\Model\Address();
        $command->duration        = $duration; // duration=14 meaning: when the order is shipped on the 1st May, the due date is the 15th May

        return $command;
    }

    /**
     * Factory method for the ShipOrder Command
     *
     * @param Order $order
     * @return ShipOrder
     */
    public function createShipCommand(Order $order)
    {
        $command          = new ShipOrder($order->getAttribute()->getBillieReferenceId());
        $command->orderId = $order->getNumber();

        // Get Invoice if exists
        $invoice = $this->fetchInvoice($order);

        if ($invoice) {
            $command->invoiceNumber       = $invoice->getDocumentId(); // required, given by merchant
            $command->invoiceUrl          = $this->utils->getInvoiceUrl($invoice); // Invoice API Endpoint
            // $command->shippingDocumentUrl = 'https://www.example.com/shipping_document.pdf'; // (optional)
        }

        return $command;
    }

    /**
     * Factory method for the ReduceOrderAmount command
     *
     * @param Order $order
     * @param array $amount
     * @return ReduceOrderAmount
     */
    public function createReduceAmountCommand(Order $order, $amount)
    {
        $command         = new ReduceOrderAmount($order->getAttribute()->getBillieReferenceId());
        $command->amount = new Amount(
            $amount['net'],
            $amount['currency'],
            $amount['gross'] - $amount['net'] // Gross - Net = Tax Amount
        );

        // Get Invoice if exists
        $invoice = $this->fetchInvoice($order);

        if ($invoice) {
            $command->invoiceNumber       = $invoice->getDocumentId(); // required, given by merchant
            $command->invoiceUrl          = $this->utils->getInvoiceUrl($invoice); // Invoice API Endpoint
            // $command->shippingDocumentUrl = 'https://www.example.com/shipping_document.pdf'; // (optional)
        }

        return $command;
    }

    /**
     * Factory method for the PostponeOrderDueDate command
     *
     * @param string $refId
     * @param integer duration
     * @return PostponeOrderDueDate
     */
    public function createPostponeDueDateCommand($refId, $duration)
    {
        $command           = new PostponeOrderDueDate($refId);
        $command->duration = $duration;

        return $command;
    }

    /**
     * Create cancel order Command
     *
     * @param string $refId
     * @return CancelOrder
     */
    public function createCancelCommand($refId)
    {
        return new CancelOrder($refId);
    }

    /**
     * Create the confirm payment command
     *
     * @param string $refId
     * @param float $amount
     * @return ConfirmPayment
     */
    public function createConfirmPaymentCommand($refId, $amount)
    {
        return new ConfirmPayment($refId, $amount * 100); // amount are in cents!
    }

    /**
     * Fill Address Model
     *
     * @param array $billing
     * @param array $country
     * @return Address
     */
    public function createAddress(array $billing, array $country)
    {
        $address              = new Address();
        $address->street      = $billing['street'];
        $address->houseNumber = $billing['additionalAddressLine1'];
        $address->postalCode  = $billing['zipcode'];
        $address->city        = $billing['city'];
        $address->countryCode = $country['countryiso'];

        return $address;
    }

    /**
     * Fetch the invoice document if it exists
     *
     * @param Order $order
     * @return \Shopware\Models\Order\Document\Document|false
     */
    protected function fetchInvoice(Order $order)
    {
        $criteria  = Criteria::create()->where(Criteria::expr()->eq('typeId', self::INVOICE_TYPE));
        $documents = $order->getDocuments()->matching($criteria);

        if (in_array($order->getAttribute()->getBillieState(), ['shipped', 'created']) && $documents->count()) {
            return $documents->first();
        }

        return false;
    }
}
