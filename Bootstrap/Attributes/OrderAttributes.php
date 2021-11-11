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
        $attributeList = [
            'billie_referenceId',
            'billie_state',
            'billie_iban',
            'billie_bic',
            'billie_bank',
            'billie_duration',
            'billie_duration_date',
            'billie_external_invoice_number',
            'billie_external_invoice_url'
        ];

        foreach ($attributeList as $attributeCode) {
            $this->deleteAttribute($attributeCode);
        }
    }
}
