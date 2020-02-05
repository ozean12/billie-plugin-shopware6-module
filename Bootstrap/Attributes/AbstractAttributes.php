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
        $this->tableName = $this->container->get('models')->getClassMetadata($this->getEntityClass())->getTableName().'_attributes';
    }

    protected abstract function getEntityClass();
    protected abstract function createUpdateAttributes();
    protected abstract function uninstallAttributes();

    public function update()
    {
        $this->install();
    }

    public function install()
    {
        $this->createUpdateAttributes();
        $this->cleanUp();
    }

    protected function cleanUp()
    {
        $metaDataCache = $this->modelManager->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();
        $this->modelManager->generateAttributeModels([$this->tableName]);
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
}
