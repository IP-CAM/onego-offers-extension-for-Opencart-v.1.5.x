<?php

class ControllerTotalOnego extends Controller {

    private $error = array();
    private $errorFields = array();

    public function index() {
        $this->load->language('total/onego');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
            $this->createDbTable();
            
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
                    $this->request->post['onego_'.$field] : $this->getConfigValue($field),
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
    
    /**
     * Return setting configured through admin interface; if not available -
     * from config file.
     *
     * @global type $oneGoConfig
     * @param string $key
     * @return mixed 
     */
    private function getConfigValue($key)
    {
        global $oneGoConfig;
        if (!isset($oneGoConfig)) {
            require_once DIR_SYSTEM.'library/onego/config.inc.php';
        }
        
        $val = $this->config->get('onego_'.$key);
        if (!is_null($val)) {
            return $val;
        } else if (isset($oneGoConfig[$key])) {
            return $oneGoConfig[$key];
        }
        return false;
    }
    
    /**
     * Create DB table for storing OneGo transactions information
     */
    private function createDbTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `onego_transactions_log` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `order_id` int(11) NOT NULL COMMENT 'Opencart order ID',
                  `transaction_id` varchar(100) NOT NULL COMMENT 'OneGo transaction ID',
                  `operation` enum('CONFIRM','CANCEL','DELAY') NOT NULL COMMENT 'OneGo operation',
                  `success` tinyint(1) NOT NULL COMMENT 'Is operation successful',
                  `error_message` text COMMENT 'Error message (optional)',
                  `inserted_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  `expires_in` int(11) DEFAULT NULL COMMENT 'Delayed transaction TTL',
                  PRIMARY KEY (`id`),
                  KEY `order_id` (`order_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='OneGo transactions for orders'";
        $this->registry->get('db')->query($sql);
    }

}