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

class WC_Shiptor_Autofill_Addresses {

    public static $table = 'shiptor_address';
    protected $ajax_endpoint = 'shiptor_autofill_address';

    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        add_action('wc_ajax_' . $this->ajax_endpoint, array($this, 'ajax_autofill'));
    }

    protected static function get_validity() {
        return apply_filters('woocommerce_shiptor_autofill_addresses_validity_time', 'forever');
    }

    public static function get_all_address() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    }

    public static function get_city_by_id($kladr_id = '') {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        $city = $wpdb->get_row($wpdb->prepare("SELECT kladr_id, city_name, state, country FROM $table WHERE 1 = 1 AND kladr_id = %s", $kladr_id), ARRAY_A);

        if(isset($city['kladr_id']) && $city['kladr_id']){
            return array(
                'kladr_id' => isset($city['kladr_id']) ? $city['kladr_id'] : null,
                'state' => isset($city['state']) ? $city['state'] : null,
                'city_name' => isset($city['city_name']) ? $city['city_name'] : null,
                'country' => isset($city['country']) ? $city['country'] : null
            );
        }

        return array();
    }

    public static function get_kladr($city_name, $country) {
        $addresses = self::get_address($city_name, $country);
        $address = array_filter($addresses, function($item) use ($city_name) {
            return $item['city_name'] == $city_name;
        });

        return reset($address);
    }

    public static function get_address($city_name, $country) {
        global $wpdb;

        if (empty($city_name)) {
            return null;
        }

        $country = !empty($country) ? $country : (new WC_Countries)->get_base_country();

        $address = self::fetch_address($city_name, $country);

        self::save_address($address);

        return $address;
    }

    protected static function save_address($addresses = []) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;

        if(!count($addresses)){
            return;
        }

        $current_datetime = date('Y-m-d H:i:s');
        $values = [];
        foreach($addresses as $address){
            $address = wc_clean($address);
            $address_value = "('{$address['kladr_id']}', '{$address['city_name']}', '{$address['state']}', '{$address['country']}', '{$current_datetime }')";
            $values[] = $address_value;
        }
        $values = implode(', ', $values);

        $sql = "
            INSERT INTO {$table} (`kladr_id`, `city_name`, `state`, `country`, `last_query`)
            VALUES $values
            ON DUPLICATE KEY UPDATE city_name = city_name, state = state, country = country";

        $result = $wpdb->query($sql);
    }

    protected static function fetch_address($city, $country) {
        $address = array();

        try {
            $connect = new WC_Shiptor_Connect();
            $response = $connect->request(array(
                'method' => 'suggestSettlement',
                'params' => array(
                    'query' => $city,
                    'country_code' => $country
                )
                    ));

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            if (wp_remote_retrieve_response_code($response) == 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);

                if (isset($data['error'])) {
                    throw new Exception($data['error']['message']);
                } elseif (isset($data['result']) && is_array($data['result']) && !empty($data['result'])) {
                    foreach ($data['result'] as $result) {
                        if (empty($result['kladr_id']))
                            continue;

                        $address[] = array(
                            'country' => $result['country']['code'],
                            'state' => ( isset($result['readable_parents']) && !empty($result['readable_parents']) ) ? $result['readable_parents'] : $result['administrative_area'],
                            'city_name' => $result['name'],
                            'kladr_id' => $result['kladr_id'],
                        );
                    }
                }
            }
        } catch (Exception $e) {}

        return $address;
    }

    public function frontend_scripts() {
        if (is_cart() || is_checkout() || is_wc_endpoint_url('edit-address')) {

            wp_enqueue_script('woocommerce-shiptor-autofill-addresses', plugins_url('assets/frontend/js/autofill-address.js', WC_Shiptor::get_main_file()), array('jquery', 'jquery-blockui', 'select2'), WC_SHIPTOR_VERSION, true);

            wp_localize_script(
                    'woocommerce-shiptor-autofill-addresses', 'wc_shiptor_autofill_address_params', array(
                'url' => WC_AJAX::get_endpoint($this->ajax_endpoint)
                    )
            );
        }
    }

    public function ajax_autofill() {
        if (empty($_GET['city_name'])) {
            wp_send_json_error(array('message' =>  esc_html__('Missing city name paramater.', 'woocommerce-shiptor')));
            exit;
        }

        $city_name = sanitize_text_field($_GET['city_name']);
        $default_location = wc_get_customer_default_location();
        $country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : $default_location['country'];

        $address = self::get_address($city_name, $country);

        if (empty($address)) {
            wp_send_json_error(array('message' => sprintf( esc_html__('Invalid %s city name.', 'woocommerce-shiptor'), $city_name)));
            exit;
        }

        $address = array_filter(array_map(array($this, 'clean_address_data'), $address));

        wp_send_json_success($address);
    }

    protected function clean_address_data($array) {
        unset($array['ID']);
        unset($array['last_query']);
        return $array;
    }

    public static function clearTable() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        $wpdb->query("TRUNCATE TABLE $table;");
    }

}

new WC_Shiptor_Autofill_Addresses;