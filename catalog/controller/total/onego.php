<?php
class ControllerTotalOnego extends Controller {

    public function index()
    {
        // not used, OneGo panel included separately for Opencart v1.5.2 compatibility
    }

    public function header()
    {   
        $onego = $this->getModel();
        
        $this->data['theme'] = $this->config->get('config_template');
        $clientId = OneGoConfig::get('clientId');
        $terminalId = OneGoConfig::get('terminalId');
        $this->data['onego_jssdk_url'] = OneGoConfig::get('jsSdkURI')."?apikey={$clientId}";
        
        $html = '';
        
        // autologin attempts are blocked
        if ($onego->autologinBlockedUntil()) {
            $autologinBlockedFor = ($onego->autologinBlockedUntil() - time()) * 1000;
            $html .= "OneGoOpencart.blockAutologin({$autologinBlockedFor});\n";
        }
        
        // widget plugin
        $topOffset = (int) OneGoConfig::get('widgetTopOffset');
        $isFrozen = (OneGoConfig::get('widgetFrozen') == 'Y') ? 'true' : 'false';
        $html .= <<<END
var OneGoWidget = OneGo.plugins.slideInWidget.init({
    topOffset: {$topOffset}, 
    isFixed: {$isFrozen},
    //handleImage: 'catalog/view/theme/{$this->config->get('config_template')}/image/onego_handle.png',
    showOnFirstView: true
});

END;
        
        // OneGo events listeners
        $isAjaxCall = !empty($this->request->request['route']) && 
                ($this->request->request['route'] == 'checkout/checkout');
        
        $signedInHandler = OneGoUtils::getJsEventHandler('UserIsSignedIn');
        if ($signedInHandler) {
            $html .= "OneGo.events.on('UserIsSignedIn', {$signedInHandler});\n";
        }

        $signedOutHandler = OneGoUtils::getJsEventHandler('UserIsSignedOut');
        if ($signedOutHandler) {
            $html .= "OneGo.events.on('UserIsSignedOut', {$signedOutHandler});\n";
        }

        if ($onego->isUserAuthenticated() && !$signedOutHandler) {
            // listen for logoff event
            $html .= $isAjaxCall ? 
                "OneGo.events.on('UserIsSignedOut', OneGoOpencart.processLogoffDynamic);\n" :
                "OneGo.events.on('UserIsSignedOut', OneGoOpencart.processLogoff);\n";
        } else if (!$onego->isUserAuthenticated() && !$signedInHandler) {
            $html .= $isAjaxCall ? 
                "OneGo.events.on('UserIsSignedIn', OneGoOpencart.processLoginDynamic);\n" :
                "OneGo.events.on('UserIsSignedIn', OneGoOpencart.processAutoLogin);\n";
        }

        // transaction autorefresh
        if (OneGoConfig::get('transactionRefreshIn') && $onego->isTransactionStarted()) {
            $firstTimeout = $onego->getTransaction()->getExpiresIn() - OneGoConfig::get('transactionRefreshIn');
            if ($firstTimeout < 0) {
                $firstTimeout = 0;
            }
            $nextTimeout = $onego->getTransaction()->getTtl() - OneGoConfig::get('transactionRefreshIn');
            $html .= 'OneGoOpencart.setTransactionAutorefresh('.($firstTimeout*1000).', '.($nextTimeout*1000).');'."\n";
        }

        // include optional code for different OC versions compatibility
        $html .= $this->compatibilitySettingsJS();

        $initParams = array();
        
        $this->data['initParamsStr'] = implode(",\n", $initParams);
        $this->data['html'] = $html;
        
        $this->data['debuggingCode'] = $this->getDebugModeHeaderHTML();
        
        $this->template = 'default/template/common/onego_header.tpl';
        $this->response->setOutput($this->render());
    }

    private function compatibilitySettingsJS()
    {
        $html = '';
        if (OneGoUtils::compareVersion('1.5.2') >= 0) {
            $html .= "OneGoOpencart.config.compatibility['checkout/confirm'].dataType = 'HTML';\n";
        }
        return $html;
    }

