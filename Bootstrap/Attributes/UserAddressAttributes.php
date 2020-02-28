<?php


namespace BilliePayment\Bootstrap\Attributes;


use Billie\Util\LegalFormProvider;
use Shopware\Models\Customer\Address;

class UserAddressAttributes extends AbstractAttributes
{

    protected function getEntityClass()
    {
        return Address::class;
    }

    protected function createUpdateAttributes()
    {
    }

    public function install()
    {
        // these attributes are not required anymore
        $this->uninstall(false);
    }

    public function update()
    {
        // these attributes are not required anymore
        $this->uninstall(false);
    }

    protected function uninstallAttributes()
    {
        if ($this->crudService->get($this->tableName, 'billie_registrationNumber')) {
            $this->crudService->delete($this->tableName, 'billie_registrationNumber');
        }
        if ($this->crudService->get($this->tableName, 'billie_legalform')) {
            $this->crudService->delete($this->tableName, 'billie_legalform');
        }
    }
}
