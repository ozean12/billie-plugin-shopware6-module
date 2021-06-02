<?php

namespace BilliePayment\Bootstrap;

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

    protected $pluginDir;

    final public function __construct()
    {
    }

    abstract public function install();

    abstract public function update();

    abstract public function uninstall($keepUserData = false);

    abstract public function activate();

    abstract public function deactivate();

    /**
     * @param ContainerInterface $container
     */
    public function setContainer($container)
    {
        $this->container = $container;
        $this->modelManager = $this->container->get('models');
    }

    final public function setContext(InstallContext $context)
    {
        if ($context instanceof UpdateContext) {
            $this->updateContext = $context;
        } elseif ($context instanceof UninstallContext) {
            $this->uninstallContext = $context;
        } elseif ($context instanceof ActivateContext) {
            $this->activateContext = $context;
        } elseif ($context instanceof DeactivateContext) {
            $this->deactivateContext = $context;
        }
        $this->installContext = $context;
    }

    final public function setPluginDir($pluginDir)
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

    final protected function getOldVersion()
    {
        return $this->installContext->getCurrentVersion();
    }

    final protected function getNewVersion()
    {
        return $this->installContext->getPlugin()->getUpdateVersion();
    }
}
