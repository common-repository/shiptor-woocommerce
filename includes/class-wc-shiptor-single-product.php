<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Single_Product {

    public function __construct() {
        if ('yes' !== wc_shiptor_get_option('calculate_in_product')) {
            return;
        }
        add_action('woocommerce_single_product_summary', array($this, 'calculate_shipping'), 35);
        add_action('wp_ajax_shiptor_get_shipping_methods', array($this, 'get_methods'));
        add_action('wp_ajax_nopriv_shiptor_get_shipping_methods', array($this, 'get_methods'));
        add_action('wp_enqueue_scripts', array($this, 'load_scripts'));

        //
        add_action('wp_ajax_catch_check_box_val', array($this, 'check_box_value'));
        add_action('wp_ajax_nopriv_catch_check_box_val', array($this, 'check_box_value'));

        add_action('wp_ajax_shipping_methods_depend_product_count', array($this, 'change_product_count'));
        add_action('wp_ajax_nopriv_shipping_methods_depend_product_count', array($this, 'change_product_count'));
    }

    public function load_scripts() {
        global $post;

        if (!did_action('before_woocommerce_init')) {
            return;
        }

        wp_register_style('shiptor-product', plugins_url('/assets/frontend/css/product.css', WC_Shiptor::get_main_file()), array('select2'));
        wp_register_style('simplebar', '/assets/frontend/css/simplebar/simplebar.css', array());
        wp_register_style('font-awesome', '/assets/frontend/css/font-awesome/font-awesome.min.css', array());
        wp_register_script('simplebar', '/assets/frontend/js/simplebar/simplebar.js', array(), WC_SHIPTOR_VERSION, true);
        wp_register_script('shiptor-calculate-shipping', plugins_url('/assets/frontend/js/product.js', WC_Shiptor::get_main_file()), array('jquery', 'wp-util', 'selectWoo', 'jquery-blockui', 'underscore', 'backbone'), WC_SHIPTOR_VERSION, true);
        wp_register_script('cod-declared-cost-checkbox', plugins_url('/assets/frontend/js/cod_dec_cost_checkbox.js', WC_Shiptor::get_main_file()), array('jquery'), WC_SHIPTOR_VERSION, true);

        if (is_product()) {

            wp_enqueue_style('shiptor-product');
            wp_enqueue_style('simplebar');
            wp_enqueue_style('font-awesome');
            wp_enqueue_script('shiptor-calculate-shipping');
            wp_enqueue_script('simplebar');
            wp_enqueue_script('cod-declared-cost-checkbox');

            wp_localize_script('shiptor-calculate-shipping', 'shiptor_product_shipping', array(
                'ajax_url' => WC_AJAX::get_endpoint('shiptor_autofill_address'),
                'i18n' => array(
                    'select_state_text' => esc_attr__('Select an option&hellip;', 'woocommerce'),
                    'no_matches' => _x('No matches found', 'enhanced select', 'woocommerce'),
                    'ajax_error' => _x('Loading failed', 'enhanced select', 'woocommerce'),
                    'input_too_short_1' => _x('Please enter 1 or more characters', 'enhanced select', 'woocommerce'),
                    'input_too_short_n' => _x('Please enter %qty% or more characters', 'enhanced select', 'woocommerce'),
                    'input_too_long_1' => _x('Please delete 1 character', 'enhanced select', 'woocommerce'),
                    'input_too_long_n' => _x('Please delete %qty% characters', 'enhanced select', 'woocommerce'),
                    'selection_too_long_1' => _x('You can only select 1 item', 'enhanced select', 'woocommerce'),
                    'selection_too_long_n' => _x('You can only select %qty% items', 'enhanced select', 'woocommerce'),
                    'load_more' => _x('Loading more results&hellip;', 'enhanced select', 'woocommerce'),
                    'searching' => _x('Searching&hellip;', 'enhanced select', 'woocommerce'),
                    'choose_city_text' =>  esc_html__('Choose an city', 'woocommerce-shiptor'),
                    'no_shipping_methods_text' =>  esc_html__('There are no delivery methods to your region for the specified shopping cart settings.', 'woocommerce-shiptor')
                ),
                'myajax' => array(
                    'url' => admin_url('admin-ajax.php')
                ),
                'location' => array(
                    'id' => wc_shiptor_get_customer_kladr(),
                    'country' => WC()->customer->get_billing_country()
                ),
                'is_variable_product' => $this->is_variable_product($post->ID),
                'methods' => $this->get_shipping($post->ID),
                'nonce' => wp_create_nonce('wc_shiptor_shipping_methods_nonce'),
                'post_id' => $post->ID
            ));
        }
    }

    function calculate_shipping() {
        global $product;
        wc_get_template('single-product/calculate-shipping.php', array(
            'product' => $product
                ), '', WC_Shiptor::get_templates_path());
    }

    /**
     * Check product is variation or not
     *
     * @param $product_id
     * @return bool
     */
    function is_variable_product($product_id) {
        $product = wc_get_product($product_id);
        return $product->is_type('variable');
    }

    /**
     * Get enabled shipping methods with shipping price, days
     *
     * @param $product_id
     * @param int $product_count
     * @return array
     */
    private function get_shipping($product_id, $product_count = 1) {
        global $wpdb;

        $methods = array();
        $product = wc_get_product($product_id);

        $regular_price = $product->get_price();
        $is_variable_product = $product->is_type('variable');

        $is_enabled_cod_cost = wc_shiptor_get_option('cod_declared_cost');

        if ($is_variable_product && get_option('variation_product_price')) {
            $regular_price = get_option('variation_product_price');
        }

        $package['contents'][] = array(
            'data' => $product,
            'quantity' => $product_count
        );

        //Преверяем есть ли хотя бы один включенный метод WC_Shiptor_Shipping_Host_Method
        $shippings = array();
        $shiptor_method_id = null;
        $shipping_methods  = WC()->shipping()->get_shipping_methods();
        foreach( $shipping_methods as $shipping_method ) {
            if( $shipping_method instanceof WC_Shiptor_Shipping_Host_Method ) {
                $shiptor_method_id = $shipping_method->id;
                break;
            }
        }

        if($shiptor_method_id) {
            //Берем все включенные методы и инизиализируем их с instance_id
            $raw_methods_sql = "SELECT method_id, method_order, instance_id, is_enabled FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = %s AND is_enabled = 1";
            $raw_methods = $wpdb->get_results( $wpdb->prepare( $raw_methods_sql, $shiptor_method_id ) );

            foreach($raw_methods as $raw_method) {
                $shipping_method = new WC_Shiptor_Shipping_Host_Method( $raw_method->instance_id );
                $enabled_client_methods = $shipping_method->get_enabled_client_methods();

                //Получить из API доступные варианты для метода
                foreach($enabled_client_methods as $key => $enabled_client_method){
                    $shippings[$key] = array();
                    $shippings[$key]['settings'] = $enabled_client_method;
                    $shippings[$key]['methods'] = array();

                    $connect = new WC_Shiptor_Connect('calculate_product');
                    $connect->set_kladr_id(wc_shiptor_get_customer_kladr());
                    $connect->set_country_code(WC()->customer->get_billing_country());

                    if ($product_count > 1) {
                        $declared_cost = $cod = (int) $regular_price * $product_count;
                    } else {
                        $declared_cost = $cod = (int) $regular_price;
                    }

                    $connect->set_cod($cod);
                    $connect->set_declared_cost($declared_cost);
                    $connect->set_package($package);

                    if ($shipping_method->methodIsEndToEnd($enabled_client_method->data) && !empty($enabled_method->sender_city)) {
                        $connect->set_kladr_id_from($enabled_client_method->sender_city);
                    } elseif ( !empty($enabled_client_method->data['courier']) ) {
                        $connect->set_courier($enabled_client_method->data['courier']);
                    }

                    $api_methods = $connect->get_shipping();
                    foreach($api_methods as $api_method){
                        if ( !empty($enabled_client_method->data['group_courier']) && ($api_method['method']['group'] == $enabled_client_method->data['group_courier']) ) {
                            $shippings[$key]['methods'][] = $api_method;
                        }
                    }
                }
            }
        }

        $end_result = array();
        foreach($shippings as $key => $shipping){
            if( !count($shipping['methods']) ){
                continue;
            }

            $settings = $shipping['settings'];
            $items = $shipping['methods'];
            foreach($items as $item){
                $posts = get_option('woocommerce_shiptor-integration_settings');
                $round_delivery_cost = $posts['round_delivery_cost'];
                $round_type = $posts['round_type'];
                if ($round_delivery_cost != '2' && $round_delivery_cost != null):
                    $round = $round_type == 0 ? 'round' : ( $round_type == 1 ? 'floor' : 'ceil' );
                    $item['cost']['total']['sum'] = $round_delivery_cost == 0 ? ( $round($item['cost']['total']['sum']) ) : ( $round_delivery_cost == -1 ? ( $round($item['cost']['total']['sum']/10) )*10 : ( $round($item['cost']['total']['sum']/100) )*100);
                endif;
                $end_result[] = array(
                    'fix_cost' => $settings->fix_cost,
                    'fee' => $settings->fee,
                    'additional_time' => $settings->additional_time,
                    'show_delivery_time' => $settings->show_delivery_time,
                    'free' => $settings->free,
                    'enable_declared_cost' => $settings->enable_declared_cost,
                    'cityes_limit' => $settings->cityes_limit,
                    'cityes_list' =>$settings->cityes_list,
                    'method_id' =>$settings->method_id,
                    'name' => $settings->title ?: $item['method']['name'],
                    'status' => $item['status'],
                    'total' => $item['cost']['total']['sum'],
                    'currency' => $item['cost']['total']['currency'],
                    'readable' => $item['cost']['total']['readable'],
                    'days' => $item['days'],
                    'cost' => $item['cost']['services'],
                    'free_shipping_text' => ''
                );
            }
        }

        $methods = array();
        foreach ($end_result as $service) {
            if (!empty($service) && is_array($service)) {
                if ('ok' !== $service['status']) {
                    continue;
                }

                if ( ($round_delivery_cost == null) || ($round_delivery_cost == '2') && ($is_enabled_cod_cost === 'no') ) {
                    foreach ($service['cost'] as $val) {
                        switch ($val['service']) {

                            case 'cost_declaring':
                                if ($service['enable_declared_cost'] === 'no' || $service['enable_declared_cost'] === '') {
                                    $service['total'] = $service['total'] - $val['sum'];
                                }
                                break;

                            case 'cod':
                                $service['total'] = $service['total'] - $val['sum'];
                                break;
                        }
                    }
                }

                if (isset($service['fix_cost']) && $service['fix_cost'] != 0) {
                    $price = $service['fix_cost'];
                } elseif (isset($service['fee']) && $service['fee'] != 0) {
                    if (substr($service['fee'], -1) == '%') {
                        $price = $service['total'] + ($service['total'] * intval($service['fee'])) / 100;
                        $price = ceil($price);
                    } else {
                        $price = $service['fee'] + $service['total'];
                    }
                } else {
                    $price = $service['total'];
                }
                if (isset($service['show_delivery_time']) && 'no' === $service['show_delivery_time']) {
                    $service['days'] = '';
                } elseif (isset($service['additional_time']) && $service['additional_time'] > 0 && $service['days'] !== null) {
                    $service['days'] = wc_shiptor_get_estimating_delivery($service['name'], $service['days'], $service['additional_time']);
                    $service['days'] = strstr($service['days'], '(');
                }

                if ($service['free'] && (int) $service['free'] != 0) {
                    if ($regular_price >= (int) $service['free']) {
                        $service['free_shipping_text'] = sprintf( esc_html__('Free shipping', 'woocommerce-shiptor'));
                    }
                }

                $methods[] = array(
                    'term_id' => $service['method_id'],
                    'name' => $service['name'],
                    'cost' => $service['free_shipping_text'] ? $service['free_shipping_text'] : sprintf( esc_html__('From: %d %s', 'woocommerce-shiptor'), round($price, 0), 'руб.'),
                    'days' => $service['days'] ? $service['days'] : ''
                );
            }
        }

        return $methods;
    }

    /**
     * Create new option name in wp_options table,
     * to save variation product price
     *
     */
    public function check_box_value() {
        update_option('variation_product_price', sanitize_text_field($_POST['varPrice']) );

        $count = intval($_POST['qty']);
        $calculated_shippings = $this->get_shipping(intval($_POST['postId']), $count);
        $data = array(
            'shipping' => $calculated_shippings
        );
        echo wp_json_encode($data); // WPCS: XSS ok.
        exit;
    }

    /**
     * After change product count, recalculate shipping prices
     */
    public function change_product_count() {
        $product_count = intval($_POST['qty']);
        $calculated_shippings = $this->get_shipping( intval($_POST['postId']), $product_count);
        $data = array(
            'shipping' => $calculated_shippings
        );
        echo wp_json_encode($data); // WPCS: XSS ok.
        exit;
    }

    public function get_methods() {
        if (!isset($_POST['security'], $_POST['params'], $_POST['post_id'])) {
            wp_send_json_error('missing_fields');
            exit;
        }

        if (!wp_verify_nonce(sanitize_key($_POST['security']), 'wc_shiptor_shipping_methods_nonce')) {
            wp_send_json_error('bad_nonce');
            exit;
        }

        $changes = wc_clean($_POST['params']);
        wc_shiptor_set_customer_kladr($changes['id']);

        wp_send_json_success(array(
            'methods' => $this->get_shipping(intval($_POST['post_id']), intval($_POST['qty']) )
        ));
    }

}

new WC_Shiptor_Single_Product();
?>