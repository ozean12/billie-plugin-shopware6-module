<?php

namespace BilliePayment\Services;

use Billie\Model\Order as BillieOrder;
use Shopware\Models\Order\Order;

class BankService
{
    /**
     * @var string
     */
    private $pluginDir;

    public function __construct($pluginDir)
    {
        $this->pluginDir = $pluginDir;
    }

    public function getBankData(Order $order, BillieOrder $billieOrder)
    {
        $fileName = $this->pluginDir . '/Resources/bankdata/de.csv';
        $banks = $this->parseCsv($fileName);

        return isset($banks[strtoupper($billieOrder->bankAccount->bic)]) ? $banks[$billieOrder->bankAccount->bic] : null;
    }

    protected function parseCsv($fileName)
    {
        $data = [];
        if (($handle = fopen($fileName, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                $data[$row[0]] = [
                    'bic' => $row[0],
                    'name' => $row[1],
                ];
            }
            fclose($handle);
        }

        return $data;
    }
}
