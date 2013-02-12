<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'common.lib.php';

class ModelTotalOnego extends Model 
{   
    private static $current_eshop_cart = false;
    
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
    
    /**
     * Modifies Opencart's totals list by adding OneGo benefits and receivables
     *
     * @param array $total_data
     * @param float $total
     * @param array $taxes 
     */
    public function getTotal(&$total_data, &$total, &$taxes) {
        $onego_discount = 0;
        if ($total) {
            $initial_total = $total;
        } else {
            $initial_total = ($total += $this->cart->getSubTotal());
        }

        // detect order editing (added in OC v.1.5.2)
        if (!empty($this->request->get['route']) && ($this->request->get['route'] == 'checkout/manual') &&
            preg_match('#index\.php\?route=sale/order/update.+order_id=([0-9]+)#i', OneGoUtils::getHttpReferer(), $match))
        {
            $orderId = (int) $match[1];

            // get order totals
            $sql = "SELECT * FROM ".DB_PREFIX."order_total WHERE order_id = ".$orderId." AND code='onego' ORDER BY order_total_id";
            $totals = $this->db->query($sql);
            if (count($totals->rows)) {
                foreach ($totals->rows as $row) {
                    $row['value'] = (float) $row['value'];
                    $total_data[] = $row;
                    $total += $row['value'];
                    $onego_discount -= $row['value'];
                }
            }
        } else {

            try {
                $transaction = $this->refreshTransaction();
            } catch (OneGoAuthenticationRequiredException $e) {
                // ignore
            } catch (OneGoAPICallFailedException $e) {
                // ignore
            }

            $this->language->load('total/onego');

            if ($this->isTransactionStarted() || $this->hasAgreedToDiscloseEmail()) {
                // items discounts
                // TODO

                // shipping discounts
                // TODO
                $free_shipping = false;
                $shipping_discount = 0;
                /*
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
                }*/

                // cart discount
                $discount = $this->getTotalDiscount();
                $discountAmount = !empty($discount) ? $discount->getAmount()->getVisible() : null;
                if (!empty($discountAmount) && ($discountAmount != $shipping_discount)) {
                    $discountPercents = $this->calculateDiscountPercentage();
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

                // redemption code and OneGo account funds spent
                $funds_spent = 0;
                if ($this->isTransactionStarted()) {
                    $rc = $this->getTransaction()->getRedemptionCode();
                    if ($rc && $rc->spent) {
                        $funds_spent += $rc->spent;
                    }
                }
                $spent = $this->getPrepaidSpent();
                if (OneGoTransactionState::getCurrent()->get(OneGoTransactionState::PREPAID_SPENT)) {
                    $funds_spent += $spent;
                }
                if ($funds_spent) {
                    $total_data[] = array(
                        'code' => 'onego',
                        'title' => $this->language->get('prepaid_spent'),
                        'text' => $this->currency->format(-$funds_spent),
                        'value' => -$funds_spent,
                        'sort_order' => $this->config->get('onego_sort_order').'r'
                    );
                }

                // prepaid received
                $received = $this->getPrepaidReceivedAmount();
                if (!empty($received)) {
                    $receivables = array(
                        'code' => 'onego',
                        'title' => $this->language->get('funds_receivable'),
                        'text' => '+'.$this->currency->format($received),
                        'value' => 0,
                        'sort_order' => $this->config->get('onego_sort_order').'y',
                    );
                    $total_data[] = $receivables;
                }

                // RC remainder to be returned to account balance
                $rcRedeemed = $this->getRCUsedAmountRedeemed();
                if (!empty($rcRedeemed)) {
                    $receivables = array(
                        'code' => 'onego',
                        'title' => $this->language->get('rc_remainder'),
                        'text' => '+'.$this->currency->format($rcRedeemed),
                        'value' => 0,
                        'sort_order' => $this->config->get('onego_sort_order').'z',
                    );
                    $total_data[] = $receivables;
                }

                // onego subtotal
                $cashAmount = $this->getCashAmount();
                if ($initial_total != $cashAmount) {
                    $onego_discount = $this->getOriginalAmount() - $cashAmount;
                    $total -= $onego_discount;
                }
            }
        }
    }
    
    /**
     * Processes order confirmation. Is always called after ModelCheckoutOrder::confirm()
     *
     * @param integer $orderId Opencart order ID
     */
    public function confirmOrder($orderId)
    {
        $orderInfo = $this->getOrderInfo($orderId);
        if ($orderInfo) {
            $lastOrder = OneGoCompletedOrderState::getCurrent();
            if ($lastOrder->get('orderId') != $orderId) {
                $lastOrder->reset();
                $lastOrder->set('orderId', $orderId);
                $lastOrder->set('completedOn', time());
                $lastOrder->set('buyerEmail', $orderInfo['email']);
                $lastOrder->set('cart', $this->getEshopCart());
                $tokenState = OneGoOAuthTokenState::getCurrent();
                $transactionState = OneGoTransactionState::getCurrent();
                $lastOrder->set('transactionState', $transactionState);
                $lastOrder->set('oAuthTokenState', $tokenState);

                // process RC products in order cart
                $this->markRCCardsReserved($orderId);
                if ($this->config->get('config_complete_status_id') == $orderInfo['order_status_id']) {
                    // sold instantly
                    $this->confirmRCSale($orderId);
                }

                if ($this->isTransactionStarted()) {
                    $transactionId = $this->getTransactionId()->id;
                    try {
                        if ($this->isOrderStatusConfirmable($orderInfo['order_status_id'])) {
                            $transaction = $this->confirmTransaction();
                            OneGoTransactionsLog::log($orderId, $transactionId,
                                    OneGoSDK_DTO_TransactionEndDto::STATUS_CONFIRM);
                            $lastOrder->set('transactionDelayed', false);
                        } else {
                            $doDelay = true;
                            $transaction = $this->delayTransaction();
                            OneGoTransactionsLog::log($orderId, $transactionId,
                                    OneGoSDK_DTO_TransactionEndDto::STATUS_DELAY, $this->getDelayTtl());
                            $lastOrder->set('transactionDelayed', true);
                        }
                        $transactionState->set('transaction', $transaction);
                        $lastOrder->set('transactionState', $transactionState);
                        $lastOrder->set('prepaidReceived', $transaction->getPrepaidAmountReceived());

                        if (!$tokenState->isBuyerAnonymous()) {
                            $lastOrder->set('benefitsApplied', true);
                        }

                        if ($transactionState->hasAgreedToDiscloseEmail() && $tokenState->isBuyerAnonymous()) {
                            $this->bindEmailForOrder($lastOrder);
                        }

                    } catch (Exception $e) {
                        $this->registerFailedTransaction($orderId, $e->getMessage(), $transactionId);
                        if (!empty($doDelay)) {
                            OneGoTransactionsLog::log($orderId, $transactionId,
                                    OneGoSDK_DTO_TransactionEndDto::STATUS_DELAY, $this->getDelayTtl(), true, $e->getMessage());
                        } else {
                            OneGoTransactionsLog::log($orderId, $transactionId,
                                    OneGoSDK_DTO_TransactionEndDto::STATUS_CONFIRM, null, true, $e->getMessage());
                        }
                        $this->throwError($e->getMessage());
                    }
                } else {
                    if ($transactionState->hasAgreedToDiscloseEmail()) {
                        try {
                            $this->bindEmailForOrder($lastOrder);
                            $lastOrder->set('benefitsApplied', true);
                        } catch (OneGoException $e) {
                            $transactionId = '-undefined-';
                            $this->registerFailedTransaction($orderId, $e->getMessage(), $transactionId);
                        }
                    }
                }

                OneGoTransactionState::getCurrent()->reset();
            }
        }
    }

    public function updateOrder($orderId, $orderStatusBefore)
    {
        $orderInfo = $this->getOrderInfo($orderId);
        if ($orderInfo) {
            $this->load->model('checkout/order');
            // check if order status changed to "complete"
            if (($this->config->get('config_complete_status_id') != $orderStatusBefore) &&
                    ($this->config->get('config_complete_status_id') == $orderInfo['order_status_id']))
            {
                $this->sendRedemptionCodes($orderId);
            }
        }
    }
    
    private function isOrderStatusConfirmable($orderStatusId)
    {
        $confirmedStatuses = OneGoConfig::getArray('confirmOnOrderStatus');
        return in_array($orderStatusId, $confirmedStatuses);
    }
    
    public function saveCompletedOrderState($orderId)
    {
        $orderInfo = $this->getOrderInfo($orderId);
        $completedOrder = OneGoCompletedOrderState::getCurrent();
        $completedOrder->reset();
        $completedOrder->set('orderId', $orderId);
        $completedOrder->set('completedOn', time());
        $completedOrder->set('buyerEmail', $orderInfo['email']);
        $completedOrder->set('cart', $this->getEshopCart());
        return $completedOrder;
    }
    
    public function getCompletedOrder()
    {
        return OneGoCompletedOrderState::getCurrent();
    }

    /**
     * Notify e-shop admin about transaction, which could not be confirmed, and log it to order history
     *
     * @param integer $orderId
     * @param string $errorMessage
     * @param string $transactionId
     * @return void
     */
    public function registerFailedTransaction($orderId, $errorMessage, $transactionId)
    {
        $orderInfo = $this->getOrderInfo($orderId);
        
        $version = ONEGO_EXTENSION_VERSION;
        $text = <<<END
WARNING: order #{$orderId} has been processed using OneGo transaction, but transaction confirmation failed.
If buyer chose to spend his gift card balance or use single use offers a discount may be applied to order but
actual buyer's OneGo balance was not charged.
You may want to consider revising order status.
Please contact OneGo support for more information, including these details:

OneGo transaction ID: {$transactionId}
Failure reason: {$errorMessage}
Opencart extension version: {$version}
END;
        
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

    public function bindSessionToken($sessionToken, OneGoCompletedOrderState &$orderState)
    {
        if ($orderState->isAnonymous()) {
            $transaction = $orderState->get('transactionState')->get('transaction');
            $fundsReceived = false;
            if ($transaction) {
                try {
                    $transaction->bindSessionToken($sessionToken);
                    OneGoUtils::log('bindSessionToken() executed');
                    $orderState->set('newBuyerRegistered', true);
                    // fetch transaction status
                    $transactionState = $orderState->get('transactionState');
                    $transactionState->set('transaction', $transaction);
                    $orderState->set('transactionState', $transactionState);
                } catch (OneGoSDK_Exception $e) {
                    OneGoUtils::logCritical('bindSessionToken() failed', $e);
                    throw $e;
                }
            } else {
                $orderCart = $orderState->get('cart') ? $orderState->get('cart') : array();
                $cart = $this->collectCartEntries($orderCart);
                try {
                    $receiptNumber = '#'.$orderState->get('orderId');

                    // detect whether transaction is to be confirmed or delayed
                    $orderInfo = $this->getOrderInfo($orderState->get('orderId'));
                    if ($this->isOrderStatusConfirmable($orderInfo['order_status_id'])) {
                        $delayTtl = false;
                    } else {
                        $delayTtl = $this->getDelayTtl();
                    }

                    $transaction = $this->getApi()->bindSessionTokenNew(
                            $sessionToken,
                            $receiptNumber,
                            $delayTtl,
                            $cart);
                    //$this->saveTransaction($transaction);
                    $modifiedCart = $transaction->getModifiedCart();
                    if ($modifiedCart && $modifiedCart->getPrepaidReceived()) {
                        $fundsReceived = $modifiedCart->getPrepaidReceived()->getAmount()->getVisible();
                    }
                    $transactionState = new OneGoTransactionState();
                    $transactionState->set('transaction', $transaction);
                    $orderState->set('transactionState', $transactionState);
                    $orderState->set('newBuyerRegistered', true);
                    $orderState->set('prepaidReceived', $fundsReceived);

                    // log transaction complete
                    if ($delayTtl) {
                        OneGoTransactionsLog::log($orderState->get('orderId'), $transaction->getId()->id,
                                OneGoSDK_DTO_TransactionEndDto::STATUS_DELAY, $delayTtl);
                        $orderState->set('transactionDelayed', true);
                    } else {
                        OneGoTransactionsLog::log($orderState->get('orderId'), $transaction->getId()->id,
                                OneGoSDK_DTO_TransactionEndDto::STATUS_CONFIRM);
                        $orderState->set('transactionDelayed', false);
                    }
                } catch (OneGoSDK_Exception $e) {
                    OneGoUtils::logCritical('bindSessionTokenNew() failed', $e);
                    // log failed transaction complete
                    if ($delayTtl) {
                        OneGoTransactionsLog::log($orderState->get('orderId'), $transaction->getId()->id,
                                OneGoSDK_DTO_TransactionEndDto::STATUS_DELAY, $delayTtl,
                                true, $e->getMessage());
                    } else {
                        OneGoTransactionsLog::log($orderState->get('orderId'), $transaction->getId()->id,
                                OneGoSDK_DTO_TransactionEndDto::STATUS_CONFIRM, null,
                                true, $e->getMessage());
                    }
                    throw $e;
                }
            }
        }
    }


    /**
     * @param integer $orderId
     * @return Opencart order info
     */
    public function getOrderInfo($orderId)
    {
        $this->load->model('account/order');		
        return $this->model_account_order->getOrder($orderId);
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
     * @return OneGoSDK_Impl_Transaction
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
        return OneGoTransactionState::getCurrent()->get('cartHash');
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
     * @return OneGoSDK_DTO_TransactionIdDto OneGo transaction's id value
     */
    private function getTransactionId()
    {
        $transaction = $this->getTransaction();
        return !empty($transaction) ? $transaction->getId() : false;
    }
    
    /**
     * Singleton factory for SimpleAPI
     *
     * @return OneGoSDK_Impl_SimpleAPI Instance of SimpleAPI
     */    
    public function getApi()
    {
        $api = OneGoUtils::getFromRegistry('api');
        if (empty($api)) {
            $api = OneGoUtils::initAPI();
        
            // set persisted data
            $token = $this->getSavedOAuthToken();
            if ($token) {
                $api->setOAuthToken($token);
            }
            $transaction = $this->getSavedTransaction();
            if ($transaction) {
                $api->setTransaction($transaction);
            }

            OneGoUtils::saveToRegistry('api', $api);
        }
        return $api;
    }
    
    /**
     * Singleton factory for SimpleOAuth
     *
     * @return OneGoSDK_Impl_SimpleOAuth Instance of SimpleOAuth
     */    
    public function getAuth()
    {
        $auth = OneGoUtils::getFromRegistry('auth');
        if (empty($auth)) {
            $auth = OneGoUtils::initOAuth();
            OneGoUtils::saveToRegistry('auth', $auth);
        }
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
    public function beginTransaction(OneGoSDK_Impl_OAuthToken $token, $receiptNumber = false)
    {
        $api = $this->getApi();
        $api->setOAuthToken($token);
        
        $cart = $this->collectCartEntries();
                
        if (!$receiptNumber) {
            $receiptNumber = $this->generateReceiptNumber();
        }
        try {
            $transaction = $api->beginTransaction($receiptNumber, $cart);
            OneGoTransactionState::getCurrent()->reset();
            $this->saveTransaction($transaction);
            OneGoTransactionState::getCurrent()->set('cartHash', $this->getEshopCartHash());
            
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
     * @return mixed OneGoSDK_Impl_Transaction on success, false on fail 
     */
    public function confirmTransaction()
    {
        $api = $this->getApi();
        $transaction = $this->getTransaction();
        if ($transaction) {
            try {
                OneGoUtils::log('Transaction confirm', OneGoUtils::LOG_NOTICE);
                $transaction->confirm();
                $this->deleteTransaction();
                if (OneGoOAuthTokenState::getCurrent()->isBuyerAnonymous()) {
                    // unset token if transaction was started using RC
                    $this->deleteOAuthToken();
                }
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
        $transaction = $this->getTransaction();
        if ($transaction) {
            try {
                $transaction->cancel();
                $this->deleteTransaction();
                OneGoUtils::log('Transaction canceled', OneGoUtils::LOG_NOTICE);
                return true;
            } catch (Exception $e) {
                if (!$silent) {
                    OneGoUtils::log('Transaction cancel failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
                }
            }
        }
        $this->deleteTransaction();
        return false;
    }
    
    /**
     * Delay OneGo transaction, unset saved transaction
     *
     * @return mixed OneGoSDK_Impl_Transaction on success, false on fail 
     */
    public function delayTransaction()
    {
        $api = $this->getApi();
        $transaction = $this->getTransaction();
        if ($transaction) {
            $delayTtl = $this->getDelayTtl();
            try {
                $transaction->delay($delayTtl);
                OneGoUtils::log('Transaction delayed for '.($delayTtl / 3600).'h', OneGoUtils::LOG_NOTICE);
                $this->deleteTransaction();
                if (OneGoOAuthTokenState::getCurrent()->isBuyerAnonymous()) {
                    // unset token if transaction was started using RC
                    $this->deleteOAuthToken();
                }
                return $transaction;
            } catch (Exception $e) {
                OneGoUtils::log('Transaction delay failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
                $this->deleteTransaction();
            }
        }
        return false;
    }

    /**
     * @return integer Configured transaction delayTTL (in seconds), for transaction/bind/email/new operation
     */
    private function getDelayTtl()
    {
        return OneGoConfig::get('delayedTransactionTTL') * 3600; // convert hours to seconds
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
                OneGoTransactionState::getCurrent()->set('cartHash', $this->getEshopCartHash());

                // re-spend prepaid in case cart price has changed
                $transactionState = OneGoTransactionState::getCurrent();
                if ($transactionState->get(OneGoTransactionState::PREPAID_SPENT)) {
                    $this->spendPrepaid();
                }

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
    private function getEshopCart($reload = false)
    {
        if ($reload || (self::$current_eshop_cart === false)) {
            // add Opencart cart items
            $cart = OneGoUtils::getRegistry()->get('cart');
            $products = $cart->getProducts();

            // calculate final prices for products (add product taxes)
            $ids = array();
            foreach ($products as $key => $product) {
                $ids[] = $product['product_id'];
                $products[$key]['total_final'] = $product['total'];
                if ($product['tax_class_id']) {
                    if (method_exists($this->tax, 'getRates')) { // OpenCart v1.5.3
                        $tax_rates = $this->tax->getRates($product['total'], $product['tax_class_id']);
                        foreach ($tax_rates as $tax_rate) {
                            $products[$key]['total_final'] += $tax_rate['amount'];
                        }
                    } else {
                        $products[$key]['total_final'] += $product['total'] * $this->tax->getRate($product['tax_class_id']) / 100;
                    }
                }
            }

            self::$current_eshop_cart = $products;

            // load products details to determine item_code
            if (count($ids)) {
                $ids = implode(',', $ids);
                $products_query = $this->db->query("SELECT product_id, sku, upc FROM ".DB_PREFIX."product p WHERE product_id IN ({$ids})");
                $skuMap = array();
                foreach ($products_query->rows as $product) {
                    if (!empty($product['sku'])) {
                        $skuMap[$product['product_id']] = $product['sku'];
                    }
                }
                foreach (self::$current_eshop_cart as $key => $product) {
                    self::$current_eshop_cart[$key]['_item_code'] =
                        !empty($skuMap[$product['product_id']]) ?
                            $skuMap[$product['product_id']] :
                            OneGoConfig::get('cartItemCodePrefix').$product['product_id'];
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
    private function getEshopCartHash()
    {
        return md5(serialize($this->getEshopCart()));
    }

    /**
     * Collect opencart cart entries into OneGoSDK_Impl_Cart object
     *
     * @return OneGoSDK_Impl_Cart
     */
    public function collectCartEntries($eshopCart = null)
    {
        if (is_null($eshopCart)) {
            $eshopCart = $this->getEshopCart();
        }
        $cart = $this->getApi()->newCart();

        $rc_products_ids = $this->getRCProductsIds();

        foreach ($eshopCart as $product) {
            $ignored = !empty($product['key']) && $this->isShippingItemCode($product['key']) ||
                in_array($product['product_id'], $rc_products_ids);
            $itemPrice = round($product['total_final'] / $product['quantity'], 2);
            $totalFinal = round($product['total_final'], 2);
            $key = md5($product['key']);
            $cart->setEntry($key, $product['_item_code'], $itemPrice,
                    $product['quantity'], $totalFinal, $product['name'], false, $ignored);
        }
        return $cart;
    }
    
    /**
     *
     * @return array Shipping data for including as a cart item
     */
    private function getShippingAsItem()
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
                    'key'           => OneGoConfig::get('shippingCode'),
                    '_item_code'    => OneGoConfig::get('shippingCode'),
                    'price'         => $total,
                    'quantity'      => 1,
                    'total'         => $total,
                    'total_final'   => $total + array_sum($taxes),
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
        $shipping = $this->getShippingAsItem();
        if ($shipping) {
            $transaction_cart['_shipping'] = $shipping;
        }
    }
    
    /**
     *
     * @return array Prepaid available to buyer
     */
    public function getPrepaidAvailable()
    {
        $funds = array();
        $transaction = $this->getTransaction();
        if (!empty($transaction)) {
            $available = $transaction->getPrepaidAvailable();
            if (!is_null($funds)) {
                $spent = $transaction->getPrepaidSpent();
                if ($spent) {
                    $available += $spent;
                }
                $rc = $transaction->getRedemptionCode();
                if ($rc) {
                    $available -= $rc->spent;
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
    public function getPrepaidAmountAvailable()
    {
        $funds = $this->getPrepaidAvailable();
        return isset($funds['amount']) ? round($funds['amount'], 2) : false;
    }

    /**
     * Calculate discount percentage compared to discountable items value
     *
     * @return bool|float
     */
    public function calculateDiscountPercentage()
    {
        $cart = $this->getModifiedCart();
        if ($cart && !empty($cart->entries)) {
            $total = 0;
            foreach ($cart->entries as $entry) {
                if (!$this->isShippingItemCode($entry->itemCode)) {
                    $total += $entry->pricePerUnit * $entry->quantity;
                }
            }
            $discount = $this->getTotalDiscount();
            if ($total) {
                return round($discount->getAmount()->getVisible() * 100 / $total, 2);
            }
        }
        return false;
    }
        
    /**
     *
     * @return OneGoSDK_Impl_OAuthToken 
     */
    public function getSavedOAuthToken()
    {
        return OneGoOAuthTokenState::getCurrent()->get('token');
    }

    /**
     * Persist OAuth token
     *
     * @param OneGoSDK_Impl_OAuthToken $token
     * @param bool $isAnonymous
     * @return void
     */
    public function saveOAuthToken(OneGoSDK_Impl_OAuthToken $token, $isAnonymous = false)
    {
        OneGoOAuthTokenState::getCurrent()->set('token', $token);
        OneGoOAuthTokenState::getCurrent()->set('buyerAnonymous', $isAnonymous);
    }
    
    public function deleteOAuthToken()
    {
        OneGoOAuthTokenState::getCurrent()->reset();
        OneGoUtils::log('OAuth token destroyed');
    }
    
    public function getSavedTransaction()
    {
        return OneGoTransactionState::getCurrent()->get('transaction');;
    }
    
    public function saveTransaction(OneGoSDK_Impl_Transaction $transaction)
    {
        OneGoTransactionState::getCurrent()->set('transaction', $transaction);
    }
    
    public function deleteTransaction()
    {
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
        return !empty($token) && !$token->isExpired() && 
            !OneGoOAuthTokenState::getCurrent()->get('buyerAnonymous');
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
                    $discount += $cartEntry->getDiscount()->getAmount()->getVisible();
                }
            }
        }
        return $discount;
    }
    
    public function isShippingItem(OneGoSDK_DTO_CartEntryDto $transactionCartEntry)
    {
        return $this->isShippingItemCode($transactionCartEntry->itemCode);
    }
    
    public function isShippingItemCode($itemCode)
    {
        return in_array($itemCode, array(OneGoConfig::get('shippingCode')));
    }

    /**
     * @throws OneGoSDK_Exception
     * @return bool API operation succeeded
     */
    public function spendPrepaid()
    {
        $transaction = $this->getTransaction();
        if (empty($transaction) || !$transaction->isStarted()) {
            return false;
        }
        try {
            $amount = $this->getPrepaidAmountAvailable();
            $transaction->spendPrepaid($amount);
            OneGoTransactionState::getCurrent()->set(OneGoTransactionState::PREPAID_SPENT, $amount);
            OneGoUtils::log('Spent prepaid: '.$transaction->getPrepaidSpent(), OneGoUtils::LOG_NOTICE);
            $this->saveTransaction($transaction);
            return true;
        } catch (OneGoSDK_Exception $e) {
            OneGoUtils::log('Spend prepaid failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
        return false;
    }

    /**
     * @return bool API operation succeeded
     */
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
        } catch (OneGoSDK_Exception $e) {
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
                $token->hasScope(OneGoSDK_Impl_OneGoOAuth::SCOPE_RECEIVE_ONLY) &&
                $token->hasScope(OneGoSDK_Impl_OneGoOAuth::SCOPE_USE_BENEFITS);
    }
    
    /**
     * Check if new token is valid for current transaction, restart transaction
     * with new token if not.
     *
     * @param OneGoSDK_Impl_OAuthToken $newToken 
     * @return boolean If transaction was refreshed
     */
    public function verifyTransactionWithNewToken(OneGoSDK_Impl_OAuthToken $newToken)
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
        } catch (OneGoSDK_OperationNotAllowedException $e) {
            OneGoUtils::log('Transaction does not accept new token: '.$e->getMessage(), OneGoUtils::LOG_NOTICE);
            
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

    /**
     * Refreshes expired OAuth token and transaction, also update transaction when it is near expiration
     *
     * @throws OneGoAPICallFailedException|OneGoAuthenticationRequiredException
     * @param bool $forceUpdate Update transaction even if not expired
     * @return OneGoSDK_Impl_Transaction refreshed transaction
     */
    public function refreshTransaction($forceUpdate = false)
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
                } catch (OneGoSDK_Exception $e) {
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

        if ($transaction) {
            if ($transaction->isExpired()) {
                OneGoUtils::log('Transaction expired, delete', OneGoUtils::LOG_NOTICE);
                $this->cancelTransaction(true);
                $transactionCanceled = true;
            } else if ($this->isTransactionAboutToExpire()) {
                OneGoUtils::log('Transaction is about to expire');
                $this->updateTransactionCart();
            } else if ($forceUpdate) {
                OneGoUtils::log('Force transaction update');
                $this->updateTransactionCart();
            }
        }
        
        $action = !empty($transactionCanceled) ? 'restart' : 'autostart';
        if (!$this->isTransactionStarted() && count($this->getEshopCart()) > 0) {
            try {
                $this->beginTransaction($token);
                $transactionAutostarted = true;
                
                if (!empty($stateBeforeRestart)) {
                    $this->restoreTransactionState($stateBeforeRestart);
                }
                
            } catch (OneGoSDK_Exception $e) {
                throw new OneGoAPICallFailedException("Transaction {$action} failed", null, $e);
            }
        }
        
        // update transaction cart if outdated
        if (empty($transactionAutostarted) && $this->isTransactionStale()) {
            $this->updateTransactionCart();
        }

        $transaction = $this->getTransaction();
        if ($transaction) {
            $this->saveTransaction($transaction);
        }

        return $transaction;
    }

    /**
     * @return boolean True if there is less time left until transaction expiration than configured
     */
    private function isTransactionAboutToExpire()
    {
        if ($this->isTransactionStarted() && OneGoConfig::get('transactionRefreshIn')) {
            $transaction = $this->getTransaction();
            return ($transaction->getExpiresIn() - OneGoConfig::get('transactionRefreshIn')) <= 0;
        }
        return false;
    }
    
    public function getPrepaidReceivedAmount()
    {
        $cart = $this->getModifiedCart();
        if ($cart && ($prepaidReceived = $cart->getPrepaidReceived())) {
            return $prepaidReceived->getAmount()->getVisible();
        }
        return false;
    }
    
    public function getRCUsedNominal()
    {
        if (OneGoTransactionState::getCurrent()->get(OneGoTransactionState::RC_REDEEMED)) {
            $rc = $this->getTransaction()->getRedemptionCode();
            if ($rc) {
                return (float) $rc->original;
            }
        }
        return false;
    }


    public function getRCUsedAmountRedeemed()
    {
        if (OneGoTransactionState::getCurrent()->get(OneGoTransactionState::RC_REDEEMED)) {
            $rc = $this->getTransaction()->getRedemptionCode();
            if ($rc) {
                return (float) $rc->redeemed;
            }
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
            return (float) $prepaidSpent;
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

    /**
     * @return OneGoSDK_DTO_ModifiedCartDTO
     */
    private function getModifiedCart()
    {
        if ($this->isTransactionStarted()) {
            return $this->getTransaction()->getModifiedCart();
        } else if ($this->hasAgreedToDiscloseEmail()) {
            return $this->getAnonymousModifiedCart();
        }
        return false;
    }

    public function getModifiedCartHash()
    {
        $cart = $this->getModifiedCart();
        if ($cart) {
            $arr = OneGoUtils::objectToArray($cart, true);
            $hash = md5(serialize($arr));
            return $hash;
        }
        return false;
    }
    
    /**
     *
     * @return OneGoSDK_DTO_ModifiedCartDto 
     */
    private function getAnonymousModifiedCart()
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
            } catch (OneGoSDK_Exception $e) {
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

    /**
     * Request OAuth token by OAuth authorization code (received on buyer authentication)
     *
     * @throws OneGoSDK_OAuthException
     * @param string $authorizationCode
     * @param array $requestedScopes
     * @return OneGoSDK_Impl_OAuthToken
     */
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
            $this->saveOAuthToken($token, false);
        } catch (OneGoSDK_OAuthException $e) {
            OneGoUtils::log('Issuing OAuth token failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
        return $token;        
    }

    /**
     * Request OAuth access token by Redemption Code number
     *
     * @throws OneGoSDK_OAuthException
     * @param string $redemptionCode
     * @return OneGoSDK_Impl_OAuthToken
     */
    public function requestOAuthAccessTokenByRC($redemptionCode)
    {
        $auth = $this->getAuth();
        try {
            $token = $auth->requestAccessTokenByRedemptionCode($redemptionCode, $this->getOAuthRedirectUri());
            OneGoUtils::log('OAuth token issued by RC', OneGoUtils::LOG_NOTICE);
            
            if ($this->isTransactionStarted()) {
                $this->cancelTransaction();
            }
            
            $this->saveOAuthToken($token, true);
        } catch (OneGoSDK_OAuthException $e) {
            OneGoUtils::log('Issuing OAuth token failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
        return $token;        
    }
    
    public function getPrepaidReceivableForLastOrder()
    {
        $lastOrder = $this->getCompletedOrder();
        if (!empty($lastOrder)) {
            if ($lastOrder->get('transactionState')->get('transaction')) {
                $transaction = $lastOrder->get('transactionState')->get('transaction');
                $rcRemainder = $transaction->getRedemptionCode() ?
                        (float) $transaction->getRedemptionCode()->remaining : 0;
                $prepaidReceivable = (float) $transaction->getPrepaidAmountReceived();
                $receivable = array(
                    'rcRemainder'   => round($rcRemainder, 2),
                    'prepaid'       => round($prepaidReceivable, 2),
                );
                return $receivable;
            } else {
                $cart = $lastOrder->get('cart') ? $lastOrder->get('cart') : array();
                try {
                    $prepaidReceivable = $this->getApi()
                            ->getAnonymousAwards($this->collectCartEntries($cart))
                            ->getPrepaidReceived();
                    $awards = !empty($prepaidReceivable) ? $prepaidReceivable->getAmount()->getVisible() : null;
                } catch (OneGoSDK_Exception $e) {
                    OneGoUtils::logCritical('Failed retrieving anonymous awards', $e);
                    throw $e;
                }
                $receivable = array(
                    'rcRemainder'  => 0,
                    'prepaid'       => round((float) $awards, 2),
                );
                return $receivable;
            }
        }
        return false;
    }

    /**
     * @throws OneGoSDK_Exception|OneGoSDK_RedemptionCodeNotFoundException|OneGoRedemptionCodeInvalidException
     * @param string $redemptionCode
     * @return bool Success
     */
    public function useRedemptionCode($redemptionCode)
    {
        $transaction = $this->getTransaction();
        try {
            $transaction->useRedemptionCode($redemptionCode);
            OneGoUtils::log('RC redeemed '.$redemptionCode, OneGoUtils::LOG_NOTICE);
            $this->saveTransaction($transaction);
            OneGoTransactionState::getCurrent()->set(OneGoTransactionState::RC_REDEEMED, $redemptionCode);
            return true;
        } catch (OneGoSDK_RedemptionCodeNotFoundException $e) {
            OneGoUtils::log('Redeem code invalid', OneGoUtils::LOG_ERROR);
            throw new OneGoRedemptionCodeInvalidException($e->getMessage());
        } catch (OneGoSDK_Exception $e) {
            OneGoUtils::log('useRedemptionCode failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
    }

    /**
     * Redeem RC for anonymous buyer
     *
     * @throws Exception|OneGoSDK_OAuthInvalidGrantException|OneGoRedemptionCodeInvalidException
     * @param $redemptionCode
     * @return void
     */
    public function useRedemptionCodeAnonymously($redemptionCode)
    {
        try {
            $token = $this->requestOAuthAccessTokenByRC($redemptionCode);
            $this->beginTransaction($token);
            $this->useRedemptionCode($redemptionCode);
        } catch (OneGoSDK_OAuthInvalidGrantException $e) {
            throw new OneGoRedemptionCodeInvalidException($e->getMessage());
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Restore transaction to known state
     *
     * @param OneGoTransactionState $state
     * @return void
     */
    public function restoreTransactionState(OneGoTransactionState $state)
    {
        if ($state->get('redeemedRC')) {
            try {
                $this->useRedemptionCode($state->get('redeemedRC'));
            } catch (Exception $e) {
                // ignore
            }
        }
        if ($state->get('spentPrepaid')) {
            try {
                $this->spendPrepaid();
            } catch (Exception $e) { 
                // ignore
            }
        }
        OneGoTransactionState::getCurrent()->set('buyerAnonymous', $state->get('buyerAnonymous'));
        OneGoTransactionState::getCurrent()->set('agreedToDiscloseEmail', $state->get('agreedToDiscloseEmail'));
        OneGoTransactionState::getCurrent()->set('transaction', $state->get('transaction'));
        OneGoTransactionState::getCurrent()->set('cartHash', $state->get('cartHash'));
    }

    public function getRCProductsIds()
    {
        $sql = "SELECT p.product_id
                FROM (".DB_PREFIX.OneGoRedemptionCodes::DB_TABLE_BATCHES." b, ".DB_PREFIX."product p)
                WHERE b.product_id=p.product_id
                GROUP BY p.product_id";
        $res = $this->db->query($sql);
        $ids = array();
        if (!empty($res->rows)) {
            foreach ($res->rows as $row) {
                $ids[] = $row['product_id'];
            }
        }
        return $ids;
    }

    public function markRCCardsReserved($orderId)
    {
        $this->load->model('account/order');
        $orderInfo = $this->model_account_order->getOrder($orderId);
        $rcProductsIds = $this->getRCProductsIds();
        if ($orderInfo) {
            $products = $this->model_account_order->getOrderProducts($orderId);
            foreach ($products as $product) {
                if (in_array($product['product_id'], $rcProductsIds)) {
                    OneGoRedemptionCodes::reserveCodes($product['product_id'], $product['quantity'], $orderId);
                    OneGoRedemptionCodes::updateStock($product['product_id']);
                }
            }
        }
    }

    public function markRCCardsSold($orderId)
    {
        OneGoRedemptionCodes::sellCodes($orderId);
    }

    private function confirmRCSale($orderId)
    {
        OneGoRedemptionCodes::sellCodes($orderId);
        $this->sendRedemptionCodes($orderId);
    }

    private function sendRedemptionCodes($orderId)
    {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($orderId);
        if ($order_info && ($this->config->get('config_complete_status_id') == $order_info['order_status_id']))
        {
            // order status changed to confirmed, may now send RC details to buyer
            $codes = OneGoRedemptionCodes::getOrderCodes($orderId);
            if (!empty($codes)) {
                OneGoRedemptionCodes::createDownload($order_info, $codes, $this);
                return OneGoRedemptionCodes::sendEmail($order_info, $codes, $this);
            }
        }
        return false;
    }

    /**
     * Returns RC details for inclusion in download file
     *
     * @param integer $orderId
     * @return string RC numbers and nominals
     */
    public function getOrderRCText($orderId, $productId)
    {
        $codes = OneGoRedemptionCodes::getOrderCodes($orderId);
        $str = '';
        if (!empty($codes)) {
            foreach ($codes as $code) {
                if ($code['product_id'] == $productId) {
                    $str .= $code['number']."\r\n";
                }
            }
        }
        return $str;
    }

    public function setFlashMessage($key, $text)
    {
        $messages = OneGoUtils::getFromSession('flashMessages', array());
        $messages[$key] = $text;
        OneGoUtils::saveToSession('flashMessages', $messages);
    }

    public function pullFlashMessage($key)
    {
        $messages = OneGoUtils::getFromSession('flashMessages', array());
        if (isset($messages[$key])) {
            $ret = $messages[$key];
            unset($messages[$key]);
            OneGoUtils::saveToSession('flashMessages', $messages);
            return $ret;
        } else {
            return false;
        }
    }
}