<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 27.11.2017
 * Time: 23:28
 * Project: shiptor-woo
 */
class WC_Shiptor_Admin_Order {

    private $connect;

    public function __construct() {

        $this->connect = new WC_Shiptor_Connect('admin_order');
        $this->connect->set_debug(wc_shiptor_get_option('create_order_enable'));

        add_action('add_meta_boxes_shop_order', array($this, 'register_metabox'));
        add_filter('woocommerce_resend_order_emails_available', array($this, 'resend_tracking_code_email'));
        add_filter('woocommerce_order_actions', array($this, 'resend_tracking_code_actions'));
        add_action('woocommerce_order_action_shiptor_tracking', array($this, 'action_shiptor_tracking'));
        add_action('wp_ajax_woocommerce_shiptor_create_order', array($this, 'create_order_ajax'));
        add_action('wp_ajax_shiptor_send_order', array($this, 'create_single_order_ajax'));

        add_action('wp_ajax_getWeekendsHolidays', array($this, 'get_weekends_holidays'));


        add_action('wp_ajax_shiptor_get_order_info', array($this, 'shiptor_get_order_info'));
        add_action('wp_ajax_shiptor_update_order_info', array($this, 'shiptor_update_order_info'));

        add_action('woocommerce_order_before_calculate_totals', array($this, 'recalculate_totals'), 10, 2);

        if (defined('WC_VERSION') && version_compare(WC_VERSION, '3.0.0', '>=')) {
            add_filter('manage_edit-shop_order_columns', array($this, 'shiptor_order_column'));
            add_action('manage_shop_order_posts_custom_column', array($this, 'tracking_code_orders_list'), 100);
            add_filter('bulk_actions-edit-shop_order', array($this, 'define_bulk_actions'));
            add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
            add_action('admin_notices', array($this, 'bulk_admin_notices'));
        }

        add_action('admin_init', array($this, 'admin_init'));

        add_action('admin_print_styles', array($this, 'orders_load_style'));
        add_action('admin_enqueue_scripts', array($this, 'orders_load_scripts'));
        add_action('shiptor_shipping_methods', array($this, 'shipping_methods'));

        add_filter( 'post_class', array( $this, 'highlight_error' ), 10, 2 );
    }

    public function recalculate_totals($taxes, $order) {
        if (!empty($order->get_items('shipping'))) {
            $shipping = current($order->get_items('shipping'));
            $shiptor_method = $shipping->get_meta('shiptor_method');
            $by_pvz = !in_array($shiptor_method['category'], array(
                'to-door',
                'post-office',
                'door-to-door',
                'delivery-point-to-door'
            ));

            if (!empty($_POST['_payment_method'])) {
                $order->set_payment_method(sanitize_key($_POST['_payment_method']));
            }

            $connect = new WC_Shiptor_Connect('recalculate_totals');
            $this->set_data_to_calculate($order, $connect);

            $new_shipping = $connect->get_shipping();
            $new_shipping = array_filter($new_shipping, function($item) use ($shiptor_method) {
                return $item['method']['id'] == $shiptor_method['id'];
            });
            $new_shipping = reset($new_shipping);

            if (!$new_shipping && !empty($shiptor_method['id']) ) {
                return $this->delete_delivery_point($order,  esc_html__('Invalid delivery point, please choose a new point', 'woocommerce-shiptor'));
            }

            if ($by_pvz && !empty($shiptor_method['id'])) {
                // check new dimensions
                $_chosen_delivery_point = $order->get_meta('_chosen_delivery_point');
                $connect->set_shipping_method($new_shipping['method']['id']);
                $delivery_points = $connect->get_delivery_points();
                $point = array_filter($delivery_points, function($item) use ($_chosen_delivery_point) {
                    return $item['id'] == $_chosen_delivery_point;
                });
                $point = reset($point);

                if (!$point) {
                    return $this->delete_delivery_point($order,  esc_html__('Invalid delivery point dimensions, please choose a new point', 'woocommerce-shiptor'));
                }
            }

            $total = $new_shipping['cost']['total']['sum'];
            $posts = get_option('woocommerce_shiptor-integration_settings');
            $round_delivery_cost = $posts['round_delivery_cost'];
            $round_type = $posts['round_type'];
            if ($round_delivery_cost !== '2' && $round_delivery_cost !== null):
                $round = $round_type == 0 ? 'round' : ( $round_type == 1 ? 'floor' : 'ceil' );
                $total = $round_delivery_cost == 0 ? ( $round($total) ) : ( $round_delivery_cost == -1 ? ( $round($total/10) )*10 : ( $round($total/100) )*100);
            endif;
            $shipping->set_total($total);
            $shipping->save();
        }
    }

    protected function delete_delivery_point(&$order, $message) {
        $order->update_meta_data('_chosen_delivery_point', null);
        add_post_meta($order->ID, 'shiptor_order_error', 1, true);
        WC_Admin_Notices::add_custom_notice('shiptor_order_error', $message);

        $shipping = current($order->get_items('shipping'));
        $shipping->set_total(0);
        $shipping->save();
    }

    protected function set_data_to_calculate(&$order, &$connect) {
        $package = array();
        $products = $order->get_items();
        $total = 0;
        foreach ($products as $product) {
            $total += $product->get_total();
            $package['contents'][] = array(
                'data' => wc_get_product($product['product_id']),
                'quantity' => $product['qty']
            );
        }

        if(!empty($order->get_billing_country()) && !empty($order->get_payment_method())) {
            if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
                $cod = in_array($order->get_payment_method(), array('cod', 'cod_card')) ? $total : 0;
            } else {
                $cod = 0;
            }
        }else{
            $cod = $total;
        }

