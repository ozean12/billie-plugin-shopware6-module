<?php

namespace BilliePayment\Bootstrap\Attributes;

use BilliePayment\Bootstrap\AbstractBootstrap;
use Shopware\Bundle\AttributeBundle\Service\CrudService;

abstract class AbstractAttributes extends AbstractBootstrap
{
    /**
     * @var CrudService
     */
    protected $crudService;

    /**
     * @var string table name of the entity which should be extended
     */
    protected $tableName;

    public function setContainer($container)
    {
        parent::setContainer($container);
        $this->crudService = $this->container->get('shopware_attribute.crud_service');
        $this->tableName = $this->container->get('models')->getClassMetadata($this->getEntityClass())->getTableName() . '_attributes';
    }

    public function update()
    {
        $this->install();
    }

    public function install()
    {
        $this->createUpdateAttributes();
        $this->cleanUp();
    }

    public function uninstall($keepUserData = false)
    {
        if ($keepUserData === false) {
            $this->uninstallAttributes();
            $this->cleanUp();
        }
    }

    public function activate()
    {
        // do nothing
    }

    public function deactivate()
    {
        // do nothing
    }

    abstract protected function getEntityClass();

    abstract protected function createUpdateAttributes();

    abstract protected function uninstallAttributes();

    protected function cleanUp()
    {
        $this->modelManager->generateAttributeModels([$this->tableName]);
    }

    protected function deleteAttribute($attributeCode)
    {
        if ($this->crudService->get($this->tableName, $attributeCode)) {
            $this->crudService->delete($this->tableName, $attributeCode);
        }
    }
}
