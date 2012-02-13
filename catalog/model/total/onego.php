<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'php-api/src/OneGoAPI/init.php';

class ModelTotalOnego extends Model 
{   
    protected static $current_eshop_cart = false;
    
    /**
     * Instance factory
     *
     * @global Registry $registry
     * @return self
     */
    public static function getInstance()
    {
        return new self(OneGoUtils::getRegistry());
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
        $orderId = $order_info['order_id'];
        $lastOrder = OneGoCompletedOrderState::getCurrent();
        if ($lastOrder->get('orderId') != $orderId) {
            $this->saveCompletedOrder($orderId);
            if ($this->isTransactionStarted()) {
                $api = $this->getApi();
                $transactionId = $this->getTransactionId()->id;
                try {
                    $transaction = $this->confirmTransaction();
                    $lastOrder->set('benefitsApplied', true);
                    $lastOrder->set('prepaidReceived', $transaction->getPrepaidAmountReceived());
                } catch (Exception $e) {
                    $this->registerFailedTransaction($orderId, $e->getMessage(), $transactionId);
                    $this->throwError($e->getMessage());
                }
            } else {
                if ($this->hasAgreedToDiscloseEmail()) {
                    try {
                        $receivedFunds = $this->bindEmail($order_info['email'], $this->collectCartEntries());
                        $lastOrder->set('benefitsApplied', true);
                        $lastOrder->set('newBuyerRegistered', true);
                        $lastOrder->set('prepaidReceived', $receivedFunds);
                    } catch (OneGoException $e) {
                        $transactionId = 'UNKNOWN';
                        $this->registerFailedTransaction($orderId, $e->getMessage(), $transactionId);
                    }
                }
            }
        }
    }
    
    public function saveCompletedOrder($orderId)
    {
        $this->load->model('account/order');		
        $orderInfo = $this->model_account_order->getOrder($orderId);
        
        $completedOrder = OneGoCompletedOrderState::getCurrent();
        $completedOrder->reset();
        $completedOrder->set('orderId', $orderId);
        $completedOrder->set('completedOn', time());
        $completedOrder->set('buyerEmail', $orderInfo['email']);
        $completedOrder->set('cart', $this->getEshopCart());
    }
    
    public function getCompletedOrder()
    {
        return OneGoCompletedOrderState::getCurrent();
    }
    
