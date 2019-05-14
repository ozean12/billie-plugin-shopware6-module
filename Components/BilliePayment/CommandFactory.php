<?php

namespace BilliePayment\Components\BilliePayment;

use Doctrine\Common\Collections\Criteria;
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
        $customer = isset($args->billing['customer']) && array_key_exists('id', $args->billing['customer']) ? $args->billing['customer']['id'] : null;
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

        // TODO: Remove Test data that works with billie api
        $command = new CreateOrder();

        $companyAddress = new Address();
        $companyAddress->street = 'Charlottenstr.';
        $companyAddress->houseNumber = '4';
        $companyAddress->postalCode = '10969';
        $companyAddress->city = 'Berlin';
        $companyAddress->countryCode = 'DE';
        $command->debtorCompany = new Company('BILLIE-00000001', 'Billie GmbH', $companyAddress);
        $command->debtorCompany->legalForm = '10001';

        $command->debtorPerson = new Person('max.mustermann@musterfirma.de');
        $command->debtorPerson->salution = 'm';
        $command->debtorPerson->phone = '+4930120111111';
        $command->deliveryAddress = $companyAddress; // or: new \Billie\Model\Address();
        $command->amount = new Amount(100, 'EUR', 19);

        $command->duration = 14;

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
        $command          = new ShipOrder($order->getAttribute()->getBillieReferenceId());
        $command->orderId = $order->getNumber();

        // Get Invoice if exists
        $criteria  = Criteria::create()->where(Criteria::expr()->eq('typeId', '1'));
        $documents = $order->getDocuments()->matching($criteria);
        
        if ($documents->count()) {
            $invoice                      = $documents->first();
            $command->invoiceNumber       = $invoice->getDocumentId(); // required, given by merchant
            $command->invoiceUrl          = 'https://www.example.com/invoice.pdf'; // TODO: required, given by merchant
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
        $criteria  = Criteria::create()->where(Criteria::expr()->eq('typeId', '1'));
        $documents = $order->getDocuments()->matching($criteria);

        if ($order->getAttribute()->getBillieState() === 'shipped' && $documents->count()) {
            $invoice                      = $documents->first();
            $command->invoiceNumber       = $invoice->getDocumentId(); // required, given by merchant
            $command->invoiceUrl          = 'https://www.example.com/invoice.pdf'; // TODO: required, given by merchant
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
