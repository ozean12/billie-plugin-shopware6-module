<?php

namespace BilliePayment\Bootstrap\Attributes;

use BilliePayment\Enum\PaymentMethods as PaymentMethodsEnum;
use Shopware\Components\Model\ModelRepository;
use Shopware\Models\Payment\Payment;

class PaymentMethodAttributes extends AbstractAttributes
{
    /**
     * @var ModelRepository
     */
    private $paymentMethodRepo;

    public function setContainer($container)
    {
        parent::setContainer($container);
        $this->paymentMethodRepo = $container->get('models')->getRepository(\Shopware\Models\Payment\Payment::class); // @phpstan-ignore-line
    }

    public function install()
    {
        parent::install();

        foreach (PaymentMethodsEnum::PAYMENTS as $options) {
            $payment = $this->paymentMethodRepo->findOneBy(['name' => $options['name']]);
            if ($payment instanceof Payment) {
                $this->modelManager->getConnection()->executeQuery(
                    'REPLACE INTO ' . $this->tableName . '(paymentmeanID, billie_duration) VALUES(:id, :duration);',
                    [
                        'id' => $payment->getId(),
                        'duration' => $options['billie_config']['default_duration'],
                    ]
                );
            }
        }
    }

    protected function getEntityClass()
    {
        return Payment::class;
    }

    protected function createUpdateAttributes()
    {
        $this->crudService->update($this->tableName, 'billie_duration', 'integer', [
            'label' => 'Term of Payment',
            'helpText' => 'Number of days until the customer has to pay the invoice',
            'displayInBackend' => true,
            'custom' => false,
        ], null, false, 14);
    }

    protected function uninstallAttributes()
    {
        $this->deleteAttribute('billie_duration');
    }
}