    private function getDebugModeHeaderHTML()
    {
        $html = '';
        if (OneGoConfig::get('debugModeOn')) {
            $onego = $this->getModel();
            $html .= '<script type="text/javascript">';
            $html .= 'if (console && console.dir) { '."\r\n";
            $log = OneGoUtils::getLog(true);
            if (!empty($log)) {
                foreach ($log as $row) {
                    $msg = 'OneGo: '.$row['message'];
                    $msg = OneGoUtils::escapeJsString($msg);
                    list($usec, $sec) = explode(" ", $row['time']);
                    if (!empty($sec)) {
                        $msg .= ' ['.date('H:i:s', $sec).']';
                    }
                    if ($row['level'] == OneGoUtils::LOG_ERROR) {
                        $msg .= ' :: '.$row['pid'].' / '.$row['backtrace'];
                    }
                    switch ($row['level']) {
                        case OneGoUtils::LOG_INFO:
                            $html .= 'console.log(\''.$msg.'\');';
                            break;
                        case OneGoUtils::LOG_NOTICE:
                            $html .= 'console.info(\''.$msg.'\');';
                            break;
                        case OneGoUtils::LOG_WARNING:
                            $html .= 'console.warn(\''.$msg.'\');';
                            break;
                        default:
                            $html .= 'console.error(\''.$msg.'\');';
                    }
                    $html .= "\r\n";
                }
            }
            $html .= 'var transactionState = {\'transactionState\' : $.parseJSON('.json_encode(json_encode(OneGoTransactionState::getCurrent()->toArray())).')};'."\r\n";
            $html .= 'console.dir(transactionState);'."\r\n";
            $html .= 'var tokenState = {\'oauthTokenState\' : $.parseJSON('.json_encode(json_encode(OneGoOAuthTokenState::getCurrent()->toArray())).')};'."\r\n";
            $html .= 'console.dir(tokenState);'."\r\n";
            if ($transaction = $onego->getTransaction()) {
                $html .= 'var transaction = {\'transaction\' : $.parseJSON('.json_encode(json_encode($transaction->getTransactionDto())).')};'."\r\n";
                $html .= 'console.dir(transaction);'."\r\n";
                $html .= 'var transactionTtl = {\'expires\' : $.parseJSON('.json_encode(json_encode(date('Y-m-d H:i:s', time() + $transaction->getExpiresIn()))).')};'."\r\n";
                $html .= 'console.dir(transactionTtl);'."\r\n";
                $html .= 'var cart = {\'modifiedCart\' : $.parseJSON('.json_encode(json_encode($transaction->getModifiedCart())).')};'."\r\n";
                $html .= 'console.dir(cart);'."\r\n";
            }
            $html .= 'var orderState = {\'CompletedOrderState\' : $.parseJSON('.json_encode(json_encode(OneGoCompletedOrderState::getCurrent()->toArray())).')};'."\r\n";
            $html .= 'console.dir(orderState);'."\r\n";
            $html .= 'var cartHash = {\'modifiedCartHash\' : $.parseJSON('.json_encode(json_encode($onego->getModifiedCartHash())).')};'."\r\n";
            $html .= 'console.dir(cartHash);'."\r\n";
            $html .= '}</script>'."\r\n";
        }
        return $html;
    }
    
