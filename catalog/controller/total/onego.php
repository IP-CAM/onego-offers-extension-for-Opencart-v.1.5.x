<?php

class ControllerTotalOnego extends Controller {

    public function index() {
        $this->language->load('total/onego');
        
        $this->data['heading_title'] = $this->language->get('heading_title');
        if (isset($this->session->data['onego'])) {
            $this->data['onego'] = $this->session->data['onego'];
        } else {
            $this->data['onego'] = '';
        }

        $onego = $this->getModel();
        if (!$onego->isTransactionStarted() && ($token = $onego->getOAuthToken())) {
            try {
                $onego->beginTransaction($token->accessToken);
            } catch (Exception $e) {
                // dissmiss failure
            }
        }
        
        if ($onego->isTransactionStarted()) {
            $this->data['transaction'] = $onego->getTransaction();
            $this->data['cart_products'] = $this->cart->getProducts();
            $this->data['button_disable'] = $this->language->get('button_disable');
            $this->data['onego_disable'] = $this->url->link('total/onego/disable');
            $this->data['button_update'] = $this->language->get('button_update');
            $this->data['onego_update'] = $this->url->link('total/onego/updatebenefits');
            
            $this->data['funds'] = $onego->getFundsAvailable();
            $this->data['use_funds'] = $this->language->get('use_funds');
            $this->data['no_funds_available'] = $this->language->get('no_funds_available');
            $this->data['funds_action'] = $this->url->link('checkout/cart');
            $this->data['onego_buyer'] = $onego->getBuyerName();
            
            if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_account.tpl')) {
                $this->template = $this->config->get('config_template') . '/template/total/onego_account.tpl';
            } else {
                $this->template = 'default/template/total/onego_account.tpl';
            }
        } else {
            $this->data['button_onego_login'] = $this->language->get('button_onego_login');
            $this->data['onego_login'] = $this->url->link('total/onego/issuetoken');
            
            if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_account.tpl')) {
                $this->template = $this->config->get('config_template') . '/template/total/onego.tpl';
            } else {
                $this->template = 'default/template/total/onego.tpl';
            }
        }

        $this->response->setOutput($this->render());
    }
    
    public function issuetoken()
    {
        $onego = $this->getModel();
        $referer = $this->getReferer();
        
        // login not required if user is already athenticated with OneGo, return
        if ($onego->isTransactionStarted()) {
            $onego->log('issuetoken impossible, transaction is already started', ModelTotalOnego::LOG_NOTICE);
            $this->redirect($referer);
        }
        
        // redirect to OneGo authentication page
        $api = $onego->getApi();
        try {
            $onego->log('issueEshopToken call', ModelTotalOnego::LOG_NOTICE);
            $res = $api->issueEshopToken('saulius@megarage.com', $this->url->link('total/onego/verifytoken'));
        } catch (Exception $e) {
            $onego->throwError('failed OneGo authentication: '.$e->getMessage());
        }
        
        // TODO: error response handling
        if (!empty($res->token) && !empty($res->authUrl)) {
            $onego->saveToSession('referer', $referer);
            $onego->saveToSession('eshop_token', $res->token);
            $this->redirect($onego->fixAuthUrl($res->authUrl));
        } else {
            $onego->throwError('could not issue e-shop token');
        }
    }
    
    public function autologin()
    {
        $onego = $this->getModel();
        $referer = $this->getReferer();
        
        // login not required if user is already athenticated with OneGo, return
        if ($onego->isUserAuthenticated()) {
            $onego->log('autologin not needed, user already authenticated', ModelTotalOnego::LOG_NOTICE);
            $this->redirect($referer);
        }
        
        if ($onego->autologinBlockedUntil()) {
            $onego->log('autologin blocked after last fail', ModelTotalOnego::LOG_NOTICE);
            $this->redirect($referer);
        }
        
        // redirect to OneGo authentication page
        $redirect_uri = $this->url->link('total/onego/authorizationResponse');
        $request_id = uniqid();
        $onego->saveToSession('oauth_authorize_request', array(
            'request_id'    => $request_id,
            'referer'       => $referer,
            'scope'         => ModelTotalOnego::SCOPE_RECEIVE_ONLY,
        ));
        $authorize_url = $onego->getOAuthAuthorizationUrl(
            $redirect_uri, 
            ModelTotalOnego::SCOPE_RECEIVE_ONLY, 
            true,
            $request_id
        );
        $this->redirect($authorize_url);
    }
    
    public function authorizationResponse()
    {
        $onego = $this->getModel();
        $request = $this->registry->get('request');
        $auth_request = $onego->getFromSession('oauth_authorize_request');
        try {
            $onego->processAuthorizationResponse($request->get, $auth_request);
            $onego->log('authorization success', ModelTotalOnego::LOG_INFO);
        } catch (OneGoOAuthException $e) {
            switch ($e->getCode()) {
                case (OneGoOAuthException::OAUTH_ACCESS_DENIED):
                case (OneGoOAuthException::OAUTH_BAD_LOGIN_ATTEMPT):
                case (OneGoOAuthException::OAUTH_INVALID_REQUEST):
                case (OneGoOAuthException::OAUTH_INVALID_SCOPE):
                case (OneGoOAuthException::OAUTH_TEMPORARILY_UNAVAILABLE):
                case (OneGoOAuthException::OAUTH_UNAUTHORIZED_CLIENT):
                case (OneGoOAuthException::OAUTH_UNSUPPORTED_RESPONSE_TYPE):
                case (OneGoOAuthException::OAUTH_USER_ERROR):
                case (OneGoOAuthException::OAUTH_SERVER_ERROR):
                default:
                    $this->session->data['error'] = $e->getMessage();
                    $onego->blockAutologin(30);
            }
            $onego->log('authorization failed: '.$e->getMessage(), ModelTotalOnego::LOG_ERROR);
        } catch (Exception $e) {
            $this->session->data['error'] = $e->getMessage();
            $onego->blockAutologin(30);
            $onego->log('authorization failed: '.$e->getMessage(), ModelTotalOnego::LOG_ERROR);
        }
        $referer = !empty($auth_request['referer']) ? $auth_request['referer'] : $this->getDefaultReferer();
        $this->redirect($referer);
    }
    
    public function auth()
    {
        $onego = $this->getModel();
        $status_page = $this->url->link('total/onego/authStatus');
        
        // login not required if user is already athenticated with OneGo, return
        if ($onego->isTransactionStarted()) {
            $onego->log('auth not needed, transaction is already started', ModelTotalOnego::LOG_NOTICE);
            $this->redirect($status_page);
        }
        
        // redirect to OneGo authentication page
        $api = $onego->getApi();
        try {
            $onego->log('issueEshopToken call', ModelTotalOnego::LOG_NOTICE);
            $res = $api->issueEshopToken('saulius@megarage.com', $this->url->link('total/onego/verifytoken'));
        } catch (Exception $e) {
            //$onego->throwError('failed OneGo authentication: '.$e->getMessage());
            $onego->saveToRegistry('auth_error', $e->getMessage());
            $this->redirect($status_page);
        }
        
        if (!empty($res->token) && !empty($res->authUrl)) {
            $onego->saveToSession('referer', $status_page);
            $onego->saveToSession('eshop_token', $res->token);
            $this->redirect($onego->fixAuthUrl($res->authUrl));
        } else {
            $onego->saveToRegistry('auth_error', $e->getMessage());
            $this->redirect($status_page);
        }
    }
    
    public function authStatus()
    {
        $onego = $this->getModel();
        $this->data['onego_enabled'] = $onego->isTransactionStarted();
        $this->data['error'] = $onego->getFromRegistry('auth_error');
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_auth_status.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego_auth_status.tpl';
        } else {
            $this->template = 'default/template/total/onego_auth_status.tpl';
        }
        $this->response->setOutput($this->render());
    }
    
    public function verifytoken()
    {
        $this->language->load('total/onego');
        $onego = $this->getModel();
        $token = $onego->getFromSession('eshop_token');
        $referer = $onego->getFromSession('referer') ? $onego->getFromSession('referer') : $this->getDefaultReferer();
        
        if ($token != $onego->getFromSession('verified_token')) {
            // verify token
            $api = $onego->getApi();
            try {
                $onego->log('verifyEshopToken call', ModelTotalOnego::LOG_NOTICE);
                $res = $api->verifyEshopToken($token);
                if ($res) {
                    // success, start transaction
                    $onego->saveToSession('verified_token', $token);
                    try {
                        $onego->beginTransaction($token);
                        $onego->getSession()->data['success'] = $this->language->get('benefits_applied');
                    } catch (Exception $e) {
                        $onego->throwError('could not start transaction - '.$e->getMessage());
                    }
                } else {
                    $onego->throwError('eshop token could not be validated');
                }
            } catch (Exception $e) {
                $onego->throwError('failed verifying eshop token - '.$e->getMessage());
            }
            
        } else {
            $onego->log('verifyEshopToken skipped, already verified', ModelTotalOnego::LOG_NOTICE);
        }
        
        $this->redirect($referer);
    }
    
    public function disable()
    {
        $this->language->load('total/onego');
        $onego = $this->getModel();
        
        if ($onego->isTransactionStarted()) {
            if ($onego->cancelTransaction()) {
                $onego->log('transaction cancelled');
                $onego->getSession()->data['success'] = $this->language->get('benefits_disabled');
            } else {
                $onego->log('failed to cancel transaction', ModelTotalOnego::LOG_WARNING);
            }
        }
        
        if (!ModelTotalOnego::isAjaxRequest()) {
            $this->redirect($this->getReferer());
        }
    }
    
    public function updatebenefits()
    {
        $onego = $this->getModel();
        $referer = $this->getReferer();
        
        if ($onego->isTransactionStarted()) {
            $onego->updateTransactionCart();
        } else {
            $onego->log('transaction update impossible, transaction not started', ModelTotalOnego::LOG_WARNING);
        }
        
        $this->redirect($referer);
    }
    
    public function agree()
    {
        $onego = $this->getModel();
        $agreed = (bool) !empty($this->request->post['agree']);
        $onego->saveToSession('onego_agreed', $agreed);
    }
    
    public function claimBenefits()
    {
        $this->language->load('checkout/success');

        $this->document->setTitle($this->language->get('heading_title'));

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
            'href' => $this->url->link('checkout/success'),
            'text' => $this->language->get('text_success'),
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
        
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/common/onego_claimed.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/common/onego_claimed.tpl';
        } else {
            $this->template = 'default/template/common/onego_claimed.tpl';
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
        $this->template = 'default/template/total/onego_giftcard.tpl';
        $this->response->setOutput($this->render());
    }
    
    
    // === service methods
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
        return $onego->getHttpReferer() ? $onego->getHttpReferer() : $this->getDefaultReferer();
    }
    
    protected function getDefaultReferer()
    {
        return $this->url->link('checkout/cart');
    }
}