<?php
class ControllerTotalOnego extends Controller {

    public function index() 
    {
        $this->language->load('total/onego');
        
        $this->data['heading_title'] = $this->language->get('heading_title');
        
        $onego = $this->getModel();
        
        try {
            $onego->refreshTransaction();
        } catch (OneGoAuthenticationRequiredException $e) {
            // ignore
        } catch (OneGoAPICallFailedException $e) {
            // ignore
        }
        
        $this->data['onego_panel'] = $this->getChild('total/onego/panel');
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego.tpl';
        } else {
            $this->template = 'default/template/total/onego.tpl';
        }
        
        $this->response->setOutput($this->render());
    }
    
    public function header()
    {   
        $onego = $this->getModel();
        
        $this->data['theme'] = $this->config->get('config_template');
        $this->data['onego_jssdk_url'] = "http://plugins-local.dev.onego.com/scripts/webapp/v0.1/{$onego->getConfig('clientId')}/{$onego->getConfig('terminalId')}/main.js";
        
        $html = '';
        
        // autologin attempts are blocked
        if ($onego->autologinBlockedUntil()) {
            $autologinBlockedFor = ($onego->autologinBlockedUntil() - time()) * 1000;
            $html .= "OneGoOpencart.blockAutologin({$autologinBlockedFor});\n";
        }
        
        // widget plugin
        if ($onego->getConfig('widgetShow') == 'Y') {
            $topOffset = (int) $onego->getConfig('widgetTopOffset');
            $isFrozen = ($onego->getConfig('widgetFrozen') == 'Y') ? 'true' : 'false';
            $html .= <<<END
var OneGoWidget = OneGo.plugins.slideInWidget.init({
    topOffset: {$topOffset}, 
    isFixed: {$isFrozen},
    handleImage: '/catalog/view/theme/{$this->config->get('config_template')}/image/onego_handle.png'
});

END;
        }
        
        // OneGo events listeners
        $isAjaxCall = !empty($this->request->request['route']) && 
                ($this->request->request['route'] == 'checkout/checkout');

        if ($onego->isUserAuthenticated()) {
            // listen for logoff event
            $html .= $isAjaxCall ? 
                "OneGo.events.on('UserIsSignedOut', OneGoOpencart.processLogoffDynamic);\n" :
                "OneGo.events.on('UserIsSignedOut', OneGoOpencart.processLogoff);\n";
        } else {
            if ($onego->getConfig('autologinOn')) {
                $html .= $isAjaxCall ? 
                    "OneGo.events.on('UserIsSignedIn', OneGoOpencart.processLoginDynamic);\n" :
                    "OneGo.events.on('UserIsSignedIn', OneGoOpencart.processAutoLogin);\n";
            }
        }
        
        $initParams = array();
        
        // enable debugging
        if ($onego->getConfig('debugModeOn')) {
            //$initParams[] = "debug: true";
        }
        
        $this->data['initParamsStr'] = implode(",\n", $initParams);
        $this->data['html'] = $html;
        
        
        // logging output
        $log = OneGoUtils::getLog(true);
        $html = '';
        if ($onego->getConfig('debugModeOn')) {
            $html .= '<script type="text/javascript">';
            $html .= 'if (console && console.dir) { '."\r\n";
            if (!empty($log)) {

                foreach ($log as $row) {
                    $msg = 'OneGo: '.$row['message'];
                    $msg = preg_replace('/[\r\n]+/', ' ', $msg);
                    $msg = preg_replace('/\'/', '\\\'', $msg);
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
                $html .= 'var transactionTtl = {\'expires\' : $.parseJSON('.json_encode(json_encode(date('Y-m-d H:i:s', time() + $transaction->getTtl()))).')};'."\r\n";
                $html .= 'console.dir(transactionTtl);'."\r\n";
            }
            $html .= 'var orderState = {\'CompletedOrderState\' : $.parseJSON('.json_encode(json_encode(OneGoCompletedOrderState::getCurrent()->toArray())).')};'."\r\n";
            $html .= 'console.dir(orderState);'."\r\n";
            $html .= '}</script>'."\r\n";
        }
        $this->data['debuggingCode'] = $html;
        
        $this->template = 'default/template/common/onego_header.tpl';
        $this->response->setOutput($this->render());
    }
    
    public function panel()
    {
        $this->language->load('total/onego');
        $onego = $this->getModel();
        $this->data['onego_use_funds_url'] = $this->url->link('total/onego/useFunds');
        $this->data['onego_scope_sufficient'] = $onego->isCurrentScopeSufficient();
        $this->data['onego_login_invitation'] = $this->language->get('invite_to_login');
        $this->data['onego_vgc_invitation'] = $this->language->get('invite_to_use_vgc');
        $this->data['onego_button_redeem'] = $this->language->get('button_redeem');
        $this->data['onego_vgc_number'] = $this->language->get('vgc_number');
        $this->data['onego_prepaid_spent'] = $onego->hasSpentPrepaid();
        $this->data['onego_or'] = $this->language->get('or');
        $this->data['onego_user_authenticated'] = $onego->isUserAuthenticated();
        $this->data['onego_transaction_started'] = $onego->isTransactionStarted();
        $this->data['onego_vgc_text'] = $this->language->get('vgc_funds_redeemed');
        $this->data['onego_redeemed_vgc_amount'] = $onego->getPrepaidRedeemedAmount() ?
                $this->currency->format($onego->getPrepaidRedeemedAmount()) : false;
        if ($onego->isUserAuthenticated()) {
            $this->data['onego_action'] = $this->url->link('checkout/confirm');
            $this->data['onego_disable'] = $this->url->link('total/onego/cancel');
            $this->data['authWidgetText'] = $this->language->get('auth_widget_text');
            $this->data['authWidgetTextLoading'] = $this->language->get('auth_widget_text_loading');

            if ($this->data['onego_applied'] = $onego->isTransactionStarted()) {
                $this->data['onego_funds'] = $onego->getFundsAvailable();
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
        $this->data['js_page_reload_callback'] = OneGoUtils::isAjaxRequest() ?
                'OneGoOpencart.reloadCheckoutOrderInfo' : 'OneGoOpencart.reloadPage';
        
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
        $this->data['onego_register'] = $onego->getConfig('anonymousRegistrationURI');
        
        $this->language->load('total/onego');
        $orderInfo = $onego->getCompletedOrder();
        
        $this->data['onego_funds_received'] = $orderInfo->get('prepaidReceived') ?
                sprintf($this->language->get('funds_received'), $this->currency->format($orderInfo->get('prepaidReceived'))) 
                : false;
        $this->data['onego_suggest_disclose'] = $this->language->get('suggest_disclose');
        $this->data['onego_button_agree'] = $this->language->get('button_agree_disclose');
        $this->data['onego_claim_benefits'] = $this->language->get('title_claim_your_benefits');
        $this->data['onego_buyer_created'] = 
                sprintf($this->language->get('anonymous_buyer_created'), $onego->getConfig('anonymousRegistrationURI'));
        $this->data['onego_button_register'] = $this->language->get('button_register_anonymous');
        
        if ($onego->isAnonymousRewardsApplied()) {
            $this->data['onego_benefits_applied'] = true;
        } else if ($onego->isAnonymousRewardsApplyable()) {
            $this->data['onego_benefits_applyable'] = true;
            
            // get reward amount
            try {
                $receivable = $onego->getAnonymousPrepaidReceivableForLastOrder();
            } catch (OneGoAPI_Exception $e) {
                // TODO error handling
                $receivable = false;
            }
            $this->data['onego_funds_receivable'] = $receivable ? 
                    sprintf($this->language->get('funds_receivable_descr'), $this->currency->format($receivable)) : false;
        }
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_success.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego_success.tpl';
        } else {
            $this->template = 'default/template/total/onego_success.tpl';
        }
        
        $this->response->setOutput($this->render());
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
    
    public function loginDialog()
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
    
    public function agree()
    {
        $onego = $this->getModel();
        $agreed = (bool) !empty($this->request->post['agree']);
        OneGoTransactionState::getCurrent()->set(OneGoTransactionState::AGREED_DISCLOSE_EMAIL, $agreed);
    }
    
    public function claimbenefits()
    {
        $this->language->load('total/onego');
        $onego = $this->getModel();
        $lastOrder = OneGoCompletedOrderState::getCurrent();
        if ($lastOrder->get('benefitsApplied')) {
            $this->redirect($this->url->link('checkout/success'));
        }
        
        if (!$lastOrder->get('newBuyerRegistered')) {
            try {
                $fundsReceived = $onego->bindEmailForOrder($lastOrder);
            } catch (OneGoAPI_InvalidInputException $e) {
                $this->data['onego_error'] = $this->language->get('error_bindnew_invalid_email');
            } catch (Exception $e) {
                $this->data['onego_error'] = $this->language->get('error_bindnew_failed');
                $this->data['onego_error'] = $e->getMessage();
                $this->data['show_try_again'] = true;
            }
        } else {
            $fundsReceived = $lastOrder->get('prepaidReceived');
        }
        $this->data['onego_rewarded'] = !empty($fundsReceived) ?
                sprintf($this->language->get('anonymous_rewarded'), $this->currency->format($fundsReceived))
                : false;
        $this->data['onego_anonymous_buyer_created'] = 
                sprintf($this->language->get('anonymous_buyer_created'), $onego->getConfig('anonymousRegistrationURI'));
        $this->data['onego_button_register'] = $this->language->get('button_register_anonymous');
        $this->data['onego_registration_uri'] = $onego->getConfig('anonymousRegistrationURI');
        
        // rest of page output
        $this->data['onego_claim_benefits'] = $this->language->get('title_claim_your_benefits');
        $this->data['onego_button_try_again'] = $this->language->get('button_try_again');
        $this->data['link_reload'] = $this->url->link('total/onego/claimbenefits');
        $this->data['onego_benefits_claimed'] = $this->language->get('title_benefits_claimed');
        
        $this->language->load('checkout/success');

        $this->document->setTitle($this->language->get('title_claim_your_benefits'));

        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'href' => $this->url->link('common/home'),
            'text' => $this->language->get('text_home'),
            'separator' => false
        );

        $this->data['breadcrumbs'][] = array(
            'href' => $this->url->link('checkout/cart'),
            'text' => $this->language->get('text_basket'),
            'separator' => $this->language->get('text_separator')
        );

        $this->data['breadcrumbs'][] = array(
            'href' => $this->url->link('checkout/checkout', '', 'SSL'),
            'text' => $this->language->get('text_checkout'),
            'separator' => $this->language->get('text_separator')
        );

        $this->data['breadcrumbs'][] = array(
            'href' => $this->url->link('total/onego/claimbenefits'),
            'text' => $this->language->get('title_claim_your_benefits'),
            'separator' => $this->language->get('text_separator')
        );

        $this->data['heading_title'] = $this->language->get('heading_title');

        if ($this->customer->isLogged()) {
            $this->data['text_message'] = sprintf($this->language->get('text_customer'), $this->url->link('account/account', '', 'SSL'), $this->url->link('account/order', '', 'SSL'), $this->url->link('account/download', '', 'SSL'), $this->url->link('information/contact'));
        } else {
            $this->data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
        }

        $this->data['button_continue'] = $this->language->get('button_continue');

        $this->data['continue'] = $this->url->link('common/home');
        
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_claimed.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego_claimed.tpl';
        } else {
            $this->template = 'default/template/total/onego_claimed.tpl';
        }

        $this->children = array(
            'common/column_left',
            'common/column_right',
            'common/content_top',
            'common/content_bottom',
            'common/footer',
            'common/header'
        );

        $this->response->setOutput($this->render());
    }
    
    public function redeemgiftcard()
    {
        $onego = $this->getModel();
        $this->language->load('total/onego');
        $request = $this->registry->get('request');
        if (!empty($request->post['cardnumber'])) {
            $response = false;
            
            try {
                if ($onego->isTransactionStarted()) {
                    $transaction = $onego->refreshTransaction();
                    
                    $res = $onego->redeemVirtualGiftCard($request->post['cardnumber']);
                    if ($res) {
                        $response = array('success' => true);
                    }
                } else {
                    $onego->redeemAnonymousVirtualGiftCard($request->post['cardnumber']);
                    
                    $response = array('success' => true);
                }
                
            } catch (OneGoAuthenticationRequiredException $e) {
                $errorMessage = $this->language->get('error_authentication_expired');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            } catch (OneGoVirtualGiftCardNumberInvalidException $e) {
                $errorMessage = $this->language->get('error_redeem_cardnumber_invalid');
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
            
            $this->response->setOutput(OneGoAPI_JSON::encode($response));
        }
    }
    
    
    // === service methods
    /**
     *
     * @return ModelTotalOnego
     */
    protected function getModel()
    {
        if (empty($this->model_total_onego)) {
            $this->load->model('total/onego');
        }
        return $this->model_total_onego;
    }
    
    protected function getReferer()
    {
        $onego = $this->getModel();
        return OneGoUtils::getHttpReferer() ? OneGoUtils::getHttpReferer() : $this->getDefaultReferer();
    }
    
    protected function getDefaultReferer()
    {
        return $this->url->link('checkout/cart');
    }
    
    protected function setGlobalErrorMessage($error)
    {
        $this->session->data['error'] = $error;
    }
    
    protected function takeoverGlobalErrorMessage()
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