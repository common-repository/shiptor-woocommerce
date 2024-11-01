<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Checkout {

    public function __construct() {
        add_filter('woocommerce_default_address_fields', array($this, 'default_address_fields'));
        add_filter('woocommerce_get_country_locale', array($this, 'country_locale'), 20);
        add_filter('woocommerce_form_field_hidden', array($this, 'form_field_hidden'), 10, 4);
        add_filter('woocommerce_checkout_get_value', array($this, 'checkout_get_value'), 10, 4);
        add_action('woocommerce_checkout_update_order_review', array($this, 'update_order_review'));
        add_filter('woocommerce_cart_shipping_packages', array($this, 'shipping_packages'));
        add_action('woocommerce_calculated_shipping', array($this, 'calculated_shipping'));
        add_filter('woocommerce_update_order_review_fragments', array($this, 'order_review_fragments'));
        add_filter('woocommerce_package_rates', array($this, 'package_rates'), 10);

        add_action('wp_enqueue_scripts', array($this, 'load_scripts'));

        add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');
        add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
        add_action('wc_ajax_set_delivery_point', array($this, 'set_delivery_point'));
        add_filter('woocommerce_shipping_package_name', array($this, 'shipping_package_name'), 10, 3);
        add_filter('woocommerce_shipping_rate_label', array($this, 'shipping_rate_label'), 10, 2);
        add_action('woocommerce_checkout_create_order', array($this, 'checkout_create_order'), 10, 2);
        add_action('woocommerce_shipping_method_chosen', array($this, 'reset_shipping_method_chosen'));
        add_filter('woocommerce_shipping_chosen_method', array($this, 'reset_default_shipping_chosen_method'), 10, 3);
        add_filter('woocommerce_states', array($this, 'add_germany_states'));
        add_action( 'woocommerce_after_checkout_form', array($this, 'get_delivery_points_html'), 15 );
        add_action( 'woocommerce_after_shipping_rate', array($this, 'add_delivery_point_selector'));
        add_action( 'woocommerce_before_checkout_form', array($this, 'clear_stored_rates'));

    }

    public function add_germany_states($states) {

        $states['DE'] = array(
            'BW' => 'Baden-Württemberg',
            'BA' => 'Bavaria (Freistaat Bayern)',
            'BE' => 'Berlin',
            'BD' => 'Brandenburg',
            'BR' => 'Bremen (Freie Hansestadt Bremen)',
            'HA' => 'Hamburg (Freie und Hansestadt Hamburg)',
            'HS' => 'Hesse (Hessen)',
            'LS' => 'Lower Saxony (Niedersachsen)',
            'MV' => 'Mecklenburg-Vorpommern',
            'RW' => 'North Rhine-Westphalia (Nordrhein-Westfalen)',
            'RP' => 'Rhineland-Palatinate (Rheinland-Pfalz)',
            'SR' => 'Saarland',
            'SX' => 'Saxony (Freistaat Sachsen)',
            'SA' => 'Saxony-Anhalt (Sachsen-Anhalt)',
            'SH' => 'Schleswig-Holstein',
            'TH' => 'Thuringia (Freistaat Thüringen)',
        );

        return $states;
    }

    public function load_scripts() {
        $payments = WC()->payment_gateways->payment_gateways();
        $cod_title = $payments['cod']->title ?:  esc_html__('Cash on delivery', 'woocommerce-shiptor');
        $cod_card_title = $payments['cod_card']->title ?:  esc_html__('Payment by card', 'woocommerce-shiptor');

        $ym_apikey = get_option('woocommerce_shiptor-integration_settings');
        $ym_api_args = array('lang' => get_locale());
        if ( !empty ($ym_apikey['yandex_api_token']) ) {
            $ym_api_args['apikey'] = $ym_apikey['yandex_api_token'];
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $ym_api_args['mode'] = 'debug';
        }

        wp_register_script('shiptor-checkout', plugins_url('/assets/frontend/js/checkout.js', WC_Shiptor::get_main_file()), array('jquery'), WC_SHIPTOR_VERSION, true);
        wp_register_script('yandex-map', add_query_arg($ym_api_args, '//api-maps.yandex.ru/2.1'), array('shiptor-checkout'), '2.1', true);
        wp_register_style('shiptor', plugins_url('/assets/frontend/css/shiptor.css', WC_Shiptor::get_main_file()), array());
        wp_register_style('shiptor-checkout', plugins_url('/assets/frontend/css/checkout.css', WC_Shiptor::get_main_file()), array());
        wp_localize_script('shiptor-checkout', 'shiptor_checkout_params', array(
            'delivery_point_url' => WC_AJAX::get_endpoint('set_delivery_point'),
            'cod_title' => $cod_title,
            'cod_card_title' => $cod_card_title,
            'ym_apikey' => $ym_apikey['yandex_api_token'],
        ));

        wp_register_style(
            'jquery-modal',
            plugins_url('/assets/frontend/css/jquery-modal/jquery.modal.min.css', WC_Shiptor::get_main_file())
        );

        wp_register_script(
            'jquery-modal',
            plugins_url('/assets/frontend/js/jquery-modal/jquery.modal.min.js', WC_Shiptor::get_main_file()),
            array('jquery')
        );

        if (is_checkout()) {
            wp_enqueue_style('shiptor');
            wp_enqueue_style('shiptor-checkout');
            wp_enqueue_style('jquery-modal');
            wp_enqueue_script('jquery-modal');
            wp_enqueue_script('yandex-map');
            wp_enqueue_script('shiptor-checkout');
        }

        if (is_account_page() || is_customize_preview()) {
            wp_enqueue_style('shiptor');
        }
    }

    public function default_address_fields($fields) {
        $city = WC_Shiptor_Autofill_Addresses::get_city_by_id(wc_shiptor_get_customer_kladr());

        if (!$city || !isset($city['kladr_id']) || !$city['kladr_id']) {
            $city = WC_SHIPTOR_DEFAULT_CITY;
        }

        if ($city && isset($city['kladr_id']) && $city['kladr_id']) {
            if ( WC()->customer && $city['country'] && $city['country'] != WC()->customer->get_billing_country() ) {
                WC()->customer->set_billing_country($city['country']);
            }

            $fields['country']['default'] = isset($city['country']) ? $city['country'] : '';

            // admin page or checkout page
            if (is_admin() || WC()->customer && in_array(WC()->customer->get_billing_country(), array('RU', 'KZ', 'BY'))) {
                if (function_exists('wc_get_chosen_shipping_method_ids')) {
                    $required = in_array('local_pickup', wc_get_chosen_shipping_method_ids()) ? false : true;
                } else {
                    $required = true;
                }

                $city_option = array($city['city_name'] => sprintf('%s (%s)', $city['city_name'], $city['state']));
                $fields['city'] = array(
                    'label' =>  esc_html__('Town / City', 'woocommerce'),
                    'required' => $required,
                    'class' => array('form-row-wide', 'address-field'),
                    'validate' => array('state'),
                    'default' => $city['city_name'],
                    'type' => 'select',
                    'input_class' => array('city_select'),
                    'options' => $city_option,
                    'priority' => $fields['country']['priority'] + 1
                );
            }
        }

        $country = isset($_POST['s_country']) ? sanitize_text_field($_POST['s_country']) : 'RU';
        $required = !in_array($country, array('RU', 'KZ', 'BY'));
        $fields['state'] = array(
            'type' => 'text',
            'label' =>  esc_html__('State / County', 'woocommerce'),
            'required' => $required,
            'class' => array('form-row-hidden')
        );

        $fields['kladr_id'] = array(
            'type' => 'hidden',
            'class' => array('form-row-hidden'),
            'label' =>  esc_html__('KLADR ID', 'woocommerce-shiptor'),
            'default' => wc_shiptor_get_customer_kladr()
        );

        $fields['to_door_address'] = array(
            'type' => 'hidden',
            'class' => array('form-row-hidden'),
            'label' => 'Адрес до двери',
            'default' => WC()->customer ? WC()->customer->get_billing_address_1() : '',
        );

        unset($fields['address_2']);

        return apply_filters('woocommerce_shiptor_default_address_fields', $fields, $this);
    }

    public function country_locale($locale) {
        foreach (array('RU', 'KZ', 'BY') as $iso) {
            $locale[$iso]['postcode']['required'] = false;
            $locale[$iso]['postcode']['hidden'] = false;
        }

        foreach (WC()->countries->get_shipping_countries() as $country) {
            if (in_array($country, array('RU', 'KZ', 'BY'))) {
                continue;
            }

            $locale[$country]['state']['required'] = true;
            $locale[$country]['state']['default'] = WC()->customer ? WC()->customer->get_billing_state() : '';
        }

        return $locale;
    }

    public function form_field_hidden($field, $key, $args, $value) {
        if (is_null($value)) {
            $value = $args['default'];
        }

        $sort = $args['priority'] ? $args['priority'] : '';
        $container_class = esc_attr(implode(' ', $args['class']));
        $container_id = esc_attr($args['id']) . '_field';
        $field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr($sort) . '">%3$s</p>';
        $field_html = sprintf('<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />', esc_attr($key), esc_attr($value));
        $field = sprintf($field_container, $container_class, $container_id, $field_html);

        return $field;
    }

    public function checkout_get_value($value, $input) {
        if( $input == 'billing_kladr_id' ) {
            $value = wc_shiptor_get_customer_kladr();
        }

        return $value;
    }

    public function update_order_review($post_data) {
        $post_data = wp_parse_args($post_data);
        wc_shiptor_set_customer_kladr($post_data['billing_kladr_id']);

        WC()->session->set('to_door_address', $post_data['billing_to_door_address']);
    }

    public function calculated_shipping() {
        wc_shiptor_set_customer_kladr( sanitize_text_field($_POST['calc_shipping_kladr_id']) );
    }

    public function shipping_package_name($name, $i, $package) {
        if ('yes' == wc_shiptor_get_option('shipping_class_sort')) {
            $cart_items = $package['contents'];
            $get_classes = WC()->shipping->get_shipping_classes();
            $name =  esc_html__('Shipping other class', 'woocommerce-shiptor');
            foreach ($cart_items as $item) {
                $shipping_class = $item['data']->get_shipping_class();
                if (isset($shipping_class) && $shipping_class != '') {
                    foreach ($get_classes as $class) {
                        if ($class->slug == $shipping_class) {
                            $name =  esc_html__('Shipping', 'woocommerce') . ': ' . $class->name;
                            break;
                        }
                    }
                }
            }
        }
        return $name;
    }

    public function shipping_rate_label($label, $rate) {

        if (is_cart() || is_checkout()) {
            $meta = $rate->get_meta_data();
            if (isset($meta['shiptor_method']) && !empty($meta['shiptor_method']['label'])) {
                $label = $meta['shiptor_method']['label'];
            }
        }

        return $label;
    }

    public function shipping_packages($packages) {

        if ('yes' == wc_shiptor_get_option('shipping_class_sort')) {
            $packages = array();
            $shipping_classes = $other = array();
            $get_classes = WC()->shipping->get_shipping_classes();

            foreach ($get_classes as $key => $class) {
                $shipping_classes[$class->term_id] = $class->slug;
                $array_name = $class->slug;
                $$array_name = array();
            }

            $shipping_classes['misc'] = 'other';

            foreach (WC()->cart->get_cart() as $item) {
                if ($item['data']->needs_shipping()) {
                    $item_class = $item['data']->get_shipping_class();
                    if (isset($item_class) && $item_class != '') {
                        foreach ($shipping_classes as $class_id => $class_slug) {
                            if ($item_class == $class_slug) {
                                array_push($$class_slug, $item);
                            }
                        }
                    } else {
                        $other[] = $item;
                    }
                }
            }

            $n = 0;

            foreach ($shipping_classes as $key => $value) {
                if (count($$value)) {
                    $packages[$n] = array(
                        'contents' => $$value,
                        'contents_cost' => array_sum(wp_list_pluck($$value, 'line_total')),
                        'applied_coupons' => WC()->cart->applied_coupons,
                        'payment_method' => WC()->session->get('chosen_payment_method'),
                        'destination' => array(
                            'country' => WC()->customer->get_shipping_country(),
                            'state' => WC()->customer->get_shipping_state(),
                            'postcode' => WC()->customer->get_shipping_postcode(),
                            'city' => WC()->customer->get_shipping_city(),
                            'address' => WC()->customer->get_shipping_address(),
                            'kladr_id' => wc_shiptor_get_customer_kladr()
                        )
                    );

                    $n++;
                }
            }
        } else {
            $new_packages = array();

            foreach ($packages as $index => $package) {
                $new_packages[$index] = $package;
                $new_packages[$index]['destination']['kladr_id'] = wc_shiptor_get_customer_kladr();
                $new_packages[$index]['payment_method'] = WC()->session->get('chosen_payment_method');
            }

            return $new_packages;
        }

        return $packages;
    }

    protected function get_delivery_points() {
        $points = array();

        $chosen_package = wc_shiptor_chosen_shipping_package();
        $chosen_rate = wc_shiptor_chosen_shipping_rate();

        if(!$chosen_package && !$chosen_rate){
            return $points;
        }

        $shipping_methods = WC()->shipping()->get_shipping_methods();
        foreach($shipping_methods as $shipping_method){
            if(!($shipping_method instanceof WC_Shiptor_Shipping_Host_Method)){
                continue;
            }

            $method_meta_data = $chosen_rate->get_meta_data();
            if( !isset($method_meta_data['shiptor_method']) && !isset($method_meta_data['shiptor_method']['category']) ){
                continue;
            }

            if( !$shipping_method->methodShoudHaveDeliveryPoints($method_meta_data['shiptor_method']) ) {
                continue;
            }

            if($shipping_method->id == $chosen_rate->get_method_id()){
                $shipping_method = new WC_Shiptor_Shipping_Host_Method( $chosen_rate->get_instance_id() );
                $points = $shipping_method->get_delivery_points($chosen_rate, $chosen_package);
                break;
            }
        }

        return $points;
    }

    public function get_delivery_points_html() {
        $points = $this->get_delivery_points();
        $points_count = count($points);
        $points = wp_json_encode($points, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_PARTIAL_OUTPUT_ON_ERROR );

        ob_start();
            require_once WC_SHIPTOR_TEMPLATE_DIR . '/checkout/modals/pickpoints.php';
        $view = ob_get_clean();

        echo $view; // WPCS: XSS ok.
    }

    public function order_review_fragments($fragments) {

        $packages = WC()->shipping->get_packages();
        $checkout_fields = WC()->checkout->get_checkout_fields('billing');
        $billing_address_args = array();

        if (isset($checkout_fields['billing_address_1'])) {
            $billing_address_args = $checkout_fields['billing_address_1'];
        }

        $billing_address_args['default'] = WC()->session->get('to_door_address');

        $delivery_points = $this->get_delivery_points();
        if ( !empty( $delivery_points ) ) {
            $billing_address_args['label'] = 'Адрес пункта выдачи';
            $billing_address_args['placeholder'] = 'Выберите пункт выдачи заказов на карте';
            $billing_address_args['custom_attributes'] = array('readonly' => 'readonly');
            $billing_address_args['default'] = null;

            $chosen_delivery_poin_id = WC()->session->get('chosen_delivery_point');
            foreach($delivery_points as $delivery_point){
                if($delivery_point['id'] == $chosen_delivery_poin_id){
                    $billing_address_args['default'] = $delivery_point['address'];
                    break;
                }
            }
        }

        ob_start();
        $this->get_delivery_points_html();
        $delivery_points = ob_get_clean();
        $fragments['#tmpl-wc-shiptor-pickpoints-map'] = $delivery_points;

        ob_start();
        wc_cart_totals_shipping_html();
        $fragments['.woocommerce-shipping-totals.shipping'] = ob_get_clean();

        if (!empty($billing_address_args)) {
            ob_start();
            woocommerce_form_field('billing_address_1', $billing_address_args);
            $fragments['#billing_address_1_field'] = ob_get_clean();
        }

        return $fragments;
    }

    public function package_rates($rates) {

        $sorting_type = wc_shiptor_get_option('sorting_type');

        if ('cost' == $sorting_type) {

            $prices = array();

            foreach ($rates as $key => $rate) {
                $prices[$key] = (float) $rate->cost;
            }

            array_multisort($prices, $rates);
        } elseif ('date' == $sorting_type) {
            $dates = array();
            foreach ($rates as $key => $rate) {
                $meta = $rate->get_meta_data();

                if ( isset($meta['shiptor_method'], $meta['shiptor_method']['date'], $meta['shiptor_method']['show_time']) ) {
                    if ('yes' == $meta['shiptor_method']['show_time'] ) {
                        $date = $meta['shiptor_method']['date'];
                    } else {
                        if ( $meta['shiptor_method']['date'] ) {
                            $date = $meta['shiptor_method']['date'] + current_time('timestamp', 1);
                        } else {
                            $date = current_time('timestamp', 1);
                        }
                    }
                } else {
                    $date = (float) $rate->cost;
                }

                $dates[$key] = $date;
            }

            array_multisort($dates, $rates);
        }

        return $rates;
    }

    public function set_delivery_point() {
        if (isset($_POST['delivery_point'])) {
            $delivery_point = sanitize_text_field($_POST['delivery_point']);
            WC()->session->set('chosen_delivery_point', $delivery_point);
            wp_send_json_success(sprintf('Значение %s установлено.', $delivery_point));
        }
        wp_send_json_error('Пустой запрос');
    }

    public function reset_shipping_method_chosen($chosen_method) {
        $chosen_shipping_methods = array($chosen_method);

        if (!empty($_POST) && isset($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {
            $shipping_method = wc_clean($_POST['shipping_method']);

            foreach ($shipping_method as $i => $value) {
                $chosen_shipping_methods[$i] = $value;
            }
        }

        WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
    }

    public function reset_default_shipping_chosen_method($default, $rates, $chosen_method) {
        if (in_array($chosen_method, array_keys($rates))) {
            $default = $default !== $chosen_method ? $chosen_method : $default;
        }
        return $default;
    }

    public function checkout_create_order($order, $data) {
        if (!is_a($order, 'WC_Order'))
            return;

        foreach ($order->get_items('shipping') as $shipping) {
            $shipping->delete_meta_data('shiptor_rate_hash');

            $choosen_shipping_method = null;
            $shipping_methods = get_shiptor_wc_instanced_methods();
            foreach($shipping_methods as $shipping_method){
                if(!($shipping_method instanceof WC_Shiptor_Shipping_Host_Method)){
                    continue;
                }

                if($shipping_method->id == $shipping->get_method_id()){
                    $choosen_shipping_method = $shipping_method;
                    break;
                }
            }

            if( !$choosen_shipping_method ){
                continue;
            }

            $delivery_points = $choosen_shipping_method->get_delivery_points($shipping, wc_shiptor_chosen_shipping_package());
            $chosen_delivery_point = WC()->session->get('chosen_delivery_point');

            $shiptor_method = $shipping->get_meta('shiptor_method');
            $should_have_dp = WC_Shiptor_Shipping_Host_Method::methodShoudHaveDeliveryPointsStatic($shiptor_method);

            if (!empty($delivery_points) && count($delivery_points)) {
                $shipping->add_meta_data('delivery_points', $delivery_points, true);

                if (empty($chosen_delivery_point) && $should_have_dp) {
                    throw new Exception(sprintf( esc_html__('For %s shipping method need choose delivery point', 'woocommerce-shiptor'), $shiptor_method['name']));
                } else {
                    if ($should_have_dp) {
                        $delivery_point = array_filter($delivery_points, function($item) use ($data){
                            return $item['address'] == $data['billing_address_1'];
                        });

                        $delivery_point = reset($delivery_point);
                        $chosen_delivery_point = $delivery_point['id'];
                        $find = wp_list_filter($delivery_points, array(
                            'id' => $chosen_delivery_point,
                            'kladr_id' => $data['billing_kladr_id']
                        ));

                        if (empty($find)) {
                            throw new Exception(sprintf( esc_html__('The delivery point selected can not be found in the city %s', 'woocommerce-shiptor'), $data['billing_city']));
                        }

                        $order->update_meta_data('_chosen_delivery_point', $chosen_delivery_point);
                    }
                }
            }
        }
    }

    public function add_delivery_point_selector($method){

        if(!is_checkout() || !is_ajax_referer_from_checkout()){
            return;
        }

        $method_is_choosen = false;
        $packages = WC()->shipping()->get_packages();

        foreach ( $packages as $i => $package ) {
            $chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
            if($method->id == $chosen_method){
                $method_is_choosen = true;
                break;
            }
        }

        if(!$method_is_choosen){
            return;
        }

        $shipping_methods  = WC()->shipping()->get_shipping_methods();
        foreach($shipping_methods as $key => $shipping_method){
            if (is_numeric($key)) {
                continue;
            }

            if(!($shipping_method instanceof WC_Shiptor_Shipping_Host_Method)){
                continue;
            }

            if($shipping_method->id != $method->get_method_id()){
                continue;
            }

            $method_meta_data = $method->get_meta_data();
            if( !isset($method_meta_data['shiptor_method']) && !isset($method_meta_data['shiptor_method']['category']) ){
                continue;
            }

            if( !$shipping_method->methodShoudHaveDeliveryPoints($method_meta_data['shiptor_method']) ) {
                continue;
            }

            $found_delivery_point = null;
            $chosen_delivery_poin_id = WC()->session->get('chosen_delivery_point');

            if($chosen_delivery_poin_id){
                $delivery_points = $this->get_delivery_points();
                foreach($delivery_points as $delivery_point){
                    if($delivery_point['id'] == $chosen_delivery_poin_id){
                        $found_delivery_point = $delivery_point;
                        break;
                    }
                }
            }

            if($found_delivery_point){
                printf('<div class="wc-shiptor-delivery-point-selector_wrapper"><a class="wc-shiptor-delivery-point-selector point-choosen" href="#">%1$s</a></div>', $found_delivery_point['address']);
            } else{
                WC()->session->set('chosen_delivery_point', null);
                printf('<div class="wc-shiptor-delivery-point-selector_wrapper"><a class="wc-shiptor-delivery-point-selector point-empty" href="#">%1$s</a></div>',  esc_html__('Choose pickpoint', 'woocommerce-shiptor'));
            }

        }
    }

    public function clear_stored_rates(){
        $packages = WC()->shipping()->get_packages();
        foreach($packages as $package_key => $package){
            $wc_session_key = 'shipping_for_package_' . $package_key;
            WC()->session->set(
                $wc_session_key,
                null
            );
            WC()->session->save_data();
        }
    }

}

return new WC_Shiptor_Checkout();
