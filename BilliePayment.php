<?php

namespace BilliePayment;

use BilliePayment\Bootstrap\AbstractBootstrap;
use BilliePayment\Bootstrap\Attributes\OrderAttributes;
use BilliePayment\Bootstrap\Attributes\PaymentMethodAttributes;
use BilliePayment\Bootstrap\Attributes\UserAddressAttributes;
use BilliePayment\Bootstrap\Attributes\UserAttributes;
use BilliePayment\Bootstrap\PaymentMethods;
use BilliePayment\Compiler\FileLoggerPass;
use Shopware\Components\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BilliePayment extends Plugin
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new FileLoggerPass($this->getContainerPrefix()));
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
        // install payment-method before the payment-method-attribute, cause the attributes cache will be cleared,
        // but the model-class has been already loaded. So this will ends in an error, when the columns in the database
        // does not exist anymore e.g. after an uninstall
        /** @var AbstractBootstrap[] $bootstrapper */
        $bootstrapper = [
            new PaymentMethods(),
            new PaymentMethodAttributes(),
            new OrderAttributes(),
            new UserAddressAttributes(),
            new UserAttributes(),
        ];

        // initialize all bootstraps
        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->setContext($context);
            $bootstrap->setContainer($this->container);
            $bootstrap->setPluginDir($this->getPath());
        }

        return $bootstrapper;
    }
}

if (!class_exists(\Billie\Sdk\Model\Order::class)) {
    require_once __DIR__ . '/vendor/autoload.php';
}
