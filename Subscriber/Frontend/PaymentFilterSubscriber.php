<?php

namespace BilliePayment\Subscriber\Frontend;

use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Services\SessionService;
use Enlight\Event\SubscriberInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Attribute;
use Shopware\Components\Model\ModelEntity;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Attribute\Payment;

class PaymentFilterSubscriber implements SubscriberInterface
{
    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    private $db;

    /**
     * @var SessionService
     */
    private $sessionService;

    public function __construct(
        ModelManager $modelManager,
        \Enlight_Components_Db_Adapter_Pdo_Mysql $db,
        SessionService $sessionService
    ) {
        $this->modelManager = $modelManager;
        $this->db = $db;
        $this->sessionService = $sessionService;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter' => 'onFilterPayments',
        ];
    }

    public function onFilterPayments(\Enlight_Event_EventArgs $args)
    {
        $paymentMethods = $args->getReturn();

        $debtorCompany = $this->sessionService->getDebtorCompany();

        if ($debtorCompany === null || empty($debtorCompany->getName())) {
            // remove all payment methods cause the customer is not a B2B customer.
            foreach (PaymentMethods::getNames() as $name) {
                foreach ($paymentMethods as $i => $paymentMethod) {
                    if ($name === $paymentMethod['name']) {
                        unset($paymentMethods[$i]);
                    }
                }
            }

            return $paymentMethods;
        }

        foreach ($paymentMethods as $i => $paymentMethod) {
            if ($billieMethod = PaymentMethods::getMethod($paymentMethod['name'])) {
                if (in_array($debtorCompany->getAddress()->getCountryCode(), $billieMethod['billie_config']['allowed_in_countries'], true) === false) {
                    unset($paymentMethods[$i]);
                } else {
                    // add backward compatibility
                    if (!isset($paymentMethod['attributes'])) {
                        $meta = $this->modelManager->getClassMetadata(Payment::class);
                        $attributeData = $this->db->fetchRow('SELECT * FROM ' . $meta->getTableName() . ' WHERE paymentmeanID = ?', [$paymentMethod['id']]);
                        /* @var ModelEntity $attributeModel */
                        $paymentMethods[$i]['attributes']['core'] = new Attribute(is_array($attributeData) ? $attributeData : []);
                    }
                }
            }
        }

        return $paymentMethods;
    }
}
