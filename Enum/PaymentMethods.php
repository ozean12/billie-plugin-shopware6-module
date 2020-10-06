<?php

namespace BilliePayment\Enum;

use Shopware\Models\Payment\Payment;

final class PaymentMethods extends Enum
{
    const PAYMENT_BILLIE_1 = 'billie_payment_after_delivery_1';
    const PAYMENT_BILLIE_2 = 'billie_payment_after_delivery_2';

    const PAYMENTS = [
        self::PAYMENT_BILLIE_1 => [
            'name' => self::PAYMENT_BILLIE_1,
            'description' => 'Billie Rechnungskauf',
            'action' => 'BilliePayment',
            'active' => 1,
            'position' => 0,
            'template' => 'billie_change_payment.tpl',
            'additionalDescription' => 'Bezahlen Sie bequem und sicher auf Rechnung - innerhalb von {$payment_mean.attributes.core->get(\'billie_duration\')} Tagen nach Erhalt der Ware.',
            'billie_config' => [
                'default_duration' => 14,
                'allowed_in_countries' => ['DE'],
            ],
        ],
        self::PAYMENT_BILLIE_2 => [
            'name' => self::PAYMENT_BILLIE_2,
            'description' => 'Billie Rechnungskauf plus',
            'action' => 'BilliePayment',
            'active' => 0,
            'position' => 0,
            'template' => 'billie_change_payment.tpl',
            'additionalDescription' => 'Bezahlen Sie bequem und sicher auf Rechnung - innerhalb von {$payment_mean.attributes.core->get(\'billie_duration\')} Tagen nach Erhalt der Ware.',
            'billie_config' => [
                'default_duration' => 30,
                'allowed_in_countries' => ['DE'],
            ],
        ],
    ];

    public static function getNames()
    {
        return array_keys(self::PAYMENTS);
    }

    /**
     * @param string|Payment $paymentMethod
     *
     * @return bool
     */
    public static function exists($paymentMethod)
    {
        $paymentMethod = $paymentMethod instanceof Payment ? $paymentMethod->getName() : $paymentMethod;

        return $paymentMethod ? array_key_exists($paymentMethod, self::PAYMENTS) : false;
    }

    public static function getMethod($paymentMethod)
    {
        $paymentMethod = $paymentMethod instanceof Payment ? $paymentMethod->getName() : $paymentMethod;

        return self::exists($paymentMethod) ? self::PAYMENTS[$paymentMethod] : false;
    }
}
