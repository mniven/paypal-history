<?php

namespace Starsquare\PayPal\Parser;

use Keboola\Csv\CsvFile;

class Csv extends AbstractParser {
    protected $fields;
    protected $expectedConversion;

    protected $currency = 'GBP';

    protected function getDate(array $row) {
        $dateString = "{$row['Date']} {$row['Time']} {$row['Time Zone']}";
        $date = \DateTime::createFromFormat('d/m/Y H:i:s e', $dateString);
        $date->setTimezone(new \DateTimeZone('UTC'));

        return $date;
    }

    public function loadFile($file) {
        $file = new CsvFile($file);

        foreach ($file as $row) {
            if ($this->fields === null) {
                $this->fields = array_map('trim', $row);
            } else {
                $row = array_combine($this->fields, $row);
                $data = array(
                    'date'     => $this->getDate($row),
                    'name'     => $row['Name'],
                    'type'     => $row['Type'],
                    'currency' => $row['Currency'],
                    'rate'     => 1,
                    'amount'   => (int) bcmul($row['Amount'], '100'),
                    'id'       => $row['Receipt ID'],
                );

                if (isset($this->expectedConversion)) {
                    if ($row['Type'] != 'Currency Conversion') {
                        if ($row['Currency'] == $this->currency) {
                            $this->data[$this->expectedConversion]['rate'] = - $data['amount'] / $this->data[$this->expectedConversion]['amount'];
                        } else {
                            // refund in a foreign currency
                            $data['rate'] = - $this->data[$this->expectedConversion]['amount'] / $data['amount'];
                        }

                        $this->data[] = $data;
                        unset($this->expectedConversion);
                    }
                } else {
                    $key = count($this->data);

                    // not seen the foreign currency yet; must have been a refund
                    if ($row['Type'] == 'Currency Conversion') {
                        $this->expectedConversion = $key - 1;
                    } else {
                        $this->data[$key] = $data;

                        if ($row['Currency'] != $this->currency) {
                            $this->expectedConversion = $key;
                        }
                    }
                }
            }
        }
    }
}