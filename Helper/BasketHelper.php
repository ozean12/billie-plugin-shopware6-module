<?php

namespace BilliePayment\Helper;

use Billie\Sdk\Model\Amount;

class BasketHelper
{
    /**
     * @return Amount
     */
    public static function getProductAmount(array $product)
    {
        $fetchKeys = [
            'gross' => [
                'amountWithTax',
                'amountNumeric',
                'priceNumeric',
            ],
            'net' => [
                'amountnetNumeric',
                'netprice',
            ],
        ];

        $gross = $net = 0;
        foreach ($fetchKeys as $variable => $keys) {
            $value = null;
            foreach ($keys as $key) {
                if (isset($product[$key])) {
                    $value = $product[$key];
                    break;
                }
            }
            if ($value) {
                switch ($variable) {
                    case 'gross':
                        $gross = $value;
                        break;
                    case 'net':
                        $net = $value;
                        break;
                }
            }
        }

        return (new Amount())
            ->setGross($gross)
            ->setNet($net);
    }

    public static function getTotalAmount(array $basket)
    {
        $net = $basket['AmountNetNumeric'];
        $gross = empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];

        return (new Amount())
            ->setNet($net)
            ->setGross($gross);
    }
}
