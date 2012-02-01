<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'php-api/src/OneGoAPI/init.php';

class ModelTotalOnego extends Model 
{
    const LOG_INFO = 0;
    const LOG_NOTICE = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;
    
    const AUTH_MESSAGE_AUTHENTICATED = 'onego.widget.user.authenticated';
    const AUTH_MESSAGE_ANONYMOUS = 'onego.widget.user.anonymous';
    
    protected $registrykey = 'onego_extension';
    
    protected static $current_eshop_cart = false;
    
    public function getInitHeaderCode()
    {
        $pluginsURI = $this->getConfig('pluginsURI');
        
        $html = '';
        
        // autologin attempts are blocked
        if ($this->autologinBlockedUntil()) {
            $autologinBlockedFor = ($this->autologinBlockedUntil() - time()) * 1000;
            $html .= "OneGoOpencart.blockAutologin({$autologinBlockedFor});\n";
        }
        
        // widget plugin
        if ($this->getConfig('widgetShow') == 'Y') {
            $topOffset = (int) $this->getConfig('widgetTopOffset');
            $isFrozen = ($this->getConfig('widgetFrozen') == 'Y') ? 'true' : 'false';
            $html .= <<<END
var OneGoWidget = OneGo.plugins.slideInWidget.init({
    topOffset: {$topOffset}, 
    isFixed: {$isFrozen},
    handleImage: '/catalog/view/theme/{$this->config->get('config_template')}/image/onego_handle.png'
});4

END;
        }
        
        // OneGo events listeners
        $isAjaxCall = !empty($this->request->request['route']) && 
                ($this->request->request['route'] == 'checkout/checkout');

        if ($this->isUserAuthenticated()) {
            // listen for logoff event
            $html .= $isAjaxCall ? 
                "OneGo.events.on('UserIsSignedOut', OneGoOpencart.processLogoffDynamic);\n" :
                "OneGo.events.on('UserIsSignedOut', OneGoOpencart.processLogoff);\n";
        } else {
            $html .= $isAjaxCall ? 
                "OneGo.events.on('UserIsSignedIn', OneGoOpencart.processLoginDynamic);\n" :
                "OneGo.events.on('UserIsSignedIn', OneGoOpencart.processAutoLogin);\n";
        }
        
        $initParams = array();
        
        // enable debugging
        if ($this->getConfig('debugModeOn')) {
            $initParams[] = "debug: true";
        }
        
        $initParamsStr = implode(",\n", $initParams);
        
        $html = <<<END
<link rel="stylesheet" type="text/css" href="catalog/view/theme/{$this->config->get('config_template')}/stylesheet/onego.css" />
<script type="text/javascript" src="http://plugins-local.dev.onego.com/scripts/webapp/v0.1/{$this->getConfig('clientId')}/{$this->getConfig('terminalId')}/main.js"></script>
<script type="text/javascript" src="catalog/view/javascript/onego.js"></script>
<script type="text/javascript">
OneGo.init({
{$initParamsStr}
});
{$html}</script>

END;
        return $html;
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
    
    public function getConfig($key)
    {
        return OneGoConfig::getInstance()->get($key);
    }

    /**
     * Modifies Opencart's totals list by adding OneGo benefits and receivables
     *
     * @param array $total_data
     * @param float $total
     * @param array $taxes 
     */
    public function getTotal(&$total_data, &$total, &$taxes) {
        try {
            $transaction = $this->refreshTransaction();
        } catch (OneGoAuthenticationRequiredException $e) {
            // ignore
        } catch (OneGoAPICallFailedException $e) {
            // ignore
        }
        
        $this->load->language('total/onego');
        
        if ($this->isTransactionStarted() || $this->hasAgreedToDiscloseEmail()) {
            $initial_total = $total;

            // items discounts
            // TODO

            // shipping discounts
            $free_shipping = false;
            $shipping_discount = $this->getShippingDiscount();
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

            // cart discount
            $discount = $this->getTotalDiscount();
            $discountAmount = !empty($discount) ? $discount->getAmount() : null;
            if (!empty($discountAmount) && ($discountAmount != $shipping_discount)) {
                // (TEMPORARY FIX)
                $discountPercents = $discount->getPercents();
                if (!empty($discountPercents)) {
                    $title = sprintf($this->language->get('onego_cart_discount_percents'), 
                            round($discountPercents, 2));
                } else {
                    $title = $this->language->get('onego_cart_discount');
                }
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $title,
                    'text' => $this->currency->format(-$discountAmount),
                    'value' => -$discountAmount,
                    'sort_order' => $this->config->get('onego_sort_order').'a'
                );
                $modified = true;
            }
            
            // funds spent
            $spent = $this->getPrepaidSpent();
            if (!empty($spent)) {
                $total_data[] = array(
                    'code' => 'onego',
                    'title' => $this->language->get('prepaid_spent'),
                    'text' => $this->currency->format(-$spent),
                    'value' => 0,
                    'sort_order' => $this->config->get('onego_sort_order').'p'
                );
            }
        
            // funds received
            $received = $this->getPrepaidReceivedAmount();
            if (!empty($received)) {
                $receivables = array(
                    'code' => 'onego',
                    'title' => $this->language->get('funds_receivable'),
                    'text' => $this->currency->format($received),
                    'value' => 0,
                    'sort_order' => 1000,
                );
                $total_data[] = $receivables;
            }
            
            // onego subtotal
            $onego_discount = 0;
            $cashAmount = $this->getCashAmount();
            if ($initial_total != $cashAmount) {
                $onego_discount = $this->getOriginalAmount() - $cashAmount;
                $total -= $onego_discount;
            }
            
            // decrease taxes if discount was applied
            if ($onego_discount) {
                // decrease taxes to be applied for products
                foreach ($this->cart->getProducts() as $product) {
                    if ($product['tax_class_id']) {
                        // discount part for this product
                        $discount = $onego_discount * ($product['total'] / $initial_total);
                        $tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);
                        foreach ($tax_rates as $tax_rate) {
                            if ($tax_rate['type'] == 'P') {
                                $taxes[$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
                            }
                        }
                    }
                }
                // decrease taxes to be applied for shipping
                if ($free_shipping && isset($this->session->data['shipping_method'])) {
                    if (!empty($this->session->data['shipping_method']['tax_class_id'])) {
                        // tax rates that will be applied (or were already) to shipping
                        $tax_rates = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);
                        // subtract them
                        foreach ($tax_rates as $tax_rate) {
                            $taxes[$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
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
        $benefits_applied = false;
        if ($this->isTransactionStarted()) {
            $api = $this->getApi();
            try {
                $this->confirmTransaction();
                $this->saveOrderDetails($order_info['order_id'], true);
            } catch (Exception $e) {
                $this->throwError($e->getMessage());
            }
        }        
    }
    
    // TO DO: deprecate
    public function saveOrderDetails($order_id, $benefits_applied = true)
    {
        $last_order = $this->getFromSession('last_order');
        if (!$last_order || ($last_order['order_id'] != $order_id)) {
            $this->load->model('account/order');		
            $order_info = $this->model_account_order->getOrder($order_id);
            $last_order = array(
                'order_id'          => $order_id,
                'confirmed_on'      => time(),
                'benefits_applied'  => $benefits_applied,
                'buyer_email'       => $order_info['email'],
            );
            $this->saveToSession('last_order', $last_order);
        }
    }
    
    public function isAnonymousRewardsApplied()
    {
        $last_order = $this->getFromSession('last_order');
        if ($this->hasAgreedToDiscloseEmail() && !$last_order['benefits_applied']) {
            // apply rewards - To DO
            
            // fake
            $last_order['benefits_applied'] = true;
            $this->saveToSession('last_order', $last_order);
            return true;
        }
        return false;
    }
    
    public function isOnegoBenefitsApplyable()
    {
        $last_order = $this->getFromSession('last_order');
        return !$this->hasAgreedToDiscloseEmail() && !$last_order['benefits_applied'];
    }
    
    /**
     *
     * @return boolean
     */
    public function isTransactionStarted()
    {
        $transaction = $this->getTransaction();
        return !empty($transaction) && $transaction->isStarted();
    }
    
    /**
     * Return current OneGo transaction object from session
     *
     * @param boolean $autoupdate Whether to update transaction if it is stale
     * @return OneGoAPI_Impl_Transaction
     */
    public function getTransaction()
    {
        return $this->getApi()->getTransaction();
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
        $transaction = $this->getTransaction();
        return !empty($transaction) ? $transaction->getId() : false;
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
        $cfg = new OneGoAPI_APIConfig(
                $this->getConfig('clientId'),
                $this->getConfig('clientSecret'), 
                $this->getConfig('terminalId'), 
                $this->getConfig('transactionTTL')
        );
        $cfg->apiUri = $this->getConfig('apiURI');
        $cfg->currencyCode = $this->getRegistryObj()->get('config')->get('config_currency');
        $api = OneGoAPI_Impl_SimpleAPI::init($cfg);
        $token = $this->getSavedOAuthToken();
        if ($token) {
            $api->setOAuthToken($token);
        }
        $transaction = $this->getSavedTransaction();
        if ($transaction) {
            $api->setTransaction($transaction);
        }

        $this->saveToRegistry('api', $api);
        
        return $api;
    }
    
    /**
     * Singleton factory for SimpleOAuth
     *
     * @return OneGoAPI_Impl_SimpleOAuth Instance of SimpleOAuth
     */    
    public function getAuth()
    {
        $auth = $this->getFromRegistry('auth');
        if (empty($auth)) {
            $auth = $this->initAuth();
        }
        return $auth;
    }
    
    /**
     * Initializer
     *
     * @return OneGoAPI_Impl_SimpleOAuth 
     */
    private function initAuth()
    {
        $cfg = new OneGoAPI_OAuthConfig(
                $this->getConfig('clientId'), 
                $this->getConfig('clientSecret'), 
                $this->getConfig('authorizationURI'), 
                $this->getConfig('oAuthURI')
        );
        $auth = OneGoAPI_Impl_SimpleOAuth::init($cfg);
        $this->saveToRegistry('auth', $auth);
        return $auth;
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
        $onego_data[$key] = serialize($val);
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
        $data = isset($onego_data[$key]) ? unserialize($onego_data[$key]) : null;
        return $data;
    }
    
    /**
     *
     * @return string Pseudo unique receipt number for current date/time 
     */
    public function generateReceiptNumber()
    {
        return date('ymdHis').'_'.substr(uniqid(), 0, 23);
    }
    
    public function generateExternalId()
    {
        return uniqid();
    }
    
    /**
     * Starts OneGo transaction with current Opencart's cart items
     *
     * @param string $token
     * @return boolean Operation status 
     */
    public function beginTransaction(OneGoAPI_Impl_OAuthToken $token, $receiptNumber = false)
    {
        $api = $this->getApi();
        $api->setOAuthToken($token);
        
        $cart = $this->collectCartEntries();
                
        if (!$receiptNumber) {
            $receiptNumber = $this->generateReceiptNumber();
        }
        try {
            $transaction = $api->beginTransaction($receiptNumber, $cart);
            $this->saveTransaction($transaction);
            
            // save cart hash to later detect when transaction cart needs to be updated
            $this->saveToSession('cart_hash', $this->getEshopCartHash());
            $this->saveToSession('onego_benefits_applied', false);
            
            $this->log('transaction started with '.count($cart).' cart entries', self::LOG_NOTICE);
            return true;
        } catch (Exception $e) {
            $this->log('Begin transaction failed: '.$e->getMessage(), self::LOG_ERROR);
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
        if ($transaction = $this->getTransaction()) {
            try {
                $this->log('Transaction confirm', self::LOG_NOTICE);
                $transaction->confirm();
                $this->deleteTransaction();
                $this->saveToSession('onego_benefits_applied', true);
                return true;
            } catch (Exception $e) {
                $this->log('Transaction confirm failed: '.$e->getMessage(), self::LOG_ERROR);
                $this->deleteTransaction();
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
        if ($transaction = $this->getTransaction()) {
            try {
                $transaction->cancel();
                $this->deleteTransaction();
                $this->saveToSession('onego_benefits_applied', false);
                $this->log('Transaction canceled', self::LOG_NOTICE);
                return true;
            } catch (Exception $e) {
                $this->log('Transaction cancel failed: '.$e->getMessage(), self::LOG_ERROR);
                $this->deleteTransaction();
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
        if ($this->isTransactionStarted()) {
            $api = $this->getApi();
            $cart = $api->newCart();
            try {
                $transaction = $this->getTransaction()->updateCart($this->collectCartEntries());
                $this->log('Transaction cart updated', self::LOG_NOTICE);
                $this->saveTransaction($transaction);
                $this->saveToSession('cart_hash', $this->getEshopCartHash());
                return true;
            } catch (Exception $e) {
                $this->log('Transaction cart update failed: '.$e->getMessage(), self::LOG_ERROR);
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
                    self::$current_eshop_cart[$product['product_id']]['_item_code'] = 
                        !empty($product['sku']) ? 
                            $product['sku'] : 
                            $this->getConfig('cartItemCodePrefix').$product['product_id'];
                }
                
                // add shipping as an item
                $this->addShippingToCart(self::$current_eshop_cart);
            }
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
     * Collect opencart cart entries into OneGoAPI_Impl_Cart object
     *
     * @return OneGoAPI_Impl_Cart
     */
    public function collectCartEntries()
    {
        $cart = $this->getApi()->newCart();
        foreach ($this->getEshopCart() as $product) {
            $cart->setEntry($product['key'], $product['_item_code'], $product['price'], 
                    $product['quantity'], $product['total'], $product['name']);
        }
        return $cart;
    }
    
    /**
     *
     * @return array Shipping data for including as a cart item
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
                    'key'           => $this->getConfig('shippingCode'),
                    '_item_code'    => $this->getConfig('shippingCode'),
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
     * @return array Prepaid available to buyer
     */
    public function getFundsAvailable()
    {
        $funds = array();
        $transaction = $this->getTransaction();
        if (!empty($transaction)) {
            $available = $transaction->getPrepaidAvailable();
            if (!is_null($funds)) {
                $currency = $transaction->getCurrencyCode();
                $spent = $transaction->getPrepaidSpent();
                if ($spent) {
                    $available += $spent;
                }
                $funds = array(
                    'title'     => sprintf($this->language->get('funds_prepaid'), 
                            $this->currency->format($available)),
                    'amount'    => $available,
                    'is_used'   => !empty($spent)
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
    public function getFundsAmountAvailable()
    {
        $funds = $this->getFundsAvailable();
        return isset($funds['amount']) ? $funds['amount'] : false;
    }
    
    // TO DO
    public function processGiftCard($cardno)
    {
        if ($cardno != '1111') {
            $this->getSession()->data['error'] = 'Invalid OneGo gift card number';
        } else {
            $this->getSession()->data['success'] = 'OneGo gift card redeemed';
        }
        header('Location: '.self::selfUrl());
        exit();
    }
    
    /**
     * Get HTML/JS code required by this model
     */
    public static function getHeaderHtml()
    {
        $onego = self::getInstance();
        return $onego->getInitHeaderCode().$onego->getDebugLogCode();
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
        if ($this->getConfig('debugModeOn')) {
            $log = $this->getLog();
            $log[] = array(
                'time'      => microtime(),
                'pid'       => getmypid(),
                'backtrace' => implode(' / ', self::debugBacktrace()),
                'message'   => $str,
                'level'     => $level,
            );
            $log = array_slice($log, -$max_length); // keep log small
            $this->saveToSession('log', $log);
        }
    }
    
    public function writeLog($str)
    {
        // write critical errors to log file
        $fh = fopen(DIR_LOGS.'onego_error.log', 'a');
        if ($fh) {
            $ln = date('Y-m-d H:i:s').' '.$str.' => '.implode(' / ', self::debugBacktrace());
            fwrite($fh, $ln."\n");
            fclose($fh);
        }
    }
    
    public function logCritical($errorStr, $exception = null)
    {
        if (!empty($exception) && is_a($exception, 'Exception')) {
            $errorStr = $errorStr.' :: '.get_class($exception).' :: '.$exception->getMessage();
        }
        $this->log($errorStr, self::LOG_ERROR);
        $this->writeLog($errorStr);
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
    public function getDebugLogCode()
    {
        $log = $this->getLog(true);
        $html = '';
        if ($this->getConfig('debugModeOn')) {
            $html .= '<script type="text/javascript">';
            $html .= 'if (typeof console != \'undefined\') { '."\r\n";
            if (!empty($log)) {

                foreach ($log as $row) {
                    $msg = 'OneGo: '.$row['message'];
                    $msg = preg_replace('/[\r\n]+/', ' ', $msg);
                    $msg = preg_replace('/\'/', '\\\'', $msg);
                    list($usec, $sec) = explode(" ", $row['time']);
                    if (!empty($sec)) {
                        $msg .= ' ['.date('H:i:s', $sec).']';
                    }
                    if ($row['level'] == self::LOG_ERROR) {
                        $msg .= ' :: '.$row['pid'].' / '.$row['backtrace'];
                    }
                    switch ($row['level']) {
                        case self::LOG_INFO:
                            $html .= 'console.log(\''.$msg.'\');';
                            break;
                        case self::LOG_NOTICE:
                            $html .= 'console.info(\''.$msg.'\');';
                            break;
                        case self::LOG_WARNING:
                            $html .= 'console.warn(\''.$msg.'\');';
                            break;
                        default:
                            $html .= 'console.error(\''.$msg.'\');';
                    }
                    $html .= "\r\n";
                }
            }
            if ($transaction = $this->getTransaction()) {
                $html .= 'var transaction = {\'transaction\' : $.parseJSON('.json_encode(json_encode($transaction->getTransactionDto())).')};'."\r\n";
                $html .= 'console.dir(transaction);'."\r\n";
                $html .= 'var transactionTtl = {\'expires\' : $.parseJSON('.json_encode(json_encode(date('Y-m-d H:i:s', time() + $transaction->getTtl()))).')};'."\r\n";
                $html .= 'console.dir(transactionTtl);'."\r\n";
            }        
            if ($token = $this->getSavedOAuthToken()) {
                $html .= 'var scopes = {\'token\' : $.parseJSON('.json_encode(json_encode($token)).')};'."\r\n";
                $html .= 'console.dir(scopes);'."\r\n";
            }
            /*
            $html .= 'var onego_session = {\'onego_session\' : $.parseJSON('.json_encode(json_encode($this->session->data[$this->registrykey])).')};'."\r\n";
            $html .= 'console.dir(onego_session);'."\r\n";
            */
            $html .= '}</script>'."\r\n";
        }
        return $html;
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
    
    public static function selfUrl()
    {
        $pageURL = 'http';
        if (!empty($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on")) {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }
    
    // =================== NEW
    
    /**
     *
     * @return OneGoAPI_Impl_OAuthToken 
     */
    public function getSavedOAuthToken()
    {
        $token = $this->getFromSession('OAuthToken');
        return $token;
    }
    
    public function saveOAuthToken(OneGoAPI_Impl_OAuthToken $token)
    {
        $this->saveToSession('OAuthToken', $token);
    }
    
    public function deleteOAuthToken()
    {
        $this->saveToSession('OAuthToken', null);
        $this->log('OAuth token destroyed');
    }
    
    public function getSavedTransaction()
    {
        $transaction = $this->getFromSession('Transaction');
        return $transaction;
    }
    
    public function saveTransaction(OneGoAPI_Impl_Transaction $transaction)
    {
        $this->saveToSession('Transaction', $transaction);
    }
    
    public function deleteTransaction()
    {
        $this->saveToSession('Transaction', null);
        $this->log('Transaction destroyed');
    }
    
    public function isAutologinAttemptExpected()
    {
        if (($token = $this->getSavedOAuthToken()) && !$token->isExpired()) {
            return false;
        }
        return true;
    }
    
    public function getOAuthRedirectUri()
    {
        return $this->registry->get('url')->link('total/onego/authorizationResponse');
    }
    
    public function autologinBlockedUntil()
    {
        $blocked_until = $this->getFromSession('autologinBlocked');
        return $blocked_until > time() ? $blocked_until : false;
    }
    
    public function blockAutologin($period = 60) // seconds
    {
        $this->saveToSession('autologinBlocked', time() + $period);
        $this->log('Autologin blocked until '.date('Y-m-d H:i:s', time() + $period));
    }
    
    public function isUserAuthenticated()
    {
        $token = $this->getSavedOAuthToken();
        return !empty($token) && !$token->isExpired();
    }
    
    public function userHasScope($scope)
    {
        $token = $this->getSavedOAuthToken();
        return ($token && $token->hasScope($scope));
    }
    
    public function getShippingDiscount()
    {
        $cart = $this->getModifiedCart();
        $discount = null;
        if ($cart) {
            foreach ($cart->getEntries() as $cartEntry) {
                if ($this->isShippingItem($cartEntry) && $cartEntry->getDiscount()) {
                    $discount += $cartEntry->getDiscount()->getAmount();
                }
            }
        }
        return $discount;
    }
    
    public function isShippingItem(OneGoAPI_DTO_CartEntryDto $transactionCartEntry)
    {
        return in_array($transactionCartEntry->itemCode, array($this->getConfig('shippingCode')));
    }
    
    public function spendPrepaid()
    {
        $transaction = $this->getTransaction();
        if (empty($transaction) || !$transaction->isStarted()) {
            return false;
        }
        try {
            $transaction->spendPrepaid($this->getFundsAmountAvailable());
            $this->log('Spent prepaid: '.$this->getFundsAmountAvailable(), self::LOG_NOTICE);
            $this->saveTransaction($transaction);
            return true;
        } catch (OneGoAPI_Exception $e) {
            $this->log('Spend prepaid failed: '.$e->getMessage(), self::LOG_ERROR);
        }
        return false;
    }
    
    public function cancelSpendingPrepaid()
    {
        $transaction = $this->getTransaction();
        if (empty($transaction) || !$transaction->isStarted()) {
            return false;
        }
        try {
            $transaction->cancelSpendingPrepaid();
            $this->log('Spend prepaid canceled', self::LOG_NOTICE);
            $this->saveTransaction($transaction);
            return true;
        } catch (OneGoAPI_Exception $e) {
            $this->log('Cancel spending prepaid failed: '.$e->getMessage(), self::LOG_ERROR);
        }
        return false;
    }
    
    public function isCurrentScopeSufficient()
    {
        $token = $this->getSavedOAuthToken();
        return $token && 
                $token->hasScope(OneGoAPI_Impl_OneGoOAuth::SCOPE_RECEIVE_ONLY) &&
                $token->hasScope(OneGoAPI_Impl_OneGoOAuth::SCOPE_USE_BENEFITS);
    }
    
    /**
     * Check if new token is valid for current transaction, restart transaction
     * with new token if not.
     *
     * @param OneGoAPI_Impl_OAuthToken $newToken 
     * @return boolean If transaction was refreshed
     */
    public function verifyTransactionWithNewToken(OneGoAPI_Impl_OAuthToken $newToken)
    {
        if (!$this->isTransactionStarted()) {
            return false;
        }
        $api = $this->getApi();
        $transaction = $this->getTransaction();
        $api->setOAuthToken($newToken);
        try {
            $res = $transaction->get();
            $this->log('Transaction readable with new token', self::LOG_NOTICE);
        } catch (Exception $e) {
            $this->log('Transaction does not accept token: '.$e->getMessage(), self::LOG_NOTICE);
            
            // getting transaction has failed, restart
            $receiptNumber = $transaction->getReceiptNumber();
            
            $api->setOAuthToken($this->getSavedOAuthToken());
            try {
                // cancel current transaction
                $transaction->cancel();
                $this->log('Transaction canceled.', self::LOG_NOTICE);
            } catch (Exception $e) {
                $this->log('Transaction cancel failed: '.$e->getMessage(), self::LOG_ERROR);
            }
            
            $api->setOAuthToken($newToken);
            try {
                // start new
                $this->beginTransaction($newToken, $receiptNumber);
                return true;
            } catch (Exception $e) {
                $this->log('Transaction start failed: '.$e->getMessage(), self::LOG_ERROR);
            }
        }
    }
    
    public function refreshTransaction()
    {
        if ($this->isTransactionStarted()) {
            // unset anonymous awards
            $this->deleteAnonymousModifiedCart();
        } else if ($this->hasAgreedToDiscloseEmail()) {
            // update anonymous awards if needed
            $this->getAnonymousModifiedCart();
        }        
        
        // refresh token if expired
        $token = $this->getSavedOAuthToken();
        if ($token) {
            if ($token->isExpired() && !empty($token->refreshToken)) {
                try {
                    $auth = $this->getAuth();
                    $token = $auth->refreshAccessToken($token->refreshToken);
                    $this->saveOAuthToken($token);
                    
                    $tokenRefreshed = true;
                    $this->log('OAuth token refreshed', self::LOG_NOTICE);
                } catch (OneGoAPI_Exception $e) {
                    $this->log('OAuth token refresh failed: ['.get_class($e).'] '.$e->getMessage(), self::LOG_ERROR);
                    throw new OneGoAPICallFailedException('OAuth token refresh failed', null, $e);
                }
            }
        } else {
            throw new OneGoAuthenticationRequiredException();
        }
        
        // start transaction if not started and token available,
        // refresh transaction if expired
        $transaction = $this->getTransaction();
        if ($this->isTransactionStarted()) {
            // memorize transaction state to restore on restart
            $prepaidSpent = $transaction->getPrepaidSpent();
        }
        
        if ($transaction && $transaction->isExpired()) {
            $res = $this->cancelTransaction();
            $transactionCanceled = true;
        }
        
        $transaction = $this->getTransaction();
        
        $action = !empty($transactionCanceled) ? 'restart' : 'autostart';
        if (!$this->isTransactionStarted()) {
            try {
                $this->beginTransaction($token);
                $transactionAutostarted = true;
                
                // return transaction state to previous
                if (!empty($prepaidSpent)) {
                    $this->spendPrepaid();
                }
            } catch (OneGoAPI_Exception $e) {
                throw new OneGoAPICallFailedException("Transaction {$action} failed", null, $e);
            }
        }
        
        // update transaction cart if outdated
        if (empty($transactionAutostarted) && $this->isTransactionStale()) {
            $this->updateTransactionCart();
        }
        
        return $this->getTransaction();
    }
    
    public function getPrepaidReceivedAmount()
    {
        $cart = $this->getModifiedCart();
        if ($cart && ($prepaidReceived = $cart->getPrepaidReceived())) {
            return $prepaidReceived->getAmount()->visible;
        }
        return false;
    }
    
    public function getTotalDiscount()
    {
        $cart = $this->getModifiedCart();
        if ($cart && ($totalDiscount = $cart->getTotalDiscount())) {
            return $totalDiscount;
        }
        return false;
    }
    
    public function getPrepaidSpent()
    {
        $cart = $this->getModifiedCart();
        if ($cart && ($prepaidSpent = $cart->getPrepaidSpent())) {
            return $prepaidSpent;
        }
        return false;
    }
    
    public function getCashAmount()
    {
        $cart = $this->getModifiedCart();
        if ($cart && ($cashAmount = $cart->getCashAmount())) {
            return $cashAmount->visible;
        }
    }
    
    public function getOriginalAmount()
    {
        $cart = $this->getModifiedCart();
        if ($cart && ($originalAmount = $cart->getOriginalAmount())) {
            return $originalAmount->visible;
        }
    }
    
    protected function getModifiedCart()
    {
        if ($this->isTransactionStarted()) {
            return $this->getTransaction()->getModifiedCart();
        } else {
            return $this->getAnonymousModifiedCart();
        }
        return false;
    }
    
    /**
     *
     * @return OneGoAPI_DTO_ModifiedCartDto 
     */
    protected function getAnonymousModifiedCart()
    {
        if ((is_null($this->getFromSession('anonymousModifiedCart'))) ||
            ($this->getEshopCartHash() != $this->getFromSession('anonymousModifiedCartHash'))) 
        {
            $api = $this->getApi();
            try {
                $modifiedCart = $api->getAnonymousAwards($this->collectCartEntries());
                $this->log('Anonymous awards requested', self::LOG_NOTICE);
                $this->saveToSession('anonymousModifiedCart', $modifiedCart);
                $this->saveToSession('anonymousModifiedCartHash', $this->getEshopCartHash());
            } catch (OneGoAPI_Exception $e) {
                // ignore
                $this->log('Anonymous awards request failed: '.$e->getMessage(), self::LOG_ERROR);
                return false;
            }
        }
        $modifiedCart = $this->getFromSession('anonymousModifiedCart');
        return is_null($modifiedCart) ? false : $modifiedCart;
    }
    
    public function deleteAnonymousModifiedCart()
    {
        $this->saveToSession('anonymousModifiedCart', null);
    }
    
    public function agreeToDiscloseEmail($agreed)
    {
        $this->saveToSession('agreedToDiscloseEmail', $agreed);
    }
    
    public function hasAgreedToDiscloseEmail()
    {
        return $this->getFromSession('agreedToDiscloseEmail');
    }
    
    public function requestOAuthAccessToken($authorizationCode, $requestedScopes = false)
    {
        $auth = $this->getAuth();
        try {
            $token = $auth->requestAccessToken($authorizationCode, $this->getOAuthRedirectUri());
            $this->log('OAuth token issued', self::LOG_NOTICE);
            if (!empty($requestedScopes)) {
                // remember token scope(s)
                $token->setScopes($requestedScopes);
            }

            if ($this->isTransactionStarted()) {
                // check if current transaction works with the new token, restart if not
                $this->verifyTransactionWithNewToken($token);
            }
            $this->saveOAuthToken($token);
        } catch (OneGoAPI_OAuthException $e) {
            $this->log('Issuing OAuth token failed: '.$e->getMessage(), self::LOG_ERROR);
            throw $e;
        }
        return $token;        
    }
}

class OneGoConfig
{
    private static $instance;
    private $onegoConfig;
    private $config;
    
    private function __construct(Config $config)
    {
        require_once DIR_ONEGO.'config.inc.php';
        $this->onegoConfig = $oneGoConfig;
        $this->config = $config;
    }
    
    public static function getInstance()
    {
        global $registry;
        if (empty(self::$instance)) {
            $config = $registry->get('config');
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    public function get($key)
    {
        $val = $this->config->get('onego_'.$key);
        if (!is_null($val)) {
            return $val;
        }
        if (isset($this->onegoConfig[$key])) {
            return $this->onegoConfig[$key];
        }
        return null;
    }
}

class OneGoException extends Exception {}
class OneGoAuthenticationRequiredException extends OneGoException {}
class OneGoAPICallFailedException extends OneGoException {}