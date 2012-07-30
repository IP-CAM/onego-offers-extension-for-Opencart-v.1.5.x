<?php
define('DIR_ONEGO', DIR_SYSTEM.'library/onego/');
require_once DIR_ONEGO.'common.lib.php';

class ControllerSaleOnegoVgc extends Controller
{
    const VGC_PRODUCT_SKU_PREFIX = 'onego_vgc';

    public function index()
    {        
        $this->load->language('total/onego');
        $this->document->setTitle($this->language->get('vgc_heading_title'));
        $this->data['heading_title'] = $this->language->get('vgc_heading_title');
        $this->data['lang'] = $this->language;
        $this->data['upload_url'] = $this->url->link('sale/onego_vgc/upload', 'token='.$this->session->data['token'], 'SSL');

        $this->data['breadcrumbs'] = $this->getBreadcrumbs();

        $batches = array();

        $this->data['batches'] = $batches;

        $this->template = 'sale/onego_vgc_list.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    public function upload()
    {
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
        
        OneGoVirtualGiftCards::init();

        // get list of pending cards
        $list = OneGoVirtualGiftCards::getPendingCardsCount();
        $vgc_nominal = false;
        if (count($list) > 1) {
            $this->data['error_warning'] = $this->language->get('vgc_error_cards_import_duplicate');
        } else {
            list($vgc_nominal, $this->data['vgc_count']) = each($list);
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
        } else {

        }

        if (!empty($cards_loaded)) {
            // refresh pending cards count
            $list = OneGoVirtualGiftCards::getPendingCardsCount();

            list($this->data['vgc_nominal'], $this->data['vgc_count']) = each($list);
            $vgc_nominal = $this->data['vgc_nominal'];
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
  
}