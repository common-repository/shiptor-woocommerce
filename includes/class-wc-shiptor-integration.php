<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 0:16
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Integration extends WC_Integration {

    public static $shipping_methods_table = 'shiptor_shipping_methods';

    public function __construct() {

        $this->id = 'shiptor-integration';
        $this->method_title =  esc_html__(sprintf('Shiptor %s', WC_SHIPTOR_VERSION), 'woocommerce-shiptor');

        $this->init_form_fields();
        $this->init_settings();

        $this->api_token = $this->get_option('api_token');
        $this->city_origin = $this->get_option('city_origin');
        $this->update_interval = $this->get_option('update_interval');
        $this->minimum_weight = $this->get_option('minimum_weight');
        $this->minimum_height = $this->get_option('minimum_height');
        $this->minimum_width = $this->get_option('minimum_width');
        $this->minimum_length = $this->get_option('minimum_length');
        $this->tracking_enable = $this->get_option('tracking_enable');
        $this->enable_tracking_debug = $this->get_option('enable_tracking_debug');
        $this->create_order_enable = $this->get_option('create_order_enable');
        $this->autofill_validity = $this->get_option('autofill_validity');
        $this->autofill_empty_database = $this->get_option('autofill_empty_database');
        $this->enable_requests_caching = $this->get_option('enable_requests_caching');

        $this->connect = new WC_Shiptor_Connect();

        // API settings actions.
        add_filter('woocommerce_shiptor_api_token', array($this, 'setup_api_token'), 10);
        add_filter('woocommerce_shiptor_city_origin', array($this, 'setup_city_origin'), 10);
        add_filter('woocommerce_shiptor_update_interval', array($this, 'setup_update_interval'), 10);
        // Product options.
        add_filter('woocommerce_shiptor_default_weight', array($this, 'setup_default_weight'), 10);
        add_filter('woocommerce_shiptor_default_height', array($this, 'setup_default_height'), 10);
        add_filter('woocommerce_shiptor_default_width', array($this, 'setup_default_width'), 10);
        add_filter('woocommerce_shiptor_default_length', array($this, 'setup_default_length'), 10);
        // Tracking history actions.
        add_filter('woocommerce_shiptor_enable_tracking_history', array($this, 'setup_tracking_history'), 10);
        // Autofill address actions.
        add_filter('woocommerce_shiptor_autofill_addresses_validity_time', array($this, 'setup_autofill_addresses_validity_time'), 10);
        add_action('wp_ajax_shiptor_autofill_addresses_empty_addresses', array($this, 'ajax_empty_addresses'));

        // Actions.
        add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));

        //Connect Actions
        add_action('wp_ajax_shiptor_clear_cache', array($this, 'ajax_empty_cached_requests'));
        add_action('wp_ajax_shiptor_clear_logs', array($this, 'ajax_shiptor_clear_logs'));
        add_action('wp_ajax_shiptor_synchronize_methods_with_api', array($this, 'ajax_synchronize_methods_with_api'));

        add_action('wp_ajax_shiptor_warehouse_info', array($this, 'shiptor_warehouse_info'));
        add_action('wc_ajax_get_delivery_points', array($this, 'get_delivery_points'));
    }

    public function get_delivery_points() {
        $shipping_method = absint($_GET['shipping_method']);
        $kladr_id = sanitize_key($_GET['kladr_id']);

        if (!$kladr_id) {
            return wp_send_json_error(array('message' =>  esc_html__('Sender city required', 'woocommerce-shiptor')));
        }

        $connect = new WC_Shiptor_Connect('shiptor_delivery_points');
        $connect->set_shipping_method($shipping_method);
        $connect->set_kladr_id($kladr_id);
        $this->set_additional_info($connect);

        $atts = array();
        if (empty($_GET['order_id'])) {
            $atts['self_pick_up'] = true;
        }
        $points = $connect->get_delivery_points($atts);

        if (!$points) {
            return wp_send_json_error(array('message' =>  esc_html__('There are no delivery points that meet the order requirements', 'woocommerce-shiptor')));
        }

        wp_send_json_success($points);
    }

    private function set_additional_info(&$connect) {
        $shipping_method_id = intval($_GET['shipping_method']);
        $order_id = intval($_GET['order_id']);

        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

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

        $connect->set_package($package);
        $connect->set_card($order->get_payment_method() === 'cod_card');
        $connect->set_cod($total);
        $shiptor_method = get_shipping_method_info($shipping_method_id);
        if ($shiptor_method) {
            $connect->set_courier($shiptor_method['courier']);
        }
    }

    public function shiptor_warehouse_info() {
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array('message' =>  esc_html__('Missing parameters!', 'woocommerce-shiptor')));
            exit;
        }
        if (!wp_verify_nonce(sanitize_key($_POST['nonce']), 'shiptor_warehouse_info_nonce')) {
            wp_send_json_error(array('message' =>  esc_html__('Invalid nonce!', 'woocommerce-shiptor')));
            exit;
        }

        $connect = new WC_Shiptor_Connect('shiptor_warehouse');
        $warehouse_id = intval($_POST['id']);
        $stock = $connect->get_stock($warehouse_id);

        wp_send_json_success($stock);
    }

    protected function get_tracking_log_link() {
        return sprintf('<a href="%s">%s</a>', esc_url(add_query_arg(array('page' => 'wc-status', 'tab' => 'logs', 'log_file' => sanitize_file_name(wp_hash($this->id)) . '.log'), admin_url('admin.php'))),  esc_html__('View logs.', 'woocommerce-shiptor'));
    }

    public function init_form_fields() {

        $city_origin = WC_Shiptor_Autofill_Addresses::get_city_by_id(wc_shiptor_get_option('city_origin'));
        if (empty($city_origin['kladr_id'])) {
            $city_origin = WC_SHIPTOR_DEFAULT_CITY;
        }

        $this->form_fields_grouped = array(
            'tab_1' => array(
                'api_settings' => array(
                    'title' =>  esc_html__('Shiptor integration API', 'woocommerce-shiptor'),
                    'type' => 'title',
                    'description' =>  esc_html__('Main settings for Shiptor integration', 'woocommerce-shiptor'),
                ),
                'api_token' => array(
                    'title' =>  esc_html__('API token', 'woocommerce-shiptor'),
                    'type' => 'text',
                    'placeholder' =>  esc_html__('Enter API Token here', 'woocommerce-shiptor'),
                    'description' =>  esc_html__('The Token API is required for the plugin to work. You can get it in your personal account https://shiptor.ru/account/settings/api', 'woocommerce-shiptor'),
                    'custom_attributes' => array('required' => 'required')
                ),
                'city_origin' => array(
                    'title' =>  esc_html__('Town / City', 'woocommerce-shiptor'),
                    'class' => 'wc-enhanced-select',
                    'type' => 'select_city_origin',
                    'default' => '77000000000',
                    'description' =>  esc_html__('Select the city of delivery and departure (end-to-end delivery) by default.', 'woocommerce-shiptor')
                ),
                'shiptor_warehouse' => array(
                    'title' =>  esc_html__('Shiptor warehouse', 'woocommerce-shiptor'),
                    'class' => 'wc-enhanced-select',
                    'type' => 'select_shiptor_warehouse',
                    'default' => 0,
                    'description' =>  esc_html__('Shipment terminal or fulfillment warehouse', 'woocommerce-shiptor'),
                ),
                'update_interval' => array(
                    'title' =>  esc_html__('Status update interval', 'woocommerce-shiptor'),
                    'type' => 'select',
                    'default' => 'five_min',
                    'class' => 'wc-enhanced-select',
                    'description' =>  esc_html__('Set the refresh interval for the delivery order processing statuses', 'woocommerce-shiptor'),
                    'options' => array(
                        'one_min' =>  esc_html__('One minute', 'woocommerce-shiptor'),
                        'five_min' =>  esc_html__('Five minutes', 'woocommerce-shiptor'),
                        'fifteen_min' =>  esc_html__('15 minutes', 'woocommerce-shiptor'),
                        'half_hour' =>  esc_html__('30 minutes', 'woocommerce-shiptor'),
                        'hourly' =>  esc_html__('One hour', 'woocommerce-shiptor')
                    )
                ),
                'methods_sort' => array(
                    'title' =>  esc_html__('Sort shipping methods', 'woocommerce-shiptor'),
                    'type' => 'title'
                ),
                'sorting_type' => array(
                    'title' =>  esc_html__('Sort shipping methods by', 'woocommerce-shiptor'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'options' => array(
                        '-1' =>  esc_html__('By Default', 'woocommerce-shiptor'),
                        'cost' =>  esc_html__('By Cost', 'woocommerce-shiptor'),
                        'date' =>  esc_html__('By time of delivery', 'woocommerce-shiptor'),
                    )
                ),
                'shipping_class_sort' => array(
                    'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Enable shipping methods sorting by shipping classes. (Not available yet.)', 'woocommerce-shiptor'),
                    'disabled' => true,
                    'default' => 'no'
                ),
                'cron_settings' => array(
                    'title' =>  esc_html__('Cron', 'woocommerce-shiptor'),
                    'type' => 'title'
                ),
                'cron_order_status' => array(
                    'title' =>  esc_html__('Cron order status', 'woocommerce-shiptor'),
                    'type' => 'multiselect',
                    'description' =>  esc_html__('Statuses for sending orders to shiptor by cron', 'woocommerce-shiptor'),
                    'class' => 'wc-enhanced-select',
                    'options' => wc_get_order_statuses(),
                ),
                'cron_period' => array(
                    'title' =>  esc_html__('Cron period', 'woocommerce-shiptor'),
                    'type' => 'select',
                    'options' => array(
                        'one_hour' =>  esc_html__('1 hour', 'woocommerce-shiptor'),
                        'three_hours' =>  esc_html__('3 hours', 'woocommerce-shiptor'),
                        'six_hours' =>  esc_html__('6 hours', 'woocommerce-shiptor'),
                        'twelve_hours' =>  esc_html__('12 hours', 'woocommerce-shiptor'),
                    ),
                ),
                'yandex_api_token' => array(
                    'title' =>  esc_html__('API token Yandex.Maps', 'woocommerce-shiptor'),
                    'type' => 'text',
                    'description' =>  esc_html__('The Yandex.Maps API key for connecting the search string on the map can be obtained from <a target="_blank" href="https://developer.tech.yandex.ru/services/">Developer\'s Office</a>', 'woocommerce-shiptor'),
                ),
            ),
            'tab_2' => array(
                'package_default' => array(
                    'title' =>  esc_html__('Product options', 'woocommerce-shiptor'),
                    'type' => 'title',
                    'description' =>  esc_html__('These parameters will be used by default if the item is not filled with these parameters', 'woocommerce-shiptor')
                ),
                'minimum_weight' => array(
                    'title' =>  esc_html__('Default weight, (kg)', 'woocommerce-shiptor'),
                    'type' => 'decimal',
                    'css' => 'width:50px;',
                    'default' => 0.5,
                    'custom_attributes' => array('required' => 'required')
                ),
                'minimum_height' => array(
                    'title' =>  esc_html__('Default height, (cm)', 'woocommerce-shiptor'),
                    'type' => 'decimal',
                    'css' => 'width:50px;',
                    'default' => 15,
                    'custom_attributes' => array('required' => 'required')
                ),
                'minimum_width' => array(
                    'title' =>  esc_html__('Default Width, (cm.)', 'woocommerce-shiptor'),
                    'type' => 'decimal',
                    'css' => 'width:50px;',
                    'default' => 15,
                    'custom_attributes' => array('required' => 'required')
                ),
                'minimum_length' => array(
                    'title' =>  esc_html__('Default length, (cm.)', 'woocommerce-shiptor'),
                    'type' => 'decimal',
                    'css' => 'width:50px;',
                    'default' => 15,
                    'custom_attributes' => array('required' => 'required')
                ),
            ),
            'tab_3' => array(
                'tracking' => array(
                    'title' =>  esc_html__('Delivery options', 'woocommerce-shiptor'),
                    'type' => 'title',
                    'description' =>  esc_html__('Displays a table with informations about the shipping in My Account > View Order page.', 'woocommerce-shiptor'),
                ),
                'tracking_enable' => array(
                    'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Enable Tracking History Table', 'woocommerce-shiptor'),
                    'default' => 'no',
                ),
                'enable_tracking_debug' => array(
                    'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Enable Tracking History debug', 'woocommerce-shiptor'),
                    'default' => 'no',
                ),
                'create_order_enable' => array(
                    'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Enable create order debug', 'woocommerce-shiptor'),
                    'default' => 'no',
                ),
                'calculate_in_product' => array(
                    'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Show delivery methods in the product card', 'woocommerce-shiptor'),
                    'default' => 'no'
                ),
                'cod_declared_cost' => array(
                    'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Show prices including cash on delivery and insurance', 'woocommerce-shiptor'),
                    'default' => 'no'
                ),
                'is_export_insurance' => array(
                    'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Insure parcels for export', 'woocommerce-shiptor'),
                    'default' => 'no'
                ),
                'round_delivery_cost' => array(
                    'title'    =>  esc_html__( 'Rounding shipping costs', 'woocommerce-shiptor' ),
                    'id'       => 'round_delivery_cost',
                    'type'     => 'select',
                    'options'     => array(
                        '2' =>  esc_html__( 'Do not round', 'woocommerce-shiptor' ),
                        '0' =>  esc_html__( 'Up to units', 'woocommerce-shiptor' ),
                        '-1' =>  esc_html__( 'Up to tens', 'woocommerce-shiptor' ),
                        '-2' =>  esc_html__( 'Up to hundreds', 'woocommerce-shiptor' ),
                    ),
                ),
                'round_type' => array(
                    'title'    =>  esc_html__( 'Rounding type', 'woocommerce-shiptor' ),
                    'id'       => 'round_type',
                    'type'     => 'select',
                    'options'     => array(
                        '0' =>  esc_html__( 'Mathematical', 'woocommerce-shiptor' ),
                        '1' => esc_html__( 'In favor of the client', 'woocommerce-shiptor' ),
                        '2' =>  esc_html__( 'In favor of the store', 'woocommerce-shiptor' ),
                    ),
                ),
            ),
            'tab_4' => array(
                'autofill_addresses' => array(
                    'title' =>  esc_html__('Autofill Addresses', 'woocommerce-shiptor'),
                    'type' => 'title',
                    'description' =>  esc_html__('Displays a table with informations about the shipping in My Account > View Order page.', 'woocommerce-shiptor'),
                ),
                'autofill_validity' => array(
                    'title' =>  esc_html__('Cities list Validity', 'woocommerce-shiptor'),
                    'type' => 'select',
                    'default' => '3',
                    'class' => 'wc-enhanced-select',
                    'description' =>  esc_html__('Defines how long a cities will stay saved in the database before a new query.', 'woocommerce-shiptor'),
                    'options' => array(
                        '1' =>  esc_html__('1 month', 'woocommerce-shiptor'),
                        '2' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 2),
                        '3' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 3),
                        '4' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 4),
                        '5' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 5),
                        '6' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 6),
                        '7' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 7),
                        '8' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 8),
                        '9' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 9),
                        '10' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 10),
                        '11' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 11),
                        '12' => sprintf( esc_html__('%d months', 'woocommerce-shiptor'), 12),
                        'forever' =>  esc_html__('Forever', 'woocommerce-shiptor'),
                    ),
                ),
                'autofill_empty_database' => array(
                    'title' =>  esc_html__('Empty Database', 'woocommerce-shiptor'),
                    'type' => 'button',
                    'label' =>  esc_html__('Empty Database', 'woocommerce-shiptor'),
                    'description' =>  esc_html__('Delete all the saved cities in the database, use this option if you have issues with outdated cities.', 'woocommerce-shiptor'),
                ),
            ),
            'tab_5' => array(
                'requests_caching' => array(
                    'title' =>  esc_html__('Requests caching', 'woocommerce-shiptor'),
                    'type' => 'title',
                    'description' =>  esc_html__('Cache queries to speed up results.', 'woocommerce-shiptor'),
                ),
                'enable_requests_caching' => array(
                    'title' =>  esc_html__('Caching', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'label' =>  esc_html__('Enable requests caching', 'woocommerce-shiptor'),
                    'default' => 'no'
                ),
                'requests_caching_empty_database' => array(
                    'title' =>  esc_html__('Empty cache', 'woocommerce-shiptor'),
                    'type' => 'button',
                    'label' =>  esc_html__('Empty cache', 'woocommerce-shiptor'),
                    'description' =>  esc_html__('Delete all the saved requests.', 'woocommerce-shiptor'),
                ),
                'enable_common_log' => array(
                    'title' =>  esc_html__('Enable common debug log', 'woocommerce-shiptor'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'description' => ''
                )
            ),
        );

        // init fields by woocommerce
        $this->form_fields = array();
        foreach ($this->form_fields_grouped as $group) {
            $this->form_fields = array_merge($this->form_fields, $group);
        }
    }

    public function admin_options() {

        echo '<h2>' .esc_html($this->get_method_title()). '</h2>';

        echo wp_kses_post(wpautop($this->get_method_description()));

        include WC_Shiptor::get_plugin_path() . 'includes/admin/views/html-admin-help-message.php';
        include WC_Shiptor::get_plugin_path() . 'includes/admin/views/html-admin-tabs.php';

        echo '<div><input type="hidden" name="section" value="' . esc_attr($this->id) . '" /></div>';

        echo '<div class="js_tabs_container">';
        foreach ($this->form_fields_grouped as $tab_id => $fields) {
            $hidden = $tab_id != 'tab_1' ? 'hidden' : '';
            echo '<div id="'.esc_attr($tab_id).'" class="js_shiptor_tab '.esc_attr($hidden).'">';
            echo '<table>';
            $this->generate_settings_html($fields); // WPCS: XSS ok.
            echo '</table>';
            echo '</div>';
        }
        echo '</div>';


        wp_enqueue_style($this->id . '-admin', plugins_url('assets/admin/css/integration.css', WC_Shiptor::get_main_file()), array(), WC_SHIPTOR_VERSION);
        wp_enqueue_script('city_autocomplete', plugins_url('assets/admin/js/city_autocomplete.js', WC_Shiptor::get_main_file()), array('jquery', 'select2'), WC_SHIPTOR_VERSION, true);
        wp_enqueue_script($this->id . '-admin', plugins_url('assets/admin/js/integration.js', WC_Shiptor::get_main_file()), array('jquery', 'jquery-blockui', 'select2', 'city_autocomplete'), WC_SHIPTOR_VERSION, true);
        wp_enqueue_script($this->id . '-admin-widget', plugins_url('assets/admin/js/shiptor-logging/logging.js', WC_Shiptor::get_main_file()), array(), WC_SHIPTOR_VERSION);

        wp_localize_script(
            $this->id . '-admin', 'wc_shiptor_admin_params', array(
                'i18n' => array(
                    'confirm_message' => sprintf(__('Are you sure you want to delete all (%d) cities from the database? If you delete all cities, the settings associated with cities may be reset.', 'woocommerce-shiptor'), count(WC_Shiptor_Autofill_Addresses::get_all_address())),
                    'confirm_cached_requests_deletion_message' => sprintf(__('Are you sure you want to delete all cached requests?', 'woocommerce-shiptor')),
                    'confirm_logs_requests_deletion_message' => sprintf(__('Are you sure you want to delete all shiptor logs', 'woocommerce-shiptor'))
                ),
                'empty_autofill_addresses_nonce' => wp_create_nonce('empty_autofill_addresses_nonce'),
                'empty_shiptor_cache_nonce' => wp_create_nonce('empty_shiptor_cache_nonce'),
                'empty_shiptor_logs_nonce' => wp_create_nonce('empty_shiptor_logs_nonce'),
                'ajax_url' => WC_AJAX::get_endpoint("%%endpoint%%"),
                'country_iso' => WC()->countries->get_base_country()
            )
        );
    }

    public function generate_select_shiptor_warehouse_html($key, $data) {
        $connect = new WC_Shiptor_Connect('shiptor_warehouse');
        $stocks = $connect->get_stocks();
        $options = array(__('Not selected', 'woocommerce-shiptor'));

        foreach ($stocks as $row) {
            $options[$row['id']] = $row['address'];
        }

        $value = $this->get_option( $key );
        if ($value) {
            $stock = $connect->get_stock($value);

            if ($stock) {
                $type = implode('/', $stock['roles']);

                $data['after_description'] = array(
                    'address' => $stock['address'],
                    'type' => $type,
                );
            }
        }

        $data['options'] = $options;

        return $this->generate_select_descripted_html($key, $data); // WPCS: XSS ok.
    }

    public function generate_select_city_origin_html($key, $data) {

        $city_origin = WC_Shiptor_Autofill_Addresses::get_city_by_id($this->get_option($key));

        if (empty($city_origin['kladr_id'])) {
            $city_origin = array(
                'kladr_id' => '77000000000',
                'city_name' => 'Москва',
                'state' => 'г.Москва'
            );
        }

        $data['type'] = 'select';
        $data['options'] = array($city_origin['kladr_id'] => sprintf('%s (%s)', $city_origin['city_name'], $city_origin['state']));

        return $this->generate_select_html($key, $data); // WPCS: XSS ok.
    }

    public function generate_button_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'label' => '',
            'desc_tip' => false,
            'description' => '',
        );
        $data = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); // WPCS: XSS ok.?></label>
                <?php echo $this->get_tooltip_html($data);// WPCS: XSS ok. ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); // WPCS: XSS ok.?></span></legend>
                    <button class="button-secondary" type="button" id="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['label']); // WPCS: XSS ok.?></button>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function generate_checkbox_html($key, $data) {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'title'             => '',
            'label'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args( $data, $defaults );

        if ( ! $data['label'] ) {
            $data['label'] = $data['title'];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); // WPCS: XSS ok.?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] );// WPCS: XSS ok. ?></span></legend>
                    <label for="<?php echo esc_attr( $field_key ); ?>">
                    <input <?php disabled( (boolean)$data['disabled'], true ); ?> class="<?php echo esc_attr( $data['class'] ); ?>" type="checkbox" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="1" <?php checked( esc_attr($this->get_option( $key )), 'yes' ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> /> <?php echo wp_kses_post( $data['label'] ); ?></label><br/>
                    <?php if($key == 'enable_common_log'): ?>
                        <?php echo $this->after_log_settings_html( $data );// WPCS: XSS ok. ?>
                    <?php else: ?>
                        <?php echo $this->get_description_html( $data );// WPCS: XSS ok. ?>
                    <?php endif ?>

                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();// WPCS: XSS ok.
    }

    public function generate_select_descripted_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); // WPCS: XSS ok.?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); // WPCS: XSS ok.?></span></legend>
					<select class="select <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( (boolean)$data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>>
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, esc_attr( $this->get_option( $key ) ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>

                    <?php if (!empty($data['after_description'])): ?>
                        <p class="description">
					    <?php _e(sprintf('Address: %s', $data['after_description']['address']), 'woocommerce-shiptor'); ?>
                        </p>
                        <p class="description">
					    <?php _e(sprintf('Type: %s', $data['after_description']['type']), 'woocommerce-shiptor'); ?>
                        </p>
                    <?php endif; ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();// WPCS: XSS ok.
	}

    public function after_log_settings_html(){
        $log_files = WC_Shiptor_Log::getLogFiles();
        ob_start();
        ?>
        <div class="woocomerce-shiptor-log-analyzer">
            <?php if(count($log_files)):?>
                <div class="woocomerce-shiptor-log-analyzer-logs">
                    <select id="woocomerce-shiptor-log-analyzer-logs-select">
                        <?php foreach($log_files as $file_name => $file_link): ?>
                        <?php $timestamp = time()?>
                        <option value="<?php echo esc_attr("{$file_link}?t={$timestamp}") ?>" ><?php echo esc_html($file_name); ?></option>
                        <?php endforeach?>
                    <select>
                </div>
                <div class="woocomerce-shiptor-log-analyzer-actions">
                    <?php
                        $first_file_url = reset($log_files);
                    ?>
                    <a href="#" data-url="<?php echo esc_url($first_file_url) ?>" data-role="shiptor_widget_logging_show" id="woocommerce-shiptor-shiptor-log-analyze" class="button-primary"><?php esc_html_e('Analyze log', 'woocommerce-shiptor')?></a>
                    <a id="woocommerce-shiptor-shiptor-log-clear" class="button-primary"><?php esc_html_e('Clear logs', 'woocommerce-shiptor')?></a>
                </div>
            <?php endif ?>
        </div>
        <?php
        return ob_get_clean();// WPCS: XSS ok.
    }

    public function setup_api_token() {
        return $this->api_token;
    }

    public function setup_city_origin() {
        return $this->city_origin;
    }

    public function setup_update_interval() {
        return $this->update_interval;
    }

    public function setup_default_weight() {
        return $this->minimum_weight;
    }

    public function setup_default_height() {
        return $this->minimum_height;
    }

    public function setup_default_width() {
        return $this->minimum_width;
    }

    public function setup_default_length() {
        return $this->minimum_length;
    }

    public function setup_tracking_history() {
        return 'yes' === $this->tracking_enable;
    }

    public function setup_autofill_addresses_validity_time() {
        return $this->autofill_validity;
    }

    public function ajax_empty_addresses() {
        global $wpdb;
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array('message' =>  esc_html__('Missing parameters!', 'woocommerce-shiptor')));
            exit;
        }
        if (!wp_verify_nonce(sanitize_key($_POST['nonce']), 'empty_autofill_addresses_nonce')) {
            wp_send_json_error(array('message' =>  esc_html__('Invalid nonce!', 'woocommerce-shiptor')));
            exit;
        }

        WC_Shiptor_Autofill_Addresses::clearTable();
        wp_send_json_success(array('message' =>  esc_html__('Cities database emptied successfully!', 'woocommerce-shiptor')));
    }

    public function ajax_shiptor_clear_logs() {
        global $wpdb;
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array('message' =>  esc_html__('Missing parameters!', 'woocommerce-shiptor')));
            exit;
        }
        if (!wp_verify_nonce(sanitize_key($_POST['nonce']), 'empty_shiptor_logs_nonce')) {
            wp_send_json_error(array('message' =>  esc_html__('Invalid nonce!', 'woocommerce-shiptor')));
            exit;
        }
        WC_Shiptor_Log::clearLogs();
        wp_send_json_success(array('message' =>  esc_html__('Logs emptied successfully!', 'woocommerce-shiptor')));
    }

    public function ajax_empty_cached_requests() {
        global $wpdb;
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array('message' =>  esc_html__('Missing parameters!', 'woocommerce-shiptor')));
            exit;
        }
        if (!wp_verify_nonce(sanitize_key($_POST['nonce']), 'empty_shiptor_cache_nonce')) {
            wp_send_json_error(array('message' =>  esc_html__('Invalid nonce!', 'woocommerce-shiptor')));
            exit;
        }

        try{
            $shiptor_cache = new WC_Shiptor_Cache();
            $shiptor_cache->clearCache();
            wp_send_json_success(array('message' =>  esc_html__('Requests cache emptied successfully!', 'woocommerce-shiptor')));
        } catch(Exception $e){
            wp_send_json_error(array('message' =>  esc_html__('Error happen while clearing requests cache!', 'woocommerce-shiptor')));
        }

    }

    public function ajax_synchronize_methods_with_api() {
        global $wpdb;

        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array('message' =>  esc_html__('Missing parameters!', 'woocommerce-shiptor')));
            exit;
        }
        if (!wp_verify_nonce(sanitize_key($_POST['nonce']), 'synchronize_methods_with_api_nonce')) {
            wp_send_json_error(array('message' =>  esc_html__('Invalid nonce!', 'woocommerce-shiptor')));
            exit;
        }

        $table = $wpdb->prefix . self::$shipping_methods_table;
        $wpdb->query("TRUNCATE TABLE $table;");

        $shipping_methods = $this->connect->get_shipping_methods();

        $values = array();
        foreach($shipping_methods as $method){
            $method = wc_clean($method);
            $value = "('{$method['id']}', '{$method['name']}', '{$method['category']}', '{$method['group']}', '{$method['courier']}', '{$method['courier_code']}', '{$method['description']}', '{$method['help_url']}' )";
            $values[] = $value;
        }

        if( count($values) ){
            $values = implode(', ', $values);
            $sql = "
                INSERT INTO {$table} (`id`, `name`, `category`, `group_courier`, `courier`, `courier_code`, `description`, `help_url`)
                VALUES $values";

            $result = $wpdb->query($sql);
        }

        wp_send_json_success(array());
    }

    public function process_admin_options() {
        parent::process_admin_options();

        if (empty($this->settings['api_token'])) {
            $message = __('Empty API token. ', 'woocommerce-shiptor') .  esc_html__('The Token API is required for the plugin to work. You can get it in your personal account https://shiptor.ru/account/settings/api', 'woocommerce-shiptor');
            WC_Admin_Notices::add_custom_notice($this->id . '_api_token', $message);
        } else {
            if (WC_Admin_Notices::has_notice($this->id . '_api_token')) {
                WC_Admin_Notices::remove_notice($this->id . '_api_token');
            }
        }

        wp_clear_scheduled_hook('shiptor_orders_cron');
        if ($this->settings['cron_order_status'] && $this->settings['cron_period']) {
            wp_schedule_event(time(), $this->settings['cron_period'], 'shiptor_orders_cron');
        }
        if ($this->settings['update_interval'] !== $this->update_interval) {
            wp_clear_scheduled_hook('shiptor_update_order_statuses');
            wp_schedule_event(time(), $this->settings['update_interval'], 'shiptor_update_order_statuses');
        }
    }
}