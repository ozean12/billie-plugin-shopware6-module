<?php

namespace BilliePayment;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Doctrine\ORM\Tools\SchemaTool;
use BilliePayment\Models\Api;

/**
 * Main Plugin Class with plugin options.
 * Handles (un-)installation and (de-)activation.
 */
class BilliePayment extends Plugin
{
    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $options = [
            'name'        => 'billie_payment_after_delivery',
            'description' => 'Billie Payment After Delivery',
            'action'      => 'BilliePayment',
            'active'      => 1,
            'position'    => 0,
            'additionalDescription' =>
                '<img src="http://your-image-url"/>'
                . '<div id="payment_desc">'
                . '  Example billie payment method.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        /** @var ModelManager $entityManager */
        $entityManager = $this->container->get('models');
        $tool          = new SchemaTool($entityManager);
 
        $classMetaData = [
            $entityManager->getClassMetadata(Api::class)
        ];
 
        $tool->createSchema($classMetaData);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        // Set to inactive on uninstall to not mess with previous orders!
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);

        /** @var ModelManager $entityManager */
        $entityManager = $this->container->get('models');
        $tool          = new SchemaTool($entityManager);
 
        $classMetaData = [
            $entityManager->getClassMetadata(Api::class)
        ];
 
        $tool->dropSchema($classMetaData);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
    }

    /**
     * @param \Shopware\Models\Payment\Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $models = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $models->flush();
    }
}
