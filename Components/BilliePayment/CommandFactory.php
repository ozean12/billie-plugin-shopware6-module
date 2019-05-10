<?php

namespace BilliePayment\Components\BilliePayment;

use Shopware\Models\Order\Order;
use Shopware\Models\Order\Billing;

/**
 * Factory class to create billie sdk commands
 * fillied with the required parameters.
 */
class CommandFactory
{
    /**
     * Factory method for the CreateOrder Command
     *
     * @param Order $order
     * @return CreateOrder
     */
    public function createOrderCommand(Order $order)
    {
        // Prepare data
        $amountNet   = $order->getInvoiceAmountNet() + $order->getInvoiceShippingNet();
        $amountGross = $order->getInvoiceAmount() + $order->getInvoiceShipping();
        $taxRate     = round(($amountGross / $amountNet - 1) * 100); // TODO: find correct rate in db
        $billing     = $order->getBilling();
        $address     = $this->createAddress($billing);

        // Create command and fill it
        $command = new Billie\Command\CreateOrder();

        // TODO: Company information, whereas CUSTOMER_ID is the merchant's customer id (use _null_ for guest orders)
        $command->debtorCompany = new Billie\Model\Company(
            $order->getCustomer()->getId(),
            $billing->getCompany(),
            $address
        );
        $command->debtorCompany->legalForm = '10001'; //TODO: find correct legelform
        $command->debtorPerson             = new Billie\Model\Person($order->getCustomer()->getEmail());
        $command->debtorPerson->salution   = $billing->getSalutation() === 'mr' ? 'm' : 'f'; // m or f
        $command->deliveryAddress          = $address; // or: new \Billie\Model\Address();

        $command->amount   = new Billie\Model\Amount($amountGross * 100, $order->getCurrency(), $taxRate); // amounts are in cent!
        $command->duration = $this->config['duration']; // duration=14 meaning: when the order is shipped on the 1st May, the due date is the 15th May

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
        $command = new Billie\Command\ShipOrder($order->getAttribute()->getBillieReferenceId());
        $command->orderId = $order->getId(); // TODO: order_id or order_number? id that the customer know
        $command->invoiceNumber = '12/0001/2019'; // required, given by merchant
        $command->invoiceUrl = 'https://www.example.com/invoice.pdf'; // required, given by merchant
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
        $command = new Billie\Command\ReduceOrderAmount($refId);
        $command->amount = new Billie\Model\Amount(
            $amount['net'],
            $amount['currency'],
            $amount['gross'] - $amount['net'] // Gross - Net = Tax Amount
        );

        // TODO: ONLY if the order has been SHIPPED already, you need to provide a invoice url and invoice number
        if ($state === 'shipped') {
            $command->invoiceNumber = '12/0002/2019';
            $command->invoiceUrl = 'https://www.example.com/invoice_new.pdf';
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
        $command = new Billie\Command\PostponeOrderDueDate($refId);
        $command->duration = $duration;
        
        return $command;
    }

    /**
     * Fill Address Model
     *
     * @param Billing $billing
     * @return Address
     */
    public function createAddress(Billing $billing)
    {
        $address = new Billie\Model\Address();
        $address->street      = $billing->getStreet(); // TODO: Split street and housenumber
        $address->houseNumber = $billing->getStreet(); // TODO: Split street and housenumber
        $address->postalCode  = $billing->getZipCode();
        $address->city        = $billing->getCity();
        $address->countryCode = $billing->getCountry()->getIso();

        return $address;
    }
}
