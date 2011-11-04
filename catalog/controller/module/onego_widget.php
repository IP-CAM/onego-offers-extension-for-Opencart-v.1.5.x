<?php

class ControllerModuleOnegoWidget extends Controller {

    protected function index() {
        $this->language->load('module/onego_widget');

        $this->data['heading_title'] = $this->language->get('heading_title');

        $this->data['code'] = html_entity_decode($this->config->get('onego_widget_code'));

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/module/onego_widget.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/module/onego_widget.tpl';
        } else {
            $this->template = 'default/template/module/onego_widget.tpl';
        }

        $this->render();
    }

}

?>