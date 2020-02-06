<?php

namespace BilliePayment\Components\Api;

use Billie\Command\CheckoutSessionConfirm;
use BilliePayment\Components\Utils;
use Doctrine\Common\Collections\Criteria;
use Shopware\Models\Document\Document;
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
use Billie\Util\LegalFormProvider;
use BilliePayment\Components\MissingDocumentsException;
use BilliePayment\Components\MissingLegalFormException;

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
     * @return CreateOrder
     */
    public function createOrderCommand(ApiArguments $args)
    {
        // Create command and fill it, prepend id wiht 'CUSTOMER_ID_' or api will deny it!
        $customer = isset($args->billing['customer']) && array_key_exists('id', $args->billing['customer'])
            ? 'CUSTOMER_ID_' . $args->billing['customer']['id']
            : null;
        $address  = $this->createAddress($args->billing, $args->country);
        $command  = new CreateOrder();

        // Company information, whereas CUSTOMER_ID is the merchant's customer id (null for guest orders)
        $command->debtorCompany                     = new Company($customer, $args->billing['company'], $address);
        $command->debtorCompany->legalForm          = $args->billing['attributes']['billieLegalform'];
        $command->debtorCompany->registrationNumber = $args->billing['attributes']['billieRegistrationnumber'];
        $command->debtorCompany->taxId              = $args->billing['ustid'];

        // Debtor Person data
        $command->debtorPerson            = new Person($args->customerEmail);
        $command->debtorPerson->salution  = $args->billing['salutation'] === 'mr' ? 'm' : 'f'; // m or f
        $command->debtorPerson->firstname = $args->billing['firstname'];
        $command->debtorPerson->lastname  = $args->billing['lastname'];
        $command->debtorPerson->phone     = $args->billing['phone'];

        // amounts are in cent!
        $command->amount          = new Amount($args->amountNet * 100, $args->currency, $args->taxAmount * 100);
        $command->deliveryAddress = $address; // or: new \Billie\Model\Address();
        $command->duration        = $args->duration; // duration=14 => due date = shipping date + 14

        // Check what fields are required based on legal form
        $this->validateLegalForm($command->debtorCompany);

        return $command;
    }

    /**
     * Factory method for the ShipOrder Command
     *
     * @param Order $order
     * @param string|null $invoice
     * @param string|null $url
     * @return ShipOrder
     */
    public function createShipCommand(Order $order, $invoice = null, $url = null)
    {
        $command          = new ShipOrder($order->getAttribute()->getBillieReferenceId());
        $command->orderId = $order->getNumber();

        // Get External Invoice Info
        if (!empty($invoice)) {
            $command->invoiceUrl    = empty($url) || trim($url) === '' ? 'MISSING' : $url; // required!
            $command->invoiceNumber = $invoice;
            return $command;
        }

        // Get Invoice if exists
        $invoice = $this->fetchInvoice($order);

        if (!$invoice) {
            throw new MissingDocumentsException(
                'The Invoice Document is missing. Please generate the documents before marking the order as shipped.'
            );
        }

        // $command->shippingDocumentUrl = 'https://www.example.com/shipping_document.pdf'; // (optional)
        $command->invoiceUrl          = $this->utils->getInvoiceUrl($invoice); // Invoice API Endpoint
        $command->invoiceNumber       = $invoice->getDocumentId(); // required, given by merchant

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
        /** @var Document $document */
        $invoice = $this->fetchInvoice($order);
        if($invoice) {
            $command->invoiceUrl = $this->utils->getInvoiceUrl($invoice);
            $command->invoiceNumber = $invoice->getDocumentId();
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

    public function createConfirmCheckoutSessionCommand($refId, array $amount, $duration)
    {
        $model = new CheckoutSessionConfirm($refId);
        $model->duration = $duration;
        $model->amount = new Amount($amount['net'], $amount['currency'], $amount['tax']);
        return $model;
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
        $address->fullAddress = $billing['street'];
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

    /**
     * Validate the legal form attributes
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @param Company $company
     * @return void
     * @throws MissingLegalFormException if some attributes are missing
     */
    protected function validateLegalForm(Company $company)
    {
        $errors    = [];

        if (LegalFormProvider::isVatIdRequired($company->legalForm) && !$company->taxId) {
            $errors[] = 'MISSING_VAT_ID';
        }

        if (LegalFormProvider::isRegistrationIdRequired($company->legalForm) && !$company->registrationNumber) {
            $errors[] = 'MISSING_REGISTRATION_ID';
        }

        if (!empty($errors)) {
            throw new MissingLegalFormException($errors);
        }
    }
}
