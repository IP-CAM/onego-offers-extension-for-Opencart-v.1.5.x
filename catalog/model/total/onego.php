<?php

class ModelTotalOnego extends Model {
    
    const LOG_INFO = 0;
    const LOG_NOTICE = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;
    const FUNDS_MONETARY_POINTS = 'mp';
    const FUNDS_PREPAID = 'pp';
    const SHIPPING = 'shipping';
    
    protected $registrykey = 'onego_extension';
    protected $api_key = '72A7101DEFEC11D473B7B0911EFC9265E15F';
    protected $api_pass = '9C7A5C0874424BD21870764C533AD6BA3077';
    protected $api_url = 'http://api.test.onego.com/pos/v1/';
    protected $terminal_id = '1';
    protected static $current_eshop_cart = false;
    
    /**
     * Constructor method: initializes and processes any related actions submitted
     *
     * @param Registry $registry 
     */
    public function __construct($registry) {
        parent::__construct($registry);
        $this->processActions();
    }
    
    /**
     * Instance factory
     *
     * @global Registry $registry
     * @return self
     */
    public static function getInstance()
    {
        global $registry;
        return new self($registry);
    }

    /**
     * Modifies Opencart's totals list by adding OneGo benefits and receivables
     *
     * @param array $total_data
     * @param float $total
     * @param array $taxes 
     */
    public function getTotal(&$total_data, &$total, &$taxes) {        
        // autostart transaction if verified token is available
        if (!$this->isTransactionStarted() 
                && ($token = $this->getFromSession('verified_token'))) 
        {
            try {
                $this->beginTransaction($token);
            } catch (Exception $e) {
                // dissmiss failure
            }
        }
        
        $transaction = $this->getTransaction();
        if ($transaction && !empty($transaction->modifiedCart) 
                && ($onegocart = $transaction->modifiedCart)) 
        {
            $this->load->language('total/onego');
            
            $initial_total = $total;
            
            // items discounts
            // TODO
            
            // shipping discounts
            $free_shipping = false;
            if (!empty($onegocart->entries)) {
                $shipping_discount = 0;
                foreach ($onegocart->entries as $cartitem) {
                    if ($cartitem->itemCode == self::SHIPPING && !empty($cartitem->discount->amount->visible)) {
                        $shipping_discount += $cartitem->discount->amount->visible;
                    }
                }
                if ($shipping_discount > 0) {
                    $total -= $shipping_discount;
                    $total_data[] = array(
                        'code' => 'onego',
                        'title' => $this->language->get('text_shipping_discount'),
                        'text' => $this->currency->format(-$shipping_discount),
                        'value' => $shipping_discount,
                        'sort_order' => $this->config->get('onego_sort_order').'y'
                    );
                }
                if ($shipping_discount && isset($this->session->data['shipping_method'])) {
                    $opencart_shipping_cost = $this->session->data['shipping_method']['cost'];
                    if ($opencart_shipping_cost - $shipping_discount == 0) {
                        $free_shipping = true;
                    }
                }
            }
            
            // cart discount
            if (!empty($onegocart->totalDiscount) && ($discount = $onegocart->totalDiscount) 
                    && !empty($discount->amount->visible) 
                    && ($discount->amount->visible != $shipping_discount)) // TEMPORARY FIX
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
                    'sort_order' => $this->config->get('onego_sort_order').'a'
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
                    'sort_order' => $this->config->get('onego_sort_order').'m'
                );
            }
            if (!empty($onegocart->prepaidSpent)) {
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('prepaid_spent'),
                    'text' => $this->currency->format(-$onegocart->prepaidSpent),
                    'value' => 0,
                    'sort_order' => $this->config->get('onego_sort_order').'p'
                );
            }
            
            
            // funds received
            $funds_fields = array('monetaryPointsReceived'/*, 'couponPointsReceived', 'prepaidReceived'*/);
            foreach ($funds_fields as $fundfield) {
                if (!empty($onegocart->{$fundfield}) && $onegocart->{$fundfield}->amount->visible) {
                    if (!isset($receivables)) {
                        $receivables = array(
                            'code' => 'onego',
                            'title' => $this->language->get('funds_receivable'),
                            'text' => '',
                            'value' => 0,
                            'sort_order' => 1000,//$this->config->get('onego_sort_order').'x'
                        );
                        $receivables_text = $receivables_text_rich = array();
                    }
                    $fund = strtolower(preg_replace('/([a-z]+)([A-Z]+)/', '$1_$2', $fundfield));
                    $fund = preg_replace('/_received$/', '', $fund);
                    $receivables_text[] = sprintf(
                            $this->language->get('funds_receivable_text'),  
                            $this->language->get($fund), 
                            round($onegocart->{$fundfield}->amount->visible, 2)
                        );
                    $receivables_text_rich[] = 
                            sprintf(
                                $this->language->get('funds_receivable_text_rich'), 
                                'catalog/view/theme/default/image/onego_'.$fund.'.png', 
                                $this->language->get($fund.'_full'), 
                                round($onegocart->{$fundfield}->amount->visible, 2)
                            );
                }
            }
            if (!empty($receivables)) {
                $receivables_text_rich = implode('&nbsp;', $receivables_text_rich);
                $receivables_text = implode(' / ', $receivables_text);
                $receivables['text'] = $receivables_text;
                $total_data[] = $receivables;
                // save rich text version to replace on page
                $this->saveToRegistry('receivables', array($receivables_text => $receivables_text_rich));
            }
            
            // onego subtotal
            $onego_discount = 0;
            if ($initial_total != $onegocart->cashAmount->visible) {
                $onego_discount = $onegocart->originalAmount->visible - $onegocart->cashAmount->visible;
                $total -= $onego_discount;
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('text_sub_total'),
                    'text' => $this->currency->format($onegocart->cashAmount->visible),
                    'value' => $onegocart->cashAmount->visible,
                    'sort_order' => $this->config->get('onego_sort_order').'t'
                );
            }
            
            // decrease taxes if discount was applied
            if ($onego_discount) {
                // decrease taxes applied for products
                foreach ($this->cart->getProducts() as $product) {
                    if ($product['tax_class_id']) {
                        // discount part for this product
                        $discount = $onego_discount * ($product['total'] / $total);
                        $tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);
                        foreach ($tax_rates as $tax_rate) {
                            if ($tax_rate['type'] == 'P') {
                                //$taxes[$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
                            }
                        }
                    }
                }
                // decrease taxes applied for shipping
                if ($free_shipping && isset($this->session->data['shipping_method'])) {
                    if (!empty($this->session->data['shipping_method']['tax_class_id'])) {
                        // tax rates that will be applied (or were already) to shipping
                        $tax_rates = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);
                        // subtract them
                        foreach ($tax_rates as $tax_rate) {
                            if ($tax_rate['type'] == 'P') {
                                //$taxes[$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Method is called from Opencart code on order confirmation
     *
     * @param array $order_info
     * @param type $order_total 
     */
    public function confirm($order_info, $order_total)
    {
        if ($this->isTransactionStarted()) {
            $api = $this->getApi();
            try {
                $this->confirmTransaction();
                $this->saveToSession('onego_benefits_applied', true);
                $this->log('benefits applied');
            } catch (Exception $e) {
                $this->throwError($e->getMessage());
            }
            $this->log('confirm() called, params: '.count(func_get_args()), self::LOG_NOTICE);
        }
    }
    
    /**
     *
     * @return boolean
     */
    public function isTransactionStarted()
    {
        return ($this->getTransaction(false) !== false);
    }
    
    /**
     * Return current OneGo transaction object from session; autoupdate if required
     *
     * @param boolean $autoupdate Whether to update transaction if it is stale
     * @return OneGoAPI_DTO_TransactionDto
     */
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
    
    /**
     *
     * @return string Current transaction's cart's hash code
     */
    public function getTransactionCartHash()
    {
        return $this->getFromSession('cart_hash');
    }
    
    /**
     * Compare current saved OneGo transaction cart's hash code to Opencart cart's hash
     * 
     * @return boolean
     */
    public function isTransactionStale()
    {
        return $this->getTransactionCartHash() != $this->getEshopCartHash();
    }
    
    /**
     *
     * @return Object OneGo transaction's id value
     */
    protected function getTransactionId()
    {
        $transaction = $this->getTransaction(false);
        return !empty($transaction) ? $transaction->id : false;
    }
    
    /**
     * Singleton factory for SimpleAPI
     *
     * @return OneGoAPI_Impl_SimpleAPI Instance of SimpleAPI
     */    
    public function getApi()
    {
        $api = $this->getFromRegistry('api');
        if (empty($api)) {
            $api = $this->initApi();
        }
        return $api;
    }
    
    /**
     * Initializer
     *
     * @return OneGoAPI_Impl_SimpleAPI 
     */
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
    
    /**
     *
     * @return Registry 
     */
    public function getRegistryObj()
    {
        return $this->registry;
    }
    
    /**
     *
     * @return Session 
     */
    public function getSession()
    {
        $session = $this->getRegistryObj()->get('session');
        if (is_null($session)) {
            $this->throwError('session object not found');
        }
        return $session;
    }
    
    /**
     * Saves data to registry simulating own namespace
     *
     * @param string $key
     * @param mixed $val 
     */
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
    
    /**
     * Getter from registry namespace
     *
     * @param string $key
     * @return mixed null if no such data is available 
     */
    public function getFromRegistry($key)
    {
        $registry = $this->getRegistryObj();
        $onego_data = $registry->get($this->registrykey);
        return isset($onego_data[$key]) ? $onego_data[$key] : null;
    }
    
    /**
     * Wrapper for exception throwing
     *
     * @param string $message 
     */
    public function throwError($message)
    {
        $this->log('exeption: '.$message, self::LOG_ERROR);
        throw new Exception('OneGo extension error: '.$message);
    }
    
    /**
     * Saves data to session under own namespace
     *
     * @param string $key
     * @param mixed $val 
     */
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
    
    /**
     * Namespaced getter from session
     *
     * @param string $key
     * @return mixed 
     */
    public function getFromSession($key)
    {
        $session = $this->getSession();
        $onego_data = isset($session->data[$this->registrykey]) ? $session->data[$this->registrykey] : array();
        return isset($onego_data[$key]) ? $onego_data[$key] : null;
    }
    
    /**
     * Temporary fix for auth URL returned by issueEshopToken
     *
     * @param string $url
     * @return string 
     */
    public function fixAuthUrl($url)
    {
        if (!preg_match('#^http.?//:#i', $url)) {
            $url = 'http://auth.test.onego.com'.$url;
        }
        return $url;
    }
    
    /**
     *
     * @return string Pseudo unique receipt number for current date/time 
     */
    public function generateReceiptNumber()
    {
        return date('ymdHis').'_'.substr(uniqid(), 0, 23);
    }
    
    /**
     * Starts OneGo transaction with current Opencart's cart items
     *
     * @param string $token
     * @return boolean Operation status 
     */
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
            $this->saveToSession('cart_hash', $this->getEshopCartHash());
            $this->saveToSession('onego_benefits_applied', false);
            $transaction = $this->getTransaction();
            $this->log('transaction started: '.$transaction->id->id);
            return true;
        } catch (Exception $e) {
            $this->log('transaction/begin exception: '.$e->getMessage(), self::LOG_ERROR);
            throw $e;
        }
    }
    
    /**
     * Confirm OneGo transaction, unset saved transaction
     *
     * @return boolean status 
     */
    public function confirmTransaction()
    {
        $api = $this->getApi();
        if ($transaction_id = $this->getTransactionId()) {
            try {
                $this->log('transaction confirm', self::LOG_NOTICE);
                $api->confirmTransaction($transaction_id);
                $this->saveToSession('transaction', null);
                $this->saveToSession('onego_benefits_applied', true);
                return true;
            } catch (Exception $e) {
                $this->log('transaction/end/confirm exception: '.$e->getMessage(), self::LOG_ERROR);
                $this->saveToSession('transaction', null);
            }
        }
        return false;
    }
    
    /**
     * Reset OneGo transaction, unset saved transaction
     *
     * @return boolean status
     */
    public function cancelTransaction()
    {
        $api = $this->getApi();
        if ($transaction_id = $this->getTransactionId()) {
            try {
                $this->log('transaction cancel', self::LOG_NOTICE);
                $api->cancelTransaction($transaction_id);
                $this->saveToSession('transaction', null);
                $this->saveToSession('onego_benefits_applied', false);
                return true;
            } catch (Exception $e) {
                $this->log('transaction/end/cancel exception: '.$e->getMessage(), self::LOG_ERROR);
                $this->saveToSession('transaction', null);
            }
        }
        return false;
    }
    
    /**
     * Update transaction cart entries using current Opencart's cart
     *
     * @return boolean status
     */
    public function updateTransactionCart()
    {
        $api = $this->getApi();
        if ($transaction_id = $this->getTransactionId()) {
            try {
                $this->log('transaction cart update', self::LOG_NOTICE);
                $transaction = $api->updateCart($transaction_id, $this->collectCartEntries());
                $this->saveToSession('transaction', $transaction);
                $this->saveToSession('cart_hash', $this->getEshopCartHash());
                return true;
            } catch (Exception $e) {
                $this->log('transaction cart update exception: '.$e->getMessage(), self::LOG_ERROR);
            }
        }
        return false;
    }
    
    /**
     * Calculates current Opencart shopping cart contents, adds other data required
     * for OneGo transaction handling; adds shipping info as cart item to apply
     * OneGo discounts for shipping
     *
     * @param boolean $reload Whether to force cart reload
     * @return array List of collected current cart items and shipping 
     */
    protected function getEshopCart($reload = false)
    {
        if ($reload || (self::$current_eshop_cart === false)) {
            // add Opencart cart items
            $cart = $this->getRegistryObj()->get('cart');
            $products = $cart->getProducts();
            self::$current_eshop_cart = $products;
            
            // load products details to determine item_code
            if (count(self::$current_eshop_cart)) {
                $ids = implode(',', array_keys(self::$current_eshop_cart));
                $products_query = $this->db->query("SELECT product_id, sku, upc FROM ".DB_PREFIX."product p WHERE product_id IN ({$ids})");
                foreach ($products_query->rows as $product) {
                    self::$current_eshop_cart[$product['product_id']]['_item_code'] = !empty($product['sku']) ? $product['sku'] : 'PID'.$product['product_id'];
                }
            }
            
            // add shipping as an item
            $this->addShippingToCart(self::$current_eshop_cart);
        }
        return self::$current_eshop_cart;
    }
    
    /**
     *
     * @return string hash for current Opencart cart
     */
    protected function getEshopCartHash()
    {
        return md5(serialize($this->getEshopCart()));
    }
    
    /**
     *
     * @return array List of OneGoAPI_DTO_CartEntryDto objects for current cart 
     */
    public function collectCartEntries()
    {
        $onego_cart = new OneGoAPI_DTO_CartDto();
        foreach ($this->getEshopCart() as $product) {
            $onego_cart->setEntry($product['key'], $product['_item_code'], $product['price'], 
                    $product['quantity'], $product['total'], $product['name']);
        }
        return $onego_cart->cartEntries;
    }
    
    /**
     *
     * @return array Data for including shipping as a cart item
     */
    protected function getShippingAsItem()
    {
        if ($this->config->get('shipping_status') && $this->cart->hasShipping() 
                && isset($this->session->data['shipping_method'])) 
        {
            $this->load->model('total/shipping');
            $shipping = $this->registry->get('model_total_shipping');
            $total_data = array();
            $taxes = array();
            $total = 0;
            $shipping->getTotal($total_data, $total, $taxes);
            if ($total > 0) {
                return array(
                    'key'           => self::SHIPPING,
                    '_item_code'    => self::SHIPPING,
                    'price'         => $total,
                    'quantity'      => 1,
                    'total'         => $total,
                    'name'          => 'Shipping',
                );
            }
        }
        return false;
    }
    
    /**
     *
     * @param array $transaction_cart Cart entries for OneGo transaction
     */
    protected function addShippingToCart(&$transaction_cart)
    {
        if ($shipping = $this->getShippingAsItem()) {
            $transaction_cart['shipping'] = $shipping;
        }
    }
    
    /**
     *
     * @return array List of funds owned by buyer, as set in transaction's buyerInfo property 
     */
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
        }       
        return $funds;
    }
    
    /**
     *
     * @param string $fundstype
     * @return float total amount of funds of specified type owned by buyer
     */
    public function getFundsAmountAvailable($fundstype)
    {
        $funds = $this->getFundsAvailable();
        return isset($funds[$fundstype]) ? $funds[$fundstype]['amount'] : false;
    }
    
    /**
     * Call corresponding API methods to update funds usage, for changed usage only
     *
     * @param array $usage list of $fundtype => $is_used values, where $is_used is 'y' or 'n'
     */
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
    
    /**
     * Call API method for using/cancel using of fund type; uses max amount of funds available to user
     *
     * @param string $fundtype
     * @param boolean $do_use
     * @return boolean status 
     */
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
                    /*
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
                    */
                }
                return true;
            } catch (Exception $e) {
                $this->log('funds usage call exception: '.$e->getMessage(), self::LOG_ERROR);
            }
        }
        return false;
    }
    
    /**
     * Process all POST data in HTTP request, related to this module
     */
    protected function processActions()
    {
        // OneGo funds usage
        if (!empty($this->request->post['use_onego_funds'])) {
            $this->processFundsUsage($this->request->post['use_onego_funds']);
            $this->request->post['use_onego_funds'] = null;
        }
    }
    
    /**
     * Show HTML/JS code required by this model
     */
    public static function showOutput()
    {
        $onego = self::getInstance();
        echo $onego->getLogForFirebugConsole();
        echo $onego->getHtmlDecoratorCode();
    }
    
    /**
     *
     * @return string HTML/JC code to modify page contents 
     */
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
    
    // ******* helper methods **************************************************
    
    /**
     * Save log message to session to later display in debugger, truncate log to
     * specified length 
     *
     * @param string $str Log message text
     * @param string $level self::LOG_INFO, self::LOG_NOTICE, self::LOG_WARNING, self::LOG_ERROR
     * @param integer $max_length Max amount of log messages to be saved
     */
    public function log($str, $level = self::LOG_INFO, $max_length = 25)
    {
        $log = $this->getLog();
        $log[] = array(
            'time'      => microtime(),
            'pid'       => getmypid().' / '.implode(' / ', self::debugBacktrace()),
            'message'   => $str,
            'level'     => $level,
        );
        $log = array_slice($log, -$max_length); // keep log small
        $this->saveToSession('log', $log);
    }
    
    /**
     * Returns list of messages saved in log
     *
     * @param boolean $clear Whether to remove returned log messages
     * @return array List of saved log entries
     */
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
    
    /**
     * Output HTML/JS code required to display log entries in Firebug's console
     */
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
            if ($transaction = $this->getTransaction(false)) {
                echo 'var transaction = {\'transaction\' : $.parseJSON('.json_encode(json_encode($transaction)).')};'."\r\n";
                echo 'console.dir(transaction);'."\r\n";
            }
            echo 'var onego_session = {\'onego_session\' : $.parseJSON('.json_encode(json_encode($this->session->data[$this->registrykey])).')};'."\r\n";
            echo 'console.dir(onego_session);'."\r\n";
            echo '}</script>';
        }
    }
    
    /**
     * Get backtrace info for debugging purposes, valid for the calling method
     *
     * @param integer $limit Max amount of backtrace steps to be returned
     * @return array List of backtrace data from latest calls to oldest
     */
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
    
    /**
     *
     * @param string $str
     * @param boolean $strong Whether to escape all characters and not just quotes
     * @return string
     */
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
    
    /**
     * Determine if current request is an AJAX request
     *
     * @return boolean
     */
    public static function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            ($_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
    }
    
    /**
     *
     * @return string HTTP referrer's URL
     */
    public function getHttpReferer()
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    }
}