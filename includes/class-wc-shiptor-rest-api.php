<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 0:11
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WC_Shiptor_REST_API {

    public function __construct() {
        add_filter('woocommerce_api_order_response', array($this, 'legacy_orders_response'), 100, 3);
        add_filter('woocommerce_api_create_order', array($this, 'legacy_orders_update'), 100, 2);
        add_filter('woocommerce_api_edit_order', array($this, 'legacy_orders_update'), 100, 2);
        add_action('rest_api_init', array($this, 'register_tracking_code'), 100);
    }

    public function legacy_orders_response($data, $order, $fields) {
        $data['shiptor_tracking_code'] = implode(',', wc_shiptor_get_tracking_codes($order));
        if ($fields) {
            $data = WC()->api->WC_API_Customers->filter_response_fields($data, $order, $fields);
        }
        return $data;
    }

    public function legacy_orders_update($order_id, $data) {
        if (isset($data['shiptor_tracking_code'])) {
            wc_shiptor_update_tracking_code($order_id, $data['shiptor_tracking_code']);
        }
    }

    public function register_tracking_code() {
        if (!function_exists('register_rest_field')) {
            return;
        }
        register_rest_field('shop_order', 'shiptor_tracking_code', array(
            'get_callback' => array($this, 'get_tracking_code_callback'),
            'update_callback' => array($this, 'update_tracking_code_callback'),
            'schema' => array(
                'description' =>  esc_html__('Shiptor tracking code.', 'woocommerce-shiptor'),
                'type' => 'string',
                'context' => array('view', 'edit'),
            ),
                )
        );
    }

    function get_tracking_code_callback($data) {
        return implode(',', wc_shiptor_get_tracking_codes($data['id']));
    }

    function update_tracking_code_callback($value, $object) {
        if (!$value || !is_string($value)) {
            return;
        }
        return wc_shiptor_update_tracking_code($object->ID, $value);
    }

}

new WC_Shiptor_REST_API();
