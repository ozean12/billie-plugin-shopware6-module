<?php

namespace BilliePayment\Bootstrap\Attributes;

use Shopware\Models\Customer\Customer;

class UserAttributes extends AbstractAttributes
{
    protected function getEntityClass()
    {
        return Customer::class;
    }

    protected function createUpdateAttributes()
    {
        $this->crudService->update($this->tableName, 'billie_iban', 'string', [
            'label' => 'IBAN',
            'displayInBackend' => true,
            'custom' => false,
        ]);
        $this->crudService->update($this->tableName, 'billie_bic', 'string', [
            'label' => 'BIC',
            'displayInBackend' => true,
            'custom' => false,
        ]);
    }

    protected function uninstallAttributes()
    {
        $this->deleteAttribute('billie_iban');
        $this->deleteAttribute('billie_bic');
    }
}
