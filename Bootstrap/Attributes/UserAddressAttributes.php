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
        // Get all legal forms.
        $allLegalForms = LegalFormProvider::all();
        $legalData = [];
        foreach ($allLegalForms as $legal) {
            $legalData[] = ['key' => $legal['code'], 'value' => $legal['label']];
        }

        $this->crudService->update($this->tableName, 'billie_registrationNumber', 'string', [
            'label' => 'Registration Number',
            'displayInBackend' => true,
            'custom' => false
        ]);
        $this->crudService->update($this->tableName, 'billie_legalform', 'combobox', [
            'label' => 'Legalform',
            'displayInBackend' => true,
            'arrayStore' => $legalData,
            'custom' => false
        ]);
    }

    protected function uninstallAttributes()
    {
        $this->crudService->delete($this->tableName, 'billie_registrationNumber');
        $this->crudService->delete($this->tableName, 'billie_legalform');
    }
}
