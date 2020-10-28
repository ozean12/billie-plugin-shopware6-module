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

require_once __DIR__ . '/lib/api-php-sdk/vendor/autoload.php';

/**
 * Main Plugin Class with plugin options.
 * Handles (un-)installation and (de-)activation.
 */
class BilliePayment extends Plugin
{
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
        $bootstrapClasses = $this->getBootstrapClasses($context);
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->preInstall();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->install();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->postInstall();
        }
        parent::install($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    public function update(Plugin\Context\UpdateContext $context)
    {
        $bootstrapClasses = $this->getBootstrapClasses($context);
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->preUpdate();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->update();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->postUpdate();
        }
        parent::update($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    public function uninstall(Plugin\Context\UninstallContext $context)
    {
        $bootstrapClasses = $this->getBootstrapClasses($context);
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->preUninstall();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->uninstall($context->keepUserData());
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->postUninstall();
        }
        parent::uninstall($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    public function deactivate(Plugin\Context\DeactivateContext $context)
    {
        $bootstrapClasses = $this->getBootstrapClasses($context);
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->preDeactivate();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->deactivate();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->postDeactivate();
        }
        parent::deactivate($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    public function activate(Plugin\Context\ActivateContext $context)
    {
        $bootstrapClasses = $this->getBootstrapClasses($context);
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->preActivate();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->activate();
        }
        foreach ($bootstrapClasses as $bootstrap) {
            $bootstrap->postActivate();
        }
        parent::activate($context);
        $context->scheduleClearCache($context::CACHE_LIST_ALL);
    }

    /**
     * @return AbstractBootstrap[]
     */
    protected function getBootstrapClasses(Plugin\Context\InstallContext $context)
    {
        /** @var AbstractBootstrap[] $bootstrapper */
        $bootstrapper = [
            new PaymentMethodAttributes(),
            new OrderAttributes(),
            new UserAddressAttributes(),
            new UserAttributes(),
            new PaymentMethods(),
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
}
