<?php

namespace BilliePayment;

use BilliePayment\Bootstrap\AbstractBootstrap;
use BilliePayment\Bootstrap\Attributes\OrderAttributes;
use BilliePayment\Bootstrap\Attributes\PaymentMethodAttributes;
use BilliePayment\Bootstrap\Attributes\UserAddressAttributes;
use BilliePayment\Bootstrap\Attributes\UserAttributes;
use BilliePayment\Bootstrap\PaymentMethods;
use BilliePayment\Services\Logger\FileLogger;
use Shopware\Components\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Main Plugin Class with plugin options.
 * Handles (un-)installation and (de-)activation.
 */
class BilliePayment extends Plugin
{

    public static function isPackage()
    {
        return file_exists(self::getPackageVendorAutoload());
    }

    public static function getPackageVendorAutoload()
    {
        return __DIR__ . '/vendor/autoload.php';
    }


    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $loggerServiceName = $this->getContainerPrefix() . '.logger';
        if ($container->has($loggerServiceName) === false) {
            // SW 5.6 auto register a logger for each plugin - so if service not found
            // (cause lower sw-version than 5.6), we will register our own logger
            $container->register($loggerServiceName, FileLogger::class)
                ->addArgument($container->getParameter('kernel.logs_dir'));
        }
    }

    public function install(Plugin\Context\InstallContext $context)
    {
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->preInstall();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->install();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->postInstall();
        }
        parent::install($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    /**
     * @param Plugin\Context\InstallContext $context
     * @return AbstractBootstrap[]
     */
    protected function getBootstrapClasses(Plugin\Context\InstallContext $context)
    {
        /** @var AbstractBootstrap[] $bootstrapper */
        $bootstrapper = [
            new PaymentMethods(),
            new OrderAttributes(),
            new PaymentMethodAttributes(),
            new UserAddressAttributes(),
            new UserAttributes()
        ];

        $logger = new FileLogger($this->container->getParameter('kernel.logs_dir'));
        // initialize all bootstraps
        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->setContext($context);
            $bootstrap->setLogger($logger);
            $bootstrap->setContainer($this->container);
            $bootstrap->setPluginDir($this->getPath());
        }
        return $bootstrapper;
    }

    public function update(Plugin\Context\UpdateContext $context)
    {
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->preUpdate();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->update();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->postUpdate();
        }
        parent::update($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    public function uninstall(Plugin\Context\UninstallContext $context)
    {
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->preUninstall();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->uninstall($context->keepUserData());
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->postUninstall();
        }
        parent::uninstall($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    public function deactivate(Plugin\Context\DeactivateContext $context)
    {
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->preDeactivate();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->deactivate();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->postDeactivate();
        }
        parent::deactivate($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    public function activate(Plugin\Context\ActivateContext $context)
    {
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->preActivate();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->activate();
        }
        foreach ($this->getBootstrapClasses($context) as $bootstrap) {
            $bootstrap->postActivate();
        }
        parent::activate($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }
}

if (BilliePayment::isPackage()) {
    require_once BilliePayment::getPackageVendorAutoload();
}
