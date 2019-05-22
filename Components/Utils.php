<?php

namespace BilliePayment\Components;

use Shopware\Models\Shop\Shop;

/**
 * Utility Class
 */
class Utils
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    protected $entityManager = null;

    /**
     * @var \Shopware\Components\Logger
     */
    protected $logger = null;

    /**
     * @var \Shopware\Components\Model\QueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * Get the plugin Configuration
     *
     * @return array
     */
    public function getPluginConfig()
    {
        // Get Plugin Config
        $container = Shopware()->Container();
        $shop      = $container->initialized('shop')
            ? $container->get('shop')
            : $container->get('models')->getRepository(Shop::class)->getActiveDefault();

        return $container
            ->get('shopware.plugin.cached_config_reader')
            ->getByPluginName('BilliePayment', $shop);
    }

    /**
     * Internal helper function to get access to the query builder.
     *
     * @return \Shopware\Components\Model\QueryBuilder
     */
    public function getQueryBuilder()
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = $this->getEnityManager()->createQueryBuilder();
        }

        return $this->queryBuilder;
    }

    /**
     * Internal helper function to get access the Model Manager.
     *
     * @return \Shopware\Components\Model\ModelManager
     */
    public function getEnityManager()
    {
        if ($this->entityManager === null) {
            $this->entityManager = Shopware()->Container()->get('models');
        }

        return $this->entityManager;
    }

    /**
     * Internal helper function to get access the plugin logger
     *
     * @return \Shopware\Components\Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = Shopware()->Container()->get('pluginlogger');
        }

        return $this->logger;
    }
}
