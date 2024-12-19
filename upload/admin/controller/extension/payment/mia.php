<?php

class ControllerExtensionPaymentMia extends Controller {
    private $error = [];

    public function install() {
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('mia', 'catalog/model/checkout/order/addOrderHistory/before', 'extension/payment/mia/addOrderHistoryBefore');
        $this->model_setting_event->addEvent('mia', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/payment/mia/addOrderHistoryAfter');
    }

    public function uninstall() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('mia');
    }

    public function index() {
        $this->load->language('extension/payment/mia');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->cache->delete("access_token");
            $this->cache->delete("access_token_expires");

            $this->model_setting_setting->editSetting('payment_mia', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token='
                . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['breadcrumbs'] = $this->getBreadCrumbs();

        $data['_form'] = $this->getPostData();

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['_error'] = $this->error;

        $data['action'] = $this->url->link('extension/payment/mia', 'user_token='
            . $this->session->data['user_token'], 'SSL');

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token='
            . $this->session->data['user_token'] . '&type=payment', true);

        $catalog = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;
        $data['callback_url'] = $catalog . 'index.php?route=extension/payment/mia/callback';
        $data['success_url'] = $catalog . 'index.php?route=extension/payment/mia/success';
        $data['fail_url'] = $catalog . 'index.php?route=extension/payment/mia/fail';

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $template = 'extension/payment/mia';
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($template, $data));
    }

    private function validate() {
        $post_data = $this->request->post;

        if (!$this->user->hasPermission('modify', 'extension/payment/mia')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $required = ['payment_mia_merchant_id', 'payment_mia_secret_key', 'payment_mia_terminal_id', 'payment_mia_base_url'];

        foreach ($required as $field) {
            if (empty($post_data[$field])) {
                $this->error[$field] = $this->language->get('error_empty_field');
            }
        }

        return empty($this->error);
    }

    private function getBreadCrumbs() {
        $breadcrumbs = [];

        $breadcrumbs[] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token='
                . $this->session->data['user_token'], 'SSL'),
            'separator' => false
        ];

        $breadcrumbs[] = [
            'text' => $this->language->get('text_extensions'),
            'href' => $this->url->link('marketplace/extension', 'user_token='
                . $this->session->data['user_token'] . '&type=payment', true),
            'separator' => ' :: '
        ];

        $breadcrumbs[] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/mia', 'user_token='
                . $this->session->data['user_token'], 'SSL'),
            'separator' => ' :: '
        ];

        return $breadcrumbs;
    }

    private function getPostData() {
        $defaults = $this->getDefaults();
        foreach ($defaults as $key => $value) {
            $config = $this->config->get($key);
            if ($config !== null) {
                $defaults[$key] = $config;
            }
        }
        return array_merge($defaults, $this->request->post);
    }

    private function getDefaults() {
        return [
            'payment_mia_title' => 'MIA POS Payment Gateway',
            'payment_mia_status' => 1,
            'payment_mia_debug' => 0,
            'payment_mia_sort_order' => 0,
            'payment_mia_geo_zone_id' => '',
            'payment_mia_merchant_id' => '',
            'payment_mia_secret_key' => '',
            'payment_mia_terminal_id' => '',
            'payment_mia_base_url' => 'https://ecomm.mia-pos.md',
            'payment_mia_payment_type' => 'qr',
            'payment_mia_language' => 'ro',
            'payment_mia_order_pending_status_id' => 1,
            'payment_mia_order_success_status_id' => 2,
            'payment_mia_order_fail_status_id' => 10,
        ];
    }
}
