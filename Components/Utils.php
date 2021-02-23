<?php

namespace BilliePayment\Components;

use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Models\Order\Document\Document;

/**
 * Utility Class
 */
class Utils
{

    /**
     * @var array
     */
    protected $config = [];

    public function __construct(
        ConfigReader $configReader,
        $pluginName
    )
    {
        $this->config = $configReader->getByPluginName($pluginName);
    }

    /**
     * Get the plugin Configuration
     * @return array
     * @deprecated
     */
    public function getPluginConfig()
    {
        return $this->config;
    }

    /**
     * Get a snippet via the snippet mananger
     * @param string $namespace
     * @param string $snippet
     * @deprecated
     */
    public function getSnippet($namespace, $snippet, $default = null)
    {
        /** @var \Shopware_Components_Snippet_Manager $snippets */
        $snippets = Shopware()->Container()->get('snippets');

        return $snippets->getNamespace($namespace)->get($snippet, $default);
    }
}