    public function registerFailedTransaction($orderId, $errorMessage, $transactionId)
    {
        $this->load->model('account/order');		
        $orderInfo = $this->model_account_order->getOrder($orderId);
        
        $version = ONEGO_EXTENSION_VERSION;
        $text = <<<END
WARNING: order #{$orderId} has been processed using OneGo benefits, but transaction confirmation failed.
If buyer chose to spend his OneGo funds or use single use coupon the discount was applied to order but OneGo funds were not charged.
You may want to consider revising order status.
Please contact OneGo support for more information, including these details:

OneGo transaction ID: {$transactionId}
Failure reason: {$errorMessage}
Opencart extension version: {$version}
END;
echo $text;
        
        // add record to order history
        $sql = "INSERT INTO ".DB_PREFIX."order_history 
                SET order_id='".(int)$orderId."', 
                    order_status_id = '".(int)$orderInfo['order_status_id']."', 
                    notify = '0', 
                    comment = '{$this->db->escape($text)}', 
                    date_added = NOW()";
        $this->db->query($sql);
        
        // send email to e-shop admin
        $mail = new Mail(); 
        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->hostname = $this->config->get('config_smtp_host');
        $mail->username = $this->config->get('config_smtp_username');
        $mail->password = $this->config->get('config_smtp_password');
        $mail->port = $this->config->get('config_smtp_port');
        $mail->timeout = $this->config->get('config_smtp_timeout');
        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($orderInfo['store_name']);
        $mail->setSubject('Warning: OneGo transaction for order #'.$orderId.' failed, please revise order status');
        $mail->setText(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
        $mail->send();
    }
    
    public function isAnonymousRewardsApplied()
    {
        return OneGoCompletedOrderState::getCurrent()->get('newBuyerRegistered');
    }
    
    public function isAnonymousRewardsApplyable()
    {
        $lastOrder = $this->getCompletedOrder();
        return !$this->hasAgreedToDiscloseEmail() && !$lastOrder->get('benefitsApplied');
    }
    
    public function bindEmail($email, OneGoAPI_Impl_Cart $cart)
    {
        try {
            $email = trim($email);
            if (empty($email)) {
                throw new OneGoException('bindEmail requires valid email address');
            }
            
            $api = $this->getApi();
            if ($this->isTransactionStarted()) {
                throw new OneGoException('bindEmail() for started transactions NOT IMPLEMENTED');
            } else {
                $modifiedCart = $api->bindNewEmail($email, $cart);
                $prepaidReceived = isset($modifiedCart->prepaidReceived) ?
                        $modifiedCart->getPrepaidReceived()->getAmount()->visible : 0;
                OneGoUtils::log('bindNewEmail executed, prepaid received: '.$prepaidReceived);
                return $prepaidReceived;
            }
                    
        } catch (Exception $e) {
            OneGoUtils::logCritical('bindEmail failed', $e);
            throw $e;
        }
        return true;
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
        return OneGoUtils::getFromSession('cart_hash');
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
        $api = OneGoUtils::getFromRegistry('api');
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
        $cfg->currencyCode = OneGoUtils::getRegistry()->get('config')->get('config_currency');
        $api = OneGoAPI_Impl_SimpleAPI::init($cfg);
        $token = $this->getSavedOAuthToken();
        if ($token) {
            $api->setOAuthToken($token);
        }
        $transaction = $this->getSavedTransaction();
        if ($transaction) {
            $api->setTransaction($transaction);
        }

        OneGoUtils::saveToRegistry('api', $api);
        
        return $api;
    }
    
    /**
     * Singleton factory for SimpleOAuth
     *
     * @return OneGoAPI_Impl_SimpleOAuth Instance of SimpleOAuth
     */    
    public function getAuth()
    {
        $auth = OneGoUtils::getFromRegistry('auth');
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
        OneGoUtils::saveToRegistry('auth', $auth);
        return $auth;
    }
    
    /**
     * Wrapper for exception throwing
     *
     * @param string $message 
     */
    public function throwError($message)
    {
        OneGoUtils::log('exeption: '.$message, OneGoUtils::LOG_ERROR);
        throw new Exception('OneGo extension error: '.$message);
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
            OneGoUtils::saveToSession('cart_hash', $this->getEshopCartHash());
            OneGoTransactionState::getCurrent()->reset();
            
            OneGoUtils::log('transaction started with '.count($cart).' cart entries', OneGoUtils::LOG_NOTICE);
            return true;
        } catch (Exception $e) {
            OneGoUtils::log('Begin transaction failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
    }
    
    /**
     * Confirm OneGo transaction, unset saved transaction
     *
     * @return mixed OneGoAPI_Impl_Transaction on success, false on fail 
     */
    public function confirmTransaction()
    {
        $api = $this->getApi();
        if ($transaction = $this->getTransaction()) {
            try {
                OneGoUtils::log('Transaction confirm', OneGoUtils::LOG_NOTICE);
                $transaction->confirm();
                $this->deleteTransaction();
                return $transaction;
            } catch (Exception $e) {
                OneGoUtils::log('Transaction confirm failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
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
    public function cancelTransaction($silent = false)
    {
        $api = $this->getApi();
        if ($transaction = $this->getTransaction()) {
            try {
                $transaction->cancel();
                $this->deleteTransaction();
                OneGoUtils::log('Transaction canceled', OneGoUtils::LOG_NOTICE);
                return true;
            } catch (Exception $e) {
                if (!$silent) {
                    OneGoUtils::log('Transaction cancel failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
                }
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
                OneGoUtils::log('Transaction cart updated', OneGoUtils::LOG_NOTICE);
                $this->saveTransaction($transaction);
                OneGoUtils::saveToSession('cart_hash', $this->getEshopCartHash());
                return true;
            } catch (Exception $e) {
                OneGoUtils::log('Transaction cart update failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
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
            $cart = OneGoUtils::getRegistry()->get('cart');
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
    public function collectCartEntries($eshopCart = null)
    {
        if (is_null($eshopCart)) {
            $eshopCart = $this->getEshopCart();
        }
        $cart = $this->getApi()->newCart();
        foreach ($eshopCart as $product) {
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
    
    
    /**
     *
     * @return OneGoAPI_Impl_OAuthToken 
     */
    public function getSavedOAuthToken()
    {
        $token = OneGoUtils::getFromSession('OAuthToken');
        return $token;
    }
    
    public function saveOAuthToken(OneGoAPI_Impl_OAuthToken $token, $isAnonymous = false)
    {
        OneGoUtils::saveToSession('OAuthToken', $token);
        OneGoUtils::saveToSession('OAuthTokenAnonymous', $isAnonymous);
    }
    
    public function deleteOAuthToken()
    {
        OneGoUtils::saveToSession('OAuthToken', null);
        OneGoUtils::saveToSession('OAuthTokenAnonymous', null);
        OneGoUtils::log('OAuth token destroyed');
    }
    
    public function getSavedTransaction()
    {
        $transaction = OneGoUtils::getFromSession('Transaction');
        return $transaction;
    }
    
    public function saveTransaction(OneGoAPI_Impl_Transaction $transaction)
    {
        OneGoUtils::saveToSession('Transaction', $transaction);
    }
    
    public function deleteTransaction()
    {
        OneGoUtils::saveToSession('Transaction', null);
        OneGoTransactionState::getCurrent()->reset();
        OneGoUtils::log('Transaction destroyed');
    }
    
    public function getOAuthRedirectUri()
    {
        return $this->registry->get('url')->link('total/onego/authorizationResponse');
    }
    
    public function autologinBlockedUntil()
    {
        $blocked_until = OneGoUtils::getFromSession('autologinBlocked');
        return $blocked_until > time() ? $blocked_until : false;
    }
    
    public function blockAutologin($period = 60) // seconds
    {
        OneGoUtils::saveToSession('autologinBlocked', time() + $period);
        OneGoUtils::log('Autologin blocked until '.date('Y-m-d H:i:s', time() + $period));
    }
    
    public function isUserAuthenticated()
    {
        $token = $this->getSavedOAuthToken();
        return !empty($token) && !$token->isExpired() && !OneGoUtils::getFromSession('OAuthTokenAnonymous');
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
            $amount = $this->getFundsAmountAvailable();
            $transaction->spendPrepaid($amount);
            OneGoTransactionState::getCurrent()->set(OneGoTransactionState::PREPAID_SPENT, $amount);
            OneGoUtils::log('Spent prepaid: '.$amount, OneGoUtils::LOG_NOTICE);
            $this->saveTransaction($transaction);
            return true;
        } catch (OneGoAPI_Exception $e) {
            OneGoUtils::log('Spend prepaid failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
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
            OneGoTransactionState::getCurrent()->set(OneGoTransactionState::PREPAID_SPENT, false);
            OneGoUtils::log('Spend prepaid canceled', OneGoUtils::LOG_NOTICE);
            $this->saveTransaction($transaction);
            return true;
        } catch (OneGoAPI_Exception $e) {
            OneGoUtils::log('Cancel spending prepaid failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
        }
        return false;
    }
    
    public function hasSpentPrepaid()
    {
        return OneGoTransactionState::getCurrent()->get(OneGoTransactionState::PREPAID_SPENT);
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
            OneGoUtils::log('Transaction readable with new token', OneGoUtils::LOG_NOTICE);
        } catch (OneGoAPI_Exception $e) {
            OneGoUtils::log('Transaction does not accept token: '.$e->getMessage(), OneGoUtils::LOG_NOTICE);
            
            // getting transaction has failed, restart
            $receiptNumber = $transaction->getReceiptNumber();
            
            $api->setOAuthToken($this->getSavedOAuthToken());
            try {
                // cancel current transaction
                $transaction->cancel();
                OneGoUtils::log('Transaction canceled.', OneGoUtils::LOG_NOTICE);
            } catch (Exception $e) {
                OneGoUtils::log('Transaction cancel failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            }
            
            $api->setOAuthToken($newToken);
            try {
                // start new
                $this->beginTransaction($newToken, $receiptNumber);
                return true;
            } catch (Exception $e) {
                OneGoUtils::log('Transaction start failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
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
                    OneGoUtils::log('OAuth token refreshed', OneGoUtils::LOG_NOTICE);
                } catch (OneGoAPI_Exception $e) {
                    OneGoUtils::log('OAuth token refresh failed: ['.get_class($e).'] '.$e->getMessage(), OneGoUtils::LOG_ERROR);
                    throw new OneGoAPICallFailedException('OAuth token refresh failed', null, $e);
                }
            }
        } else {
            throw new OneGoAuthenticationRequiredException();
        }
        
        // start transaction if not started and token available,
        // refresh transaction if expired
        $transaction = $this->getTransaction();
        
        // save transaction state to restore on restart
        if ($this->isTransactionStarted()) {
            $stateBeforeRestart = OneGoTransactionState::getCurrent();
        }
        
        if ($transaction && $transaction->isExpired()) {
            OneGoUtils::log('Transaction expired, delete', OneGoUtils::LOG_NOTICE);
            $this->cancelTransaction(true);
            $transactionCanceled = true;
        }
        
        $transaction = $this->getTransaction();
        
        $action = !empty($transactionCanceled) ? 'restart' : 'autostart';
        if (!$this->isTransactionStarted()) {
            try {
                $this->beginTransaction($token);
                $transactionAutostarted = true;
                
                if (!empty($stateBeforeRestart)) {
                    $this->restoreTransactionToState($stateBeforeRestart);
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
    
    public function getPrepaidRedeemedAmount()
    {
        // TODO actual value
        if (OneGoTransactionState::getCurrent()->get(OneGoTransactionState::VGC_REDEEMED)) {
            return $this->getPrepaidSpent();
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
        // prevent multiple requests on the same page if first request failed
        if (OneGoUtils::getFromRegistry('anonymousRequestFailed')) {
            return false;
        }
        
        if ((is_null(OneGoUtils::getFromSession('anonymousModifiedCart'))) ||
            ($this->getEshopCartHash() != OneGoUtils::getFromSession('anonymousModifiedCartHash'))) 
        {
            $api = $this->getApi();
            try {
                $modifiedCart = $api->getAnonymousAwards($this->collectCartEntries());
                OneGoUtils::log('Anonymous awards requested', OneGoUtils::LOG_NOTICE);
                OneGoUtils::saveToSession('anonymousModifiedCart', $modifiedCart);
                OneGoUtils::saveToSession('anonymousModifiedCartHash', $this->getEshopCartHash());
                OneGoUtils::saveToRegistry('anonymousRequestFailed', false);
            } catch (OneGoAPI_Exception $e) {
                // ignore
                OneGoUtils::log('Anonymous awards request failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
                OneGoUtils::saveToRegistry('anonymousRequestFailed', true);
                return false;
            }
        }
        $modifiedCart = OneGoUtils::getFromSession('anonymousModifiedCart');
        return is_null($modifiedCart) ? false : $modifiedCart;
    }
    
    public function deleteAnonymousModifiedCart()
    {
        OneGoUtils::saveToSession('anonymousModifiedCart', null);
    }
    
    public function hasAgreedToDiscloseEmail()
    {
        return OneGoTransactionState::getCurrent()->get(OneGoTransactionState::AGREED_DISCLOSE_EMAIL);
    }
    
    public function requestOAuthAccessToken($authorizationCode, $requestedScopes = false)
    {
        $auth = $this->getAuth();
        try {
            $token = $auth->requestAccessToken($authorizationCode, $this->getOAuthRedirectUri());
            OneGoUtils::log('OAuth token issued', OneGoUtils::LOG_NOTICE);
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
            OneGoUtils::log('Issuing OAuth token failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
        return $token;        
    }
    
    public function requestOAuthAccessTokenByVGC($cardNumber)
    {
        $auth = $this->getAuth();
        try {
            $token = $auth->requestAccessTokenByVirtualGiftCard($cardNumber, $this->getOAuthRedirectUri());
            OneGoUtils::log('OAuth token issued by VGC', OneGoUtils::LOG_NOTICE);
            
            if ($this->isTransactionStarted()) {
                $this->cancelTransaction();
            }
            
            $this->saveOAuthToken($token, true);
        } catch (OneGoAPI_OAuthException $e) {
            OneGoUtils::log('Issuing OAuth token failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
        return $token;        
    }
    
    public function getAnonymousPrepaidReceivableForLastOrder()
    {
        $lastOrder = $this->getCompletedOrder();
        if (!empty($lastOrder)) {
            $cart = $lastOrder->get('cart') ? $lastOrder->get('cart') : array();
            try {
                $prepaidReceived = $awards = $this->getApi()
                        ->getAnonymousAwards($this->collectCartEntries($cart))
                        ->getPrepaidReceived();
                $awards = !empty($prepaidReceived) ? $prepaidReceived->getAmount()->visible : null;
            } catch (OneGoAPI_Exception $e) {
                OneGoUtils::logCritical('Failed retrieving anonymous awards', $e);
                throw $e;
            }
            return (float) $awards;
        }
        return false;
    }
    
    public function redeemVirtualGiftCard($cardNumber)
    {
        $transaction = $this->getTransaction();
        try {
            $transaction->redeemVirtualGiftCard($cardNumber);
            OneGoUtils::log('VGC redeemed for '.$cardNumber, OneGoUtils::LOG_NOTICE);
            $this->saveTransaction($transaction);
            OneGoTransactionState::getCurrent()->set(OneGoTransactionState::VGC_REDEEMED, $cardNumber);
            return true;
        } catch (OneGoAPI_Exception $e) {
            OneGoUtils::log('redeemVirtualGiftCard failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            if (in_array(get_class($e), array(
                'OneGoAPI_VirtualGiftCardNotFoundException',
                'OneGoAPI_InvalidInputException'
            ))) 
            {
                throw new OneGoVirtualGiftCardNumberInvalidException($e);
            } else {
                throw $e;
            }
        }
    }
    
    public function redeemAnonymousVirtualGiftCard($cardNumber)
    {
        try {
            $token = $this->requestOAuthAccessTokenByVGC($cardNumber);
            $this->beginTransaction($token);
            $this->redeemVirtualGiftCard($cardNumber);
        } catch (OneGoAPI_Exception $e) {
            if (in_array(get_class($e), array(
                'OneGoAPI_OAuthInvalidGrantException',
                'OneGoAPI_OAuthInvalidRequestException'
            ))) 
            {
                throw new OneGoVirtualGiftCardNumberInvalidException($e);
            } else {
                throw $e;
            }
        }
    }
    
    public function restoreTransactionToState(OneGoTransactionState $state)
    {
        if ($state->get('redeemedVGC')) {
            try {
                $this->redeemVirtualGiftCard($state->get('redeemedVGC'));
            } catch (OneGoAPI_Exception $e) {
                // ignore
            }
        }
        if ($state->get('spentPrepaid')) {
            try {
                $this->spendPrepaid();
            } catch (OneGoAPI_Exception $e) { 
                // ignore
            }
        }
    }
}

abstract class OneGoPersistentState
{
    abstract protected function initialize();
    abstract protected function getStorageKey();

    public function get($key)
    {
        return isset($this->$key) ? $this->$key : null;
    }
    
    public function reset()
    {
        $this->initialize();
        $this->save();
    }
    
    public function set($key, $value)
    {
        $this->$key = $value;
        $this->save();
    }
    
    public function __construct() {
        $this->initialize();
    }
    
    public function toArray()
    {
        return get_object_vars($this);
    }
    
    protected function save()
    {
        OneGoUtils::saveToSession($this->getStorageKey(), $this);
    }
    
    /**
     *
     * @return OneGoPersistentState 
     */
    public static function loadCurrent(OneGoPersistentState $newDefault)
    {
        return OneGoUtils::getFromSession($newDefault->getStorageKey(), $newDefault);
    }
}

class OneGoTransactionState extends OneGoPersistentState
{
    const PREPAID_SPENT = 'spentPrepaid';
    const VGC_REDEEMED = 'redeemedVGC';
    const AGREED_DISCLOSE_EMAIL = 'agreedToDiscloseEmail';
    const BUYER_ANONYMOUS = 'buyerAnonymous';
    
    protected $spentPrepaid;
    protected $redeemedVGC;
    protected $agreedToDiscloseEmail;
    protected $buyerAnonymous;
    
    protected function getStorageKey()
    {
        return 'TransactionState';
    }
    
    protected function initialize()
    {
        $this->spentPrepaid = false;
        $this->redeemedVGC = false;
        $this->agreedToDiscloseEmail = false;
        $this->buyerAnonymous = false;
    }
    
    /**
     *
     * @return OneGoTransactionState 
     */
    public static function getCurrent()
    {
        return parent::loadCurrent(new self());
    }
}

class OneGoCompletedOrderState extends OneGoPersistentState
{
    protected $orderId;
    protected $completedOn;
    protected $benefitsApplied;
    protected $buyerEmail;
    protected $newBuyerRegistered;
    protected $prepaidReceived;
    protected $cart;
    
    protected function getStorageKey()
    {
        return 'CompletedOrderState';
    }
    
    protected function initialize()
    {
        $this->orderId = false;
        $this->completedOn = false;
        $this->benefitsApplied = false;
        $this->buyerEmail = false;
        $this->newBuyerRegistered = false;
        $this->prepaidReceived = false;
        $this->cart = false;
    }
    
    /**
     *
     * @return OneGoCompletedOrderState 
     */
    public static function getCurrent()
    {
        return parent::loadCurrent(new self());
    }
}


class OneGoUtils
{
    const STORAGE_KEY = 'OneGoOpencart';
    
    const LOG_INFO = 0;
    const LOG_NOTICE = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;
    
    public static function getRegistry()
    {
        global $registry;
        return $registry;
    }
    
    /**
     *
     * @return Session 
     */
    public static function getSession()
    {
        $session = self::getRegistry()->get('session');
        return $session;
    }
    
    /**
     * Saves data to registry simulating own namespace
     *
     * @param string $key
     * @param mixed $val 
     */
    public static function saveToRegistry($key, $val)
    {
        $registry = self::getRegistry();
        $onego_data = $registry->get(self::STORAGE_KEY);
        if (empty($onego_data)) {
            $onego_data = array();
        }
        $onego_data[$key] = $val;
        $registry->set(self::STORAGE_KEY, $onego_data);
    }
    
    /**
     * Getter from registry namespace
     *
     * @param string $key
     * @return mixed null if no such data is available 
     */
    public static function getFromRegistry($key, $default = null)
    {
        $registry = self::getRegistry();
        $onego_data = $registry->get(self::STORAGE_KEY);
        return isset($onego_data[$key]) ? $onego_data[$key] : $default;
    }
    
    /**
     * Saves data to session under own namespace
     *
     * @param string $key
     * @param mixed $val 
     */
    public static function saveToSession($key, $val)
    {
        $session = self::getSession();
        $onego_data = isset($session->data[self::STORAGE_KEY]) ? $session->data[self::STORAGE_KEY] : array();
        $onego_data[$key] = serialize($val);
        $session->data[self::STORAGE_KEY] = $onego_data;
    }
    
    /**
     * Namespaced getter from session
     *
     * @param string $key
     * @return mixed 
     */
    public static function getFromSession($key, $default = null)
    {
        $session = self::getSession();
        $onego_data = isset($session->data[self::STORAGE_KEY]) ? $session->data[self::STORAGE_KEY] : array();
        $data = isset($onego_data[$key]) ? unserialize($onego_data[$key]) : $default;
        return $data;
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
    public static function getHttpReferer()
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    }
    
    /**
     * Save log message to session to later display in debugger, truncate log to
     * specified length 
     *
     * @param string $str Log message text
     * @param string $level self::LOG_INFO, self::LOG_NOTICE, self::LOG_WARNING, self::LOG_ERROR
     * @param integer $max_length Max amount of log messages to be saved
     */
    public static function log($str, $level = self::LOG_INFO, $max_length = 25)
    {
        if (ModelTotalOnego::getInstance()->getConfig('debugModeOn')) {
            $log = self::getLog();
            $log[] = array(
                'time'      => microtime(),
                'pid'       => getmypid(),
                'backtrace' => implode(' / ', self::debugBacktrace()),
                'message'   => $str,
                'level'     => $level,
            );
            $log = array_slice($log, -$max_length); // keep log small
            OneGoUtils::saveToSession('log', $log);
        }
    }
    
    public static function writeLog($str)
    {
        // write critical errors to log file
        $fh = fopen(DIR_LOGS.'onego_error.log', 'a');
        if ($fh) {
            $ln = date('Y-m-d H:i:s').' '.$str.' => '.implode(' / ', self::debugBacktrace());
            fwrite($fh, $ln."\n");
            fclose($fh);
        }
    }
    
    public static function logCritical($errorStr, $exception = null)
    {
        if (!empty($exception) && is_a($exception, 'Exception')) {
            $errorStr = $errorStr.' :: '.get_class($exception).' :: '.$exception->getMessage();
        }
        self::log($errorStr, self::LOG_ERROR);
        self::writeLog($errorStr);
    }
    
    /**
     * Returns list of messages saved in log
     *
     * @param boolean $clear Whether to remove returned log messages
     * @return array List of saved log entries
     */
    public static function getLog($clear = false)
    {
        $log = OneGoUtils::getFromSession('log');
        if (empty($log)) {
            $log = array();
        }
        if ($clear) {
            OneGoUtils::saveToSession('log', array());
        }
        return $log;
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
class OneGoVirtualGiftCardNumberInvalidException extends OneGoException {}
class OneGoAPICallFailedException extends OneGoException {}