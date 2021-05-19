<?php

namespace BilliePayment\Bootstrap\Attributes;

use Shopware\Models\Order\Order;

class OrderAttributes extends AbstractAttributes
{
    protected function getEntityClass()
    {
        return Order::class;
    }

    protected function createUpdateAttributes()
    {
        $this->crudService->update($this->tableName, 'billie_referenceId', 'string');
        $this->crudService->update($this->tableName, 'billie_state', 'string');
        $this->crudService->update($this->tableName, 'billie_iban', 'string');
        $this->crudService->update($this->tableName, 'billie_bic', 'string');
        $this->crudService->update($this->tableName, 'billie_bank', 'string');
        $this->crudService->update($this->tableName, 'billie_duration', 'integer');
        $this->crudService->update($this->tableName, 'billie_duration_date', 'string');
        $this->crudService->update($this->tableName, 'billie_external_invoice_number', 'string', [
            'displayInBackend' => true,
            'label' => 'Billie Payment: External invoice number',
            'translatable' => false,
        ]);
        $this->crudService->update($this->tableName, 'billie_external_invoice_url', 'string', [
            'displayInBackend' => true,
            'label' => 'Billie Payment: External invoice url',
            'translatable' => false,
        ]);
    }

    protected function uninstallAttributes()
    {
        $this->crudService->delete($this->tableName, 'billie_referenceId');
        $this->crudService->delete($this->tableName, 'billie_state');
        $this->crudService->delete($this->tableName, 'billie_iban');
        $this->crudService->delete($this->tableName, 'billie_bic');
        $this->crudService->delete($this->tableName, 'billie_bank');
        $this->crudService->delete($this->tableName, 'billie_duration');
        $this->crudService->delete($this->tableName, 'billie_duration_date');
        $this->crudService->delete($this->tableName, 'billie_external_invoice_number');
        $this->crudService->delete($this->tableName, 'billie_external_invoice_url');
    }
}
