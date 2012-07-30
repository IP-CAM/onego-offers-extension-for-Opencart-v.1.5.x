<?php
class ModelSaleOnegoVgc extends Model
{
    public function addCardToQueue($row)
    {
        return OneGoVirtualGiftCards::addPendingCard($row[0], $row[1], strtolower($row[2]) == 'true');
    }

    public function isValidCsvFileRow($row)
    {
        return count($row >= 3) &&
            strlen(trim($row[0])) &&
            preg_match('/^[\d\.]+$/', $row[1]) &&
            preg_match('/true|false/', $row[2]);
    }

    public function isCardNominalMatching($card_data, $nominal)
    {
        return (string) $card_data[1] == (string) $nominal;
    }
}