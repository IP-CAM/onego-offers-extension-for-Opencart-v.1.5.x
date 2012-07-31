<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'common.lib.php';

class ControllerSaleOnegoVgc extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('catalog/product');
        $this->load->language('total/onego');
        $this->document->setTitle($this->language->get('vgc_heading_title'));
        $this->data['heading_title'] = $this->language->get('vgc_heading_title');
        $this->data['lang'] = $this->language;
        $this->data['upload_url'] = $this->url->link('sale/onego_vgc/upload', 'token='.$this->session->data['token'], 'SSL');
        $this->data['url_self'] = $this->url->link('sale/onego_vgc', 'token='.$this->session->data['token'], 'SSL');

        $this->data['breadcrumbs'] = $this->getBreadcrumbs();

        OneGoVirtualGiftCards::init();

        if (!empty($this->request->get['product_added'])) {
            $product_id = (int) $this->request->get['product_added'];
            $this->load->model('catalog/product');
            $product = $this->model_catalog_product->getProduct($product_id);
            if ($product) {
                $product_url = $this->url->link('catalog/product/update', 'token='.$this->session->data['token'].'&product_id='.$product_id, 'SSL');
                $this->data['success'] = sprintf($this->language->get('vgc_product_added'), $product['name'], $product_url);
            }
        } else if (!empty($this->request->post['action']) && !empty($this->request->post['selected'])) {
            $action = $this->request->post['action'];
            if (in_array($action, array('enable', 'disable'))) {
                foreach ($this->request->post['selected'] as $product_id) {
                    $this->setProductStatus($product_id, $action == 'enable');
                }
            } else if ($action == 'delete') {
                foreach ($this->request->post['selected'] as $product_id) {
                    $this->deleteUnsoldCards($product_id);
                }
            }
        }

        $this->data['list'] = $this->getList();

        $this->template = 'sale/onego_vgc_list.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    public function getList()
    {
        $model = $this->getModel();
        $list = array();
        foreach ($model->getGridList() as $row) {
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
        $this->document->setTitle($this->language->get('vgc_heading_title'));
        $this->data['heading_title'] = $this->language->get('vgc_upload');
        $this->data['lang'] = $this->language;
        $this->data['url_cancel'] = $this->url->link('sale/onego_vgc/cancel', 'token='.$this->session->data['token'], 'SSL');
        $this->data['url_self'] = $this->url->link('sale/onego_vgc/upload', 'token='.$this->session->data['token'], 'SSL');

        $this->data['breadcrumbs'] = $this->getBreadcrumbs();
        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('vgc_upload'),
            'href'      => $this->url->link('sale/onego_vgc/upload', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );
        $model = $this->getModel();
        
        // get list of pending cards
        $list = OneGoVirtualGiftCards::getPendingCardsCount();
        $vgc_nominal = false;
        if (count($list) > 1) {
            $this->data['error_warning'] = $this->language->get('vgc_error_cards_import_duplicate');
        } else {
            list($vgc_nominal, $vgc_count) = each($list);
            $this->data['vgc_count'] = $vgc_count;
            $this->data['vgc_nominal'] = $this->formatCurrency($vgc_nominal);
        }

        // handle actions
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            // CSV upload
            $uploaded_file = !empty($this->request->files['csv_list']) ? $this->request->files['csv_list'] : false;
            if ($uploaded_file && is_uploaded_file($uploaded_file['tmp_name'])) {
                $fh = fopen($uploaded_file['tmp_name'], 'r');
                if (is_resource($fh)) {
                    $line = 0;
                    $cards_loaded = 0;
                    while ($row = fgetcsv($fh)) {
                        $is_valid_row = $model->isValidCsvFileRow($row);
                        if (!$row || ($line && !$is_valid_row)) {
                            $this->data['error_warning'] = sprintf($this->language->get('vgc_error_csv_file_format'), $line + 1);
                            break;
                        } else if ($line) {
                            if ($vgc_nominal && !$model->isCardNominalMatching($row, $vgc_nominal)) {
                                $nominal_str = $this->formatCurrency($row[1]);
                                $this->data['error_warning'] = sprintf($this->language->get('vgc_error_csv_nominal'), $nominal_str);
                                break;
                            } else if ($model->addCardToQueue($row)) {
                                $cards_loaded++;
                            }
                        }
                        $line++;
                    }
                    fclose($fh);
                } else {
                    $this->data['error_warning'] = $this->language->get('vgc_error_cant_read_uploaded_file');
                }
                if (empty($this->data['error_warning'])) {
                    if (!$cards_loaded) {
                        $this->data['success'] = $this->language->get('vgc_no_cards_loaded');
                    } else {
                        $this->data['success'] = sprintf($this->language->get('vgc_cards_loaded'), $cards_loaded);
                    }
                }
            }



            if ($this->request->post['save'] && $this->request->post['product']) {
                $this->data['product'] = $this->request->post['product'];
                if (!$this->validateForm()) {
                    $this->data['errors'] = $this->error;
                    if (isset($this->error['warning'])) {
                        $this->data['error_warning'] = $this->error['warning'];
                    }
                } else if ($product_id = $model->addCardsToNewProduct($this->request->post['product'])) {
                    // success, redirect to list
                    $this->redirect($this->url->link('sale/onego_vgc', 'token='.$this->session->data['token'].'&product_added='.$product_id, 'SSL'));

                } else {
                    $this->data['error_warning'] = $this->language->get('vgc_error_generic');
                }
            }
        }

        if (!empty($cards_loaded)) {
            // refresh pending cards count
            $list = OneGoVirtualGiftCards::getPendingCardsCount();

            list($vgc_nominal, $vgc_count) = each($list);
            $this->data['vgc_count'] = $vgc_count;
            $this->data['vgc_nominal'] = $this->formatCurrency($vgc_nominal);
        }

        if ($vgc_count) {
            $this->data['create_product'] = true;
            $this->load->model('localisation/language');
            $this->data['languages'] = $this->model_localisation_language->getLanguages();
            $this->load->model('catalog/category');
            $this->data['categories'] = $this->model_catalog_category->getCategories(0);
            
        }
        

        $this->template = 'sale/onego_vgc_upload.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    public function cancel()
    {
        OneGoVirtualGiftCards::resetPendingCards();
        return $this->forward('sale/onego_vgc');
    }

    private function setProductStatus($product_id, $enabled)
    {
        if (!$this->user->hasPermission('modify', 'catalog/product')) {
            $this->error['warning'] = $this->language->get('error_permission');
        } else if ($this->getModel()->setProductStatus($product_id, $enabled)) {
            $this->data['success'] = $enabled ?
                    $this->language->get('vgc_product_enabled') :
                    $this->language->get('vgc_product_disabled');
        }
    }

    private function deleteUnsoldCards($product_id)
    {
        if (!$this->user->hasPermission('modify', 'catalog/product')) {
            $this->error['warning'] = $this->language->get('error_permission');
        } else if ($this->getModel()->deleteUnsoldCards($product_id)) {
            $this->data['success'] = $this->language->get('vgc_cards_deleted');
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
            'text'      => $this->language->get('vgc_heading_title'),
            'href'      => $this->url->link('sale/onego_vgc', 'token=' . $this->session->data['token'] . $url, 'SSL'),
            'separator' => ' :: '
        );
        return $breadcrumbs;
    }

    private function getModel()
    {
        $this->load->model('sale/onego_vgc');
        return $this->model_sale_onego_vgc;
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
            $this->error['price'] = $this->language->get('vgc_error_price');
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }
  
}