    public function panel()
    {
        $this->language->load('total/onego');
        $onego = $this->getModel();

        try {
            // refresh or start transaction and token
            $onego->refreshTransaction();
        } catch (OneGoAPICallFailedException $e) {
            $this->data['onego_warning'] = $this->language->get('error_api_call_failed');
        } catch (Exception $e) {
            // ignore
        }

        $this->data['onego_use_funds_url'] = $this->url->link('total/onego/useFunds');
        $this->data['onego_scope_sufficient'] = $onego->isCurrentScopeSufficient();
        $this->data['onego_login_invitation'] = $this->language->get('invite_to_login');
        $this->data['onego_rc_invitation'] = $this->language->get('invite_to_use_rc');
        $this->data['onego_button_redeem'] = $this->language->get('button_redeem');
        $this->data['onego_prepaid_spent'] = $onego->hasSpentPrepaid();
        $this->data['onego_or'] = $this->language->get('or');
        $this->data['onego_user_authenticated'] = $onego->isUserAuthenticated();
        $this->data['onego_transaction_started'] = $onego->isTransactionStarted();
        $this->data['onego_rc_text'] = $this->language->get('rc_funds_redeemed');
        $this->data['onego_redeemed_rc_amount'] = $onego->getRCUsedNominal() ?
                $this->currency->format($onego->getRCUsedNominal()) : false;
        if ($onego->isUserAuthenticated()) {
            $this->data['onego_action'] = $this->url->link('checkout/confirm');
            $this->data['onego_disable'] = $this->url->link('total/onego/cancel');
            $this->data['authWidgetText'] = $this->language->get('auth_widget_text');
            $this->data['authWidgetTextLoading'] = $this->language->get('auth_widget_text_loading');

            if ($this->data['onego_applied'] = $onego->isTransactionStarted()) {
                $this->data['onego_funds'] = $onego->getPrepaidAvailable();
                $this->data['use_funds'] = $this->language->get('use_funds');
                $this->data['no_funds_available'] = $this->language->get('no_funds_available');                    
            }
        } else {
            $this->data['onego_login_url'] = $this->url->link('total/onego/login');
        }
        $this->data['onego_redeem_failed'] = $this->language->get('error_redeem_failed');
        $this->data['onego_login_button'] = $this->language->get('button_onego_login');
        $this->data['onego_agreed'] = $onego->hasAgreedToDiscloseEmail();
        $this->data['onego_agree_email_expose'] = $this->language->get('agree_email_expose');
        $this->data['onego_error_spend_prepaid'] = $this->language->get('error_api_call_failed');
        $this->data['isAjaxRequest'] = OneGoUtils::isAjaxRequest();
        $this->data['onego_success'] = $onego->pullFlashMessage('success');
        $this->data['lang'] = $this->language;
        
        $isCheckoutPage = OneGoUtils::isAjaxRequest();
        $this->data['onego_is_checkout_page'] = $isCheckoutPage;
        if ($isCheckoutPage && OneGoConfig::get('transactionRefreshIn')) {
            // override transaction autorefresh
            if ($onego->isTransactionStarted()) {
                $firstTimeout = $onego->getTransaction()->getExpiresIn() - OneGoConfig::get('transactionRefreshIn');
                if ($firstTimeout < 0) {
                    $firstTimeout = 0;
                }
                $nextTimeout = $onego->getTransaction()->getTtl() - OneGoConfig::get('transactionRefreshIn');
                $this->data['enable_autorefresh'] = array($firstTimeout, $nextTimeout);
            } else {
                $this->data['disable_autorefresh'] = true;
            }
        }
        $this->data['onego_modified_cart_hash'] = $onego->getModifiedCartHash();

        if (!empty($this->request->get['warn_change']) && !empty($this->request->get['cart_hash'])) {
            if ($onego->getModifiedCartHash() != $this->request->get['cart_hash']) {
                $this->data['onego_warning'] = $this->language->get('warning_cart_changed');
            }
        }
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_panel.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego_panel.tpl';
        } else {
            $this->template = 'default/template/total/onego_panel.tpl';
        }
        
