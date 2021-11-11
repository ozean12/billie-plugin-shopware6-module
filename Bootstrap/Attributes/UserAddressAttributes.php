<?php

namespace BilliePayment\Bootstrap\Attributes;

use Shopware\Models\Customer\Address;

class UserAddressAttributes extends AbstractAttributes
{
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

    protected function getEntityClass()
    {
        return Address::class;
    }

    protected function createUpdateAttributes()
    {
    }

    protected function uninstallAttributes()
    {
        $this->deleteAttribute('billie_registrationNumber');
        $this->deleteAttribute('billie_legalform');
    }
}
