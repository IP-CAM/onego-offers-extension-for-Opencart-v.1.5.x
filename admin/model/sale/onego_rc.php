<?php
class ModelSaleOnegoRc extends Model
{
    public function addCodeToQueue($row)
    {
        return OneGoRedeemCodes::addPendingCode($row[0], $row[1], strtolower($row[2]) == 'true');
    }

    public function isValidCsvFileRow($row)
    {
        return count($row) >= 3 &&
            strlen(trim($row[0])) &&
            preg_match('/^[\d\.]+$/', $row[1]) &&
            preg_match('/true|false/', $row[2]);
    }

    public function isCodeNominalMatching($code_data, $nominal)
    {
        return (string) $code_data[1] == (string) $nominal;
    }

    public function getGridList($nominal = false)
    {
        $addwhere = $nominal ? ' AND b.nominal=\''.$this->db->escape($nominal).'\'' : '';
        $sql = "SELECT p.product_id, pd.name, p.status,
                    MAX(b.added_on) AS last_batch_added_on, b.nominal,
                    SUM(IF(c.status='".OneGoRedeemCodes::STATUS_AVAILABLE."', 1, 0)) AS codes_available,
                    SUM(IF(c.status='".OneGoRedeemCodes::STATUS_SOLD."' OR c.status='".OneGoRedeemCodes::STATUS_RESERVED."', 1, 0)) AS codes_sold
                FROM (".DB_PREFIX.OneGoRedeemCodes::DB_TABLE_BATCHES." b, ".DB_PREFIX."product p)
                LEFT JOIN ".DB_PREFIX."product_description pd ON p.product_id=pd.product_id AND pd.language_id='".(int) $this->config->get('config_language_id')."'
                LEFT JOIN ".DB_PREFIX.OneGoRedeemCodes::DB_TABLE_CODES." c ON b.id=c.batch_id
                WHERE b.product_id=p.product_id {$addwhere}
                GROUP BY p.product_id
                ORDER BY pd.name";
        $res = $this->db->query($sql);
        return $res->rows;
    }

    public function addCodesToNewProduct($product_data)
    {
        $pending = OneGoRedeemCodes::getPendingCodesCount();
        list($nominal, $count) = each($pending);

        // create product
        $product_id = $this->createProduct($product_data, $count);
        if (!$product_id) {
            return false;
        }

        // create batch
        $batch_id = OneGoRedeemCodes::createBatch($nominal, $product_id);

        // update cards status
        OneGoRedeemCodes::activatePendingCodes($batch_id, $nominal);

        return $product_id;
    }

    public function addCodesToProduct($product_id)
    {
        $pending = OneGoRedeemCodes::getPendingCodesCount();
        list($nominal, $count) = each($pending);

        // create batch
        $batch_id = OneGoRedeemCodes::createBatch($nominal, $product_id);

        // update codes status
        OneGoRedeemCodes::activatePendingCodes($batch_id, $nominal);
        
        OneGoRedeemCodes::updateStock($product_id);

        return $product_id;
    }

    public function createProduct($data, $codes_count)
    {
        $sql = "INSERT INTO " . DB_PREFIX . "product
                SET model = '" . $this->db->escape($data['model']) . "', 
                    sku = '',
                    upc = '',
                    location = '',
                    quantity = '".(int) $codes_count."',
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

    public function deleteUnsoldCodes($id)
    {
        $id = (int) $id;
        if (OneGoRedeemCodes::deleteUnsoldCodes($id)) {
            OneGoRedeemCodes::updateStock($id);
            return true;
        }
        return false;
    }

    public function disableAllProducts()
    {
        $products = $this->getGridList();
        $were_disabled = false;
        if (!empty($products)) {
            foreach ($products as $product) {
                if ($product['status']) {
                    $this->setProductStatus($product['product_id'], false);
                    $were_disabled = true;
                }
            }
        }
        return $were_disabled;
    }
}