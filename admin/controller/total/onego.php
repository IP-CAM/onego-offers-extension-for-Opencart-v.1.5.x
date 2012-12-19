<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'common.lib.php';

class ControllerTotalOnego extends Controller {

    private $error = array();
    private $errorFields = array();

    /**
     * Extension configuration page
     *
     * @return void
     */
    public function index() {
        $this->load->language('total/onego');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {

            OneGoTransactionsLog::init();
            OneGoRedemptionCodes::init();
            $this->addPermissions();

            $post = $this->request->post;
            $post['onego_confirmOnOrderStatus'] = implode('|', empty($post['onego_confirmOnOrderStatus']) ? array() : $post['onego_confirmOnOrderStatus']);
            $post['onego_cancelOnOrderStatus'] = implode('|', empty($post['onego_cancelOnOrderStatus']) ? array() : $post['onego_cancelOnOrderStatus']);
            $this->model_setting_setting->editSetting('onego', $post);

            $this->session->data['success'] = $this->language->get('text_success');

            $extension_was_disabled = $this->config->get('onego_status') && !$post['onego_status'];
            if ($extension_was_disabled) {
                // disable RC products on extension disable as they won't work
                $this->load->model('sale/onego_rc');
                if ($this->model_sale_onego_rc->disableAllProducts()) {
                    $this->session->data['success'] = $this->language->get('text_success_rc_disabled');
                }
            }
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
        
        $config_fields = array('apiKey', 'apiSecret', 'terminalId', 'checkCredentials',
            'shippingCode', 'transactionTTL', 'confirmOnOrderStatus', 'delayedTransactionTTL', 'cancelOnOrderStatus',
            'widgetFrozen', 'widgetTopOffset');
        $fields = array();
        foreach ($config_fields as $field) {
            $help_key = 'entry_help_'.$field;
            $help = $this->language->get($help_key);
            $cfgVal = in_array($field, array('confirmOnOrderStatus', 'cancelOnOrderStatus')) ?
                        OneGoConfig::getArray($field) : OneGoConfig::get($field);
            $row = array(
                'title' => $this->language->get('entry_'.$field),
                'value' => (isset($this->request->post['onego_'.$field]) ?
                    $this->request->post['onego_'.$field] : $cfgVal),
                'help'  => ($help == $help_key) ? '' : $help,
            );
            $fields[$field] = $row;
        }
        $this->data['onego_config_fields'] = $fields;
        
        $this->load->model('localisation/order_status');
        $this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->data['onego_button_check'] = $this->language->get('button_check_credentials');
        $this->data['onego_check_uri'] = $this->url->link('total/onego/check', 'token=' . $this->session->data['token'], 'SSL');
        $this->data['onego_error_js_missing'] = $this->language->get('error_javascript_not_loaded');

        $this->data['onego_extension_info'] = $this->language->get('onego_extension_info');

        $this->template = 'total/onego.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    /**
     * OneGo transaction state for Opencart order, with optional controls
     */
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
            if ($statusSuccess['operation'] == OneGoSDK_DTO_TransactionEndDto::STATUS_DELAY) {
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
                    $this->data['onego_allow_status_change'] = true;
                    $confirmStatuses = OneGoConfig::getArray('confirmOnOrderStatus');
                    $cancelStatuses = OneGoConfig::getArray('cancelOnOrderStatus');
                    $this->data['confirm_statuses'] = $confirmStatuses;
                    $this->data['cancel_statuses'] = $cancelStatuses;
                    $this->data['onego_btn_confirm'] = $this->language->get('button_confirm_transaction');
                    $this->data['onego_btn_cancel'] = $this->language->get('button_cancel_transaction');
                    $this->data['onego_btn_delay'] = $this->language->get('button_delay_transaction');

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

        $this->data['confirm_confirm'] = $this->language->get('confirm_transaction_confirm');
        $this->data['confirm_cancel'] = $this->language->get('confirm_transaction_cancel');
        $this->data['delay_periods'] = $this->language->get('delay_period');
        $this->data['delay_for_period'] = $this->language->get('delay_for_period');
        $this->data['confirm_delay'] = $this->language->get('confirm_transaction_delay');
        $this->data['status_will_confirm'] = $this->language->get('transaction_will_confirm');
        $this->data['status_will_cancel'] = $this->language->get('transaction_will_cancel');
        
        $this->template = 'total/onego_status.tpl';
        $this->response->setOutput($this->render());
    }

    /**
     * Extension configuration checking
     */
    public function check()
    {
        $params = $this->request->get;
        $this->language->load('total/onego');

        $resp = '';

        // check environment
        $resp .= $this->language->get('check_environment').': ';
        if (function_exists('curl_init')) {
            $resp .= '<span class="onego_ok">'.$this->language->get('ok').'</span>';
            $curlOk = true;
        } else {
            $resp .= '<span class="onego_error">'.$this->language->get('failed').'</span>';
            $resp .= ' ['.$this->language->get('error_curl_missing').']';
            $curlOk = false;
        }
        $resp .= '<br />';

        // check Opencart versions
        $resp .= $this->language->get('check_opencart_version').': ';
        if (in_array(VERSION, $this->getSupportedVersions())) {
            $resp .= '<span class="onego_ok">'.$this->language->get('ok').'</span>';
        } else {
            $resp .= '<span class="onego_error">'.$this->language->get('version_unsupported').'</span>';
        }
        $resp .= '<br />';

        // check credentials
        $resp .= $this->language->get('check_credentials').': ';
        if ($curlOk) {
            $APIConfig = OneGoUtils::getAPIConfig();
            $APIConfig->apiKey = $params['onego_apiKey'];
            $APIConfig->apiSecret = $params['onego_apiSecret'];
            $APIConfig->terminalId = $params['onego_terminalId'];
            $api = OneGoSDK_Impl_SimpleAPI::init($APIConfig);
    
            try {
                $api->getAnonymousAwards();
                $resp .= '<span class="onego_ok">'.$this->language->get('ok').'</span>';
            } catch (OneGoSDK_HTTPConnectionTimeoutException $e) {
                $resp .= '<span class="onego_error">'.$this->language->get('failed').'</span>';
                $resp .= ' ['.$this->language->get('error_connection_timeout').']';
            } catch (OneGoSDK_ForbiddenException $e) {
                $resp .= '<span class="onego_error">'.$this->language->get('failed').'</span>';
                $resp .= ' ['.$this->language->get('error_forbidden').']';
            } catch (OneGoSDK_Exception $e) {
                $resp .= '<span class="onego_error">'.$this->language->get('failed').'</span>';
                $resp .= ' ['.$e->getMessage().']';
            }
        } else {
            $resp .= '<span class="onego_error">'.$this->language->get('cannot_check').'</span>';
        }

        $this->response->setOutput($resp);
    }

    /**
     * OneGo transaction processing for Opencart orders
     *
     * @throws Exception|OneGoSDK_Exception
     * @return void
     */
    public function endTransaction()
    {
        $this->language->load('total/onego');
        
        $orderId = (int) $this->request->post['order_id'];
        $action = $this->request->post['action'];
        
        if (!$this->config->get('onego_status') || 
                !$this->user->hasPermission('modify', 'sale/order') ||
                empty($orderId) || empty($action) ||
                !in_array($action, OneGoSDK_DTO_TransactionEndDto::getStatusesAvailable()))
        {
            $ret = array('error' => 'Unauthorized call');
        } else {
        
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
                        if ($action == OneGoSDK_DTO_TransactionEndDto::STATUS_DELAY) {
                            $delayDays = (int) $this->request->post['duration'];
                            $delayPeriodEnd = mktime(23, 59, 59, date('m'), date('d')+$delayDays, date('Y'));
                            $delayTtl = $delayPeriodEnd - time();

                            $transaction->delay($delayTtl);

                        } else if ($action == OneGoSDK_DTO_TransactionEndDto::STATUS_CONFIRM) {

                            $transaction->confirm();

                        } else if ($action == OneGoSDK_DTO_TransactionEndDto::STATUS_CANCEL) {

                            $transaction->cancel();

                        }
                        $ret = array(
                            'success' => true
                        );
                        OneGoTransactionsLog::log($orderId, $transactionId, $action, $delayTtl);
                    } catch (OneGoSDK_Exception $e) {
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
        $this->request->post['onego_apiKey'] = trim($this->request->post['onego_apiKey']);
        $this->request->post['onego_apiSecret'] = trim($this->request->post['onego_apiSecret']);
        $this->request->post['onego_terminalId'] = trim($this->request->post['onego_terminalId']);
        $this->request->post['onego_transactionTTL'] = (int) trim($this->request->post['onego_transactionTTL']) > 0 ? 
                (int) trim($this->request->post['onego_transactionTTL']) : '';
        
        // validate
        $post = $this->request->post;
        $requiredFields = array('onego_apiKey', 'onego_apiSecret', 'onego_terminalId',
            'onego_transactionTTL', 'onego_confirmOnOrderStatus', 'onego_cancelOnOrderStatus');
        foreach ($requiredFields as $field) {
            if (empty($post[$field])) {
                $this->error['warning'] = $this->language->get('error_missing_required_fields');
                $this->errorFields[] = $field;
            }
        }

        return $this->error ? false : true;
    }

    private function addPermissions()
    {
        if ($this->user->hasPermission('modify', 'total/onego')) {
            $this->load->model('user/user_group');
            $this->model_user_user_group->addPermission($this->user->getId(), 'access', 'sale/onego_rc');
        }
    }

    private function getSupportedVersions()
    {
        return array('1.5.0', '1.5.0.1', '1.5.0.2', '1.5.0.3', '1.5.0.4', '1.5.0.5', '1.5.1', '1.5.1.1',
            '1.5.1.2', '1.5.1.3', '1.5.2', '1.5.2.1', '1.5.3', '1.5.4');
    }
}