<?php

namespace BilliePayment\Components;

use Shopware\Models\Order\Document\Document;
use Shopware\Components\Plugin\ConfigReader;

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
     * @var array
     */
    protected $config = [];

    /**
     * Load Plugin Config
     *
     * @param ConfigReader $configReader
     * @param string $pluginName
     */
    public function __construct(ConfigReader $configReader, $pluginName)
    {
        $this->config = $configReader->getByPluginName($pluginName);
    }

    /**
     * Get the plugin Configuration
     *
     * @return array
     */
    public function getPluginConfig()
    {
        return $this->config;
    }

    /**
     * Get a snippet via the snippet mananger
     *
     * @param string $namespace
     * @param string $snippet
     * @param mixed $default
     * @return mixed
     */
    public function getSnippet($namespace, $snippet, $default = null)
    {
        /** @var \Shopware_Components_Snippet_Manager $snippets */
        $snippets = Shopware()->Container()->get('snippets');
        return $snippets->getNamespace($namespace)->get($snippet, $default);
    }

    /**
     * Assemble the invoice url
     *
     * @param Document $document
     * @return string
     */
    public function getInvoiceUrl(Document $document)
    {
        $data = [
            'controller' => 'BillieInvoice',
            'action'     => 'invoice',
            'module'     => 'frontend',
            'hash'       => $document->getHash()
        ];

        return Shopware()->Front()->Router()->assemble($data);
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
            $this->logger = Shopware()->Container()->get('billie_payment.logger');
        }

        return $this->logger;
    }
}
