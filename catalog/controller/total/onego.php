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
        $onego->autostartTransaction();
        
        if ($onego->isUserAuthenticated()) {
            $this->data['onego_disable'] = $this->url->link('total/onego/cancel');
            $this->data['onego_update'] = $this->url->link('total/onego/updatebenefits');
            $this->data['onego_action'] = $this->url->link('checkout/cart');
            $this->data['onego_use_funds_url'] = $this->url->link('total/onego/usefunds');
            $this->data['onego_scope_extended'] = $onego->isCurrentScopeSufficient();
            
            if ($onego->isTransactionStarted()) {
                $this->data['onego_applied'] = true;
                
                $this->data['transaction'] = $onego->getTransaction();
                $this->data['cart_products'] = $this->cart->getProducts();
                $this->data['onego_funds'] = $onego->getFundsAvailable();
                $this->data['use_funds'] = $this->language->get('use_funds');
                $this->data['no_funds_available'] = $this->language->get('no_funds_available');
            } else {
                $this->data['onego_applied'] = false;
            }
            
            if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_account.tpl')) {
                $this->template = $this->config->get('config_template') . '/template/total/onego_account.tpl';
            } else {
                $this->template = 'default/template/total/onego_account.tpl';
            }
        } else {
            $this->data['button_onego_login'] = $this->language->get('button_onego_login');
            $this->data['onego_login'] = $this->url->link('total/onego/login');
            
            if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_account.tpl')) {
                $this->template = $this->config->get('config_template') . '/template/total/onego.tpl';
            } else {
                $this->template = 'default/template/total/onego.tpl';
            }
        }

        $this->response->setOutput($this->render());
    }
    
    public function usefunds()
    {
        $onego = $this->getModel();
        $request = $this->registry->get('request');
        if ($onego->isTransactionStarted() && !empty($request->post['use_funds'])) {
            $api = $onego->getApi();
            $transaction = $onego->getTransaction();
            $do_use = $request->post['use_funds'] == 'true';
            try {
                if ($do_use) {
                    $onego->log('transaction/prepaid/spend', ModelTotalOnego::LOG_NOTICE);
                    $response = array('status' => $onego->spendPrepaid() ? 1 : 0);
                } else {
                    $onego->log('transaction/prepaid/spending/cancel', ModelTotalOnego::LOG_NOTICE);
                    $response = array('status' => $onego->cancelSpendingPrepaid() ? 0 : 1);
                }
            } catch (Exception $e) {
                $response = array(
                    'error'     => $e->getCode(),
                    'message'   => $e->getMessage(),
                );
                $onego->log('funds usage call exception: '.$e->getMessage(), ModelTotalOnego::LOG_ERROR);
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
        if ($onego->isUserAuthenticated()) {
            $onego->log('autologin not needed, user already authenticated', ModelTotalOnego::LOG_NOTICE);
            $this->redirect($referer);
        }
        
        if ($onego->autologinBlockedUntil()) {
            $onego->log('autologin blocked after last fail', ModelTotalOnego::LOG_NOTICE);
            $this->redirect($referer);
        }
        
        // redirect to OneGo authentication page
        $requestId = uniqid();
        $onego->saveToSession('oauth_authorize_request', array(
            'request_id'    => $requestId,
            'referer'       => $referer,
            'scope'         => $reqScope,
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
        if ($onego->isUserAuthenticated() && $onego->userHasScope($reqScope)) {
            $onego->log('login not needed, user already authenticated and has required scope', ModelTotalOnego::LOG_NOTICE);
            $this->redirect($returnpage);
        }
        
        // redirect to OneGo authentication page
        $requestId = uniqid();
        $onego->saveToSession('oauth_authorize_request', array(
            'request_id'    => $requestId,
            'referer'       => $returnpage,
            'scope'         => $reqScope,
        ));
        $authorizeUrl = $auth->getAuthorizationUrl($onego->getOAuthRedirectUri(), $reqScope, $requestId);
        $this->redirect($authorizeUrl);
    }
    
    public function authorizationResponse()
    {
        $onego = $this->getModel();
        $request = $this->registry->get('request');
        $auth_request = $onego->getFromSession('oauth_authorize_request');
        try {
            $this->processAuthorizationResponse($request->get, $auth_request);
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
    
    private function processAuthorizationResponse($response_params, $authorization_request)
    {
        $onego = $this->getModel();
        if (!empty($response_params['code'])) {
            // issue token
            try {
                $auth = $onego->getAuth();
                $redirectUri = $onego->getOAuthRedirectUri();
                $token = $auth->requestAccessToken($response_params['code'], $redirectUri);
                if (isset($authorization_request['scope'])) {
                    // remember token scope(s)
                    $token->setScopes($authorization_request['scope']);
                }
                $onego->saveOAuthToken($token);
            } catch (Exception $e) {
                throw $e;
            }
            return true;
        } else {
            $error_code = !empty($response_params['error']) ? 
                $response_params['error'] : 'authorization_response_error';
            $error_message = !empty($response_params['error_description']) ? 
                $response_params['error_description'] : '';
            throw new OneGoOAuthException($error_code, $error_message);
        }
    }
    
    /* DEPRECATED */
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
    
    public function auth2()
    {
        $status_page = $this->url->link('total/onego/authStatus');
        $this->login($status_page);
    }
    
    public function authStatus()
    {
        $onego = $this->getModel();
        $this->data['onego_authenticated'] = $onego->isUserAuthenticated();
        $this->data['onego_applied'] = $onego->isTransactionStarted();
        $this->data['error'] = $onego->getFromRegistry('auth_error');
        
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
        
        if ($onego->isTransactionStarted()) {
            if ($onego->cancelTransaction()) {
                $onego->log('transaction cancelled');
                $onego->getSession()->data['success'] = $this->language->get('benefits_disabled');
            } else {
                $onego->log('failed to cancel transaction', ModelTotalOnego::LOG_WARNING);
            }
        }
        $onego->deleteOAuthToken();
        
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