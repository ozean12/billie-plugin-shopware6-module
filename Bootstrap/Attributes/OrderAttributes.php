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
    }

    protected function uninstallAttributes()
    {
        // TODO: Implement uninstallAttributes() method.
    }
}
