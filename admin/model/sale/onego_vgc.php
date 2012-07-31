<?php
class ModelSaleOnegoVgc extends Model
{
    public function addCardToQueue($row)
    {
        return OneGoVirtualGiftCards::addPendingCard($row[0], $row[1], strtolower($row[2]) == 'true');
    }

    public function isValidCsvFileRow($row)
    {
        return count($row) >= 3 &&
            strlen(trim($row[0])) &&
            preg_match('/^[\d\.]+$/', $row[1]) &&
            preg_match('/true|false/', $row[2]);
    }

    public function isCardNominalMatching($card_data, $nominal)
    {
        return (string) $card_data[1] == (string) $nominal;
    }

    public function getGridList()
    {
        $sql = "SELECT p.product_id, pd.name, p.status,
                    MAX(b.added_on) AS last_batch_added_on, b.nominal,
                    SUM(IF(c.status='".OneGoVirtualGiftCards::STATUS_AVAILABLE."', 1, 0)) AS cards_available,
                    SUM(IF(c.status='".OneGoVirtualGiftCards::STATUS_SOLD."', 1, 0)) AS cards_sold
                FROM (".DB_PREFIX."onego_vgc_batches b, ".DB_PREFIX."product p)
                LEFT JOIN ".DB_PREFIX."product_description pd ON p.product_id=pd.product_id AND pd.language_id='".(int) $this->config->get('config_language_id')."'
                LEFT JOIN ".DB_PREFIX."onego_vgc_cards c ON b.id=c.batch_id
                WHERE b.product_id=p.product_id
                GROUP BY p.product_id
                ORDER BY b.nominal";
        $res = $this->db->query($sql);
        return $res->rows;
    }

    public function addCardsToNewProduct($product_data)
    {
        $pending = OneGoVirtualGiftCards::getPendingCardsCount();
        list($nominal, $count) = each($pending);

        // create product
        $product_id = $this->createProduct($product_data, $count);
        if (!$product_id) {
            return false;
        }

        // create batch
        $batch_id = OneGoVirtualGiftCards::createBatch($nominal, $product_id);

        // update cards status
        OneGoVirtualGiftCards::activatePendingCards($batch_id, $nominal);

        return $product_id;
    }

    public function createProduct($data, $cards_count)
    {
        $sql = "INSERT INTO " . DB_PREFIX . "product
                SET model = '" . $this->db->escape($data['model']) . "', 
                    sku = '',
                    upc = '',
                    location = '',
                    quantity = '".(int) $cards_count."',
                    minimum = '1',
                    subtract = '1',
                    stock_status_id = '" .(int) $this->config->get('config_stock_status_id'). "',
                    date_available = CURDATE(),
                    manufacturer_id = '0',
                    shipping = '0',
                    price = '" . (float) $data['price'] . "',
                    points = '0',
                    weight = '0',
                    weight_class_id = '" . (int) $this->config->get('config_weight_class_id') . "',
                    length = '0',
                    width = '0',
                    height = '0',
                    length_class_id = '" . (int) $this->config->get('config_length_class_id'). "',
                    status = '0',
                    tax_class_id = '0',
                    sort_order = '0',
                    date_added = NOW()";
        $this->db->query($sql);
        $product_id = $this->db->getLastId();

        foreach ($data['name'] as $language_id => $name) {
            $sql = "INSERT INTO ".DB_PREFIX."product_description
                    SET product_id = '" . (int)$product_id . "',
                        language_id = '" . (int)$language_id . "',
                        name = '" . $this->db->escape($name) . "',
                        meta_keyword = '',
                        meta_description = '',
                        description = ''";
            $this->db->query($sql);
        }

        $this->db->query("INSERT INTO ".DB_PREFIX."product_to_store
                            SET product_id = '" . (int)$product_id . "',
                                store_id = '0'");

        if (!empty($data['category'])) {
            foreach ($data['category'] as $category_id) {
                $this->db->query("INSERT INTO ".DB_PREFIX."product_to_category
                                    SET product_id='".(int) $product_id."',
                                        category_id = '".(int) $category_id."'");
            }
        }

        $this->cache->delete('product');

        return $product_id;
    }

    public function setProductStatus($id, $enabled = true)
    {
        $id = (int) $id;
        $status = $enabled ? '1' : '0';
        $sql = "UPDATE ".DB_PREFIX."product SET status='{$status}' WHERE product_id={$id}";
        $res = $this->db->query($sql);
        $this->cache->delete('product');
        return $res;
    }

    public function deleteUnsoldCards($id)
    {
        $id = (int) $id;
        if (OneGoVirtualGiftCards::deleteUnsoldCards($id)) {
            $this->updateStock($id);
            return true;
        }
        return false;
    }

    public function updateStock($product_id)
    {
        $stock = OneGoVirtualGiftCards::getCardsStock($product_id);
        $sql = "UPDATE ".DB_PREFIX."product SET quantity='{$stock}' WHERE product_id={$id}";
        return $this->db->query($sql);
    }
}