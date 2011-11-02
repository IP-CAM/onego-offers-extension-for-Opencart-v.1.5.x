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
        $referer = $onego->getFromSession('referer') ? $onego->getFromSession('referer') : $this->getDefaultReferer();
        
        if ($onego->isTransactionStarted()) {
            if ($onego->cancelTransaction()) {
                $onego->log('transaction cancelled');
                $onego->getSession()->data['success'] = $this->language->get('benefits_disabled');
            } else {
                $onego->log('failed to cancel transaction', ModelTotalOnego::LOG_WARNING);
            }
        }
        
        $this->redirect($referer);
    }
    
    public function updatebenefits()
    {
        $onego = $this->getModel();
        $referer = $onego->getFromSession('referer') ? $onego->getFromSession('referer') : $this->getDefaultReferer();
        
        if ($onego->isTransactionStarted()) {
            $onego->updateTransactionCart();
        } else {
            $onego->log('transaction update impossible, transaction not started', ModelTotalOnego::LOG_WARNING);
        }
        
        $this->redirect($referer);
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