        $this->response->setOutput($this->render());
    }
    
    public function success()
    {
        $onego = $this->getModel();
        $this->data['onego_claim'] = $this->url->link('total/onego/claimbenefits');
        $this->data['onego_register'] = OneGoConfig::get('anonymousRegistrationURI');
        
        $this->language->load('total/onego');
        $orderInfo = $onego->getCompletedOrder();
        if (!$orderInfo || !$orderInfo->get('orderId')) {
            $this->response->setOutput('');
            return;
        }
        
        $isAnonymous = $orderInfo->isAnonymous();
        $isDelayedTransaction = $orderInfo->get('transactionDelayed');

        $transaction = $orderInfo->get('transactionState') ? $orderInfo->get('transactionState')->get('transaction') : false;
        if ($isAnonymous) {
            try {
                // need to fetch possible cashback for anonymous buyers
                $cart = $orderInfo->get('cart');
                if ($cart) {
                    $cartEntries = $onego->collectCartEntries($cart);
                    $modifiedCart = $onego->getApi()->getAnonymousAwards($cartEntries);
                }
            } catch (OneGoAPI_Exception $e) {
                // ignore
            }
        } else if ($transaction) {
            $modifiedCart = $transaction->getModifiedCart();
        }
        if (!empty($modifiedCart)) {
            $prepaidReceived = $modifiedCart->getPrepaidReceived();
            if ($prepaidReceived) {
                if ($isAnonymous) {
                    $text = $this->language->get('onego_cashback_possible');
                } else if ($isDelayedTransaction) {
                    $text = $this->language->get('onego_cashback_delayed');
                } else {
                    $text = $this->language->get('onego_cashback_received');
                }
                $this->data['onego_prepaid_received'] = sprintf($text, $this->currency->format($prepaidReceived->getAmount()->visible));

                if (!empty($prepaidReceived->validFrom)) {
                    $this->data['onego_prepaid_received_pending'] = sprintf(
                            $this->language->get('onego_pending_till'),
                            date($this->language->get('onego_date_format'), $prepaidReceived->validFrom / 1000));
                }
            }
        }

        if ($transaction && ($rc = $transaction->getRedemptionCode())) {
            foreach (array('spent', 'remaining', 'redeemed') as $key) {
                if (!empty($rc->$key) && (float) $rc->$key) {
                    $this->data['onego_rc_funds'][$key] = sprintf(
                            $this->language->get('onego_rc_funds_'.$key),
                            $this->currency->format($rc->$key));
                }
            }
        }

        if (!$isAnonymous) {
            $this->data['onego_giftcard_balance'] = sprintf(
                    $this->language->get('onego_giftcard_balance'),
                    $this->currency->format($transaction->getPrepaidAvailable()));
        } else {
            $this->data['show_registration_invite'] = !$onego->isUserAuthenticated();
            $this->data['onego_anonymous_buyer_invitation'] = !empty($prepaidReceived) ?
                    sprintf($this->language->get('onego_registration_invitation_prepaid_received'),
                            $orderInfo->get('buyerEmail')) :
                    sprintf($this->language->get('onego_registration_invitation'),
                            $orderInfo->get('buyerEmail'));
            if (!empty($rc) && (float) $rc->remaining) {
                $this->data['onego_registration_notification'] = sprintf(
                        $this->language->get('onego_registration_notice_rc_transfer'),
                        $this->currency->format($rc->remaining)
                        );
            }
            $this->data['onego_registration_button'] = $this->language->get('onego_registration_button');

            // set buyer sign-in/sign-up listener to bind buyer account to completed order
            OneGoUtils::setJsEventHandler('UserIsSignedIn', 'OneGoOpencart.catchSignInOnAnonymousOrderSuccess');
        }
        $this->data['buyer_email'] = $orderInfo->get('buyerEmail');

        if ($isDelayedTransaction && !empty($prepaidReceived)) {
            $this->data['onego_transaction_notice'] = $this->language->get('onego_delayed_transaction_notice');
        }

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_success.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego_success.tpl';
        } else {
            $this->template = 'default/template/total/onego_success.tpl';
        }
        
        $this->response->setOutput($this->render());
    }

    /**
     * Bind session token to claim completed anonymous purchase rewards
     */
    public function bindSessionToken()
    {
        $onego = $this->getModel();
        $request = $this->registry->get('request');

        $res = array('success' => false);

        $sessionToken = !empty($request->post['sessionToken']) ? $request->post['sessionToken'] : false;

        $orderInfo = $onego->getCompletedOrder();
        if ($orderInfo && $orderInfo->get('orderId') && $orderInfo->isAnonymous() && $sessionToken) {
            try {
                $onego->bindSessionToken($sessionToken, $orderInfo);
                $res['success'] = true;
            } catch (Exception $e) {

            }
        }
        $this->response->setOutput(OneGoAPI_JSON::encode($res));
    }
    
    public function spendprepaid()
    {
        $onego = $this->getModel();
        $this->language->load('total/onego');
        $request = $this->registry->get('request');
        if (!empty($request->post['use_funds'])) {
            $response = false;
            
            try {
                $onego->refreshTransaction();
                
                if ($onego->isTransactionStarted()) {
                    $do_use = $request->post['use_funds'] == 'true';
                    if ($do_use) {
                        $response = array('status' => $onego->spendPrepaid() ? 1 : 0);
                    } else {
                        $response = array('status' => $onego->cancelSpendingPrepaid() ? 1 : 0);
                    }
                } else {
                    throw new OneGoException('Transaction not started');
                }
            } catch (OneGoAuthenticationRequiredException $e) {
                $errorMessage = $this->language->get('error_authentication_expired');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            } catch (Exception $e) {
                $errorMessage = $this->language->get('error_api_call_failed');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            }
            
            $this->response->setOutput(OneGoAPI_JSON::encode($response));
        }
    }
    
    public function autologin()
    {
        $onego = $this->getModel();
        $referer = $this->getReferer();
        
        $auth = $onego->getAuth();
        $reqScope = OneGoAPI_Impl_OneGoOAuth::SCOPE_RECEIVE_ONLY;
        
        // login not required if user is already athenticated with OneGo, return
        $token = $onego->getSavedOAuthToken();
        if ($onego->isUserAuthenticated() && $token && !$token->isExpired()) {
            OneGoUtils::log('autologin not needed, user already authenticated');
            $this->redirect($referer);
        }
        
        if ($onego->autologinBlockedUntil()) {
            OneGoUtils::log('autologin blocked after last fail');
            $this->redirect($referer);
        }
        
        // redirect to OneGo authentication page
        $requestId = uniqid();
        // remember request parameters
        OneGoUtils::saveToSession('oauth_authorize_request', array(
            'request_id'    => $requestId,
            'referer'       => $referer,
            'scope'         => $reqScope,
            'silent'        => true,
        ));
        $authorizeUrl = $auth->getAuthorizationUrl($onego->getOAuthRedirectUri(), $reqScope, $requestId, true);
        $this->redirect($authorizeUrl);
    }
    
    public function login($returnpage = false)
    {
        $onego = $this->getModel();
        $returnpage = empty($returnpage) ? $this->getReferer() : $returnpage;
        
        $auth = $onego->getAuth();
        $reqScope = array(OneGoAPI_Impl_OneGoOAuth::SCOPE_USE_BENEFITS, OneGoAPI_Impl_OneGoOAuth::SCOPE_RECEIVE_ONLY);
        
        // login not required if user is already athenticated with OneGo, return
        $token = $onego->getSavedOAuthToken();
        if ($onego->isUserAuthenticated() && $onego->userHasScope($reqScope) && $token && !$token->isExpired()) {
            OneGoUtils::log('login not needed, user already authenticated and has required scope');
            $this->redirect($returnpage);
        }
        
        // redirect to OneGo authentication page
        $requestId = uniqid();
        OneGoUtils::saveToSession('oauth_authorize_request', array(
            'request_id'    => $requestId,
            'referer'       => $returnpage,
            'scope'         => $reqScope,
            'silent'        => false,
        ));
        $authorizeUrl = $auth->getAuthorizationUrl($onego->getOAuthRedirectUri(), $reqScope, $requestId);
        $this->redirect($authorizeUrl);
    }
    
    public function authorizationResponse()
    {
        $this->language->load('total/onego');
        $onego = $this->getModel();
        $request = $this->registry->get('request');
        $auth_request = OneGoUtils::getFromSession('oauth_authorize_request');
        $autologinBlockTtl = 30;
        $errorMessage = false;
        OneGoUtils::saveToSession('authorizationSuccess', false);
        try {
            $this->processAuthorizationResponse($request->get, $auth_request);
            OneGoUtils::saveToSession('authorizationSuccess', true);
        } catch (OneGoAPI_OAuthAccessDeniedException $e) {
            $errorMessage = $this->language->get('error_authorization_access_denied');
        } catch (OneGoAPI_OAuthTemporarilyUnavailableException $e) {
            $errorMessage = $this->language->get('error_authorization_temporarily_unavailable');
            $onego->blockAutologin($autologinBlockTtl);
        } catch (Exception $e) {
            $errorMessage = $this->language->get('error_authorization_failed');
            $onego->blockAutologin($autologinBlockTtl);
            OneGoUtils::logCritical('authorization failed', $e);
        }
        
        if (!empty($errorMessage) && empty($auth_request['silent'])) {
            $this->setGlobalErrorMessage($errorMessage);
        }
        
        $referer = !empty($auth_request['referer']) ? $auth_request['referer'] : $this->getDefaultReferer();
        $this->redirect($referer);
    }
    
    public function logindialog()
    {
        $status_page = $this->url->link('total/onego/authStatus');
        
        $this->login($status_page);
    }
    
    public function authStatus()
    {
        $onego = $this->getModel();
        $authorizationSuccessful = OneGoUtils::getFromSession('authorizationSuccess');
        $this->data['onego_authenticated'] = $authorizationSuccessful;
        $this->data['onego_error'] = $authorizationSuccessful ? false : $this->takeoverGlobalErrorMessage();
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_auth_status.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego_auth_status.tpl';
        } else {
            $this->template = 'default/template/total/onego_auth_status.tpl';
        }
        $this->response->setOutput($this->render());
    }
    
    public function cancel()
    {
        $this->language->load('total/onego');
        $onego = $this->getModel();
        
        $onego->cancelTransaction(true);
        $onego->deleteOAuthToken();
        
        if (!OneGoUtils::isAjaxRequest()) {
            $this->redirect($this->getReferer());
        }
    }
    
    public function update()
    {
        $onego = $this->getModel();
        $referer = $this->getReferer();
        
        try {
            $onego->updateTransactionCart();
        } catch (Exception $e) {
            
        }
        
        $onego->deleteAnonymousModifiedCart();
        
        $this->redirect($referer);
    }
    
    public function useredeemcode()
    {
        $onego = $this->getModel();
        $this->language->load('total/onego');
        $request = $this->registry->get('request');
        $success = false;
        if (!empty($request->post['code'])) {
            $response = false;
            
            try {
                $redemptionCode = preg_replace('/[^a-z0-9]/i', '', $request->post['code']);
                if ($onego->isTransactionStarted()) {
                    $transaction = $onego->refreshTransaction();
                    
                    if ($onego->useRedemptionCode($redemptionCode)) {
                        $success = true;
                    }
                } else {
                    $onego->useRedemptionCodeAnonymously($redemptionCode);
                    $success = true;
                }
                
            } catch (OneGoAuthenticationRequiredException $e) {
                $errorMessage = $this->language->get('error_authentication_expired');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            } catch (OneGoRedemptionCodeInvalidException $e) {
                $errorMessage = $this->language->get('error_redeem_code_invalid');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            } catch (Exception $e) {
                $errorMessage = $this->language->get('error_redeem_failed');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            }
            if ($success) {
                $response = array('success' => true);
                if (!empty($request->post['setFlashMessage'])) {
                    $amount = $onego->getRCUsedNominal();
                    $msg = sprintf($this->language->get('rc_redeemed'), $this->currency->format($amount));
                    $onego->setFlashMessage('success', $msg);
                }
            }
            
            $this->response->setOutput(OneGoAPI_JSON::encode($response));
        }
    }

    public function refreshtransaction()
    {
        $onego = $this->getModel();
        $response = array('success' => true);
        if ($onego->isTransactionStarted()) {
            $hashBefore = $onego->getModifiedCartHash();
            try {
                $onego->refreshTransaction(true);
                if ($hashBefore != $onego->getModifiedCartHash()) {
                    $response['error'] = 'Failed to silently restart transaction';
                    unset($response['success']);
                }
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
                unset($response['success']);
            }
        }
        $this->response->setOutput(OneGoAPI_JSON::encode($response));
    }
    
    
    // === service methods
    /**
     *
     * @return ModelTotalOnego
     */
    private function getModel()
    {
        if (empty($this->model_total_onego)) {
            $this->load->model('total/onego');
        }
        return $this->model_total_onego;
    }
    
    private function getReferer()
    {
        $onego = $this->getModel();
        return OneGoUtils::getHttpReferer() ? OneGoUtils::getHttpReferer() : $this->getDefaultReferer();
    }
    
    private function getDefaultReferer()
    {
        return $this->url->link('checkout/cart');
    }
    
    private function setGlobalErrorMessage($error)
    {
        $this->session->data['error'] = $error;
    }
    
    private function takeoverGlobalErrorMessage()
    {
        if (!isset($this->session->data['error'])) {
            return false;
        }
        $message = $this->session->data['error'];
        unset($this->session->data['error']);
        return $message;
    }
    
    private function processAuthorizationResponse($response_params, $authorization_request)
    {
        $onego = $this->getModel();
        if (!empty($response_params['code'])) {
            // issue token
            $requestedScopes = !empty($authorization_request['scope']) ? $authorization_request['scope'] : false;
            $onego->requestOAuthAccessToken($response_params['code'], $requestedScopes);
            return true;
        } else {
            // transform response to OneGoAPI_OAuthException
            $error_code = !empty($response_params['error']) ? 
                $response_params['error'] : '';
            $error_message = !empty($response_params['error_description']) ? 
                $response_params['error_description'] : '';
            $errorDto = OneGoAPI_Impl_Transform::transform('OneGoAPI_DTO_OAuthErrorDto', (object) $response_params);
            throw OneGoAPI_Exception::fromError($errorDto);
        }
    }
}