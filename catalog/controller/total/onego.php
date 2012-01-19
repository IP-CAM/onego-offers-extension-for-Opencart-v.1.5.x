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
        
        try {
            $onego->refreshTransaction();
        } catch (OneGoAuthenticationRequiredException $e) {
            // ignore
        } catch (OneGoAPICallFailedException $e) {
            // ignore
        }
        
        if ($onego->isUserAuthenticated()) {
            $this->data['onego_action'] = $this->url->link('checkout/cart');
            $this->data['onego_use_funds_url'] = $this->url->link('total/onego/useFunds');
            $this->data['onego_scope_extended'] = $onego->isCurrentScopeSufficient();
            $this->data['checkoutUri'] = $this->url->link('checkout/checkout');
            
            if ($onego->isTransactionStarted()) {
                $this->data['onego_applied'] = true;
                
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
    
    public function useFunds()
    {
        $onego = $this->getModel();
        $this->language->load('total/onego');
        $request = $this->registry->get('request');
        if (!empty($request->post['use_funds'])) {
            $response = false;
            
            try {
                $transaction = $onego->refreshTransaction();
            } catch (OneGoAuthenticationRequiredException $e) {
                $errorMessage = $this->language->get('error_authentication_expired');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            } catch (OneGoException $e) {
                $errorMessage = $this->language->get('error_api_call_failed');
                $response = array(
                    'error' => get_class($e),
                    'message' => $errorMessage,
                );
            }
            
            if (empty($response) && $onego->isTransactionStarted()) {
                $do_use = $request->post['use_funds'] == 'true';
                if ($do_use) {
                    $response = array('status' => $onego->spendPrepaid() ? 1 : 0);
                } else {
                    $response = array('status' => $onego->cancelSpendingPrepaid() ? 1 : 0);
                }
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
            $onego->log('autologin not needed, user already authenticated');
            $this->redirect($referer);
        }
        
        if ($onego->autologinBlockedUntil()) {
            $onego->log('autologin blocked after last fail');
            $this->redirect($referer);
        }
        
        // redirect to OneGo authentication page
        $requestId = uniqid();
        // remember request parameters
        $onego->saveToSession('oauth_authorize_request', array(
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
            $onego->log('login not needed, user already authenticated and has required scope');
            $this->redirect($returnpage);
        }
        
        // redirect to OneGo authentication page
        $requestId = uniqid();
        $onego->saveToSession('oauth_authorize_request', array(
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
        $auth_request = $onego->getFromSession('oauth_authorize_request');
        $autologinBlockTtl = 30;
        $errorMessage = false;
        $onego->saveToSession('authorizationSuccess', false);
        try {
            $this->processAuthorizationResponse($request->get, $auth_request);
            $onego->saveToSession('authorizationSuccess', true);
        } catch (OneGoAPI_OAuthAccessDeniedException $e) {
            $errorMessage = $this->language->get('error_authorization_access_denied');
        } catch (OneGoAPI_OAuthTemporarilyUnavailableException $e) {
            $errorMessage = $this->language->get('error_authorization_temporarily_unavailable');
            $onego->blockAutologin($autologinBlockTtl);
        } catch (Exception $e) {
            $errorMessage = $this->language->get('error_authorization_failed');
            $onego->blockAutologin($autologinBlockTtl);
            $onego->logCritical('authorization failed', $e);
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
        $authorizationSuccessful = $onego->getFromSession('authorizationSuccess');
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
        
        if ($onego->cancelTransaction()) {
            $onego->getSession()->data['success'] = $this->language->get('benefits_disabled');
        }
        $onego->deleteOAuthToken();
        
        if (!ModelTotalOnego::isAjaxRequest()) {
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
        $onego->agreeToDiscloseEmail($agreed);
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
    
    public function widget()
    {
        $this->language->load('total/onego');
        
        $onego = $this->getModel();
        $this->data['widgetCode'] = html_entity_decode($onego->getConfig('widgetCode'));
        $this->data['widget_show'] = $this->language->get('widget_handle_show');
        $this->data['widget_hide'] = $this->language->get('widget_handle_hide');
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/total/onego_widget.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/total/onego_widget.tpl';
        } else {
            $this->template = 'default/template/total/onego_widget.tpl';
        }
        $this->response->setOutput($this->render());
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
        return $onego->getHttpReferer() ? $onego->getHttpReferer() : $this->getDefaultReferer();
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