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

            $this->load->language('total/onego');

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
                $discountAmount = !empty($discount) ? $discount->getAmount() : null;
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

                // virtual gift card spent
                if ($this->isTransactionStarted()) {
                    $vgc = $this->getTransaction()->getVirtualGiftCard();
                    if ($vgc && $vgc->spent) {
                        $total_data[] = array(
                            'code' => 'onego',
                            'title' => $this->language->get('vgc_spent'),
                            'text' => $this->currency->format(-$vgc->spent),
                            'value' => -$vgc->spent,
                            'sort_order' => $this->config->get('onego_sort_order').'p'
                        );
                    }
                }

                // prepaid spent
                $spent = $this->getPrepaidSpent();
                if (OneGoTransactionState::getCurrent()->get(OneGoTransactionState::PREPAID_SPENT)) {
                    $total_data[] = array(
                        'code' => 'onego',
                        'title' => $this->language->get('prepaid_spent'),
                        'text' => $this->currency->format(-$spent),
                        'value' => -$spent,
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

                if ($this->isTransactionStarted()) {
                    $transactionId = $this->getTransactionId()->id;
                    try {
                        if ($this->isOrderStatusConfirmable($orderInfo['order_status_id'])) {
                            $transaction = $this->confirmTransaction();
                            OneGoTransactionsLog::log($orderId, $transactionId,
                                    OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM);
                            $lastOrder->set('transactionDelayed', false);
                        } else {
                            $doDelay = true;
                            $transaction = $this->delayTransaction();
                            OneGoTransactionsLog::log($orderId, $transactionId,
                                    OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY, $this->getDelayTtl());
                            $lastOrder->set('transactionDelayed', true);
                        }
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
                                    OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY, $this->getDelayTtl(), true, $e->getMessage());
                        } else {
                            OneGoTransactionsLog::log($orderId, $transactionId,
                                    OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM, null, true, $e->getMessage());
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
WARNING: order #{$orderId} has been processed using OneGo benefits, but transaction confirmation failed.
If buyer chose to spend his OneGo funds or use single use coupon the discount was applied to order but OneGo funds were not charged.
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
    
    public function isAnonymousRewardsApplied()
    {
        $lastOrder = $this->getCompletedOrder();
        return $lastOrder->get('newBuyerRegistered');
    }
    
    public function isAnonymousRewardsApplyable()
    {
        $lastOrder = $this->getCompletedOrder();
        $transactionState = $lastOrder->get('transactionState');
        return $transactionState && $lastOrder &&
               !$transactionState->get('agreedToDiscloseEmail') && !$lastOrder->get('benefitsApplied');
    }

    /**
     * Wrapper for binding email for last order
     *
     * @throws OneGoAPI_Exception
     * @param OneGoCompletedOrderState $order
     * @return bool
     */
    public function bindEmailForOrder(OneGoCompletedOrderState &$order)
    {
        $tokenState = $order->get('oAuthTokenState');
        $transactionState = $order->get('transactionState');
        $transaction = $transactionState->get('transaction');
        $fundsReceived = false;
        $email = trim($order->get('buyerEmail'));
        if ($tokenState->isBuyerAnonymous() && !empty($transaction)) {
            try {
                $transaction->bindEmail($email);
                OneGoUtils::log('bindEmail() executed');
                $order->set('newBuyerRegistered', true);
                $fundsReceived = $transaction->getPrepaidAmountReceived();
                $order->set('prepaidReceived', $fundsReceived);
            } catch (OneGoAPI_Exception $e) {
                OneGoUtils::logCritical('bindEmail() failed', $e);
                throw $e;
            }
        } else if ($tokenState->isBuyerAnonymous()) {
            $orderCart = $order->get('cart') ? $order->get('cart') : array();
            $cart = $this->collectCartEntries($orderCart);
            try {
                $receiptNumber = '#'.$order->get('orderId');
                
                // detect whether transaction is to be confirmed or delayed
                $orderInfo = $this->getOrderInfo($order->get('orderId'));
                if ($this->isOrderStatusConfirmable($orderInfo['order_status_id'])) {
                    $delayTtl = false;
                } else {
                    $delayTtl = $this->getDelayTtl();
                }
                
                $transaction = $this->getApi()->bindEmailNew(
                        $order->get('buyerEmail'), 
                        $receiptNumber,
                        $delayTtl,
                        $cart);
                $this->saveTransaction($transaction);
                $modifiedCart = $transaction->getModifiedCart();
                if ($modifiedCart && $modifiedCart->getPrepaidReceived()) {
                    $fundsReceived = $modifiedCart->getPrepaidReceived()->getAmount()->visible;
                }
                $order->set('newBuyerRegistered', true);
                $order->set('prepaidReceived', $fundsReceived);
                
                // log transaction complete
                if ($delayTtl) {
                    OneGoTransactionsLog::log($order->get('orderId'), $transaction->getId()->id, 
                            OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY, $delayTtl);
                    $order->set('transactionDelayed', true);
                } else {
                    OneGoTransactionsLog::log($order->get('orderId'), $transaction->getId()->id, 
                            OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM);
                    $order->set('transactionDelayed', false);
                }
            } catch (OneGoAPI_Exception $e) {
                OneGoUtils::logCritical('bindEmailNew() failed', $e);
                // log failed transaction complete
                if ($delayTtl) {
                    OneGoTransactionsLog::log($order->get('orderId'), $transaction->getId()->id, 
                            OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY, $delayTtl,
                            true, $e->getMessage());
                } else {
                    OneGoTransactionsLog::log($order->get('orderId'), $transaction->getId()->id, 
                            OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM, null,
                            true, $e->getMessage());
                }
                throw $e;
            }
        }
        return $fundsReceived;
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
     * @return OneGoAPI_DTO_TransactionIdDto OneGo transaction's id value
     */
    private function getTransactionId()
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
     * @return OneGoAPI_Impl_SimpleOAuth Instance of SimpleOAuth
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
     * @return mixed OneGoAPI_Impl_Transaction on success, false on fail 
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
                    // unset token if transaction was started using VGC
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
     * @return mixed OneGoAPI_Impl_Transaction on success, false on fail 
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
                    // unset token if transaction was started using VGC
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
            $ignored = !empty($product['product_id']) && $this->isShippingItemCode($product['product_id']);
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
            $transaction_cart['shipping'] = $shipping;
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
                $currency = $transaction->getCurrencyCode();
                $spent = $transaction->getPrepaidSpent();
                if ($spent) {
                    $available += $spent;
                }
                $vgc = $transaction->getVirtualGiftCard();
                if ($vgc) {
                    $available -= $vgc->spent;
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
        return isset($funds['amount']) ? $funds['amount'] : false;
    }
    
    public function calculateDiscountPercentage()
    {
        $cart = $this->getModifiedCart();
        if ($cart && !empty($cart->entries)) {
            $total = 0;
            foreach ($cart->entries as $entry) {
                if (!$this->isShippingItemCode($entry->itemCode)) {
                    $total += $entry->cash;
                }
            }
            $discount = $this->getTotalDiscount();
            if ($total) {
                return round($discount->getAmount() * 100 / $total, 2);
            }
        }
        return false;
    }
        
    /**
     *
     * @return OneGoAPI_Impl_OAuthToken 
     */
    public function getSavedOAuthToken()
    {
        return OneGoOAuthTokenState::getCurrent()->get('token');
    }

    /**
     * Persist OAuth token
     *
     * @param OneGoAPI_Impl_OAuthToken $token
     * @param bool $isAnonymous
     * @return void
     */
    public function saveOAuthToken(OneGoAPI_Impl_OAuthToken $token, $isAnonymous = false)
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
    
    public function saveTransaction(OneGoAPI_Impl_Transaction $transaction)
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
                    $discount += $cartEntry->getDiscount()->getAmount();
                }
            }
        }
        return $discount;
    }
    
    public function isShippingItem(OneGoAPI_DTO_CartEntryDto $transactionCartEntry)
    {
        return $this->isShippingItemCode($transactionCartEntry->itemCode);
    }
    
    public function isShippingItemCode($itemCode)
    {
        return in_array($itemCode, array(OneGoConfig::get('shippingCode')));
    }

    /**
     * @throws OneGoAPI_Exception
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
        } catch (OneGoAPI_Exception $e) {
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
        } catch (OneGoAPI_OperationNotAllowedException $e) {
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
     * @return OneGoAPI_Impl_Transaction refreshed transaction
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
                
            } catch (OneGoAPI_Exception $e) {
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
            return $prepaidReceived->getAmount()->visible;
        }
        return false;
    }
    
    public function getPrepaidRedeemedAmount()
    {
        // TODO actual value
        if (OneGoTransactionState::getCurrent()->get(OneGoTransactionState::VGC_REDEEMED)) {
            $vgc = $this->getTransaction()->getVirtualGiftCard();
            if ($vgc) {
                return $vgc->original;
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
            if ($this->isTransactionStarted()) {
                $vgc = $this->getTransaction()->getVirtualGiftCard();
                if ($vgc) {
                    $prepaidSpent -= $vgc->spent;
                }
            }
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
     * @return OneGoAPI_DTO_ModifiedCartDto 
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

    /**
     * Request OAuth token by OAuth authorization code (received on buyer authentication)
     *
     * @throws OneGoAPI_OAuthException
     * @param string $authorizationCode
     * @param array $requestedScopes
     * @return OneGoAPI_Impl_OAuthToken
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
        } catch (OneGoAPI_OAuthException $e) {
            OneGoUtils::log('Issuing OAuth token failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
        return $token;        
    }

    /**
     * Request OAuth access token by Virtual Gift Card number
     *
     * @throws OneGoAPI_OAuthException
     * @param string $cardNumber
     * @return OneGoAPI_Impl_OAuthToken
     */
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
    
    public function getPrepaidReceivableForLastOrder()
    {
        $lastOrder = $this->getCompletedOrder();
        if (!empty($lastOrder)) {
            if ($lastOrder->get('transactionState')->get('transaction')) {
                $transaction = $lastOrder->get('transactionState')->get('transaction');
                $vgcRemainder = $transaction->getVirtualGiftCard() ?
                        (float) $transaction->getVirtualGiftCard()->remaining : 0;
                $prepaidReceivable = (float) $transaction->getPrepaidAmountReceived() - $vgcRemainder;
                $receivable = array(
                    'vgcRemainder'  => round($vgcRemainder, 2),
                    'prepaid'       => round($prepaidReceivable, 2),
                );
                return $receivable;
            } else {
                $cart = $lastOrder->get('cart') ? $lastOrder->get('cart') : array();
                try {
                    $prepaidReceivable = $this->getApi()
                            ->getAnonymousAwards($this->collectCartEntries($cart))
                            ->getPrepaidReceived();
                    $awards = !empty($prepaidReceivable) ? $prepaidReceivable->getAmount()->visible : null;
                } catch (OneGoAPI_Exception $e) {
                    OneGoUtils::logCritical('Failed retrieving anonymous awards', $e);
                    throw $e;
                }
                $receivable = array(
                    'vgcRemainder'  => 0,
                    'prepaid'       => round((float) $awards, 2),
                );
                return $receivable;
            }
        }
        return false;
    }

    /**
     * @throws OneGoAPI_Exception|OneGoAPI_VirtualGiftCardNotFoundException|OneGoVirtualGiftCardNumberInvalidException
     * @param string $cardNumber
     * @return bool Success
     */
    public function redeemVirtualGiftCard($cardNumber)
    {
        $transaction = $this->getTransaction();
        try {
            $transaction->redeemVirtualGiftCard($cardNumber);
            OneGoUtils::log('VGC redeemed for '.$cardNumber, OneGoUtils::LOG_NOTICE);
            $this->saveTransaction($transaction);
            OneGoTransactionState::getCurrent()->set(OneGoTransactionState::VGC_REDEEMED, $cardNumber);
            return true;
        } catch (OneGoAPI_VirtualGiftCardNotFoundException $e) {
            OneGoUtils::log('Gift card number invalid', OneGoUtils::LOG_ERROR);
            throw new OneGoVirtualGiftCardNumberInvalidException($e->getMessage());
        } catch (OneGoAPI_Exception $e) {
            OneGoUtils::log('redeemVirtualGiftCard failed: '.$e->getMessage(), OneGoUtils::LOG_ERROR);
            throw $e;
        }
    }

    /**
     * Redemm VGC for anonymous buyer
     *
     * @throws Exception|OneGoAPI_OAuthInvalidGrantException|OneGoVirtualGiftCardNumberInvalidException
     * @param $cardNumber
     * @return void
     */
    public function redeemAnonymousVirtualGiftCard($cardNumber)
    {
        try {
            $token = $this->requestOAuthAccessTokenByVGC($cardNumber);
            $this->beginTransaction($token);
            $this->redeemVirtualGiftCard($cardNumber);
        } catch (OneGoAPI_OAuthInvalidGrantException $e) {
            throw new OneGoVirtualGiftCardNumberInvalidException($e->getMessage());
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
        if ($state->get('redeemedVGC')) {
            try {
                $this->redeemVirtualGiftCard($state->get('redeemedVGC'));
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
}