<?php

namespace BilliePayment;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;

/**
 * Main Plugin Class with plugin options.
 * Handles (un-)installation and (de-)activation.
 * 
 * @SuppressWarnings(PHPMD.StaticAccess)
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
            'name'                  => 'billie_payment_after_delivery',
            'description'           => 'Billie Payment After Delivery',
            'action'                => 'BilliePayment',
            'active'                => 1,
            'position'              => 0,
            'template'              => 'billie_change_payment.tpl',
            'additionalDescription' =>
            '<div id="payment_desc">'
                . ' <img src="https://www.billie.io/assets/images/favicons/favicon-16x16.png" width="16" height="16" style="display: inline-block;" />'
                . '  Billie - Payment After Delivery'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $this->autoload();
        $this->createDatabase();
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        // Set to inactive on uninstall to not mess with previous orders!
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);

        if (!$context->keepUserData()) {
            $this->removeDatabase();
        }

        $context->scheduleClearCache(UninstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        $context->scheduleClearCache(DeactivateContext::CACHE_LIST_DEFAULT);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
        $context->scheduleClearCache(ActivateContext::CACHE_LIST_DEFAULT);
    }

    /**
     * @param \Shopware\Models\Payment\Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        /** @var \Shopware\Components\Model\ModelManager $models */
        $models = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $models->flush();
    }

    /**
     * Create the database tables/columns.
     *
     * @return void
     */
    private function createDatabase()
    {
        // Get all legal forms.
        $allLegalForms = \Billie\Util\LegalFormProvider::all();
        $legalData     = [];
        foreach ($allLegalForms as $legal) {
            $legalData[] = ['key' => $legal['code'], 'value' => $legal['label']];
        }

        /** @var \Shopware\Bundle\AttributeBundle\Service\CrudService $service */
        $service = $this->container->get('shopware_attribute.crud_service');
        $service->update('s_order_attributes', 'billie_referenceId', 'string');
        $service->update('s_core_paymentmeans_attributes', 'billie_duration', 'integer', [
            'label'            => 'Term of Payment',
            'helpText'         => 'Number of days until the customer has to pay the invoice',
            'displayInBackend' => true,
        ]);
        $service->update('s_order_attributes', 'billie_state', 'string');
        $service->update('s_order_attributes', 'billie_iban', 'string');
        $service->update('s_order_attributes', 'billie_bic', 'string');
        $service->update('s_user_attributes', 'billie_iban', 'string', [
            'label'            => 'IBAN',
            'displayInBackend' => true,
        ]);
        $service->update('s_user_attributes', 'billie_bic', 'string',[
            'label'            => 'BIC',
            'displayInBackend' => true,
            'custom' => false,
        ]);
        $service->update('s_user_addresses_attributes', 'billie_registrationNumber', 'string', [
            'label'            => 'Registration Number',
            'displayInBackend' => true,
        ]);
        $service->update('s_user_addresses_attributes', 'billie_legalform', 'combobox', [
            'label'            => 'Legalform',
            'displayInBackend' => true,
            'arrayStore'       => $legalData
        ]);

        $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();
        Shopware()->Models()->generateAttributeModels(['s_order_attributes', 's_user_attributes', 's_user_addresses_attributes', 's_core_paymentmeans_attributes']);
    }

    /**
     * Remove the database tables.
     *
     * @return void
     */
    private function removeDatabase()
    {
        /** @var \Shopware\Bundle\AttributeBundle\Service\CrudService $service */
        $service = $this->container->get('shopware_attribute.crud_service');
        $service->delete('s_order_attributes', 'billie_referenceId');
        $service->delete('s_order_attributes', 'billie_state');
        $service->delete('s_order_attributes', 'billie_iban');
        $service->delete('s_order_attributes', 'billie_bic');
        $service->delete('s_user_attributes', 'billie_iban');
        $service->delete('s_user_attributes', 'billie_bic');
        $service->delete('s_core_paymentmeans_attributes', 'billie_duration');
        $service->delete('s_user_addresses_attributes', 'billie_registrationNumber');
        $service->delete('s_user_addresses_attributes', 'billie_legalform');
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BillieOverview' => 'onGetBackendController',
            'Enlight_Controller_Action_PostDispatch_Backend_Base'                 => 'extendExtJS',
            'Enlight_Controller_Front_StartDispatch'                              => 'autoload',
        ];
    }

    /**
     * @return string
     */
    public function onGetBackendController()
    {
        return __DIR__ . '/Controllers/Backend/BillieOverview.php';
    }

    /**
     * Extend Attribute Form to make BIC/IBAN readonly
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function extendExtJS(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $view       = $controller->View();
        $view->addTemplateDir($this->getPath() . '/Resources/views/');
        $view->extendsTemplate('backend/billie_payment/Shopware.attribute.Form.js');
    }

    /**
     * Include composer autoloader
     */
    public function autoload()
    {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
    }
}