        $shipping = current($order->get_items('shipping'));
        $shiptor_method = $shipping->get_meta('shiptor_method');

        $connect->set_package($package);
        $connect->set_kladr_id($order->get_meta('_billing_kladr_id'));
        $connect->set_country_code($order->get_shipping_country());
        $connect->set_declared_cost($total);
        $connect->set_cod($cod);
        if ( !empty($shiptor_method['sender_city']) ) {
            $connect->set_kladr_id_from($shiptor_method['sender_city'] ?: wc_shiptor_get_option('city_origin'));
        }
        if ( !empty($shiptor_method['courier']) ) {
            $connect->set_courier($shiptor_method['courier']);
        }
        $method = new WC_Shiptor_Shipping_Client_Method();
        if ( !empty($shiptor_method['category']) ) {
            $connect->set_is_stock($method->shipmentType($shiptor_method['category']) == 'standard');
        }
    }

    public function shiptor_update_order_info() {
        check_ajax_referer('woocommerce-shiptor-edit-order', 'nonce');

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            return wp_send_json_error(array(
                'message' =>  esc_html__('Order not found', 'woocommerce-shiptor'),
            ));
        }

        $country = $order->get_shipping_country();
        $state = $order->get_shipping_state();
        $postcode = $order->get_shipping_postcode();
        $city = $order->get_shipping_city();

        // Order and order items
        $order_items    = $order->get_items();

        // Reset shipping first
        WC()->shipping()->reset_shipping();

        // Set correct temporary location

        WC()->customer->set_billing_location( $country, $state, $postcode, $city );
        WC()->customer->set_shipping_location( $country, $state, $postcode, $city );

        // Remove all current items from cart
        if ( sizeof( WC()->cart->get_cart() ) > 0 ) {
            WC()->cart->empty_cart();
        }

        // Add all items to cart
        foreach ($order_items as $order_item) {
            WC()->cart->add_to_cart($order_item['product_id'], $order_item['qty']);
        }
        // vars area
        $shipping = current($order->get_items('shipping'));
        $shipping_methods = $this->shipping_methods();
        $new_method = intval($_POST['shipping_method']);
        $new_method_info = array_filter((array)$shipping_methods, function($item) use ($new_method){
            return $item->method_id == $new_method;
        });
        $new_method_info = reset($new_method_info);
        $by_courier = in_array($new_method_info->data['category'], array(
            'to-door',
            'post-office',
            'door-to-door',
            'delivery-point-to-door'
        ));

        // check shipping method
        $shipping_method = intval($_POST['shipping_method']);
        if (!$shipping_method) {
            return wp_send_json_error(array(
                'message' =>  esc_html__('Shipping method required', 'woocommerce-shiptor'),
            ));
        }

        // check city
        $kladr_id = sanitize_key($_POST['kladr_id']);
        $city = WC_Shiptor_Autofill_Addresses::get_city_by_id($kladr_id);
        if (!$city) {
            return wp_send_json_error(array(
                'message' =>  esc_html__('City required', 'woocommerce-shiptor'),
            ));
        }

        // check delivery point
        if (!$by_courier) {
            // by pvz
            if (empty($_POST['chosen_delivery_point'])) {
                return wp_send_json_error(array(
                    'message' =>  esc_html__('Delivery point required', 'woocommerce-shiptor'),
                ));
            }

            // update delivery point
            $chosen_delivery_point = intval($_POST['chosen_delivery_point']);
            $order->update_meta_data('_chosen_delivery_point', $chosen_delivery_point);
        } else {
            // by courier
            if (empty($_POST['address_line'])) {
                return wp_send_json_error(array(
                    'message' =>  esc_html__('Address required', 'woocommerce-shiptor'),
                ));
            }

            // update address
            $order->set_billing_address_1(sanitize_text_field($_POST['address_line']));
        }

        // update city

        $order->set_billing_city($city['city_name']);
        $order->update_meta_data('_billing_kladr_id', $city['kladr_id']);

        $order->set_shipping_city($city['city_name']);
        $order->update_meta_data('_shipping_kladr_id', $city['kladr_id']);

        // update shipping_method
        $connect = new WC_Shiptor_Connect('shiptor_update_order_info');
        $this->set_data_to_calculate($order, $connect);
        $connect->set_shipping_method($new_method_info->method_id);

        $connect->set_card($order->get_payment_method() === 'cod_card');
        $connect->set_courier($new_method_info->data['courier']);

        $new_shipping = new WC_Order_Item_Shipping();
        $new_shipping->set_name($new_method_info->title);
        $new_shipping->set_method_title($new_method_info->title);

        if ( !empty($instance_id) ) {
            $new_shipping->set_instance_id($instance_id);
        }

        $new_shipping->set_method_id($new_method_info->method_id);
        $delivery_points = $connect->get_delivery_points();

        if (!empty($delivery_points)) {
            $new_shipping->add_meta_data('delivery_points', $delivery_points, true);
        }

        $new_shipping_data = $connect->get_shipping();
        $new_shipping_data = array_filter($new_shipping_data, function($item) use ($new_method_info) {
            return $item['method']['id'] == $new_method_info->method_id;
        });
        $new_shipping_data = reset($new_shipping_data);
        $meta_shiptor_method = $new_method_info->data;
        $meta_shiptor_method['name'] = $new_method_info->title;
        $meta_shiptor_method['group'] = $meta_shiptor_method['group_courier'];
        $meta_shiptor_method['comment'] = '';
        $meta_shiptor_method['constraints'] = array();
        $meta_shiptor_method['label'] = $new_method_info->title;
        $meta_shiptor_method['declared_cost'] = $new_shipping_data['cost']['total']['sum'];
        $posts = get_option('woocommerce_shiptor-integration_settings');
        $round_delivery_cost = $posts['round_delivery_cost'];
        $round_type = $posts['round_type'];

        if ($round_delivery_cost != '2' && $round_delivery_cost != null):
            $round = $round_type == 0 ? 'round' : ( $round_type == 1 ? 'floor' : 'ceil' );
            $meta_shiptor_method['declared_cost'] = $round_delivery_cost == 0 ? ( $round($meta_shiptor_method['declared_cost']) ) : ( $round_delivery_cost == -1 ? ( $round($meta_shiptor_method['declared_cost']/10) )*10 : ( $round($meta_shiptor_method['declared_cost']/100) )*100);
        endif;
        $meta_shiptor_method['show_time'] = $new_method_info->show_delivery_time;
        $meta_shiptor_method['sender_city'] = $new_method_info->sender_city;
        $meta_shiptor_method['sender_address'] = $new_method_info->sender_address;
        $meta_shiptor_method['sender_name'] = $new_method_info->sender_name;
        $meta_shiptor_method['sender_email'] = $new_method_info->sender_email;
        $meta_shiptor_method['sender_phone'] = $new_method_info->sender_phone;
        $new_shipping->add_meta_data('shiptor_method', $meta_shiptor_method);
        $new_shipping->save();

        $order->remove_order_items('shipping');
        $order->save();

        $order->add_item($new_shipping);
        $order->save();

        $newTotal = $order->calculate_totals();
        $order->set_total($newTotal);
        $order->save();

        return wp_send_json_success();
    }

    public function shiptor_get_order_info() {
        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            return wp_send_json_error(array(
                'message' =>  esc_html__('Order not found', 'woocommerce-shiptor'),
            ));
        }

        $shipping = current($order->get_items('shipping'));
        $shiptor_method = $shipping->get_meta('shiptor_method');
        if ( !empty($shiptor_method) ) {
            $by_courier = in_array($shiptor_method['category'], array(
                'to-door',
                'post-office',
                'door-to-door',
                'delivery-point-to-door'
            ));
        } else {
            $by_courier = true;
        }
        $delivery_points = $shipping->get_meta('delivery_points');
        $shipping_methods = $this->shipping_methods();

        ob_start();

        include('views/html-admin-edit-order-details.php');

        wp_send_json_success(array(
            'html' => ob_get_clean(),
        ));
    }

    public function highlight_error($classes) {
        global $post;

        if (get_post_meta($post->ID, 'shiptor_order_error', true)) {
            $classes[] = 'shiptor_order_error';
        }

        return $classes;
    }

    public function register_metabox($post) {
        $order = wc_get_order($post->ID);
        if ($this->shipping_is_shiptor($order)) {
            $shiptor_id = $order->get_meta('_shiptor_id');
            if ($shiptor_id) {
                add_meta_box('wc_shiptor_history',  esc_html__('Shiptor order history', 'woocommerce-shiptor'), array($this, 'render_history'), 'shop_order', 'side', 'default', array('shiptor_id' => $shiptor_id));
            }
        }
        if ( $order->get_status() != 'auto-draft' ) {
            add_meta_box('wc_shiptor_order', sprintf( esc_html__('Shiptor order %s', 'woocommerce-shiptor'), $order->get_order_number()), array($this, 'render_order'), 'shop_order', 'normal', 'high');
        }
    }

    public function get_weekends_holidays() {

        if (isset($_POST) && $_POST['action'] === 'getWeekendsHolidays') {
            $response = $this->connect->get_days_off();
            echo wp_json_encode($response); // WPCS: XSS ok.
            exit;
        }
    }

    /**
     * Return next working day after weekend/holiday
     *
     * @return string
     */
    function getNextWorkingDay() {
        $todayPlus1 = date('Y-m-d', strtotime('+1days') + wc_timezone_offset());
        $daysOff = $this->connect->get_days_off();
        if ($daysOff) {
            for ($i = 0, $count = count($daysOff); $i < $count; $i++) {
                if (in_array($todayPlus1, $daysOff)) {
                    $todayPlus1 = date('Y-m-d', strtotime($todayPlus1 . ' + 1days') + wc_timezone_offset());
                }
            }
        }

        return $todayPlus1;
    }

    public function render_history($post, $meta) {
        $order = wc_get_order($post->ID);
        $transient_name = 'wc_shiptor_order_history_' . $meta['args']['shiptor_id'];
        if (false === ( $history = get_transient($transient_name) )) {
            $connect = $this->connect->get_package($meta['args']['shiptor_id']);
            if ($connect && isset($connect['history'])) {
                $history = $connect['history'];
                set_transient($transient_name, $history, HOUR_IN_SECONDS);
            }
        }
        include( 'views/html-admin-order-history.php' );
    }

    public function render_order($post) {
        $order = wc_get_order($post->ID);

        // container for yandex map
        include WC_Shiptor::get_plugin_path() . 'includes/admin/views/html-admin-yandex-map-template.php';

        if ($order->get_meta('_shiptor_id')) {
            $this->maybe_update_order_status($order->get_meta('_shiptor_id'), $order, true);
            include( 'views/html-admin-edit-order.php' );
        } else {
            if (!empty($order->get_items('shipping'))) {
                $shipping = current($order->get_items('shipping'));
                $shiptor_method = $shipping->get_meta('shiptor_method');
                $delivery_points = $shipping->get_meta('delivery_points');

                include( 'views/html-admin-create-order.php' );
            }
        }
    }

    public function create_order_action($data) {
        $order = wc_get_order($data['order_id']);
        if (!$order) {
            throw new Exception( esc_html__('Invalid order', 'woocommerce-shiptor'));
        }

        if (empty($order->get_billing_country()) || empty($order->get_formatted_billing_full_name())) {
            throw new Exception( esc_html__('Customer data not filled in or saved.', 'woocommerce-shiptor'));
        }

        delete_post_meta($order->ID, 'shiptor_order_error');

        $isNeedPostCode = true;
        $postCode = $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode();

        if (in_array($data['category'], array('door-to-delivery-point', 'delivery-point', 'delivery-point-to-door', 'delivery-point-to-delivery-point'))) {
            $isNeedPostCode = false;
        }

        if (!$postCode) {
            $isNeedPostCode = false;
        }

        if ($order->get_shipping_country() == 'RU' && $data['category'] !== 'post-office') {
            if (mb_strlen($postCode) !== 6) {
                $isNeedPostCode = false;
            }
        }

        $data = wp_parse_args($data, array(
            'order_id' => 0,
            'is_fulfilment' => false,
            'no_gather' => false,
            'method_id' => 0,
            'courier' => '',
            'category' => '',
            'method_name' => '',
            'comment' => ''
        ));
        if ($isNeedPostCode) {
            $data['postal_code'] = $postCode;
        }

        $package = $products = array();
        $item_index = 0;

        foreach ($order->get_items('line_item') as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $product = wc_get_product($variation_id ? $variation_id : $product_id);

            $package['contents'][$item_index] = array(
                'data' => $product,
                'quantity' => $item->get_quantity()
            );

            $_article = get_post_meta($product->get_id(), '_article', true);

            $article = !empty($_article) ? sanitize_text_field($_article) : ( $product->get_sku() ? $product->get_sku() : $product->get_id() );

            $products[$item_index] = array(
                'shopArticle' => $article,
                'count' => $item->get_quantity(),
                'price' => $product->get_price()
            );

            if (!in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
                $products[$item_index]['englishName'] = $product->get_meta('_eng_name') ? $product->get_meta('_eng_name') : sanitize_title($product->get_name());
            }

            $get_products = $this->connect->get_products($article);

            if (!$get_products) {

                if ($this->connect->add_product($product)) {
                    update_post_meta($product->get_id(), '_added_shiptor', time());
                }
            } else {

                $get_product = wp_list_filter($get_products, array('shopArticle' => $article));
                $get_product = current($get_product);

                if (!empty($get_product) && isset($get_product['fulfilment']['total'], $get_product['fulfilment']['waiting'])) {
                    update_post_meta($product->get_id(), '_fulfilment_total', $get_product['fulfilment']['total']);
                    update_post_meta($product->get_id(), '_fulfilment_waiting', $get_product['fulfilment']['waiting']);
                }
            }

            $item_index++;
        }

        if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
            $cost = in_array($order->get_payment_method(), array('cod', 'cod_card')) ? $order->get_total() : 0;
        } else {
            $cost = 0;
        }

        $method_id = intval($data['method_id']);

        $shipping = current($order->get_items('shipping'));
        $shiptor_method = $shipping->get_meta('shiptor_method');
        $instance_id = $shipping->get_instance_id();

        $option = new WC_Shiptor_Shipping_Host_Method($instance_id);
        $is_fulfilment = wc_string_to_bool($option->get_option("client_methods[{$shiptor_method['id']}][is_fulfilment]"));

        $atts = array(
            'external_id' => $order->get_order_number(),
            'is_fulfilment' => $is_fulfilment,
            'no_gather' => isset($data['no_gather']) && wc_string_to_bool($data['no_gather']),
            'departure' => array(
                'shipping_method' => $method_id,
                'address' => array(
                    'country' => $order->get_billing_country(),
                    'receiver' => $order->get_formatted_billing_full_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'settlement' => $order->get_billing_city(),
                    'administrative_area' => $order->get_billing_state()
                )
            ),
            'products' => $products
        );

        if (isset($data['postal_code'])) {
            $atts['departure']['address']['postal_code'] = $data['postal_code'];
        }

        if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {

            $atts['declared_cost'] = $data['declared_cost'] < 10 ? 10 : $data['declared_cost'];

            $atts['departure']['address']['name'] = $order->get_billing_first_name();
            $atts['departure']['address']['surname'] = $order->get_billing_last_name();
            $atts['departure']['address']['kladr_id'] = $order->get_meta('_billing_kladr_id');
            $atts['cod'] = $cost;

            if ($cost > 0) {

                if (isset($data['cashless_payment'])) {
                    $atts['departure']['cashless_payment'] = wc_string_to_bool($data['cashless_payment']);
                }

                $service_id = sprintf('shipping_%s_%s', $data['courier'], $data['category']);
                $found = false;
                $get_services = $this->connect->get_service();

                if ($get_services && isset($get_services['services'])) {
                    $found_service = wp_list_filter($get_services['services'], array('shop_article' => $service_id));
                    if (!empty($found_service)) {
                        $found = true;
                    }
                }

                if (!$found) {
                    $add_service = $this->connect->add_service(sprintf( esc_html__('Shipping via %s', 'woocommerce'), $data['method_name']), $service_id);
                    if ($add_service && isset($add_service['shop_article']) && $add_service['shop_article'] == $service_id) {
                        $found = true;
                    }
                }

                if ($found) {
                    $atts['services'] = array(
                        array(
                            'shopArticle' => $service_id,
                            'count' => 1,
                            'price' => $order->get_shipping_total()
                        )
                    );
                }
            }
        }

        if (!in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
            $atts['departure']['address']['address_line_1'] = $order->get_billing_address_1();
        }

        if (isset($data['chosen_delivery_point'])) {
            $atts['departure']['delivery_point'] = intval($data['chosen_delivery_point']);
            if ($data['chosen_delivery_point'] !== $order->get_meta('_chosen_delivery_point')) {
                $order->update_meta_data('_chosen_delivery_point', intval($data['chosen_delivery_point']));
            }
        }

        if (!empty($data['comment'])) {
            $atts['departure']['comment'] = sanitize_textarea_field($data['comment']);
            $order->add_order_note(sanitize_textarea_field($data['comment']), false, true);
        }

        $shipment_type = 'standard';

        switch ($data['category']) {
            case 'delivery-point-to-delivery-point' :
            case 'delivery-point-to-door' :
                $shipment_type = 'delivery-point';
                break;
            case 'door-to-door' :
            case 'door-to-delivery-point' :
                $shipment_type = 'courier';
                break;
            default :
                $shipment_type = 'standard';
                break;
        }

        $shipment = array(
            'type' => $shipment_type
        );

        if ($shipment_type == 'standard') {
            $stock = $option->get_option("client_methods[{$shiptor_method['id']}][shiptor_warehouse]");
            $stock = $stock ? $stock : wc_shiptor_get_option('shiptor_warehouse');
            if ($stock) {
                $atts['stock'] = (int)$stock;
                $stock_info = $this->connect->get_stock($stock);
                if (in_array('fulfilment', $stock_info['roles']) && in_array('logistic', $stock_info['roles'])) {
                    $is_fulfilment = wc_string_to_bool($option->get_option("client_methods[{$shiptor_method['id']}][is_fulfilment]"));
                } else if (in_array('fulfilment', $stock_info['roles'])) {
                    $is_fulfilment = true;
                } else if (in_array('fulfilment', $stock_info['roles'])) {
                    $is_fulfilment = false;
                } else {
                    $is_fulfilment = false;
                }
            } else {
                $is_fulfilment = wc_string_to_bool($option->get_option("client_methods[{$shiptor_method['id']}][is_fulfilment]"));
            }

            $atts['is_fulfilment'] = $is_fulfilment;
        }

        if (in_array($shipment_type, array('courier', 'delivery-point'))) {
            $shipment['courier'] = $data['courier'];
            $shipment['address'] = array(
                'receiver' => $data['sender_name'],
                'email' => $data['sender_email'],
                'phone' => $data['sender_phone'],
                'country' => WC()->countries->get_base_country(),
                'kladr_id' => $data['sender_city']
            );
            $shipment['date'] = date('d.m.Y', strtotime($data['sender_order_date']));
        }

        if ('delivery-point' == $shipment_type) {
            $connect = new WC_Shiptor_Connect('shiptor_delivery_points');
            $connect->set_shipping_method(intval($data['method_id']));
            $connect->set_kladr_id($data['sender_city']);
            $points = $connect->get_delivery_points(array(
                'self_pick_up' => true,
            ));
            $point = array_filter($points, function($item) use ($data){
                return $item['address'] == $data['sender_delivery_point'];
            });
            $point = current($point);

            $shipment['delivery_point'] = intval($point['id']);
        } elseif (in_array($shipment_type, array('courier', 'standard'))) {
            if (isset($data['sender_address'])) {
                $shipment['address']['street'] = $data['sender_address'];
            }
            if ($order->get_billing_address_1()) {
                $atts['departure']['address']['address_line_1'] = $order->get_billing_address_1();
            }
        }

        //$is_export = 'aramex' === $data['courier'];
        $is_export = 'shiptor-international' === $data['courier'];

        $this->connect->set_package($package);
        $result = $this->connect->add_packages($atts, $shipment, $is_export);

        if (( $is_export && isset($result['result']) ) || (!$is_export && isset($result['result']['packages']) )) {
            $package = $is_export ? $result['result'] : current($result['result']['packages']);

            $order->update_meta_data('_shiptor_id', intval($package['id']));
            $order->update_meta_data('_shiptor_status', $package['status']);
            $order->update_meta_data('_shiptor_label_url', $package['label_url']);

            if (!$is_export && isset($result['result']['shipment'])) {
                $shipment_id = $result['result']['shipment']['id'];
                $order->update_meta_data('_shipment_id', $shipment_id);

                if (isset($data['confirm_shipment']) && 'yes' === $data['confirm_shipment']) {
                    $res = $this->connect->confirmShipment($shipment_id);
                    if (isset($res['result'])) {
                        $confirmed = $res['result']['confirmed'];
                        $order->update_meta_data('_shipment_confirmed', $confirmed);
                    }
                }
            }

            if (isset($result['result']['tracking_number'])) {
                wc_shiptor_update_tracking_code($order, $package['tracking_number']);
            } else {
                $order->save();
            }

            $this->maybe_update_order_status(intval($package['id']), $order, true);
        } elseif (isset($result['error'])) {
            throw new Exception($result['error']);
        } else {
            throw new Exception( esc_html__('Can not create an order', 'woocommerce-shiptor'));
        }
    }

    public function create_order_ajax() {
        check_ajax_referer('woocommerce-shiptor-create-order', 'security');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        try {

            if (!isset($_POST['data'])) {
                throw new Exception( esc_html__('Missing parameters', 'woocommerce-shiptor'));
            }

            wp_parse_str($_POST['data'], $data);

            $this->create_order_action($data);


            $order = wc_get_order($data['order_id']);

            ob_start();

            include( 'views/html-admin-edit-order.php' );

            wp_send_json_success(array(
                'html' => ob_get_clean(),
            ));
        } catch (Exception $e) {
            if (isset($_POST['data'])) {
                wp_parse_str($_POST['data'], $data);
                if (isset($data['order_id'])) {
                    add_post_meta($data['order_id'], 'shiptor_order_error', 1, true);
                }
            }

            WC_Admin_Notices::add_custom_notice(
                'shiptor_order_error', $e->getMessage()
            );
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    private function maybe_update_order_status($shiptor_id = 0, $order, $force = false) {
        $transient_name = 'wc_shiptor_order_status_' . $shiptor_id;
        if (false === ( $package = get_transient($transient_name) ) || true === $force) {
            $connect = $this->connect->get_package($shiptor_id);
            if ($connect && isset($connect['status'])) {
                $package = $connect;
                set_transient($transient_name, $package, HOUR_IN_SECONDS);
                if (is_a($order, 'WC_Order')) {
                    $order->update_meta_data('_shiptor_status', $package['status']);
                    $order->update_meta_data('_shiptor_label_url', $package['label_url']);
                    if (isset($package['shipment'])) {
                        $order->update_meta_data('_shipment_confirmed', $package['shipment']['confirmed']);
                    }
                    if (isset($package['tracking_number']) && $package['tracking_number'] !== $order->get_meta('_shiptor_tracking_code')) {
                        wc_shiptor_update_tracking_code($order, $package['tracking_number']);
                    } else {
                        $order->save();
                    }
                }
            }
        }
    }

    private function shipping_is_shiptor($order) {
        $is_shiptor = false;
        $order = wc_get_order($order);
        $shippings = $order->get_items('shipping');
        foreach ($shippings as $shipping) {
            $shiptor_method = $shipping->get_meta('shiptor_method');
            if (!empty($shiptor_method)) {
                $is_shiptor = true;
                break;
            }
        }
        return $is_shiptor;
    }

    public function resend_tracking_code_email($emails) {
        return array_merge($emails, array('shiptor_tracking'));
    }

    public function resend_tracking_code_actions($emails) {
        $emails['shiptor_tracking'] =  esc_html__('Send shiptor tracking code', 'woocommerce-shiptor');
        return $emails;
    }

    public function action_shiptor_tracking($order) {
        WC()->mailer()->emails['WC_Shiptor_Tracking_Email']->trigger($order->get_id(), $order, wc_shiptor_get_tracking_codes($order));
    }

    public function shiptor_order_column($columns) {

        $new_columns = array();

        foreach ($columns as $column_name => $column_info) {

            $new_columns[$column_name] = $column_info;

            if ('order_status' === $column_name) {
                $new_columns['shiptor'] =  esc_html__('Shiptor', 'woocommerce-shiptor');
            }
        }

        return $new_columns;
    }

    public function tracking_code_orders_list($column) {
        global $post, $the_order;

        if ('shiptor' === $column) {
            if (empty($the_order) || $the_order->get_id() !== $post->ID) {
                $the_order = wc_get_order($post->ID);
            }

            $shipping = current($the_order->get_items('shipping'));

            if ($shipping && method_exists($shipping, 'get_meta')) {
                $shiptor_method = $shipping->get_meta('shiptor_method');

                if ($tracking_code = $the_order->get_meta('_shiptor_tracking_code')) {
                    include( 'views/html-list-table-order.php' );
                } else {
                    include( 'views/html-list-table-create-order.php' );
                }
            } else {
                _e('N/A', 'woocommerce');
            }
        }
    }

    public function define_bulk_actions($actions) {
        $actions['send_orders'] =  esc_html__('Send chosen orders to Shiptor', 'woocommerce-shiptor');

        return $actions;
    }

    public function handle_bulk_actions($redirect_to, $action, $ids) {
        if ('send_orders' !== $action) {
            return $redirect_to;
        }

        $changed = 0;
        $ids = array_map('absint', $ids);

        foreach ($ids as $id) {

            $order = wc_get_order($id);
            $shipping = current($order->get_items('shipping'));
            $shiptor_method = $shipping->get_meta('shiptor_method');
            $delivery_points = $shipping->get_meta('delivery_points');
            $shiptor_id = $order->get_meta('_shiptor_id');

            if ($shiptor_method) {
                if (!empty($shiptor_id) || 0 === strpos($shiptor_method['category'], 'delivery-point-to-')) {
                    continue;
                }

                $data = array(
                    'order_id' => absint($id),
                    'is_fulfilment' => true,
                    'no_gather' => false,
                    'method_id' => $shiptor_method['id'],
                    'courier' => $shiptor_method['courier'],
                    'category' => $shiptor_method['category'],
                    'method_name' => $shiptor_method['name'],
                    'comment' => $order->get_customer_note('edit'),
                    'address_line' => $order->get_billing_address_1()
                );

                if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
                    $data['declared_cost'] = isset($shiptor_method['declared_cost']) && $shiptor_method['declared_cost'] > 10 ? $order->get_total() : 10;
                }

                if ($order->get_billing_country() === 'RU') {
                    $data['cashless_payment'] = $order->get_payment_method() == 'cod_card';
                }

                if (!empty($delivery_points)) {
                    $data['chosen_delivery_point'] = $order->get_meta('_chosen_delivery_point');
                }

                if (0 === strpos($shiptor_method['category'], 'door-to-')) {
                    $data['sender_name'] = $shiptor_method['sender_name'];
                    $data['sender_email'] = $shiptor_method['sender_email'];
                    $data['sender_phone'] = $shiptor_method['sender_phone'];

                    if (isset($shiptor_method['sender_city'])) {
                        $data['sender_city'] = $shiptor_method['sender_city'];
                    }

                    if (isset($shiptor_method['sender_address'])) {
                        $data['sender_address'] = $shiptor_method['sender_address'];
                    }

                    $data['sender_order_date'] = $this->getNextWorkingDay();
                }
            }

            try {
                $this->create_order_action($data);
                $changed++;
            } catch (Exception $e) {

            }
        }

        $redirect_to = add_query_arg(
                array(
            'post_type' => 'shop_order',
            'sended_order' => true,
            'changed' => $changed,
            'count' => count($ids),
            'ids' => join(',', $ids),
                ), remove_query_arg(array('after_send_order'), $redirect_to)
        );

        return esc_url_raw($redirect_to);
    }

    public function admin_init() {
        if (!isset($_GET['after_send_order']) && WC_Admin_Notices::has_notice('send_order_error')) {
            WC_Admin_Notices::remove_notice('send_order_error');
        }
    }

    public function create_single_order_ajax() {

        if (current_user_can('edit_shop_orders') && check_admin_referer('shiptor-send-order')) {

            $order = wc_get_order(absint($_GET['order_id']));
            $shipping = current($order->get_items('shipping'));
            $shiptor_method = $shipping->get_meta('shiptor_method');
            $delivery_points = $shipping->get_meta('delivery_points');

            $data = array(
                'order_id' => $order->get_id(),
                'is_fulfilment' => true,
                'no_gather' => false,
                'method_id' => $shiptor_method['id'],
                'courier' => $shiptor_method['courier'],
                'category' => $shiptor_method['category'],
                'method_name' => $shiptor_method['name'],
                'comment' => $order->get_customer_note('edit'),
                'address_line' => $order->get_billing_address_1()
            );

            if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
                $data['declared_cost'] = isset($shiptor_method['declared_cost']) && $shiptor_method['declared_cost'] > 10 ? $order->get_total() : 10;
            }

            if ($order->get_billing_country() === 'RU') {
                $data['cashless_payment'] = $order->get_payment_method() == 'cod_card';
            }

            if (!empty($delivery_points)) {
                $data['chosen_delivery_point'] = $order->get_meta('_chosen_delivery_point');
            }

            if (0 === strpos($shiptor_method['category'], 'door-to-')) {
                $data['sender_name'] = $shiptor_method['sender_name'];
                $data['sender_email'] = $shiptor_method['sender_email'];
                $data['sender_phone'] = $shiptor_method['sender_phone'];

                if (isset($shiptor_method['sender_city'])) {
                    $data['sender_city'] = $shiptor_method['sender_city'];
                }

                if (isset($shiptor_method['sender_address'])) {
                    $data['sender_address'] = $shiptor_method['sender_address'];
                }

                $data['sender_order_date'] = $this->getNextWorkingDay();
            }

            try {
                $this->create_order_action($data);
            } catch (Exception $e) {
                WC_Admin_Notices::add_custom_notice(
                        'send_order_error', sprintf(
                                'Во время отправки заказа %1$s произошла ошибка: (%2$s). <a href="%3$s">Перейдите в заказ чтобы отправить его.</a>', $order->get_order_number(), $e->getMessage(), esc_url(admin_url('post.php?post=' . absint($order->get_id())) . '&action=edit')
                        )
                );
                add_post_meta($order->ID, 'shiptor_order_error', 1, true);

                wp_safe_redirect(admin_url('edit.php?post_type=shop_order&after_send_order=true'));
                exit;
            }
        }

        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('edit.php?post_type=shop_order') );
        exit;
    }

    public function bulk_admin_notices() {
        global $post_type, $pagenow;

        if ('edit.php' !== $pagenow || 'shop_order' !== $post_type) {
            return;
        }

        $shiptor_shipping_notice_message = '';
        $no_shiptor_shipping_notice_message = '';
        $no_shiptor_methods_ids = array();
        if (isset($_REQUEST['sended_order'])) {
            $ids = wp_parse_id_list($_REQUEST['ids']);

            foreach ($ids as $id) {
                $orders = wc_get_order($id);
                if (!$this->shipping_is_shiptor($orders)) {
                    $no_shiptor_methods_ids[] = $id;
                    $no_shiptor_shipping_notice_message = sprintf( esc_html__('You can not send order %s to the Shiptor.', 'woocommerce-shiptor'), implode(' ', $no_shiptor_methods_ids));
                }
            }

            $classes = array('notice', 'is-dismissible');

            $number = isset($_REQUEST['changed']) ? absint($_REQUEST['changed']) : 0;
            $count = isset($_REQUEST['count']) ? absint($_REQUEST['count']) : 0;

            if (0 == $number) {
                $classes[] = 'notice-error';
                $shiptor_shipping_notice_message = sprintf( esc_html__('No order was shipped from %s. Make sure that the selected orders have not been shipped before, the delivery method from the Shiptor module is selected, or the required fields are filled. ID-% s', 'woocommerce-shiptor'), $count, implode(' , ', $ids));
            } elseif ($count == $number) {
                $classes[] = 'notice-success';
                $shiptor_shipping_notice_message = sprintf(_n('%d order of %d has been sent.', '%d orders of %d were sent.', $number, 'woocommerce-shiptor'), number_format_i18n($number), number_format_i18n($count));
            } else {
                $classes[] = 'notice-warning';
                $shiptor_shipping_notice_message = sprintf( esc_html__('%d of %d orders(s) have been sent. Make sure that the selected orders have not been shipped before, the delivery method from the Shiptor module has been selected, or the required fields are filled', 'woocommerce-shiptor'), $number, $count, $count - $number);
            }

            echo '<div class="' . esc_attr(implode(' ', $classes)) . '"><p>' . esc_html($shiptor_shipping_notice_message) . '</p></div>';
            if ($no_shiptor_shipping_notice_message) {
                echo '<div class="notice is-dismissible notice-error"><p>' . esc_html($no_shiptor_shipping_notice_message) . '</p></div>';
            }
        }
    }

    public function orders_load_style() {

        $screen = get_current_screen();

        if (in_array($screen->id, array('shop_order', 'edit-shop_order'))) {
            wp_enqueue_style('woocommerce-shiptor-orders', plugins_url('assets/admin/css/orders.css', WC_SHIPTOR_PLUGIN_FILE), null, WC_SHIPTOR_VERSION);

            // yandex maps
            wp_enqueue_style(
                'jquery-modal',
                plugins_url('/assets/frontend/css/jquery-modal/jquery.modal.min.css', WC_SHIPTOR_PLUGIN_FILE)
            );
            wp_enqueue_style('jquery-modal-style', plugins_url('/assets/frontend/css/checkout.css', WC_SHIPTOR_PLUGIN_FILE), array('jquery-modal'), WC_SHIPTOR_VERSION);

        }
    }

    public function orders_load_scripts() {
        $screen = get_current_screen();
        $ym_apikey = get_option('woocommerce_shiptor-integration_settings');
        if (isset($screen->id) && in_array($screen->id, array('shop_order', 'edit-shop_order'))) {
            wp_enqueue_script('woocommerce-shiptor-orders', plugins_url('assets/admin/js/orders.js', WC_SHIPTOR_PLUGIN_FILE), array('wp-api', 'jquery-blockui', 'woocommerce_admin'), WC_SHIPTOR_VERSION, true);
            wp_localize_script('woocommerce-shiptor-orders', 'shiptor_order_params', array(
                'nonces' => array(
                    'cleate' => wp_create_nonce('woocommerce-shiptor-create-order'),
                    'edit_order' => wp_create_nonce('woocommerce-shiptor-edit-order'),
                )
            ));
            wp_localize_script(
                'woocommerce-shiptor-orders', 'wc_shiptor_admin_params', array(
                    'ajax_url' => WC_AJAX::get_endpoint("%%endpoint%%"),
                    'admin_url' => admin_url('admin-ajax.php'),
                    'country_iso' => WC()->countries->get_base_country(),
                    'ym_apikey' => $ym_apikey['yandex_api_token'],
                )
            );

            // yandex maps
            $ym_api_args = array('lang' => get_locale());
            if ( !empty ($ym_apikey['yandex_api_token']) ) {
                $ym_api_args['apikey'] = $ym_apikey['yandex_api_token'];
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $ym_api_args['mode'] = 'debug';
            }
            wp_enqueue_script('jquery-modal',plugins_url('/assets/frontend/js/jquery-modal/jquery.modal.min.js', WC_SHIPTOR_PLUGIN_FILE), array('jquery'));
            wp_enqueue_script('yandex-map', add_query_arg($ym_api_args, '//api-maps.yandex.ru/2.1'), array(), '2.1', true);
            wp_enqueue_script('city_autocomplete', plugins_url('assets/admin/js/city_autocomplete.js', WC_SHIPTOR_PLUGIN_FILE), array('jquery', 'select2'), WC_SHIPTOR_VERSION, true);
            wp_enqueue_script($screen->id . '-select-point-admin', plugins_url('assets/admin/js/select-point-map.js', WC_SHIPTOR_PLUGIN_FILE), array('jquery', 'yandex-map', 'jquery-modal', 'city_autocomplete'), WC_SHIPTOR_VERSION, true);
        }
    }

    public function shipping_methods() {
        $zones = WC_Shipping_Zones::get_zones();
        if ( !empty($zones) ) {
            foreach ($zones as $zone) {
                $shipping_methods_from_zones = $zone['shipping_methods'];
                foreach ($shipping_methods_from_zones as $key) {
                    if ( $key->id == 'shiptor-shipping-host' && $key->enabled == 'yes'){
                        $instance_id = $key->instance_id;
                        $shipping_method = new WC_Shiptor_Shipping_Host_Method( $instance_id );
                        $shipping_method_array[] = $shipping_method->get_enabled_client_methods();
                    }
                }
                foreach ($shipping_method_array as $shipping_method) {
                    $methods_id = array_keys($shipping_method);
                    foreach ($methods_id as $key) {
                        $shipping_methods[$key] = $shipping_method[$key];
                    }
                }
            }
        }
        return $shipping_methods;
    }
}

new WC_Shiptor_Admin_Order();
