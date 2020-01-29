<?php


namespace BilliePayment\Bootstrap\Attributes;


use Shopware\Models\Payment\Payment;

class PaymentMethodAttributes extends AbstractAttributes
{

    protected function getEntityClass()
    {
        return Payment::class;
    }

    protected function createUpdateAttributes()
    {
        $this->crudService->update($this->tableName, 'billie_duration', 'integer', [
            'label' => 'Term of Payment',
            'helpText' => 'Number of days until the customer has to pay the invoice',
            'displayInBackend' => true,
            'custom' => false
        ], null, false, 14);
    }

    protected function uninstallAttributes()
    {
        $this->crudService->delete($this->tableName, 'billie_duration');
    }
}
