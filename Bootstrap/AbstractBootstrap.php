<?php


namespace BilliePayment\Bootstrap;


use Monolog\Logger;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractBootstrap
{

    /**
     * @var InstallContext|UpdateContext
     */
    protected $updateContext;
    /**
     * @var InstallContext|UninstallContext
     */
    protected $uninstallContext;
    /**
     * @var ActivateContext|InstallContext
     */
    protected $activateContext;
    /**
     * @var DeactivateContext|InstallContext
     */
    protected $deactivateContext;
    /**
     * @var InstallContext
     */
    protected $installContext;

    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var ModelManager
     */
    protected $modelManager;

    /**
     * @var Logger
     */
    protected $logger;

    protected $pluginDir;

    public final function __construct()
    {
    }

    public abstract function install();

    public abstract function update();

    public abstract function uninstall($keepUserData = false);

    public abstract function activate();

    public abstract function deactivate();

    /**
     * @param ContainerInterface $container
     */
    public function setContainer($container)
    {
        $this->container = $container;
        $this->modelManager = $this->container->get('models');
    }

    public final function setContext(InstallContext $context)
    {
        if ($context instanceof UpdateContext) {
            $this->updateContext = $context;
        } else if ($context instanceof UninstallContext) {
            $this->uninstallContext = $context;
        } else if ($context instanceof ActivateContext) {
            $this->activateContext = $context;
        } else if ($context instanceof DeactivateContext) {
            $this->deactivateContext = $context;
        }
        $this->installContext = $context;
    }

    public final function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param mixed $pluginDir
     */
    public final function setPluginDir($pluginDir)
    {
        $this->pluginDir = $pluginDir;
    }

    public function preInstall()
    {
    }

    public function preUpdate()
    {
    }

    public function preUninstall($keepUserData = false)
    {
    }

    public function preActivate()
    {
    }

    public function preDeactivate()
    {
    }

    public function postActivate()
    {
    }

    public function postDeactivate()
    {
    }

    public function postUninstall()
    {
    }

    public function postUpdate()
    {
    }

    public function postInstall()
    {
    }

    protected final function getOldVersion()
    {
        return $this->installContext->getCurrentVersion();
    }

    protected final function getNewVersion()
    {
        return $this->installContext->getPlugin()->getUpdateVersion();
    }
}
