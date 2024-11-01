<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 17.12.2017
 * Time: 22:52
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Connect {

    /**
     * __isset legacy.
     * @param mixed $key
     * @return bool
     */
    public function __isset($key) {
        return in_array($key, array(
            'id',
            'instance_id',
            'courier',
            'package',
            'shipping_method',
            'kladr_id_from',
            'kladr_id',
            'declared_cost',
            'cod',
            'card',
            'country_code',
            'height',
            'width',
            'length',
            'weight',
            'debug',
            'log'
        ));
    }

    /**
     * __get function.
     * @param string $key
     * @return string
     */
    public function __get($key) {
        return is_callable(array($this, "get_{$key}")) ? $this->{"get_{$key}"}() : $key;
    }

    /**
     * __set function.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value) {
        if (is_callable(array($this, "set_{$key}"))) {
            $this->{"set_{$key}"}($value);
        } else {
            $this->$key = $value;
        }
    }

    public function __construct($id = 'shiptor', $instance_id = 0) {
        $this->id = $id;
        $this->instance_id = $instance_id;
        $this->declared_cost = 10;
        $this->cod = 0;
        $this->card = false;
        $this->kladr_id_from = '';
        $this->log = new WC_Logger();
        $this->cache = new WC_Shiptor_Cache();

        $this->courier = null;
        $this->height = null;
        $this->width = null;
        $this->length = null;
        $this->weight = null;
    }

    /**
     * Set the courier name.
     *
     * @param string $courier.
     */
    public function set_courier($courier = '') {
        $this->courier = $courier;
    }

    /**
     * Set shipping package.
     *
     * @param array $package Shipping package.
     */
    public function set_package($package = array()) {
        $this->package = $package;
        $shiptor_package = new WC_Shiptor_Package($package);

        if (!is_null($shiptor_package)) {
            $data = $shiptor_package->get_data();

            $this->set_height($data['height']);
            $this->set_width($data['width']);
            $this->set_length($data['length']);
            $this->set_weight($data['weight']);
        }
    }

    public function set_declared_cost($declared_cost = 10) {
        $this->declared_cost = $declared_cost;
    }

    public function set_cod($cod = 0) {
        $this->cod = $cod;
    }

    public function set_card($card) {
        $this->card = $card;
    }

    public function set_country_code($code = 'RU') {
        $this->country_code = $code;
    }

    public function set_debug($debug = 'no') {
        $this->debug = $debug;
    }

    /**
     * Set kladr id from.
     *
     * @param string $from.
     */
    public function set_kladr_id_from($from = '') {
        $this->kladr_id_from = $from;
    }

    /**
     * Set kladr ID.
     *
     * @param string $to.
     */
    public function set_kladr_id($to = '') {
        $this->kladr_id = $to;
    }

    /**
     * Set shipping package height.
     *
     * @param float $height Package height.
     */
    public function set_height($height = 0) {
        $this->height = (float) $height;
    }

    /**
     * Set shipping package width.
     *
     * @param float $width Package width.
     */
    public function set_width($width = 0) {
        $this->width = (float) $width;
    }

    /**
     * Set shipping package length.
     *
     * @param float $length Package length.
     */
    public function set_length($length = 0) {
        $this->length = (float) $length;
    }

    /**
     * Set shipping package weight.
     *
     * @param float $weight Package weight.
     */
    public function set_weight($weight = 0) {
        $this->weight = (float) $weight;
    }

    public function set_shipping_method($shipping_method = 0) {
        $this->shipping_method = $shipping_method;
    }

    public function get_courier() {
        return $this->courier;
    }

    public function set_is_stock($stock) {
        $this->is_stock = (bool)$stock;
    }

    public function get_is_stock() {
        return $this->is_stock;
    }

    /**
     * Get kladr id from.
     *
     * @return string.
     */
    public function get_kladr_id_from() {
        return $this->kladr_id_from;
    }

    /**
     * Get kladr ID.
     *
     * @return string.
     */
    public function get_kladr_id() {
        return $this->kladr_id;
    }

    /**
     * Get height.
     *
     * @return float
     */
    public function get_height() {
        return $this->float_to_string($this->height);
    }

    /**
     * Get width.
     *
     * @return float
     */
    public function get_width() {
        return $this->float_to_string($this->width);
    }

    /**
     * Get length.
     *
     * @return float
     */
    public function get_length() {
        return $this->float_to_string($this->length);
    }

    /**
     * Get weight.
     *
     * @return float
     */
    public function get_weight() {
        return $this->float_to_string($this->weight);
    }

    public function get_declared_cost() {
        return $this->declared_cost;
    }

    public function get_country_code() {
        return $this->country_code;
    }

    public function get_cod() {
        return $this->cod;
    }

    public function get_card() {
        return $this->card;
    }

    /**
     * Fix number format.
     *
     * @param  float $value  Value with dot.
     *
     * @return string        Value with comma.
     */
    protected function float_to_string($value) {
        $value = str_replace(',', '.', $value);

        return $value;
    }

    public function get_shipping() {
        $shipping = array();

        $params = apply_filters('woocommerce_shiptor_shipping_params', array(
            'height' => $this->get_height(),
            'width' => $this->get_width(),
            'length' => $this->get_length(),
            'weight' => $this->get_weight(),
            'kladr_id' => $this->get_kladr_id()
        ), $this->id, $this->instance_id, $this->package);

        if (!empty($this->country_code)) {
            $params['country_code'] = $this->get_country_code();
        }

        if (isset($this->declared_cost)) {
            $params['declared_cost'] = $this->get_declared_cost();
        }

        if (isset($this->cod)) {
            $params['cod'] = $this->get_cod();
        }

        if (isset($this->kladr_id_from) && !empty($this->kladr_id_from)) {
            $params['kladr_id_from'] = $this->get_kladr_id_from();
        }

        if (!is_null($this->courier)) {
            $params['courier'] = $this->get_courier();
        }

        if (isset($this->is_stock)) {
            $params['stock'] = $this->get_is_stock();
        }

        $params = array(
            'method' => 'calculateShipping',
            'params' => $params
        );

        $response = $this->request($params, false);

        if ( !is_wp_error($response) ) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                if (is_array($result['result']['methods'])) {
                    $shipping = $result['result']['methods'];
                }
            }
        }

        return $shipping;
    }

    public function get_international_shipping() {
        global $woocommerce;

        $shipping = array();
        $declaredCost = floatval($woocommerce->cart->cart_contents_total);
        $is_insure_export = wc_shiptor_get_option('is_export_insurance');
        $insurance = $is_insure_export === 'yes' ? true : false;
        $params = apply_filters('woocommerce_shiptor_international_shipping_params', array(
            'height' => $this->get_height(),
            'width' => $this->get_width(),
            'length' => $this->get_length(),
            'weight' => $this->get_weight(),
            'insurance' => $insurance,
            'declared_cost' => $declaredCost,
            'departure_country_code' => WC()->countries->get_base_country(),
            'destination_country_code' => $this->get_country_code()
        ), $this->id, $this->instance_id, $this->package);

        if ('yes' === $this->debug) {
            $this->log->add($this->id, 'Requesting Shiptor API...');
        }

        $response = $this->request(array(
            'method' => 'calculateShippingInternational',
            'params' => $params
        ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {

            $result = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($result['result'])) {

                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Shiptor API request: ' . print_r($result['result']['request'], true));
                }

                if (is_array($result['result']['methods'])) {
                    $shipping = $result['result']['methods'];
                }
            } elseif (isset($result['error']) && 'yes' === $this->debug) {
                $this->log->add($this->id, 'Shiptor International Error: ' . $result['error']['message']);
            }
        } else {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'Error accessing the Shiptor API: ' . print_r($response, true));
            }
        }

        return $shipping;
    }

    public function get_delivery_points(array $atts = array()) {
        $delivery_points = array();

        $required = array(
            'kladr_id' => $this->get_kladr_id(),
            'shipping_method' => $this->shipping_method,
        );

        $params = array();
        if (!is_null($this->courier)) {
            $params['courier'] = $this->get_courier();
        }

        if (!is_null($this->cod)) {
            $params['cod'] = $this->get_cod() > 0 ? true : false;
        }

        if (!is_null($this->card)) {
            $params['card'] = $this->get_card();
        }

        $params['limits'] = array();
        if (!is_null($this->height)) {
            $params['limits']['height'] = (float) $this->get_height();
        }
        if (!is_null($this->width)) {
            $params['limits']['width'] = (float) $this->get_width();
        }
        if (!is_null($this->length)) {
            $params['limits']['length'] = (float) $this->get_length();
        }
        if (!is_null($this->weight)) {
            $params['limits']['weight'] = (float) $this->get_weight();
        }
        $params = array_merge($required, $params, $atts);

        $response = $this->request(array(
            'method' => 'getDeliveryPoints',
            'params' => $params
        ), false);

        if ( !is_wp_error($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                $delivery_points = $result['result'];
            }
        }

        // replace delivery point name with profile name
        $shipping_method_info = get_shipping_method_info($this->shipping_method);
        if ($shipping_method_info) {
            $delivery_points = array_map(function($row) use ($shipping_method_info){
                $row['name'] = $shipping_method_info['name'];
                return $row;
            }, $delivery_points);
        }

        return $delivery_points;
    }

    public function get_days_off() {
        $tomorrow = date('Y-m-d', strtotime('+1day'));
        $to = date('Y-m-d', strtotime('+30day'));
        $days = array();

        $days_hash = 'shiptor_days_off_' . md5(wp_json_encode(array($tomorrow, $to)) . WC_Cache_Helper::get_transient_version('shiptor'));
        $session_key = 'shiptor_gey_days_off';
        $stored = '';
        if (WC()->session) {
            $stored = WC()->session->get($session_key);
        }
        if (!is_array($stored) || $days_hash !== $stored['hash']) {

            $response = $this->request(array(
                'method' => 'getDaysOff',
                'params' => array(
                    'from' => $tomorrow,
                    'till' => $to
                )
            ), true);

            if (is_wp_error($response)) {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
                }
            } elseif (200 == wp_remote_retrieve_response_code($response)) {

                $result = json_decode(wp_remote_retrieve_body($response), true);

                $days = $result['result'];
                if (WC()->session) {
                    WC()->session->set($session_key, array(
                        'hash' => $days_hash,
                        'days' => $days
                    ));
                }
            }
        } else {
            $days = isset($stored['days']) ? $stored['days'] : array();
        }

        return $days;
    }

    public function add_packages($atts = array(), $shipment = array(), $is_export = false) {
        $params = array_merge(
            array(
                'height' => $this->get_height(),
                'width' => $this->get_width(),
                'length' => $this->get_length(),
                'weight' => $this->get_weight()
            ), $atts
        );

        $method = 'addPackages';
        $response_data = array(
            'shipment' => $shipment,
            'packages' => array($params)
        );

        if ($is_export) {
            $method = 'addPackage';
            $response_data = $params;
        }

        $response = $this->request(array(
            'method' => $method,
            'params' => $response_data
        ), false);

        if ('yes' === $this->debug) {
            $this->log->add($this->id, 'Add package request: ' . print_r($params, true));
        }

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($result['result'])) {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Add package result: ' . print_r($result['result'], true));
                }
                return array('result' => $result['result']);
            } else {
                if (isset($result['error']) && 'yes' === $this->debug) {
                    $this->log->add($this->id, 'Add Package Error: ' . $result['error']['message']);
                }
                return array('error' => $result['error']['message']);
            }
        }

        return false;
    }

    public function confirmShipment($shipmentId) {

        $method = 'confirmShipment';
        $params = array(
            'id' => $shipmentId
        );

        $response = $this->request(array(
            'method' => $method,
            'params' => $params
                ), false);

        if ('yes' === $this->debug) {
            $this->log->add($this->id, 'Confirm Shipment request: ' . print_r($params, true));
        }

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Confirm Shipment result: ' . print_r($result['result'], true));
                }
                return array('result' => $result['result']);
            } else {
                if (isset($result['error']) && 'yes' === $this->debug) {
                    $this->log->add($this->id, 'Confirm Shipment Error: ' . $result['error']['message']);
                }
                return array('error' => $result['error']['message']);
            }
        }

        return false;
    }

    public function get_package($id, $external_id = null) {

        $params = array();

        if (!empty($id)) {
            $params['id'] = intval($id);
        }

        if (!empty($external_id)) {
            $params['external_id'] = esc_attr($external_id);
        }

        $response = $this->request(array(
            'method' => 'getPackage',
            'params' => $params
                ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                return $result['error']['message'];
            }
        }

        return false;
    }

    public function get_products($id) {

        $response = $this->request(array(
            'method' => 'getProducts',
            'params' => array(
                'shopArticle' => sanitize_text_field($id)
            )
                ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Get products error: ' . $result['error']['message']);
                }
                return $result['error']['message'];
            }
        }

        return false;
    }

    public function add_product($the_product) {

        $product = wc_get_product($the_product);

        $_article = sanitize_text_field(get_post_meta($product->get_id(), '_article', true));
        $_fragile = sanitize_key(get_post_meta($product->get_id(), '_fragile', true));
        $_danger = sanitize_key(get_post_meta($product->get_id(), '_danger', true));
        $_perishable = sanitize_key(get_post_meta($product->get_id(), '_perishable', true));
        $_neebox = sanitize_key(get_post_meta($product->get_id(), '_need_box', true));

        $article = !empty($_article) ? $_article : ( $product->get_sku() ? $product->get_sku() : $product->get_id() );

        $response = $this->request(array(
            'method' => 'addProduct',
            'params' => array(
                'name' => $product->get_name(),
                'article' => $article,
                'shopArticle' => $article,
                'url' => $product->get_permalink(),
                'length' => $product->get_length() > 0 ? wc_get_dimension($product->get_length(), 'cm') : wc_shiptor_get_option('minimum_length'),
                'width' => $product->get_width() > 0 ? wc_get_dimension($product->get_width(), 'cm') : wc_shiptor_get_option('minimum_width'),
                'height' => $product->get_height() > 0 ? wc_get_dimension($product->get_height(), 'cm') : wc_shiptor_get_option('minimum_height'),
                'weight' => $product->get_weight() > 0 ? wc_get_weight($product->get_weight(), 'kg') : wc_shiptor_get_option('minimum_weight'),
                'price' => $product->get_price() > 0 ? wc_format_decimal($product->get_price(), 2) : 0,
                'fragile' => wc_string_to_bool($_fragile),
                'danger' => wc_string_to_bool($_danger),
                'perishable' => wc_string_to_bool($_perishable),
                'needBox' => wc_string_to_bool($_neebox)
            )
                ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Add product error: ' . $result['error']['message']);
                }
                return $result['error']['message'];
            }
        }

        return false;
    }

    public function get_shipping_methods() {

        $response = $this->request(array(
            'method' => 'getShippingMethods',
            'params' => array()
        ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Get service error: ' . $result['error']['message']);
                }
                return $result['error']['message'];
            }
        }

        return false;
    }

    public function get_service() {

        $response = $this->request(array(
            'method' => 'getServices',
            'params' => array()
        ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Get service error: ' . $result['error']['message']);
                }
                return $result['error']['message'];
            }
        }

        return false;
    }

    public function add_service($name = '', $article = '') {

        $response = $this->request(array(
            'method' => 'addService',
            'params' => array(
                'name' => $name,
                'shopArticle' => $article,
                'price' => 0
            )
        ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Add service error: ' . $result['error']['message']);
                }
                return $result['error']['message'];
            }
        }

        return false;
    }

    public function get_stock($warehouse_id) {
        $stocks = $this->get_stocks();

        $stock = array_filter($stocks, function($item) use ($warehouse_id) {
            return $item['id'] == $warehouse_id;
        });

        return reset($stock);
    }

    public function get_stocks() {
        $response = $this->request(array(
            'method' => 'getStocks',
            'params' => array()
        ), false);

        if (is_wp_error($response)) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'WP_Error: ' . $response->get_error_message());
            }
        } elseif (200 == wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Get stocks error: ' . $result['error']['message']);
                }
                return [];
            }
        }

        return [];
    }

    public function request($params = array(), $is_public = true) {
        $api_url = $is_public ? 'https://api.shiptor.ru/public/v1' : 'https://api.shiptor.ru/shipping/v1';

        $params = wp_parse_args($params, array(
            'id' => 'JsonRpcClient.js',
            'jsonrpc' => '2.0',
            'method' => '',
            'params' => array()
        ));

        $http_args = array(
            'method' => 'POST',
            'timeout' => MINUTE_IN_SECONDS,
            'redirection' => 0,
            'httpversion' => '1.1',
            'user-agent' => sprintf('WooCommerce/%s (WordPress/%s)', WC_VERSION, $GLOBALS['wp_version']),
            'body' => trim(wp_json_encode($params)),
            'headers' => array('Content-Type' => 'application/json'),
            'cookies' => array(),
        );

        $http_args = apply_filters('woocommerce_shiptor_request_http_args', $http_args, $params);
        $http_args['headers']['Integration-Name'] = 'WooCommerce';
        $http_args['headers']['Integration-Version'] = WC_SHIPTOR_VERSION;
        $http_args['headers']['X-Authorization-Token'] = apply_filters('woocommerce_shiptor_api_token', '');

        if(wc_shiptor_get_option('enable_requests_caching') == 'yes'){
            $hash_request = $this->hash_request(apply_filters('woocommerce_shiptor_webservice_url', $api_url, $is_public), $http_args);

            $response = $this->cache->retrieve($hash_request);
            if(!$response){
                $response = wp_safe_remote_request(apply_filters('woocommerce_shiptor_webservice_url', $api_url, $is_public), $http_args);

                if ('yes' == wc_shiptor_get_option('enable_common_log')) {
                    $result = json_decode(wp_remote_retrieve_body($response), true);
                    $this->addToLog($params, $result);
                }

                try{
                    if( is_wp_error( $response ) ){
                        echo esc_html($response->get_error_message());
                    }elseif($response["http_response"]->get_status() == 200){
                        $cache_time = 24 * 60 * 60;
                        $cache_config = WC_SHIPTOR_CACHE_CONFIG_TIME;
                        $defined_methods = $cache_config['defined_methods'];
                        if(array_key_exists($params['method'], $defined_methods)){
                            $cache_time = $defined_methods[ $params['method'] ];
                        } else {
                            $cache_time = $cache_config['common_cache_time'];
                        }
                        $this->cache->store($hash_request, $response, $cache_time);
                    }
                }catch(Exception $e){}
            }
        } else {
            $response = wp_safe_remote_request(apply_filters('woocommerce_shiptor_webservice_url', $api_url, $is_public), $http_args);

            if ('yes' == wc_shiptor_get_option('enable_common_log')) {
                $result = json_decode(wp_remote_retrieve_body($response), true);
                $this->addToLog($params, $result);
            }
        }

        return $response;
    }

    protected function hash_request($url, $params = []){
        $params = wp_json_encode($params);

        return sha1($url . $params);
    }

    protected function addToLog($params, $response){
        $record = WC_Shiptor_Log::prepareRecord($params, $response);
        WC_Shiptor_Log::add($record);
    }

}
