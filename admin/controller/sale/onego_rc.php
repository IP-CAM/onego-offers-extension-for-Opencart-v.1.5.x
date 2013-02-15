<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'common.lib.php';

class ControllerSaleOnegoRc extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('catalog/product');
        $this->load->language('total/onego');
        $this->document->setTitle($this->language->get('rc_heading_title'));
        $this->data['heading_title'] = $this->language->get('rc_heading_title');
        $this->data['lang'] = $this->language;
        $this->data['upload_url'] = $this->url->link('sale/onego_rc/upload', 'token='.$this->session->data['token'], 'SSL');
        $this->data['url_self'] = $this->url->link('sale/onego_rc', 'token='.$this->session->data['token'], 'SSL');

        $this->data['breadcrumbs'] = $this->getBreadcrumbs();

        if (isset($this->session->data['success'])) {
            $this->data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        }

        if (!$this->config->get('onego_status')) {
            $this->data['extension_disabled'] = true;
            $this->data['error_warning'] = $this->language->get('extension_disabled');
        } else {

            OneGoRedemptionCodes::init();

            if (!empty($this->request->post['action']) && !empty($this->request->post['selected'])) {
                $action = $this->request->post['action'];
                if (in_array($action, array('enable', 'disable'))) {
                    foreach ($this->request->post['selected'] as $product_id) {
                        $this->setProductStatus($product_id, $action == 'enable');
                    }
                } else if ($action == 'delete') {
                    foreach ($this->request->post['selected'] as $product_id) {
                        $this->deleteUnsoldCodes($product_id);
                    }
                }
                $this->session->data['success'] = $this->data['success'];
                $this->redirect($this->url->link('sale/onego_rc', 'token='.$this->session->data['token'], 'SSL'));
            }

            $this->data['list'] = $this->getList();
        }

        $this->template = 'sale/onego_rc_list.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    public function getList($nominal = false)
    {
        $model = $this->getModel();
        $list = array();
        foreach ($model->getGridList($nominal) as $row) {
            $row['status_text'] = ($row['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'));
            $row['product_url'] = $this->url->link('catalog/product/update', 'token='.$this->session->data['token'].'&product_id='.$row['product_id'], 'SSL');
            $list[] = $row;
        }
        return $list;
    }

    public function upload()
    {
        $this->load->language('catalog/product');
        $this->load->language('total/onego');
        $this->document->setTitle($this->language->get('rc_heading_title'));
        $this->data['heading_title'] = $this->language->get('rc_upload');
        $this->data['lang'] = $this->language;
        $this->data['url_cancel'] = $this->url->link('sale/onego_rc/cancel', 'token='.$this->session->data['token'], 'SSL');
        $this->data['url_self'] = $this->url->link('sale/onego_rc/upload', 'token='.$this->session->data['token'], 'SSL');

        $this->data['breadcrumbs'] = $this->getBreadcrumbs();
        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('rc_upload'),
            'href'      => $this->url->link('sale/onego_rc/upload', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );
        $model = $this->getModel();
        
        // get list of pending codes
        $list = OneGoRedemptionCodes::getPendingCodesCount();
        $rc_nominal = false;
        if (count($list) > 1) {
            $this->data['error_warning'] = $this->language->get('rc_error_cards_import_duplicate');
        } else {
            list($rc_nominal, $rc_count) = each($list);
            $this->data['rc_count'] = $rc_count;
            $this->data['rc_nominal'] = $this->formatCurrency($rc_nominal);
        }

        // handle actions
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            // CSV upload
            $uploaded_file = !empty($this->request->files['csv_list']) ? $this->request->files['csv_list'] : false;
            if ($uploaded_file && is_uploaded_file($uploaded_file['tmp_name'])) {
                $fh = fopen($uploaded_file['tmp_name'], 'r');
                if (is_resource($fh)) {
                    $line = 0;
                    $codes_loaded = 0;
                    while ($row = fgetcsv($fh)) {
                        $is_valid_row = $model->isValidCsvFileRow($row);
                        if (!$row || ($line && !$is_valid_row)) {
                            $this->data['error_warning'] = sprintf($this->language->get('rc_error_csv_file_format'), $line + 1);
                            break;
                        } else if ($line) {
                            if ($rc_nominal && !$model->isCodeNominalMatching($row, $rc_nominal)) {
                                $nominal_str = $this->formatCurrency($row[1]);
                                $this->data['error_warning'] = sprintf($this->language->get('rc_error_csv_nominal'), $nominal_str);
                                break;
                            } else if ($model->addCodeToQueue($row)) {
                                $codes_loaded++;
                            }
                        }
                        $line++;
                    }
                    fclose($fh);
                } else {
                    $this->data['error_warning'] = $this->language->get('rc_error_cant_read_uploaded_file');
                }
                if (empty($this->data['error_warning'])) {
                    if (!$codes_loaded) {
                        $this->data['success'] = $this->language->get('rc_no_codes_loaded');
                    } else {
                        $this->data['success'] = sprintf($this->language->get('rc_codes_loaded'), $codes_loaded);
                    }
                }
            }

            if (!empty($this->request->post['product'])) {
                $this->data['product'] = $this->request->post['product'];
            }

            if ($this->request->post['save']) {
                if ($this->request->post['product']) {
                    $product_id = !empty($this->request->post['product_id']) ? (int) $this->request->post['product_id'] : false;
                    if (!$this->validateForm()) {
                        $this->data['errors'] = $this->error;
                        if (isset($this->error['warning'])) {
                            $this->data['error_warning'] = $this->error['warning'];
                        }
                    } else if ($product_id && $model->addCodesToProduct($product_id)) {

                        // success, redirect to list
                        $this->load->model('catalog/product');
                        $product = $this->model_catalog_product->getProduct($product_id);
                        if ($product) {
                            $product_url = $this->url->link('catalog/product/update', 'token='.$this->session->data['token'].'&product_id='.$product_id, 'SSL');
                            $this->session->data['success'] = sprintf($this->language->get('rc_added_to_product'), $product_url, $product['name']);
                        }
                        $this->redirect($this->url->link('sale/onego_rc', 'token='.$this->session->data['token'], 'SSL'));

                    } else if (!$product_id && ($product_id = $model->addCodesToNewProduct($this->request->post['product']))) {

                        // success, redirect to list
                        $this->load->model('catalog/product');
                        $product = $this->model_catalog_product->getProduct($product_id);
                        if ($product) {
                            $product_url = $this->url->link('catalog/product/update', 'token='.$this->session->data['token'].'&product_id='.$product_id, 'SSL');
                            $this->session->data['success'] = sprintf($this->language->get('rc_product_added'), $product['name'], $product_url);
                        }
                        $this->redirect($this->url->link('sale/onego_rc', 'token='.$this->session->data['token'], 'SSL'));

                    } else {
                        $this->data['error_warning'] = $this->language->get('rc_error_generic');
                    }
                }
            }
        }

        if (!empty($codes_loaded)) {
            // refresh pending codes count
            $list = OneGoRedemptionCodes::getPendingCodesCount();

            list($rc_nominal, $rc_count) = each($list);
            $this->data['rc_count'] = $rc_count;
            $this->data['rc_nominal'] = $this->formatCurrency($rc_nominal);
        }

        // get products already created for this nominal
        if ($rc_nominal) {
            $this->data['products'] = $this->getList($rc_nominal);
        }

        if ($rc_count) {
            $this->data['create_product'] = true;
            $this->load->model('localisation/language');
            $this->data['languages'] = $this->model_localisation_language->getLanguages();
            $this->load->model('catalog/category');
            $this->data['categories'] = $this->model_catalog_category->getCategories(0);
        }        

        $this->template = 'sale/onego_rc_upload.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    public function cancel()
    {
        OneGoRedemptionCodes::resetPendingCodes();
        return $this->forward('sale/onego_rc');
    }

    private function setProductStatus($product_id, $enabled)
    {
        if (!$this->user->hasPermission('modify', 'catalog/product')) {
            $this->error['warning'] = $this->language->get('error_permission');
        } else if ($this->getModel()->setProductStatus($product_id, $enabled)) {
            $this->data['success'] = $enabled ?
                    $this->language->get('rc_product_enabled') :
                    $this->language->get('rc_product_disabled');
        }
    }

    private function deleteUnsoldCodes($product_id)
    {
        if (!$this->user->hasPermission('modify', 'catalog/product')) {
            $this->error['warning'] = $this->language->get('error_permission');
        } else if ($this->getModel()->deleteUnsoldCodes($product_id)) {
            $this->data['success'] = $this->language->get('rc_codes_deleted');
        }
    }

    private function isSetUp()
    {
        return false;
    }

    private function getBreadcrumbs()
    {
        $breadcrumbs = array();
        $breadcrumbs[] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );

        $url = '';
        if (isset($this->request->get['page']) && (int) $this->request->get['page']) {
            $url .= '&page='. (int) $this->request->get['page'];
        }

        $breadcrumbs[] = array(
            'text'      => $this->language->get('rc_heading_title'),
            'href'      => $this->url->link('sale/onego_rc', 'token=' . $this->session->data['token'] . $url, 'SSL'),
            'separator' => ' :: '
        );
        return $breadcrumbs;
    }

    private function getModel()
    {
        $this->load->model('sale/onego_rc');
        return $this->model_sale_onego_rc;
    }

    private function formatCurrency($amount)
    {
        return $this->currency->format($amount);
    }

    private function validateForm()
    {
        if (!$this->user->hasPermission('modify', 'catalog/product')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['product_id'])) {
            foreach ($this->request->post['product']['name'] as $language_id => $value) {
                if ((utf8_strlen($value) < 1) || (utf8_strlen($value) > 255)) {
                    $this->error['name'][$language_id] = $this->language->get('error_name');
                }
            }

            if ((utf8_strlen($this->request->post['product']['model']) < 1) || (utf8_strlen($this->request->post['product']['model']) > 64)) {
                $this->error['model'] = $this->language->get('error_model');
            }

            $price = (float) $this->request->post['product']['price'];
            if (!$price) {
                $this->error['price'] = $this->language->get('rc_error_price');
            }
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }
  
}