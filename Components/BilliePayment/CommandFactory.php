<?php

namespace BilliePayment\Components\BilliePayment;

use Shopware\Models\Order\Order;
use Shopware\Models\Order\Billing;
use Billie\Model\Address;
use Billie\Model\Amount;
use Billie\Model\Company;
use Billie\Model\Person;
use Billie\Command\CreateOrder;
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
     * Factory method for the CreateOrder Command
     *
     * @param ApiArguments $args
     * @param int $duration
     * @return CreateOrder
     */
    public function createOrderCommand(ApiArguments $args, $duration)
    {
        // Create command and fill it
        $address = $this->createAddress($args->billing, $args->country);
        $command = new CreateOrder();

        // TODO: Company information, whereas CUSTOMER_ID is the merchant's customer id (use _null_ for guest orders)
        $command->debtorCompany = new Company(
            $args->billing['customer']['id'],
            $args->billing['company'],
            $address
        );
        $command->debtorCompany->legalForm = '10001'; //TODO: find correct legelform
        $command->debtorPerson             = new Person($args->customerEmail);
        $command->debtorPerson->salution   = $args->billing['salutation'] === 'mr' ? 'm' : 'f'; // m or f
        $command->debtorPerson->firstname  = $args->billing['firstname'];
        $command->debtorPerson->lastname   = $args->billing['lastname'];
        $command->debtorPerson->phone      = $args->billing['phone'];
        $command->deliveryAddress          = $address; // or: new \Billie\Model\Address();

        // amounts are in cent!
        $command->amount   = new Amount($args->amountNet * 100, $args->currency, $args->taxAmount * 100);
        $command->duration = $duration; // duration=14 meaning: when the order is shipped on the 1st May, the due date is the 15th May

        return  $command;
    }

    /**
     * Factory method for the ShipOrder Command
     *
     * @param Order $order
     * @return ShipOrder
     */
    public function createShipCommand(Order $order)
    {
        $command                      = new ShipOrder($order->getAttribute()->getBillieReferenceId());
        $command->orderId             = $order->getId(); // TODO: order_id or order_number? id that the customer know
        $command->invoiceNumber       = '12/0001/2019'; // required, given by merchant
        $command->invoiceUrl          = 'https://www.example.com/invoice.pdf'; // required, given by merchant
        $command->shippingDocumentUrl = 'https://www.example.com/shipping_document.pdf'; // (optional)

        return $command;
    }

    /**
     * Factory method for the ReduceOrderAmount command
     *
     * @param string $refId
     * @param string $state
     * @param array $amount
     * @return ReduceOrderAmount
     */
    public function createReduceAmountCommand($refId, $state, $amount)
    {
        $command = new ReduceOrderAmount($refId);
        $command->amount = new Amount(
            $amount['net'],
            $amount['currency'],
            $amount['gross'] - $amount['net'] // Gross - Net = Tax Amount
        );

        // TODO: ONLY if the order has been SHIPPED already, you need to provide a invoice url and invoice number
        if ($state === 'shipped') {
            $command->invoiceNumber = '12/0002/2019';
            $command->invoiceUrl    = 'https://www.example.com/invoice_new.pdf';
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
     * Fill Address Model
     *
     * @param array $billing
     * @param array $country
     * @return Address
     */
    public function createAddress(array $billing, array $country)
    {
        $address              = new Address();
        $address->street      = $billing['street']; // TODO: Split street and housenumber
        $address->houseNumber = $billing['street']; // TODO: Split street and housenumber
        $address->postalCode  = $billing['zipcode'];
        $address->city        = $billing['city'];
        $address->countryCode = $country['countryiso'];

        return $address;
    }
}
