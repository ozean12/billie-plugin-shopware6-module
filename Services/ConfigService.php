<?php


namespace BilliePayment\Services;


use InvalidArgumentException;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Shop\Shop;

class ConfigService
{

    /**
     * @var CachedConfigReader
     */
    private $configReader;
    /**
     * @var ModelManager
     */
    private $modelManager;
    private $pluginName;

    public function __construct(CachedConfigReader $configReader, ModelManager $modelManager, $pluginName)
    {
        $this->configReader = $configReader;
        $this->modelManager = $modelManager;
        $this->pluginName = $pluginName;
    }

    public function getClientId($shop = null)
    {
        return $this->getConfig('billiepayment/credentials/client_id', null, $shop);
    }

    public function getClientSecret($shop = null)
    {
        return $this->getConfig('billiepayment/credentials/client_secret', null, $shop);
    }

    public function isSandbox($shop = null)
    {
        return $this->getConfig('billiepayment/mode/sandbox', false, $shop);
    }

    public function getSalutationMapping($shop = null)
    {
        $male = explode(',', $this->getConfig('billiepayment/salutation/male', false, $shop));
        $female = explode(',', $this->getConfig('billiepayment/salutation/female', false, $shop));

        $male = array_map('trim', $male);
        $female = array_map('trim', $female);
        return ['male' => $male, 'female' => $female];
    }
    public function getFallbackSalutation($shop = null)
    {
        return $this->getConfig('billiepayment/salutation/default', 'm', $shop);
    }

    public function isOverrideCustomerAddress($shop = null) {
        return $this->getConfig('billiepayment/override_address', false, $shop);
    }

    /**
     * @param $configName
     * @param null $default
     * @param Shop|int $shop
     * @return mixed
     */
    protected function getConfig($configName, $default = null, $shop = null)
    {
        if ($shop === null) {
            $shop = $this->modelManager->getRepository(Shop::class)->getActiveDefault();
        } else if (is_numeric($shop)) {
            $shop = $this->modelManager->find(Shop::class, $shop);
            if ($shop === null) {
                throw new InvalidArgumentException('the given shop does not exist');
            }
        } else if ($shop instanceof Shop === false) {
            throw new InvalidArgumentException('please provide a valid shop parameter (Shop-Model, Shop-Id or NULL for the default default)');
        }
        $config = $this->configReader->getByPluginName($this->pluginName, $shop);
        return isset($config[$configName]) ? $config[$configName] : $default;
    }


}
