<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'common.lib.php';

class ControllerTotalOnego extends Controller {

    private $error = array();
    private $errorFields = array();

    public function index() {
        $this->load->language('total/onego');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
            OneGoTransactionsLog::init();
            
            $this->model_setting_setting->editSetting('onego', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->redirect($this->url->link('extension/total', 'token=' . $this->session->data['token'], 'SSL'));
        }

        $this->data['heading_title'] = $this->language->get('heading_title');

        $this->data['invalid_fields'] = $this->errorFields;
        
        $this->data['text_enabled'] = $this->language->get('text_enabled');
        $this->data['text_disabled'] = $this->language->get('text_disabled');

        $this->data['entry_status'] = $this->language->get('entry_status');
        $this->data['entry_sort_order'] = $this->language->get('entry_sort_order');
        
        $this->data['button_save'] = $this->language->get('button_save');
        $this->data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $this->data['error_warning'] = $this->error['warning'];
        } else {
            $this->data['error_warning'] = '';
        }

        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_total'),
            'href' => $this->url->link('extension/total', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('total/onego', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->data['action'] = $this->url->link('total/onego', 'token=' . $this->session->data['token'], 'SSL');

        $this->data['cancel'] = $this->url->link('extension/total', 'token=' . $this->session->data['token'], 'SSL');

        if (isset($this->request->post['onego_status'])) {
            $this->data['onego_status'] = $this->request->post['onego_status'];
        } else {
            $this->data['onego_status'] = $this->config->get('onego_status');
        }

        if (isset($this->request->post['onego_sort_order'])) {
            $this->data['onego_sort_order'] = $this->request->post['onego_sort_order'];
        } else {
            $this->data['onego_sort_order'] = $this->config->get('onego_sort_order');
            if (is_null($this->data['onego_sort_order'])) {
                $this->data['onego_sort_order'] = 1;
            }
        }
        $this->data['onego_sortorder_text'] = $this->language->get('entry_help_sortorder');
        
        $config_fields = array('clientId', 'clientSecret', 'terminalId', 'shippingCode', 
            'transactionTTL', 'confirmOnOrderStatus', 'delayedTransactionTTL', 'cancelOnOrderStatus',
            'widgetShow', 'widgetFrozen', 'widgetTopOffset', 'autologinOn');
        $fields = array();
        foreach ($config_fields as $field) {
            $help_key = 'entry_help_'.$field;
            $help = $this->language->get($help_key);
            $row = array(
                'title' => $this->language->get('entry_'.$field),
                'value' => isset($this->request->post['onego_'.$field]) ?
                    $this->request->post['onego_'.$field] : OneGoConfig::getInstance()->get($field),
                'help'  => $help == $help_key ? '' : $help,
            );
            $fields[$field] = $row;
        }
        $this->data['onego_config_fields'] = $fields;
        
        $this->load->model('localisation/order_status');
        $this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        

        $this->template = 'total/onego.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }
    
    public function status()
    {
        if (!$this->config->get('onego_status') || !$this->user->hasPermission('modify', 'sale/order')) {
            $this->response->setOutput('');
            return;
        }
        
        $this->language->load('total/onego');
        
        $orderId = (int) $this->request->get['order_id'];
        $operations = OneGoTransactionsLog::getListForOrder($orderId);
        foreach ($operations as $row) {
            if ($row['success'] && empty($statusSuccess)) {
                $statusSuccess = $row;
                break;
            } else if (!$row['success'] && empty($statusFailure)) {
                $statusFailure = $row;
            }
        }
        if (!empty($statusSuccess)) {
            if ($statusSuccess['operation'] == OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY) {
                // delayed transaction
                $expiresOn = strtotime($statusSuccess['inserted_on']) + $statusSuccess['expires_in'];
                if ($expiresOn <= time()) {
                    // transaction expired
                    $this->data['onego_status_success'] = sprintf(
                        $this->language->get('transaction_status_expired'), date('Y-m-d H:i:s', $expiresOn));
                } else {
                    // transaction delayed
                    $this->data['onego_status_success'] = sprintf(
                        $this->language->get('transaction_status_delayed'), 
                        date('Y-m-d H:i', strtotime($statusSuccess['inserted_on'])),
                        date('Y-m-d H:i:s', $expiresOn));
                    
                    // enable transaction completion actions
                    $this->data['onego_btn_confirm'] = $this->language->get('button_confirm_transaction');
                    $this->data['onego_btn_cancel'] = $this->language->get('button_cancel_transaction');
                    $this->data['confirm_confirm'] = $this->language->get('confirm_transaction_confirm');
                    $this->data['confirm_cancel'] = $this->language->get('confirm_transaction_cancel');
                    $this->data['delay_periods'] = $this->language->get('delay_period');
                    $this->data['delay_for_period'] = $this->language->get('delay_for_period');
                    $this->data['onego_btn_delay'] = $this->language->get('button_delay_transaction');                   
                    $this->data['confirm_delay'] = $this->language->get('confirm_transaction_delay');
                    
                }
            } else {
                $this->data['onego_status_success'] = sprintf(
                        $this->language->get('transaction_status_'.strtolower($statusSuccess['operation'])),
                        date('Y-m-d H:i', strtotime($statusSuccess['inserted_on'])));
            }
        } 
        if (!empty($statusFailure)) {
            $this->data['onego_status_failure'] = sprintf(
                    $this->language->get('transaction_operation_failed'),
                    date('Y-m-d H:i', strtotime($statusFailure['inserted_on'])),
                    $statusFailure['operation'],
                    $statusFailure['error_message']);
        } else if (empty($statusSuccess)) {
            $this->data['onego_status_undefined'] = $this->language->get('transaction_status_undefined');
        }
        $this->data['onego_status'] = $this->language->get('onego_status');
        $this->data['order_id'] = $orderId;
        $this->data['token'] = $this->session->data['token'];
        
        $this->template = 'total/onego_status.tpl';
        $this->response->setOutput($this->render());
    }
    
    public function endTransaction()
    {
        $this->language->load('total/onego');
        
        $orderId = (int) $this->request->post['order_id'];
        $action = $this->request->post['action'];
        
        if (!$this->config->get('onego_status') || 
                !$this->user->hasPermission('modify', 'sale/order') ||
                empty($orderId) || empty($action) ||
                !in_array($action, OneGoAPI_DTO_TransactionEndDto::getStatusesAvailable()))
        {
            $ret = array('error' => 'Unauthorized call');
        }
        
        $operations = OneGoTransactionsLog::getListForOrder($orderId, true);
        if (empty($operations) || !($operation = $operations[0]) || empty($operation['transaction_id'])) {
            $ret = array('error' => $this->language->get('error_transaction_id_unknown'));
        } else {
            // get transaction
            $api = OneGoUtils::initAPI();
            try {
                $transactionId = $operation['transaction_id'];
                $transaction = $api->fetchById($transactionId);
                   
                try {
                    $delayTtl = null;
                    if ($action == OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY) {
                        $delayDays = (int) $this->request->post['duration'];
                        $delayPeriodEnd = mktime(23, 59, 59, date('m'), date('d')+$delayDays, date('Y'));
                        $delayTtl = $delayPeriodEnd - time();
                        
                        $transaction->delay($delayTtl);
                        
                    } else if ($action == OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM) {
                        
                        $transaction->confirm();
                        
                    } else if ($action == OneGoAPI_DTO_TransactionEndDto::STATUS_CANCEL) {
                        
                        $transaction->cancel();
                        
                    }
                    $ret = array(
                        'success' => true
                    );
                    OneGoTransactionsLog::log($orderId, $transactionId, $action, $delayTtl);
                } catch (OneGoAPI_Exception $e) {
                    // log operation
                    OneGoTransactionsLog::log($orderId, $transactionId, $action, $delayTtl, 
                            true, $e->getMessage());                    
                    throw $e;
                }
            } catch (Exception $e) {
                $ret = array(
                    'error' => $e->getMessage()
                );
            }
        }
        
        $this->response->setOutput(json_encode($ret));
    }

    /**
     *
     * @return boolean Are configuration values valid
     */
    private function validate() {
        if (!$this->user->hasPermission('modify', 'total/onego')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        // cleanup
        $this->request->post['onego_clientId'] = trim($this->request->post['onego_clientId']);
        $this->request->post['onego_clientSecret'] = trim($this->request->post['onego_clientSecret']);
        $this->request->post['onego_terminalId'] = trim($this->request->post['onego_terminalId']);
        $this->request->post['onego_transactionTTL'] = (int) trim($this->request->post['onego_transactionTTL']) > 0 ? 
                (int) trim($this->request->post['onego_transactionTTL']) : '';
        
        // validate
        $post = $this->request->post;
        $requiredFields = array('onego_clientId', 'onego_clientSecret', 'onego_terminalId',
            'onego_transactionTTL', 'onego_confirmOnOrderStatus', 'onego_cancelOnOrderStatus');
        foreach ($requiredFields as $field) {
            if (empty($post[$field])) {
                $this->error['warning'] = $this->language->get('error_missing_required_fields');
                $this->errorFields[] = $field;
            }
        }
        
        $this->request->post['onego_confirmOnOrderStatus'] = implode('|', empty($post['onego_confirmOnOrderStatus']) ? array() : $post['onego_confirmOnOrderStatus']);
        $this->request->post['onego_cancelOnOrderStatus'] = implode('|', empty($post['onego_cancelOnOrderStatus']) ? array() : $post['onego_cancelOnOrderStatus']);

        return $this->error ? false : true;
    }
}