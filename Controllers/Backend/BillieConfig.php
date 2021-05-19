<?php

use Billie\Sdk\Exception\BillieException;
use Billie\Sdk\Util\BillieClientFactory;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Plugin\DBALConfigReader;
use Symfony\Component\HttpFoundation\JsonResponse;

class Shopware_Controllers_Backend_BillieConfig extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * @var DBALConfigReader
     */
    private $configReader;

    /**
     * @var string
     */
    private $pluginName;

    /**
     * @var Shopware_Components_Snippet_Manager|void|null
     */
    private $snippetManager;

    public function setContainer(Container $loader = null)
    {
        parent::setContainer($loader);
        $this->configReader = $this->container->get('shopware.plugin.config_reader');
        $this->snippetManager = $this->container->get('snippets');
        $this->pluginName = $this->container->getParameter('billie_payment.plugin_name');
    }

    public function getWhitelistedCSRFActions()
    {
        return ['test'];
    }

    public function testAction()
    {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

        $messageNamespace = $this->snippetManager->getNamespace('backend/billie/messages');

        $returnData = [];
        $config = $this->configReader->getByPluginName($this->pluginName);

        $isSandbox = isset($config['billiepayment/mode/sandbox']) ? $config['billiepayment/mode/sandbox'] : null;
        $clientId = isset($config['billiepayment/credentials/client_id']) ? $config['billiepayment/credentials/client_id'] : null;
        $clientSecret = isset($config['billiepayment/credentials/client_secret']) ? $config['billiepayment/credentials/client_secret'] : null;

        if ($isSandbox === null || $clientId === null || $clientSecret === null) {
            $returnData['message'] = $messageNamespace->get('PluginConfigNotSaved');
            $returnData['success'] = false;
        } else {
            try {
                BillieClientFactory::getAuthToken($clientId, $clientSecret, $isSandbox);
                $returnData['success'] = true;
                $returnData['message'] = $messageNamespace->get('ValidCredentials');
            } catch (BillieException $e) {
                $returnData['success'] = false;
                $returnData['message'] = $messageNamespace->get('InvalidCredentials');
            }
        }

        $returnData['statusText'] = $messageNamespace->get($returnData['success'] ? 'StatusSuccess' : 'StatusFailed');
        (new JsonResponse($returnData))->send();
    }
}
