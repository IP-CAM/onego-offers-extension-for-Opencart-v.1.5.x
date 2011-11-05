<?php

class ModelTotalOnego extends Model {
    
    const LOG_INFO = 0;
    const LOG_NOTICE = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;
    const FUNDS_MONETARY_POINTS = 'mp';
    const FUNDS_PREPAID = 'pp';
    
    protected $registrykey = 'onego_extension';
    protected $api_key = '72A7101DEFEC11D473B7B0911EFC9265E15F';
    protected $api_pass = '9C7A5C0874424BD21870764C533AD6BA3077';
    protected $api_url = 'http://api.test.onego.com/pos/v1/';
    protected $terminal_id = '1';
    protected static $transaction_cart = false;
    
    public function __construct($registry) {
        parent::__construct($registry);
        $this->processActions();
    }
    
    public static function getInstance()
    {
        global $registry;
        return new self($registry);
    }

    public function getTotal(&$total_data, &$total, &$taxes) {        
        // autostart transaction if verified token is available
        if (!$this->isTransactionStarted() && ($token = $this->getFromSession('verified_token'))) {
            try {
                $this->beginTransaction($token);
            } catch (Exception $e) {
                // dissmiss failure
            }
        }
        
        $transaction = $this->getTransaction();
        if ($transaction && !empty($transaction->modifiedCart) && ($onegocart = $transaction->modifiedCart)) 
        {
            $this->load->language('total/onego');
            
            // items discounts
            // TODO
            
            // cart discount
            if (!empty($onegocart->totalDiscount) && ($discount = $onegocart->totalDiscount) 
                    && !empty($discount->amount->visible)) 
            {
                $discount_visible = $discount->amount->visible;
                if (!empty($discount->percent)) {
                    $title = sprintf($this->language->get('onego_cart_discount_percents'), 
                            round($discount->percent, 2));
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
                $modified = true;
            }
            
            // funds spent
            if (!empty($onegocart->monetaryPointsSpent)) {
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('monetary_points_spent'),
                    'text' => $this->currency->format(-$onegocart->monetaryPointsSpent),
                    'value' => 0,
                    'sort_order' => $this->config->get('onego_sort_order').'c'
                );
            }
            if (!empty($onegocart->prepaidSpent)) {
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('prepaid_spent'),
                    'text' => $this->currency->format(-$onegocart->prepaidSpent),
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
                        $receivables_text = $receivables_text_rich = array();
                    }
                    $fund = strtolower(preg_replace('/([a-z]+)([A-Z]+)/', '$1_$2', $fundfield));
                    $fund = preg_replace('/_received$/', '', $fund);
                    $receivables_text[] = sprintf($this->language->get('funds_receivable_text'),  
                            $this->language->get($fund), 
                            round($onegocart->{$fundfield}->amount->visible, 2));
                    $receivables_text_rich[] = sprintf($this->language->get('funds_receivable_text_rich'), 
                            'catalog/view/theme/default/image/onego_'.$fund.'.png', 
                            $this->language->get($fund.'_full'), 
                            round($onegocart->{$fundfield}->amount->visible, 2));
                }
            }
            if (!empty($receivables)) {
                $receivables_text_rich = implode('&nbsp;', $receivables_text_rich);
                $receivables_text = implode(' / ', $receivables_text);
                $receivables['text'] = self::isAjaxRequest() ? 
                        $receivables_text_rich : $receivables_text;
                $total_data[] = $receivables;
                // save rich text version to replace on page
                $this->saveToRegistry('receivables', array($receivables_text => $receivables_text_rich));
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
        // modify order_totals texts to plain text
        
        
        if ($this->isTransactionStarted()) {
            $api = $this->getApi();
            try {
                $this->confirmTransaction();
            } catch (Exception $e) {
                $this->throwError($e->getMessage());
            }
            $this->log('confirm() called, params: '.count(func_get_args()), self::LOG_NOTICE);
        }
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
    
    protected function getTransactionCart($reload = false)
    {
        if ($reload || (self::$transaction_cart === false)) {
            // add Opencart cart items
            $cart = $this->getRegistryObj()->get('cart');
            $products = $cart->getProducts();
            self::$transaction_cart = $products;
            
            // load products details to determine item_code
            $ids = implode(',', array_keys(self::$transaction_cart));
            $products_query = $this->db->query("SELECT product_id, sku, upc FROM ".DB_PREFIX."product p WHERE product_id IN ({$ids})");
            foreach ($products_query->rows as $product) {
                self::$transaction_cart[$product['product_id']]['_item_code'] = !empty($product['sku']) ? $product['sku'] : 'PID'.$product['product_id'];
            }
            
            // add shipping as an item
            $this->addShippingToCart(self::$transaction_cart);
        }
        return self::$transaction_cart;
    }
    
    protected function getCartHash()
    {
        return md5(serialize($this->getTransactionCart()));
    }
    
    public function collectCartEntries()
    {
        $onego_cart = new OneGoAPI_DTO_CartDto();
        foreach ($this->getTransactionCart() as $product) {
            $onego_cart->setEntry($product['key'], $product['_item_code'], $product['price'], 
                    $product['quantity'], $product['total'], $product['name']);
        }
        return $onego_cart->cartEntries;
    }
    
    protected function addShippingToCart(&$transaction_cart)
    {
        if ($shipping = $this->registry->get('model_total_shipping')) {
            $total_data = array();
            $taxes = array();
            $total = 0;
            $shipping->getTotal($total_data, $total, $taxes);
            if ($total > 0) {
                $transaction_cart['shipping'] = array(
                    'key'           => 'shipping',
                    '_item_code'    => 'shipping',
                    'price'         => $total,
                    'quantity'      => 1,
                    'total'         => $total,
                    'name'          => 'Shipping',
                );
            }
        }
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
    
    public function getLogForFirebugConsole()
    {
        $log = $this->getLog(true);
        if (!empty($log)) {
            echo '<script type="text/javascript">';
            echo 'if (console) { '."\r\n";
            foreach ($log as $row) {
                $msg = 'OneGo: '.$row['message'];
                $msg = preg_replace('/[\r\n]+/', ' ', $msg);
                $msg = preg_replace('/\'/', '\\\'', $msg);
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
    
    public function getFundsAvailable()
    {
        $funds = array();
        $transaction = $this->getTransaction();
        if (!empty($transaction) && !empty($transaction->buyerInfo)) {
            $currency = isset($transaction->currencyCode) ? $transaction->currencyCode : '';
            if (isset($transaction->buyerInfo->monetaryPointsAvailable)) {
                $amount = $transaction->buyerInfo->monetaryPointsAvailable;
                if (!empty($transaction->modifiedCart->monetaryPointsSpent)) {
                    $amount += $transaction->modifiedCart->monetaryPointsSpent;
                }
                $funds[self::FUNDS_MONETARY_POINTS] = array(
                    'title'     => sprintf($this->language->get('funds_monetary_points'), 
                            $amount.' '.$currency),
                    'amount'    => $amount,
                    'is_used'   => !empty($transaction->modifiedCart->monetaryPointsSpent) ?
                            $transaction->modifiedCart->monetaryPointsSpent : false
                );
            }
            if (isset($transaction->buyerInfo->prepaidAvailable)) {
                $amount = $transaction->buyerInfo->prepaidAvailable;
                if (!empty($transaction->modifiedCart->prepaidSpent)) {
                    $amount += $transaction->modifiedCart->prepaidSpent;
                }
                $funds[self::FUNDS_PREPAID] = array(
                    'title'     => sprintf($this->language->get('funds_prepaid'), 
                            $amount.' '.$currency),
                    'amount'    => $amount,
                    'is_used'   => !empty($transaction->modifiedCart->prepaidSpent) ?
                            $transaction->modifiedCart->prepaidSpent : false
                );
            }
        }       
        return $funds;
    }
    
    public function getFundsAmountAvailable($fundstype)
    {
        $funds = $this->getFundsAvailable();
        return isset($funds[$fundstype]) ? $funds[$fundstype]['amount'] : false;
    }
    
    public function processFundsUsage($usage)
    {
        $funds_available = $this->getFundsAvailable();
        foreach ($usage as $fundtype => $use) {
            if (isset($funds_available[$fundtype]) 
                    && ((bool) $funds_available[$fundtype]['is_used'] != (bool) ($use == 'y'))) 
            {
                $this->useFunds($fundtype, $use == 'y');
            }
        }
    }
    
    private function useFunds($fundtype, $do_use = true)
    {
        $api = $this->getApi();
        if ($this->isTransactionStarted()) {
            $transaction = $this->getTransaction();
            try {
                switch ($fundtype) {
                    case self::FUNDS_MONETARY_POINTS:
                        if ($do_use) {
                            $this->log('transaction/monetary-points/spend', self::LOG_NOTICE);
                            $transaction = $api->spendMonetaryPoints($transaction->id, $this->getFundsAmountAvailable($fundtype));
                        } else {
                            $this->log('transaction/monetary-points/spending/cancel', self::LOG_NOTICE);
                            $transaction = $api->cancelSpendingMonetaryPoints($transaction->id);
                        }
                        $this->saveToSession('transaction', $transaction);
                        break;
                    case self::FUNDS_PREPAID:
                        if ($do_use) {
                            $this->log('transaction/prepaid/spend', self::LOG_NOTICE);
                            $transaction = $api->spendPrepaid($transaction->id, $this->getFundsAmountAvailable($fundtype));
                        } else {
                            $this->log('transaction/prepaid/spending/cancel', self::LOG_NOTICE);
                            $transaction = $api->cancelSpendingPrepaid($transaction->id);
                        }
                        $this->saveToSession('transaction', $transaction);
                        break;
                }
                return true;
            } catch (Exception $e) {
                $this->log('funds usage call exception: '.$e->getMessage(), self::LOG_ERROR);
            }
        }
        return false;
    }
    
    protected function processActions()
    {
        // OneGo funds usage
        if (!empty($this->request->post['use_onego_funds'])) {
            $this->processFundsUsage($this->request->post['use_onego_funds']);
            $this->request->post['use_onego_funds'] = null;
        }
    }
    
    public static function showOutput()
    {
        $onego = self::getInstance();
        echo $onego->getLogForFirebugConsole();
        echo $onego->getHtmlDecoratorCode();
    }
    
    public function getHtmlDecoratorCode()
    {
        $this->load->language('total/onego');
        $javascript = '';
        if ($receivables = $this->getFromRegistry('receivables')) {
            list($receivables_from, $receivables_to) = each($receivables);
            $receivables_from = self::escapeJs($receivables_from);
            $receivables_to = self::escapeJs($receivables_to);
            $javascript .= <<<END
$('div.cart-total td').each(function(){
    if ($(this).html() == '{$receivables_from}') {
        $(this).html('{$receivables_to}');
    }
});
END;
        }
        return <<<END
<script type="text/javascript">
{$javascript}
</script>
END;
    }
    
    public static function escapeJs($str, $strong = false) {
	$new_str = '';
	$str_len = strlen($str);
	for($i = 0; $i < $str_len; $i++) {
            $char = $str[$i];
            if ($strong || in_array($char, array('\'', '"', "\r", "\n"))) {
                $new_str .= '\\x' . dechex(ord(substr($str, $i, 1)));
            } else {
                $new_str .= $char;
            }
	}
	return $new_str;
    }
    
    public static function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            ($_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
    }
}