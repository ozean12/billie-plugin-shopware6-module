<?php


namespace BilliePayment\Helper;


class BasketHelper
{

    /* Shipping costs currently are not needed
     * public static function getShippingAmount(array $basket)
    {
        return self::round(self::addTax([
            'gross' => $basket['sShippingcostsWithTax'],
            'net' => $basket['sShippingcostsNet'],
        ]));
    }*/

    public static function getProductAmount(array $product)
    {
        return self::round(self::addTax([
            'gross' => !empty($product['amountWithTax']) ? $product['amountWithTax'] : $product['amountNumeric'],
            'net' => $product['amountnetNumeric'],
        ]));
    }

    public static function getTotalAmount(array $basket)
    {
        return self::round(self::addTax([
            'gross' => empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'],
            'net' => $basket['AmountNetNumeric']
        ]));
    }

    private static function round(array $data)
    {
        $data['tax'] = round($data['tax'], 2);
        $data['gross'] = round($data['gross'], 2);
        $data['net'] = round($data['net'], 2);
        return $data;
    }

    private static function addTax(array $data)
    {
        $data['tax'] = $data['gross'] - $data['net'];
        return $data;
    }



}
