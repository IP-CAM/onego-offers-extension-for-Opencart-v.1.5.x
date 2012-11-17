<?php
if (!defined('DIR_ONEGO')) define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'common.lib.php';

class ModelTotalOnego extends Model {
    
    /**
     * Modify Opencart orders list by adding OneGo transaction status info
     *
     * @param array $ordersList
     * @param boolean $appendToStatus Whether to modify original Opencart order status string
     * @return array Modified Opencart orders list 
     */
    public function addTransactionStatuses(&$ordersList, $appendToStatus = true)
    {
        $ordersIds = array();
        foreach ($ordersList as $order) {
            $ordersIds[] = $order['order_id'];
        }
        
        $statuses = OneGoTransactionsLog::getStatusesForOrders($ordersIds);
        
        $this->load->language('total/onego');
        
        foreach ($ordersList as $key => $order) {
            if (isset($statuses[$order['order_id']])) {
                list($title, $titleHtml) = $this->getStatusString($statuses[$order['order_id']]);
                
                $ordersList[$key]['onego_status'] = array(
                    'title' => $title,
                    'error' => !$statuses[$order['order_id']]['success'],
                    'title_html' => $titleHtml,
                );
                if ($appendToStatus) {
                    $ordersList[$key]['status'] .= ' '.$titleHtml;
                }
            } else {
                $ordersList[$key]['onego_status'] = false;
            }
        }
        return $ordersList;
    }
    
    /**
     * Get transaction status text
     *
     * @param array $transactionsLogRow Row from OneGoTransactionsLog::getStatusesForOrders()
     * @return array 0 => plain text title, 1 => full title with HTML
     */
    public function getStatusString($transactionsLogRow)
    {
        $this->load->language('total/onego');
        
        $class = !$transactionsLogRow['success'] ? 
            'onego_transaction_status_failed' : 'onego_transaction_status_ok';

        $title = $titleHtml = '';
        if (!$transactionsLogRow['success']) {
            $title = $this->language->get('status_failure');
        } else if ($transactionsLogRow['operation'] == OneGoAPI_DTO_TransactionEndDto::STATUS_CANCEL) {
            $title = $this->language->get('status_canceled');
        } else if ($transactionsLogRow['operation'] == OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM) {
            $title = $this->language->get('status_confirmed');
        } else if ($transactionsLogRow['operation'] == OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY) {
            $expiresOnTs = strtotime($transactionsLogRow['inserted_on']) + $transactionsLogRow['expires_in'];
            $expiresOn = date('Y-m-d H:i', $expiresOnTs);
            if ($expiresOnTs <= time()) {
                $class = 'onego_transaction_status_expired';
                $title = sprintf($title = $this->language->get('status_expired'), $expiresOn);
            } else {
                $title = sprintf($title = $this->language->get('status_delayed'), $expiresOn);
            }
        }
        if (strlen($title)) {
            $titleHtml = '<span class="'.$class.'">['
                    .$this->language->get('onego_status_short')
                    .' '.$title.']</span>';
        }
        return array($title, $titleHtml);
    }

    public function emailRCDetails($order_id, $order_status_before)
    {
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);
        if ($order_info &&
                ($this->config->get('config_complete_status_id') != $order_status_before) &&
                ($this->config->get('config_complete_status_id') == $order_info['order_status_id']))
        {
            // order status changed to confirmed, may now send RC details to buyer
            $codes = OneGoRedemptionCodes::getOrderCodes($order_id);
            if (!empty($codes)) {
                OneGoRedemptionCodes::createDownload($order_info, $codes, $this);
                return OneGoRedemptionCodes::sendEmail($order_info, $codes, $this);
            }
        }
        return false;
    }
}