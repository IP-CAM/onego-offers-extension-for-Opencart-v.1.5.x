<?php

class ModelTotalOnego extends Model {
    
    const LOG_INFO = 0;
    const LOG_NOTICE = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;
    
    protected $registrykey = 'onego_extension';
    protected $api_key = '72A7101DEFEC11D473B7B0911EFC9265E15F';
    protected $api_pass = '9C7A5C0874424BD21870764C533AD6BA3077';
    protected $api_url = 'http://api.test.onego.com/pos/v1/';
    protected $terminal_id = '1';
    protected static $cart_hash = false;
    
    public function __construct($registry) {
        parent::__construct($registry);
    }
    
    public function __call($name, $arguments) {
        $this->log('OUTSIDE CALL', self::LOG_WARNING);
    }

    public function getTotal(&$total_data, &$total, &$taxes) {
        $transaction = $this->getTransaction();
        if ($transaction && !empty($transaction->modifiedCart) && ($onegocart = $transaction->modifiedCart)) 
        {
            $this->load->language('total/onego');
            
            // items discounts
            // TODO
            
            // cart discount
            if (!empty($onegocart->totalDiscount) && ($discount = $onegocart->totalDiscount) 
                    && !empty($discount->amount->precise)) 
            {
                $discount_visible = $discount->amount->visible;
                $discount_precise = $discount->amount->precise;
                if (!empty($discount->percent)) {
                    $title = sprintf($this->language->get('onego_cart_discount_percents'), round($discount->percent, 2));
                } else {
                    $title = $this->language->get('onego_cart_discount');
                }
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $title,
                    'text' => $this->currency->format(-$discount_visible),
                    'value' => -$discount_visible,
                    'sort_order' => $this->config->get('onego_sort_order').'b'
                );
                //$total -= $discount_precise;
                $modified = true;
            }
            
            // funds spent
            if (!empty($onegocart->monetaryPointsSpent)) {
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('monetary_points_spent'),
                    'text' => $this->currency->format($onegocart->monetaryPointsSpent),
                    'value' => 0,
                    'sort_order' => $this->config->get('onego_sort_order').'c'
                );
            }
            if (!empty($onegocart->prepaidSpent)) {
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('prepaid_spent'),
                    'text' => $this->currency->format($onegocart->prepaidSpent),
                    'value' => 0,
                    'sort_order' => $this->config->get('onego_sort_order').'d'
                );
            }
            
            
            // funds received
            $funds_fields = array('monetaryPointsReceived', 'couponPointsReceived', 'prepaidReceived');
            foreach ($funds_fields as $fundfield) {
                if (!empty($onegocart->{$fundfield}) && $onegocart->{$fundfield}->amount->visible) {
                    if (!isset($receivables)) {
                        $receivables = array(
                            'code' => 'onego',
                            'title' => $this->language->get('funds_receivable'),
                            'text' => '',
                            'value' => 0,
                            'sort_order' => $this->config->get('onego_sort_order').'f'
                        );
                        $receivables_text = array();
                    }
                    $fund = strtolower(preg_replace('/([a-z]+)([A-Z]+)/', '$1_$2', $fundfield));
                    $fund = preg_replace('/_received$/', '', $fund);
                    $receivables_text[] = sprintf($this->language->get('funds_receivable_text'), 'catalog/view/theme/default/image/onego_'.$fund.'.png', $this->language->get($fund), round($onegocart->{$fundfield}->amount->visible, 2));
                }
            }
            if (!empty($receivables)) {
                $receivables['text'] = implode('&nbsp;', $receivables_text);
                $total_data[] = $receivables;
            }
            
            // onego subtotal
            if ($onegocart->originalAmount->visible != $onegocart->cashAmount->visible) {
                $diff = $onegocart->originalAmount->visible - $onegocart->cashAmount->visible;
                $total -= $diff;
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('text_sub_total'),
                    'text' => $this->currency->format($onegocart->cashAmount->visible),
                    'value' => $onegocart->cashAmount->visible,
                    'sort_order' => $this->config->get('onego_sort_order').'z'
                );
            }
        }
    }
    
    public function confirm($order_info, $order_total)
    {
/*
Array
(
    [0] => Array
        (
            [order_id] => 14
            [invoice_no] => 0
            [invoice_prefix] => INV-2011-00
            [store_id] => 0
            [store_name] => Your Store
            [store_url] => http://opencart/
            [customer_id] => 1
            [firstname] => Saulius
            [lastname] => Okunevičius
            [telephone] => 865561600
            [fax] => 
            [email] => saulius@megarage.com
            [shipping_firstname] => Saulius
            [shipping_lastname] => Okunevičius
            [shipping_company] => 
            [shipping_address_1] => arch
            [shipping_address_2] => 
            [shipping_postcode] => 12345
            [shipping_city] => vno
            [shipping_zone_id] => 1920
            [shipping_zone] => Vilnius
            [shipping_zone_code] => VI
            [shipping_country_id] => 123
            [shipping_country] => Lithuania
            [shipping_iso_code_2] => LT
            [shipping_iso_code_3] => LTU
            [shipping_address_format] => 
            [shipping_method] => Flat Shipping Rate
            [payment_firstname] => Saulius
            [payment_lastname] => Okunevičius
            [payment_company] => 
            [payment_address_1] => arch
            [payment_address_2] => 
            [payment_postcode] => 12345
            [payment_city] => vno
            [payment_zone_id] => 1920
            [payment_zone] => Vilnius
            [payment_zone_code] => VI
            [payment_country_id] => 123
            [payment_country] => Lithuania
            [payment_iso_code_2] => LT
            [payment_iso_code_3] => LTU
            [payment_address_format] => 
            [payment_method] => Cash On Delivery
            [comment] => 
            [total] => 105.0000
            [order_status_id] => 0
            [order_status] => 
            [language_id] => 1
            [language_code] => en
            [language_filename] => english
            [language_directory] => english
            [currency_id] => 2
            [currency_code] => USD
            [currency_value] => 1.00000000
            [date_modified] => 2011-10-28 13:53:35
            [date_added] => 2011-10-28 13:53:35
            [ip] => 127.0.0.1
        )

    [1] => Array
        (
            [order_total_id] => 49
            [order_id] => 14
            [code] => onego
            [title] => OneGo rewards
            [text] => <img src="catalog/view/theme/default/image/onego_monetary_points.png" alt="OneGo monetary points" title="OneGo monetary points" /> 25&nbsp;<img src="catalog/view/theme/default/image/onego_coupon_points.png" alt="OneGo coupon points" title="OneGo coupon po
            [value] => 0.0000
            [sort_order] => 2
        )

)
*/
        if ($this->isTransactionStarted()) {
            $api = $this->getApi();
            try {
                $this->confirmTransaction();
            } catch (Exception $e) {
                $this->throwError($e->getMessage());
            }
        }
        $this->log('confirm() called, params: '.count(func_get_args()), self::LOG_NOTICE);
    }
    
    public function isTransactionStarted()
    {
        return ($this->getTransaction() !== false);
    }
    
    public function getTransaction($autoupdate = true)
    {
        // initialize OneGo API autoloader to unserialize transaction object from session
        $api = $this->getApi();
        
        $transaction = $this->getFromSession('transaction');
        
        if (empty($transaction)) {
            $this->log('checked for transaction, not found');
            return false;
        } else {
            if ($this->isTransactionStale() && $autoupdate) {
                $this->updateTransactionCart();
                $this->log('transaction stale, cart updated', self::LOG_NOTICE);
            }
            return unserialize(serialize($transaction));
        }
    }
    
    public function getTransactionCartHash()
    {
        return $this->getFromSession('cart_hash');
    }
    
    public function isTransactionStale()
    {
        return $this->getTransactionCartHash() != $this->getCartHash();
    }
    
    protected function getTransactionId()
    {
        $transaction = $this->getTransaction(false);
        return !empty($transaction) ? $transaction->id : false;
    }
    
    public function getHttpReferer()
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    }
    
    public function getApi()
    {
        $api = $this->getFromRegistry('api');
        if (empty($api)) {
            $api = $this->initApi();
        }
        return $api;
    }
    
    private function initApi()
    {
        require_once DIR_ROOT.'../php-api/src/OneGoAPI/init.php';

        $cfg = new OneGoAPI_Config($this->terminal_id, $this->api_key, $this->api_pass);
        $cfg->apiUrl = $this->api_url;
        $cfg->currencyCode = $this->getRegistryObj()->get('config')->get('config_currency');
        $http = new OneGoAPI_Impl_CurlHttpClient();
        $gw = new OneGoAPI_Impl_Gateway($cfg, $http);
        $api = new OneGoAPI_Impl_OneGoAPI($gw);
        $simpleapi = new OneGoAPI_Impl_SimpleAPI($api);
        $this->log('API initialized');
        
        $this->saveToRegistry('api', $simpleapi);
        
        return $simpleapi;
    }
    
    public function getRegistryObj()
    {
        return $this->registry;
    }
    
    public function getSession()
    {
        $session = $this->getRegistryObj()->get('session');
        if (is_null($session)) {
            $this->throwError('session object not found');
        }
        return $session;
    }
    
    public function saveToRegistry($key, $val)
    {
        $registry = $this->getRegistryObj();
        $onego_data = $registry->get($this->registrykey);
        if (empty($onego_data)) {
            $onego_data = array();
        }
        $onego_data[$key] = $val;
        $registry->set($this->registrykey, $onego_data);
    }
    
    public function getFromRegistry($key)
    {
        $registry = $this->getRegistryObj();
        $onego_data = $registry->get($this->registrykey);
        return isset($onego_data[$key]) ? $onego_data[$key] : null;
    }
    
    public function throwError($message)
    {
        $this->log('exeption: '.$message, self::LOG_ERROR);
        throw new Exception('OneGo extension error: '.$message);
    }
    
    public function saveToSession($key, $val)
    {
        $session = $this->getSession();
        $onego_data = isset($session->data[$this->registrykey]) ? $session->data[$this->registrykey] : array();
        if (empty($onego_data)) {
            $onego_data = array();
        }
        $onego_data[$key] = $val;
        $session->data[$this->registrykey] = $onego_data;
    }
    
    public function getFromSession($key)
    {
        $session = $this->getSession();
        $onego_data = isset($session->data[$this->registrykey]) ? $session->data[$this->registrykey] : array();
        return isset($onego_data[$key]) ? $onego_data[$key] : null;
    }
    
    // TEMPORARY
    public function fixAuthUrl($url)
    {
        if (!preg_match('#^http.?//:#i', $url)) {
            $url = 'http://auth.test.onego.com'.$url;
        }
        return $url;
    }
    
    public function generateReceiptNumber()
    {
        return date('ymdHis').'_'.substr(uniqid(), 0, 23);
    }
    
    public function beginTransaction($token)
    {
        $api = $this->getApi();
        
        $receiptNumber = $this->generateReceiptNumber();
        $cart_entries = $this->collectCartEntries();
        try {
            $this->log('transaction/begin: token='.$token.'; cart entries: '.count($cart_entries), self::LOG_NOTICE);
            $transaction = $api->beginTransaction($token, $receiptNumber, $cart_entries, uniqid());
            
            $this->saveToSession('transaction', $transaction);
            // save cart hash to later detect when transaction cart needs to be updated
            $this->saveToSession('cart_hash', $this->getCartHash());
            $transaction = $this->getTransaction();
            $this->log('transaction started: '.$transaction->id->id);
            return true;
        } catch (Exception $e) {
            $this->log('transaction/begin exception: '.$e->getMessage(), self::LOG_ERROR);
            throw $e;
        }
    }
    
    public function confirmTransaction()
    {
        $api = $this->getApi();
        if ($transaction_id = $this->getTransactionId()) {
            try {
                $this->log('transaction confirm', self::LOG_NOTICE);
                $api->confirmTransaction($transaction_id);
                $this->saveToSession('transaction', null);
                return true;
            } catch (Exception $e) {
                $this->log('transaction/end/confirm exception: '.$e->getMessage(), self::LOG_ERROR);
                $this->saveToSession('transaction', null);
            }
        }
        return false;
    }
    
    public function cancelTransaction()
    {
        $api = $this->getApi();
        if ($transaction_id = $this->getTransactionId()) {
            try {
                $this->log('transaction cancel', self::LOG_NOTICE);
                $api->cancelTransaction($transaction_id);
                $this->saveToSession('transaction', null);
                return true;
            } catch (Exception $e) {
                $this->log('transaction/end/cancel exception: '.$e->getMessage(), self::LOG_ERROR);
                $this->saveToSession('transaction', null);
            }
        }
        return false;
    }
    
    public function updateTransactionCart()
    {
        $api = $this->getApi();
        if ($transaction_id = $this->getTransactionId()) {
            try {
                $this->log('transaction cart update', self::LOG_NOTICE);
                $transaction = $api->updateCart($transaction_id, $this->collectCartEntries());
                $this->saveToSession('transaction', $transaction);
                $this->saveToSession('cart_hash', $this->getCartHash());
                return true;
            } catch (Exception $e) {
                $this->log('transaction cart update exception: '.$e->getMessage(), self::LOG_ERROR);
            }
        }
        return false;
    }
    
    protected function getCartProducts($reload = false)
    {
        if ($reload || (self::$cart_hash === false)) {
            $cart = $this->getRegistryObj()->get('cart');
            $products = $cart->getProducts();
            self::$cart_hash = $products;
        }
        return self::$cart_hash;
    }
    
    protected function getCartHash()
    {
        return md5(serialize($this->getCartProducts()));
    }
    
    public function collectCartEntries()
    {
        $onego_cart = new OneGoAPI_DTO_CartDto();
        foreach ($this->getCartProducts() as $product) {
            $onego_cart->setEntry($product['key'], $product['key'], $product['price'], $product['quantity'], $product['total'], $product['name'], 'any');
        }
        return $onego_cart->entries;
    }
    
    public function log($str, $level = self::LOG_INFO)
    {
        $log = $this->getLog();
        $log[] = array(
            'time'      => microtime(),
            'pid'       => getmypid().' / '.implode(' / ', self::debugBacktrace()),
            'message'   => $str,
            'level'     => $level,
        );
        $log = array_slice($log, -25); // keep log small
        $this->saveToSession('log', $log);
    }
    
    public function getLog($clear = false)
    {
        $log = $this->getFromSession('log');
        if (empty($log)) {
            $log = array();
        }
        if ($clear) {
            $this->saveToSession('log', array());
        }
        return $log;
    }
    
    public static function outputLogToFirebug()
    {
        global $registry;
        $onego = new self($registry);
        $log = $onego->getLog(true);
        if (!empty($log)) {
            echo '<script type="text/javascript">';
            echo 'if (console) { '."\r\n";
            foreach ($log as $row) {
                $msg = 'OneGo: '.$row['message'];
                $msg = preg_replace('/[\r\n]+/', ' ', $msg);
                $msg = preg_replace('/\'/', '\\\'', $msg);
                //$msg = htmlspecialchars($msg, ENT_QUOTES);
                //$msg = json_encode($msg);
                list($usec, $sec) = explode(" ", $row['time']);
                if (!empty($sec)) {
                    $msg .= ' ['.date('H:i:s').' / '.$row['pid'].']';
                }
                switch ($row['level']) {
                    case self::LOG_INFO:
                        echo 'console.log(\''.$msg.'\');';
                        break;
                    case self::LOG_NOTICE:
                        echo 'console.info(\''.$msg.'\');';
                        break;
                    case self::LOG_WARNING:
                        echo 'console.warn(\''.$msg.'\');';
                        break;
                    default:
                        echo 'console.error(\''.$msg.'\');';
                }
                echo "\r\n";
            }
            echo '}</script>';
        }
    }
    
    public static function debugBacktrace($limit = 5)
    {
        $trace = debug_backtrace();
        $simple = array();
        foreach ($trace as $key => $val) {
            /*
            if (isset($val['object'])) {
                $trace[$key]['object'] = get_class($val['object']);
            }
            if (!empty($val['args'])) {
                foreach ($val['args'] as $argk => $argv) {
                    if (is_object($argv)) {
                        $trace[$key]['args'][$argk] = '&lt;'.get_class($argv).'&gt;';
                    } else if (is_array($argv)) {
                        $trace[$key]['args'][$argk] = '(array)';
                    } else {
                        $trace[$key]['args'][$argk] = $argv;
                    }
                }
            }
            */            
            $row = $trace[$key];
            $id = '';
            if (isset($row['class'])) {
                $id .= $row['class'].$row['type'];
            }
            $id .= $row['function'].'()';
            if (isset($row['line'])){
                $id .= ' ['.$row['line'].']';
            }
            $simple[] = $id;
        }
        
        return array_slice($simple, 2, $limit);
    }
}

?>