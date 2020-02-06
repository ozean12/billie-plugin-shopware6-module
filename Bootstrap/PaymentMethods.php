<?php


namespace BilliePayment\Bootstrap;


use BilliePayment\Enum\PaymentMethods as PaymentMethodsEnum;
use Shopware\Components\Plugin\PaymentInstaller;
use Shopware\Models\Attribute\Payment as PaymentAttribute;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Payment\Repository;

class PaymentMethods extends AbstractBootstrap
{

    /**
     * @var PaymentInstaller
     */
    private $paymentMethodInstaller;

    /**
     * @var Repository
     */
    private $paymentMethodRepo;

    public function setContainer($container)
    {
        parent::setContainer($container);
        $this->paymentMethodInstaller = $this->container->get('shopware.plugin_payment_installer');
        $this->paymentMethodRepo = $this->modelManager->getRepository(Payment::class);
    }

    public function preInstall()
    {
        $firstMethodData = PaymentMethodsEnum::getMethod(PaymentMethodsEnum::PAYMENT_BILLIE_1);

        // update payment method description - but only the unmodified data
        $qb = $this->modelManager->createQueryBuilder();
        $qb->update(Payment::class, 'payment')
            ->set('payment.description', ':description_new')
            ->andWhere($qb->expr()->like('payment.name', ':name'))
            ->andWhere($qb->expr()->eq('payment.description', ':description_old'))
            ->setParameter('name', 'billie_payment_after_delivery%')
            ->setParameter('description_old', 'Billie Payment After Delivery')
            ->setParameter('description_new', $firstMethodData['description'])
            ->getQuery()
            ->execute();

        // update payment method additional description - but only the unmodified data
        // do not format the html (also not the spacings!) - this is the old additional description, which was exactly like this saved into the database.
        $oldDescription =
            '<div id="payment_desc">'
            . ' <img src="https://www.billie.io/assets/images/favicons/favicon-16x16.png" width="16" height="16" style="display: inline-block;" />'
            . '  Billie - Payment After Delivery'
            . '</div>';
        $qb = $this->modelManager->createQueryBuilder();
        $qb->update(Payment::class, 'payment')
            ->set('payment.additionalDescription', ':additionalDescription_new')
            ->andWhere($qb->expr()->like('payment.name', ':name'))
            ->andWhere($qb->expr()->eq('payment.additionalDescription', ':additionalDescription_old'))
            ->setParameter('name', 'billie_payment_after_delivery%')
            ->setParameter('additionalDescription_old', $oldDescription)
            ->setParameter('additionalDescription_new', $firstMethodData['additionalDescription'])
            ->getQuery()
            ->execute();

        // update payment method name
        $qb = $this->modelManager->createQueryBuilder();
        $qb->update(Payment::class, 'payment')
            ->set('payment.name', ':name_new')
            ->andWhere($qb->expr()->eq('payment.name', ':name_old'))
            ->setParameter('name_old', 'billie_payment_after_delivery')
            ->setParameter('name_new', $firstMethodData['name'])
            ->getQuery()
            ->execute();

    }

    public function install()
    {
        $attributeMeta = $this->modelManager->getClassMetadata(PaymentAttribute::class);
        foreach (PaymentMethodsEnum::PAYMENTS as $options) {
            $payment = $this->paymentMethodRepo->findOneBy(['name' => $options['name']]);
            if ($payment !== null) {
                unset(
                    $options['active'],
                    $options['position'],
                    $options['description'],
                    $options['additionalDescription']
                );
            }
            $payment = $this->paymentMethodInstaller->createOrUpdate($this->installContext->getPlugin()->getName(), $options);

            $params = [
                'id' => $payment->getId(),
                'duration' => $options['billie_config']['default_duration']
            ];
            $this->modelManager->getConnection()->executeQuery(
                "REPLACE INTO " . $attributeMeta->getTableName() . " 
                    (paymentmeanID, billie_duration) 
                    VALUES(:id, :duration);",
                $params);
        }
    }

    public function update()
    {
        $this->install();
    }

    public function preUpdate()
    {
        $this->preInstall();
    }

    public function uninstall($keepUserData = false)
    {
        // we just disable the payment methods, cause maybe they are still associated to orders.
        $this->setActiveFlag(false);
    }

    public function activate()
    {
        $this->setActiveFlag(true);
    }

    public function deactivate()
    {
        $this->setActiveFlag(false);
    }

    /**
     * @param $flag bool
     * @param array $methods
     */
    private function setActiveFlag($flag)
    {
        /** @var Payment[] $methods */
        $methods = $this->installContext->getPlugin()->getPayments()->toArray();
        foreach ($methods as $payment) {
            $payment->setActive($flag);
        }
        $this->modelManager->flush($methods);
    }
}
