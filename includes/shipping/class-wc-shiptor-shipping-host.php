<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Shipping_Host_Method extends WC_Shipping_Method {

    public static $shipping_methods_table = 'shiptor_shipping_methods';

    protected $service = 'shiptor-shipping-host';

    public function __construct($instance_id = 0) {
        $this->id = 'shiptor-shipping-host';
        $this->method_title =  esc_html__('Shiptor', 'woocommerce-shiptor');
        $this->title = $this->method_title;

        $this->instance_id = absint($instance_id);
        $this->method_description = $this->method_description ? $this->method_description : sprintf( esc_html__('%s is a shipping method from Shiptor.', 'woocommerce-shiptor'), $this->method_title);
        $this->has_settings = false;
        $this->supports = array(
            'zones',
            'shipping-zones',
            'instance-settings'
        );

        $this->connect = new WC_Shiptor_Connect($this->id, $this->instance_id);
        $this->shipping_methods = $this->get_saved_shipping_methods();

        // Load the form fields.
        $this->init_form_fields();

        // Define user set variables.
        $this->client_methods = $this->get_client_methods();
        $this->init_methods_form_fields();

        $this->available_rates = array();

        // Save admin options.
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        add_filter('woocommerce_available_payment_gateways', array($this, 'disable_cod_payment'));
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_checkout'), 10, 2);
    }

    protected function get_client_methods() {
        $client_methods = array();
        $methods_array = array();

        try{
            $methods_array = unserialize( $this->get_instance_option('client_methods', serialize($methods_array)) );
        } catch(Exception $e) {
            $methods_array = array();
        }

        foreach($methods_array as $key => $method){
            if( !$method ){
                continue;
            }

            $client_method = new WC_Shiptor_Shipping_Client_Method;
            $client_method->is_new = false;

            foreach( $method as $field_name => $field_value ){
                $client_method->{$field_name} = $field_value;
            }

            $client_methods[ $key ] = $client_method;
        }

        return $client_methods;
    }

    protected function get_serialized_client_methods() {
        $methods_array = array();
        foreach($this->client_methods as $key => $client_method){
            $methods_array[ $key ] = $client_method->to_array();
        }

        return serialize($methods_array);
    }

    protected function get_saved_shipping_methods() {
        global $wpdb;
        $table = $wpdb->prefix . self::$shipping_methods_table;
        return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    }

    public function get_enabled_client_methods() {
        $client_methods = $this->client_methods;

        $client_methods = array_filter($client_methods, function($method) {
            return $method->enable_method == 'yes';
        });

        foreach( $client_methods as $key => $client_method ){
            foreach( $this->shipping_methods as $shipping_method ) {
                if( $client_method->method_id  == $shipping_method['id'] ){
                    $client_methods[ $key ]->data = $shipping_method;
                }
            }
        }

        return $client_methods;
    }

    public function admin_options() {
        // yandex maps
        $ym_apikey = get_option('woocommerce_shiptor-integration_settings');
        $ym_api_args = array('lang' => get_locale());
        if ( !empty ($ym_apikey['yandex_api_token']) ) {
            $ym_api_args['api_key'] = $ym_apikey['yandex_api_token'];
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $ym_api_args['mode'] = 'debug';
        }
        wp_enqueue_script('jquery-modal',plugins_url('/assets/frontend/js/jquery-modal/jquery.modal.min.js', WC_SHIPTOR_PLUGIN_FILE), array('jquery'));
        wp_enqueue_script('yandex-map', add_query_arg($ym_api_args, '//api-maps.yandex.ru/2.1'), array(), '2.1', true);
        wp_enqueue_script($this->id . '-select-point-admin', plugins_url('assets/admin/js/select-point-map.js', WC_SHIPTOR_PLUGIN_FILE), array('jquery', 'yandex-map', 'jquery-modal'), WC_SHIPTOR_VERSION, true);
        wp_enqueue_style(
            'jquery-modal',
            plugins_url('/assets/frontend/css/jquery-modal/jquery.modal.min.css', WC_SHIPTOR_PLUGIN_FILE)
        );
        wp_enqueue_style('jquery-modal-style', plugins_url('/assets/frontend/css/checkout.css', WC_SHIPTOR_PLUGIN_FILE), array('jquery-modal'), WC_SHIPTOR_VERSION);


        wp_enqueue_script($this->id . '-admin', plugins_url('assets/admin/js/shipping-method.js', WC_SHIPTOR_PLUGIN_FILE), array('selectWoo'), WC_SHIPTOR_VERSION, true);
        wp_enqueue_style('validate-settings', plugins_url('assets/admin/css/validation.css', WC_SHIPTOR_PLUGIN_FILE), null, WC_SHIPTOR_VERSION);
        wp_enqueue_style('shipping-method', plugins_url('assets/admin/css/shipping-method.css', WC_SHIPTOR_PLUGIN_FILE), null, WC_SHIPTOR_VERSION);

        wp_localize_script(
            $this->id . '-admin', 'wc_shiptor_admin_params', array(
                'placeholder' =>  esc_html__('Choose an city', 'woocommerce-shiptor'),
                'ajax_url' => WC_AJAX::get_endpoint("%%endpoint%%"),
                'country_iso' => WC()->countries->get_base_country(),
                'synchronize_methods_with_api_nonce' => wp_create_nonce('synchronize_methods_with_api_nonce'),
                'shiptor_warehouse_info_nonce' => wp_create_nonce('shiptor_warehouse_info_nonce'),
            )
        );

        // container for yandex map
        include WC_Shiptor::get_plugin_path() . 'includes/admin/views/html-admin-yandex-map-template.php';

        parent::admin_options();
    }

    /** Rewrited from parent */
    public function get_option( $key, $empty_value = null ) {
        if( !$this->is_method_key( $key ) ){
            return parent::get_option( $key, $empty_value );
        }

        $method_id = $this->get_method_id_from_key( $key );
        $field_name = $this->get_method_field_from_key( $key );

        if ( ! isset( $this->client_methods[ $method_id ] ) ) {
            $this->client_methods[ $method_id] = new WC_Shiptor_Shipping_Client_Method;
        }

        if( $this->client_methods[ $method_id]->is_new ){
            $form_fields            = $this->get_method_form_fields( $method_id );
            $this->client_methods[ $method_id ]->{$field_name} = $form_fields[ $key ] ? $this->get_field_default( $form_fields[ $key ] ) : '';
        }

        if ( ! is_null( $empty_value ) && '' === $this->client_methods[ $method_id ]->{$field_name} ) {
            $this->client_methods[ $method_id ]->{$field_name} = $empty_value;
        }

        return $this->client_methods[ $method_id ]->{$field_name};
    }

    public function get_instance_option( $key, $empty_value = null ) {
        if ( empty( $this->instance_settings ) ) {
            $this->init_instance_settings();
        }

        // Get option default if unset.
        if ( ! isset( $this->instance_settings[ $key ] ) ) {
            $form_fields                     = $this->get_instance_form_fields();
            if( ! isset( $form_fields[ $key ] ) ){
                $this->instance_settings[ $key ] = $empty_value;
            } else {
                $this->instance_settings[ $key ] = $this->get_field_default( $form_fields[ $key ] );
            }
        }

        if ( ! is_null( $empty_value ) && '' === $this->instance_settings[ $key ] ) {
            $this->instance_settings[ $key ] = $empty_value;
        }

        $instance_option = apply_filters( 'woocommerce_shipping_' . $this->id . '_instance_option', $this->instance_settings[ $key ], $key, $this );
        return $instance_option;
    }

    /** Rewrite from parent */
    public function process_admin_options() {
        if ( ! $this->instance_id ) {
            return parent::process_admin_options();
        }

        // Check we are processing the correct form for this instance.
        if ( ! isset( $_REQUEST['instance_id'] ) || absint( $_REQUEST['instance_id'] ) !== $this->instance_id ) { // WPCS: input var ok, CSRF ok.
            return false;
        }

        $this->init_instance_settings();

        $post_data = $this->get_post_data();
        $client_methods_field_key = $this->get_field_key( 'client_methods' );

        if( isset( $post_data[ $client_methods_field_key ] ) ){
            $post_client_methods_data = $post_data[ $client_methods_field_key ];

            foreach( $post_client_methods_data as $method_id => $post_client_method_data ){
                if ( ! isset( $this->client_methods[ $method_id ] ) ) {
                    $this->client_methods[ $method_id] = new WC_Shiptor_Shipping_Client_Method;
                }

                foreach ( $this->get_method_form_fields( $method_id ) as $key => $field ) {
                    if ( 'title' !== $this->get_field_type( $field ) ) {
                        try {
                            $method_id = $this->get_method_id_from_key( $key );
                            $field_name = $this->get_method_field_from_key( $key );
                            $value = $this->get_method_field_value( $field_name, $field, $post_client_method_data );

                            /* If method enabled check required fields */
                            if( isset($post_client_method_data['enable_method']) &&
                                $post_client_method_data['enable_method'] == '1' &&
                                isset($field['is_required']) &&
                                $field['is_required'] &&
                                (is_null($value) || $value == '') )
                            {
                                $client_method = $this->client_methods[ $method_id ];
                                throw new Exception( sprintf( esc_html__('Method %s: field %s is required', 'woocommerce-shiptor'), $client_method->title, $field['title']) );
                            }

                            if($field_name == 'method_id'){
                                $value = $method_id;
                            }
                            $this->client_methods[ $method_id ]->{$field_name} = $value;
                        } catch ( Exception $e ) {
                            $this->add_error( $e->getMessage() );
                        }
                    }
                }

                $this->client_methods[ $method_id ]->is_new = false;
            }


            foreach ($this->client_methods as $method_id => $clientMethod) {
                if (!isset($post_client_methods_data[$method_id])) {
                    unset($this->client_methods[$method_id]);
                }
            }
        }

        $this->instance_settings[ 'client_methods' ] = $this->get_serialized_client_methods();

        return update_option( $this->get_instance_option_key(), apply_filters( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', $this->instance_settings, $this ), 'yes' );
    }

    public function init_form_fields() {
        $this->instance_form_fields = array(
            'synchronize_methods_with_api' => array(
                'title' =>  esc_html__('Synchronize methods with API', 'woocommerce-shiptor'),
                'type' => 'button',
                'label' =>  esc_html__('Synchronize methods with API', 'woocommerce-shiptor'),
                'class' => array('button-primary')
            )
        );

        if( count($this->shipping_methods) ){
            $shipping_methods_tabs = array(
                'shipping_methods_tabs' => array(
                    'title' =>  esc_html__('Shipping methods', 'woocommerce-shiptor'),
                    'type' => 'shipping_tabs',
                    'tabs' => $this->shipping_methods,
                )
            );

            $this->instance_form_fields = array_merge($this->instance_form_fields, $shipping_methods_tabs);
        }
    }

    public function init_methods_form_fields() {
        $this->methods_form_fields = [];

        $shipping_methods = $this->shipping_methods;
        foreach($shipping_methods as $method){
            $this->methods_form_fields[ $method['id'] ] = $this->get_initials_methods_fields( $method );
        }
    }

    public function get_method_form_fields( $method_id ) {
        if( ! $this->methods_form_fields ){
            $this->init_methods_form_fields();
        }

        return isset($this->methods_form_fields[$method_id]) ? $this->methods_form_fields[$method_id] : [];
    }

    public function generate_cityes_multiselect_html($key, $data) {
        $data['options'] = array();
        $values = (array) $this->get_option($key, array());

        foreach ($values as $kladr_id) {
            $data['options'] += $this->get_city_by_id($kladr_id);
        }

        return $this->generate_multiselect_html($key, $data); // WPCS: XSS ok.
    }

    public function generate_shipping_tabs_html($key, $data) {
        $tabs = $data['tabs'];

        $current_selected_tab = null;
        if ( isset($_COOKIE['shiptor_admin_shipping_method_tab_current']) ) {
            $current_selected_tab = sanitize_text_field($_COOKIE['shiptor_admin_shipping_method_tab_current']);
        }

        $client_methods = $this->get_client_methods();

        ob_start();
        ?>
        </table>
        <div class="tabs-wrapper">
            <ul class="tabs">
                <?php $is_first_tab = true; ?>
                <?php foreach ($tabs as $key => $method) : ?>
                <?php
                    $is_active_tab = false;
                    $is_enabled_tab = false;
                    $is_valid_tab = true;
                    $current_client_method = null;
                    $class_list = array();
                    $class_list[] = 'tab';

                    foreach ($client_methods as $client_method) {
                        if( $client_method->method_id == $method['id']) {
                            $current_client_method = $client_method;
                            break;
                        }
                    }

                    //Check for enabled and valid tab
                    if ($current_client_method) {
                        if ($current_client_method->enable_method == 'yes') {
                            $class_list[] = 'enabled';
                            $is_enabled_tab = true;
                        } else {
                            $class_list[] = 'disabled';
                        }

                        if( $is_enabled_tab ) {
                            foreach ( $this->get_method_form_fields( $method['id'] ) as $key => $field ) {
                                if ( 'title' !== $this->get_field_type( $field ) ) {
                                    $field_name = $this->get_method_field_from_key( $key );
                                    $value = $current_client_method->{$field_name};

                                    if( isset($field['is_required']) &&
                                        $field['is_required'] &&
                                        (is_null($value) || $value == '') )
                                    {
                                        $class_list[] = 'invalid';
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    $tab_id = "#method_{$method['id']}";
                    $is_active_tab = false;

                    if ($current_selected_tab) {
                        $is_active_tab = ($current_selected_tab == $tab_id);
                    } else {
                        $is_active_tab = $is_first_tab;
                    }

                    if ($is_active_tab) {
                        $class_list[] = 'active';
                    }
                ?>

                <li class="<?php echo esc_attr(implode(' ', $class_list)) ?>">
                    <a href="<?php echo esc_url($tab_id) ?>">
                        <?php echo esc_html($method['name']) ?>
                    </a>
                </li>
                <?php $is_first_tab = false; ?>
                <?php endforeach ?>
            </ul>

            <div class="tab-content">
                <?php $is_first_tab = true; ?>
                <?php foreach ($tabs as $key => $method) : ?>
                <?php
                    $tab_id = "method_{$method['id']}";
                    $is_active_tab = false;
                    $class_list = array();
                    $class_list[] = 'tab-pane';

                    if ($current_selected_tab) {
                        $is_active_tab = ($current_selected_tab == "#$tab_id");
                    } else {
                        $is_active_tab = $is_first_tab;
                    }

                    if ($is_active_tab) {
                        $class_list[] = 'active';
                    }
                ?>
                <div class="<?php echo esc_attr(implode(' ', $class_list)) ?>" id="<?php echo esc_attr($tab_id) ?>">
                    <div class="tab-pane-header">
                        <div class="method-title">
                            <a target="_blank" href="<?php echo esc_url($method['help_url']) ?>"><?php echo esc_html($method['name']) ?></a>
                        </div>
                        <div class="method-description">
                            <?php echo esc_html($method['description']) ?>
                        </div>
                    </div>
                    <div class="tab-pane-content">
                        <table class="form-table">
                            <?php echo $this->generate_shiping_tab_form( $method ); // WPCS: XSS ok. ?>
                        </table>
                    </div>
                </div>
                <?php $is_first_tab = false; ?>
                <?php endforeach ?>
            </div>
        </div>
        <?php

        return ob_get_clean(); // WPCS: XSS ok.
    }

    public function generate_hidden_html( $key, $data ) {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'type'              => 'hidden',
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr class="hidden">
            <th></th>
            <td >
                <input class="hidden" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" />
            </td>
        </tr>
        <?php

        return ob_get_clean(); // WPCS: XSS ok.
    }

    public function generate_shiping_tab_form( $method ){
        if ( empty( $form_fields ) ) {
            $form_fields = $this->get_method_form_fields( $method['id'] );
        }

        $html = '';
        foreach ( $form_fields as $k => $v ) {
            $type = $this->get_field_type( $v );

            if ( method_exists( $this, 'generate_' . $type . '_html' ) ) {
                $html .= $this->{'generate_' . $type . '_html'}( $k, $v ); // WPCS: XSS ok.
            } else {
                $html .= $this->generate_text_html( $k, $v ); // WPCS: XSS ok.
            }
        }

        return $html; // WPCS: XSS ok.
    }

    public function generate_text_map_html( $key, $data) {
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
            'is_required'       => false
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $data['title'] ); ?>
                    <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?>
                    <?php if($data['is_required']): ?>
                    <span class="required-mark">*</span>
                    <?php endif?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html( $data['title'] ); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( (boolean)$data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
                    <button type="button" class="button-primary js_ajax_load_points" data-id="<?php echo esc_attr( $data['id'] ); ?>"><?php esc_html_e('Select', 'woocommerce-shiptor') ?></button>

                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean(); // WPCS: XSS ok.
    }

    public function generate_text_html( $key, $data ) {
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
            'is_required'       => false
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $data['title'] ); ?>
                    <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?>
                    <?php if($data['is_required']): ?>
                    <span class="required-mark">*</span>
                    <?php endif?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html( $data['title'] ); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( (boolean)$data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean(); // WPCS: XSS ok.
    }

    public function generate_select_html( $key, $data ) {
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
            'is_required'       => false
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $data['title'] ); ?>
                    <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?>
                    <?php if($data['is_required']): ?>
                    <span class="required-mark">*</span>
                    <?php endif?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html( $data['title'] ); ?></span></legend>
                    <select class="select <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( (boolean)$data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>>
                        <?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
                            <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, esc_attr( $this->get_option( $key ) ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean(); // WPCS: XSS ok.
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
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($data['title']); ?></label>
                <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html($data['title']); ?></span></legend>
                    <button class="button-secondary" type="button" id="<?php echo esc_attr($key); ?>"><?php echo wp_kses_post($data['label']); ?></button>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
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

        return $this->generate_select_html($key, $data); // WPCS: XSS ok.
    }

    public function generate_select_city_html($key, $data) {
        $data['type'] = 'select';
        $data['options'] = $this->get_city_by_id($this->get_option($key, $data['default']));
        return $this->generate_select_html($key, $data); // WPCS: XSS ok.
    }

    public function validate_cityes_multiselect_field($key, $value) {
        return $this->validate_titles_field($key, $value);
    }

    public function validate_titles_field($key, $value) {
        return $this->validate_multiselect_field($key, $value);
    }

    public function validate_price_field($key, $value) {
        $value = is_null($value) ? '' : $value;
        $new_value = '' === $value ? '' : wc_format_decimal(trim(stripslashes($value)));
        if ('%' === substr($value, -1)) {
            $new_value .= '%';
        }
        return $new_value;
    }

    public function methodShoudHaveDeliveryPoints($method) {
        return self::methodShoudHaveDeliveryPointsStatic($method);
    }
    public static function methodShoudHaveDeliveryPointsStatic($method) {
        return in_array($method['category'], array(
            'delivery-point',
            'delivery-point-to-delivery-point',
            'door-to-delivery-point'
        ));
    }

    public function methodIsEndToEnd($method) {
        if ( !empty($method['category']) ) {
            return in_array($method['category'], array(
                'door-to-door',
                'delivery-point-to-door',
                'delivery-point-to-delivery-point',
                'door-to-delivery-point'
            ));
        }
    }

    protected function get_initials_methods_fields( $method ) {
        $default_fields_keys = array(
            'method_id'             => $this->get_method_field_key($method['id'], 'method_id'),
            'enable_method'         => $this->get_method_field_key($method['id'], 'enable_method'),
            'title'                 => $this->get_method_field_key($method['id'], 'title'),
            'behavior_options'      => $this->get_method_field_key($method['id'], 'behavior_options'),
            'shiptor_warehouse'     => $this->get_method_field_key($method['id'], 'shiptor_warehouse'),
            'is_fulfilment'         => $this->get_method_field_key($method['id'], 'is_fulfilment'),
            'shipping_class_id'     => $this->get_method_field_key($method['id'], 'shipping_class_id'),
            'show_delivery_time'    => $this->get_method_field_key($method['id'], 'show_delivery_time'),
            'additional_time'       => $this->get_method_field_key($method['id'], 'additional_time'),
            'fee'                   => $this->get_method_field_key($method['id'], 'fee'),
            'fee_type'              => $this->get_method_field_key($method['id'], 'fee_type'),
            'min_cost'              => $this->get_method_field_key($method['id'], 'min_cost'),
            'free'                  => $this->get_method_field_key($method['id'], 'free'),
            'fix_cost'              => $this->get_method_field_key($method['id'], 'fix_cost'),
            'enable_declared_cost'  => $this->get_method_field_key($method['id'], 'enable_declared_cost'),
            'cityes_limit'          => $this->get_method_field_key($method['id'], 'cityes_limit'),
            'cityes_list'           => $this->get_method_field_key($method['id'], 'cityes_list'),
            'end_to_end'            => $this->get_method_field_key($method['id'], 'end_to_end'),
            'sender_city'           => $this->get_method_field_key($method['id'], 'sender_city'),
            'sender_address'        => $this->get_method_field_key($method['id'], 'sender_address'),
            'sender_name'           => $this->get_method_field_key($method['id'], 'sender_name'),
            'sender_email'          => $this->get_method_field_key($method['id'], 'sender_email'),
            'sender_phone'          => $this->get_method_field_key($method['id'], 'sender_phone'),
        );

        $method_form_fields = array(
            "{$default_fields_keys['method_id']}" => array(
                'type' => 'hidden',
                'default' => $method['id'],
            ),
            "{$default_fields_keys['enable_method']}" => array(
                'title' =>  esc_html__('Delivery Time', 'woocommerce-shiptor'),
                'type' => 'checkbox',
                'label' =>  esc_html__('Enable Shipping Method', 'woocommerce-shiptor'),
                'default' => 'no',
            ),
            "{$default_fields_keys['title']}" => array(
                'title' =>  esc_html__('Title', 'woocommerce-shiptor'),
                'type' => 'text',
                'description' => 'Этот заголовок будет отображаться только в списке добавленых методов, для вашего удобства.',
                'desc_tip' => true,
                'default' => $method['name'],
            ),
            "{$default_fields_keys['behavior_options']}" => array(
                'title' =>  esc_html__('Behavior Options', 'woocommerce-shiptor'),
                'type' => 'title',
                'default' => '',
            ),
            "{$default_fields_keys['shiptor_warehouse']}" => array(
                'title' =>  esc_html__('Shiptor warehouse', 'woocommerce-shiptor'),
                'class' => 'wc-enhanced-select shiptor_warehouse',
                'type' => 'select_shiptor_warehouse',
                'default' => 0,
                'desc_tip' => true,
                'description' =>  esc_html__('Shipment terminal or fulfillment warehouse', 'woocommerce-shiptor'),
            ),
            "{$default_fields_keys['is_fulfilment']}" => array(
                'title' =>  esc_html__('Collect from the warehouse? (only fullfilment)', 'woocommerce-shiptor'),
                'label' =>  esc_html__('On/Off (Only fulfillment)', 'woocommerce-shiptor'),
                'type' => 'checkbox',
                'default' => 'yes',
                'desc_tip' => true,
                'class' => 'shiptor_warehouse_fulfillment',
                'description' =>  esc_html__('enable/disable collection of parcels sent with this profile', 'woocommerce-shiptor'),
            ),
            "{$default_fields_keys['shipping_class_id']}" => array(
                'title' =>  esc_html__('Shipping Class', 'woocommerce-shiptor'),
                'type' => 'select',
                'description' =>  esc_html__('If necessary, select a shipping class to apply this method.', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'default' => '',
                'class' => 'wc-enhanced-select',
                'options' => $this->get_shipping_classes_options(),
            ),
            "{$default_fields_keys['show_delivery_time']}" => array(
                'title' =>  esc_html__('Delivery Time', 'woocommerce-shiptor'),
                'type' => 'checkbox',
                'label' =>  esc_html__('Show estimated delivery time', 'woocommerce-shiptor'),
                'description' =>  esc_html__('Display the estimated delivery time in working days.', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'default' => 'no',
            ),
            "{$default_fields_keys['additional_time']}" => array(
                'title' =>  esc_html__('Additional Days', 'woocommerce-shiptor'),
                'type' => 'text',
                'description' =>  esc_html__('Additional working days to the estimated delivery. Only positive values', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'default' => '0',
                'placeholder' => '0',
            ),
            "{$default_fields_keys['fee']}" => array(
                'title' =>  esc_html__('Handling Fee/Discount', 'woocommerce-shiptor'),
                'type' => 'price',
                'description' =>  esc_html__('Enter an amount, e.g. 250, or a percentage, e.g. 5%. If amount is negative e.g. -250 or a percentage, e.g. -5% a discount is applied. Leave blank to disable.', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'default' => '0',
            ),
            "{$default_fields_keys['fee_type']}" => array(
                'title' =>  esc_html__('Handling Fee type', 'woocommerce-shiptor'),
                'type' => 'select',
                'description' =>  esc_html__('Choose how to apply a surcharge', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'default' => 'order',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    'order' => 'Только на стоимость корзины',
                    'shipping' => 'Только на стоимость доставки',
                    'both' => 'На весь заказ'
                )
            ),
            "{$default_fields_keys['min_cost']}" => array(
                'title' => 'Минимальная сумма корзины',
                'type' => 'price',
                'description' =>  esc_html__('Enter minimum order price for available this method. Leave blank if you dont wanna use this option.', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'default' => '0'
            ),
            "{$default_fields_keys['free']}" => array(
                'title' =>  esc_html__('Free shipping', 'woocommerce-shiptor'),
                'type' => 'price',
                'description' =>  esc_html__('Enter the amount at which this method will be free', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'default' => '0'
            ),
            "{$default_fields_keys['fix_cost']}" => array(
                'title' =>  esc_html__('Fixed cost', 'woocommerce-shiptor'),
                'type' => 'price',
                'description' =>  esc_html__('Enter the fixed amount for this method', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'default' => '0'
            ),
            "{$default_fields_keys['enable_declared_cost']}" => array(
                'title' => 'Страховать отправление',
                'type' => 'checkbox',
                'label' => 'Учитывать страховку при расчёте стоимости',
                'description' => 'Вкл/выкл страховку в стоиомсть доставки.',
                'desc_tip' => true,
                'default' => 'yes',
            ),
            "{$default_fields_keys['cityes_limit']}" => array(
                'title' =>  esc_html__('Delivery to cities', 'woocommerce-shiptor'),
                'type' => 'select',
                'description' =>  esc_html__('Enable or disable this delivery method to specific cities.', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'default' => '-1',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    '-1' =>  esc_html__('Disabled', 'woocommerce-shiptor'),
                    'on' =>  esc_html__('Enable for specified cities', 'woocommerce-shiptor'),
                    'off' =>  esc_html__('Disable for specified cities', 'woocommerce-shiptor')
                )
            ),
            "{$default_fields_keys['cityes_list']}" => array(
                'title' =>  esc_html__('Cities', 'woocommerce-shiptor'),
                'type' => 'cityes_multiselect',
                'description' =>  esc_html__('Cities list', 'woocommerce-shiptor'),
                'desc_tip' => true,
                'class' => 'city-ajax-load'
            ),
            "{$default_fields_keys['end_to_end']}" => array(
                'title' =>  esc_html__('End-to-end delivery options', 'woocommerce-shiptor'),
                'type' => 'title',
                'description' =>  esc_html__('Enter the default parameters for end-to-end delivery. Otherwise, end-to-end delivery methods will not be available.', 'woocommerce-shiptor')
            ),
            "{$default_fields_keys['sender_city']}" => array(
                'title' =>  esc_html__('Sender city', 'woocommerce-shiptor'),
                'type' => 'select_city',
                'class' => 'city-ajax-load js_city_kladr',
                'default' => wc_shiptor_get_option('city_origin'),
                'desc_tip' =>  esc_html__('Select the city of origin.', 'woocommerce-shiptor'),
                'is_required' => true
            ),
            "{$default_fields_keys['sender_address']}" => array(
                'title' =>  esc_html__('Departure point', 'woocommerce-shiptor'),
                'type' => 'text_map',
                'is_required' => true,
                'id' => $method['id'],
                'class' => 'js_sender_address',
                'custom_attributes' => array(
                    'readonly' => true,
                ),
                'desc_tip' => 'Введите адрес откуда будет отправляться зазаз. (только для доставки типа "от двери")'
            ),
            "{$default_fields_keys['sender_name']}" => array(
                'title' =>  esc_html__('Sender name', 'woocommerce-shiptor'),
                'type' => 'text',
                'desc_tip' => true,
                'is_required' => true,
                'description' =>  esc_html__('Enter sender name. Can be personal name or Organization name.', 'woocommerce-shiptor')
            ),
            "{$default_fields_keys['sender_email']}" => array(
                'title' =>  esc_html__('Sender E-mail', 'woocommerce-shiptor'),
                'type' => 'email',
                'desc_tip' => true,
                'description' =>  esc_html__('Enter sender e-mail.', 'woocommerce-shiptor'),
                'default' => get_option('admin_email'),
                'is_required' => true
            ),
            "{$default_fields_keys['sender_phone']}" => array(
                'title' =>  esc_html__('Sender phone', 'woocommerce-shiptor'),
                'type' => 'tel',
                'desc_tip' => true,
                'description' =>  esc_html__('Enter sender phone.', 'woocommerce-shiptor'),
                'placeholder' => '+79991234567',
                'is_required' => true
            )
        );

        // Пункт 10.1
        // Для сквозных методов ПВЗ-ПВЗ или ПВЗ-Дверь
        // реализовать возможность выбора ПВЗ на карте
        // в настройках профиля и единичном заказе
        // Для методов (прямой доставки), где "category": "delivery-point-to-delivery-point"
        // или "delivery-point-to-door" реализовать возможность выбора ПВЗ в настройках профиля
        if (in_array($method['category'], array('door-to-delivery-point', 'door-to-door'))) {
            $method_form_fields["{$default_fields_keys['sender_address']}"]['title'] =  esc_html__('Departure address', 'woocommerce-shiptor');
            $method_form_fields["{$default_fields_keys['sender_address']}"]['type'] = 'text';

            unset($method_form_fields["{$default_fields_keys['sender_address']}"]['custom_attributes']['readonly']);
        }

        $exclude_fields = array();
        $non_direct_fields = array(
            "{$default_fields_keys['end_to_end']}",
            "{$default_fields_keys['sender_address']}",
            "{$default_fields_keys['sender_city']}",
            "{$default_fields_keys['sender_name']}",
            "{$default_fields_keys['sender_email']}",
            "{$default_fields_keys['sender_phone']}"
        );

        switch ($method['courier']){
            case 'shiptor-one-day':
                $exclude_fields = array_merge($non_direct_fields, array(
                    "{$default_fields_keys['show_delivery_time']}",
                    "{$default_fields_keys['additional_time']}",
                    "{$default_fields_keys['cityes_limit']}",
                    "{$default_fields_keys['cityes_list']}",
                ));
                break;
            default:
                if( ! $this->methodIsEndToEnd($method) ){
                    $exclude_fields = $non_direct_fields;
                }
        }

        foreach ($exclude_fields as $field) {
            if (isset($method_form_fields[ $field] )) {
                unset($method_form_fields[ $field ]);
            }
        }

        return $method_form_fields;
    }

    protected function get_method_field_key($method_id, $field_name){
        return "client_methods[{$method_id}][{$field_name}]";
    }

    protected function is_method_key( $key ){
        preg_match('/client_methods\[([\d]+)\]\[([\S]+)\]/', $key, $matches);
        return count($matches) ? true : false;
    }

    protected function get_method_id_from_key( $key ){
        preg_match('/client_methods\[([\d]+)\]\[([\S]+)\]/', $key, $matches);
        return $matches[1];
    }

    protected function get_method_field_from_key( $key ){
        preg_match('/client_methods\[([\d]+)\]\[([\S]+)\]/', $key, $matches);
        return $matches[2];
    }

    protected function get_method_field_value( $key, $field, $post_data ) {
        $type      = $this->get_field_type( $field );
        $value     = isset( $post_data[ $key ] ) ? $post_data[ $key ] : null;

        if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
            return call_user_func( $field['sanitize_callback'], $value );
        }

        // Look for a validate_FIELDID_field method for special handling.
        if ( is_callable( array( $this, 'validate_' . $key . '_field' ) ) ) {
            return $this->{'validate_' . $key . '_field'}( $key, $value );
        }

        // Look for a validate_FIELDTYPE_field method.
        if ( is_callable( array( $this, 'validate_' . $type . '_field' ) ) ) {
            return $this->{'validate_' . $type . '_field'}( $key, $value );
        }

        // Fallback to text.
        return $this->validate_text_field( $key, $value );
    }

    protected function get_shipping_classes_options() {
        $shipping_classes = WC()->shipping->get_shipping_classes();
        $options = array(
            '-1' =>  esc_html__('Any Shipping Class', 'woocommerce-shiptor'),
            '0' =>  esc_html__('No Shipping Class', 'woocommerce-shiptor'),
        );
        if (!empty($shipping_classes)) {
            $options += wp_list_pluck($shipping_classes, 'name', 'term_id');
        }
        return $options;
    }

    protected function get_city_by_id($city_id) {
        $city_origin = WC_Shiptor_Autofill_Addresses::get_city_by_id($city_id);
        if ($city_origin && !empty($city_origin) && isset($city_origin['kladr_id']) && $city_origin['kladr_id'] > 0) {
            return array($city_origin['kladr_id'] => sprintf('%s (%s)', $city_origin['city_name'], $city_origin['state']));
        }

        return array();
    }

    public function get_service() {
        return apply_filters('woocommerce_shiptor_shipping_method_service', $this->service, $this->id, $this->instance_id);
    }

    protected function get_declared_value($package) {
        return $package['contents_cost'];
    }

    protected function get_package_rate_hash($connect){

        $height = $connect->get_height() ?: 0;
        $width = $connect->get_width() ?: 0;
        $length = $connect->get_length() ?: 0;
        $weight = $connect->get_weight() ?: 0;
        $kladr_id = $connect->get_kladr_id() ?: 0;
        $country_code = $connect->get_country_code() ?: "";
        $declared_cost = $connect->get_declared_cost() ?: 0;
        $cod = $connect->get_cod() ?: 0;
        $kladr_id_from = $connect->get_kladr_id_from() ?: 0;
        $card = $connect->get_card() ?: '';
        $courier = $connect->get_courier() ?: '';

        $hash = sha1($height
            .$width
            .$length
            .$weight
            .$kladr_id
            .$country_code
            .$declared_cost
            .$cod
            .$kladr_id_from
            .$card
            .$courier
            .date('Y-m-d H')
        );

        return $hash;
    }

    public function is_available($package) {
        $available = true;
        if (!$this->get_rates($package)) {
            $available = false;
        }

        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this);
    }

    public function get_rates($package) {
        $enabled_methods = $this->get_enabled_client_methods();

        if( !count($enabled_methods) ){
            return false;
        }

        if ( empty($package['destination']['country'])
            || empty($package['destination']['city'])
            || !isset($package['destination']['kladr_id'])
            || empty($package['destination']['kladr_id']) ) {
            return false;
        }

        if (!$this->instance_id) {
            return false;
        }

        $rates = array();
        foreach($enabled_methods as $enabled_method){

            if( !count( $enabled_method->data ) ){
                continue;
            }

            if($enabled_method->data['courier'] == 'shiptor-one-day'){
                $available = strtotime('today 12:00') > current_time('timestamp') || strtotime('today 21:00') < current_time('timestamp');
                if(!$available) {
                    continue;
                }
            }

            $methods_rates = $this->get_rates_for_avaible_methods($enabled_method, $package);
            $rates = array_merge($rates, $methods_rates);
        }

        if (count($rates) == 0) {
            return false;
        }

        $this->available_rates = $rates;

        return true;
    }

    public function get_delivery_points($rate, $package){
        $points = array();
        $method = null;

        $rate_meta_data = array();
        if( $rate instanceof WC_Shipping_Rate ){
            $rate_meta_data = $rate->get_meta_data();
        } elseif( $rate instanceof WC_Order_Item_Shipping ) {
            $meta_data = $rate->get_meta_data();
            foreach($meta_data as $meta){
                $rate_meta_data[ $meta->key ] = $meta->value;
            }
        }

        if( !isset($rate_meta_data['shiptor_method']) && !isset($rate_meta_data['shiptor_method']['id']) ){
            return $points;
        }

        $enabled_methods = $this->get_enabled_client_methods();
        foreach($enabled_methods as $enabled_method){
            if($enabled_method->method_id == $rate_meta_data['shiptor_method']['id']){
                $method = $enabled_method;
                break;
            }
        }

        if(!$method){
            return $points;
        }

        $this->connect->set_package($package);
        $this->connect->set_kladr_id($package['destination']['kladr_id']);
        $cod = $this->get_declared_value($package);

        $chosen_payment_method = WC()->session->get('chosen_payment_method');
        if ($package['destination']['country'] == 'RU' && in_array($chosen_payment_method, array('cod', 'cod_card'))) {
            $this->connect->set_declared_cost($cod);
        }

        $this->connect->set_cod(null);
        $this->connect->set_card(null);
        $this->connect->set_courier($method->data['courier']);
        $this->connect->set_shipping_method($method->method_id);

        $points = $this->connect->get_delivery_points();

        return $points;
    }

    protected function get_rates_for_avaible_methods($method, $package){
        $connect = new WC_Shiptor_Connect($this->id, $this->instance_id);
        $connect->set_package($package);
        $connect->set_kladr_id($package['destination']['kladr_id']);
        $connect->set_country_code($package['destination']['country']);

        $rates = array();
        if ( !empty($method->cityes_limit) && '-1' !== $method->cityes_limit ) {
            $cityes_list = is_array($method->cityes_list) ? $method->cityes_list : [];
            $allow_city = in_array($package['destination']['kladr_id'], $cityes_list);

            if ($method->cityes_limit== 'on' && $allow_city === false) {
                return $rates;
            } elseif ('off' == $method->cityes_limit && $allow_city === true) {
                return $rates;
            }
        }

        if (!$method->has_only_selected_shipping_class($package)) {
            return $rates;
        }

        $cod = $this->get_declared_value($package);
        if ($method->min_cost > 0 && $method->min_cost > $cod) {
            return $rates;
        }

        if ( $this->methodIsEndToEnd($method->data) && !empty($method->sender_city) ) {
            $connect->set_kladr_id_from($method->sender_city);
        }

        $declared_cost = 'yes' == $method->enable_declared_cost ? $cod : 10;
        $connect->set_declared_cost($declared_cost);

        $chosen_payment_method = WC()->session->get('chosen_payment_method');
        if ($package['destination']['country'] == 'RU' && in_array($chosen_payment_method, array('cod', 'cod_card'))) {
            $connect->set_cod($cod);
            $connect->set_declared_cost($cod);
            $connect->set_card($chosen_payment_method === 'cod_card');
        }
        $connect->set_courier($method->data['courier']);

        /* Work with  WC_Shiptor_Cart otherwise rates is empty array*/
        $rate_hash = $this->get_package_rate_hash($connect);
        $package_rates = $package['rates'];

        foreach($package_rates as $key => $package_rate){
            $rate_meta_data = $package_rate->get_meta_data();
            if($rate_meta_data && isset($rate_meta_data['shiptor_rate_hash'])){
                if($rate_hash == $rate_meta_data['shiptor_rate_hash']){
                    $rates[] = [
                        'id' => $package_rate->get_id(),
                        'label' => $package_rate->get_label(),
                        'cost' => $package_rate->get_cost(),
                        'meta_data' => $rate_meta_data
                    ];
                }
            }
        }

        if(count($rates)){
            return $rates;
        }


        $shipping = null;
        if( in_array($package['destination']['country'], array('RU', 'BY', 'KZ')) ) {
            // <-- Начало пункт тз 11.2.1
            $warehouse = $method->shiptor_warehouse ? $method->shiptor_warehouse : wc_shiptor_get_option('shiptor_warehouse');
            $kladr_id_from = 77000000000; // Москва
            if ($warehouse) {
                $stock = $connect->get_stock($warehouse);
                if ($stock) {
                    $kladr_id_from = $stock['code'];
                }
            }

            if ($method->shipmentType() == 'standard') {
                // 11.2.1
                $connect->set_kladr_id_from($kladr_id_from);
                $connect->set_is_stock(true);
            } else if ($method->isTransparent()) {
                // 11.2.2
                $connect->set_kladr_id_from($method->sender_city);
                $connect->set_is_stock(false);
            }
            // <-- конец

            // это calculateShipping, так называемый
            $shipping = $connect->get_shipping();

            /*
            Пункт 10.4
            При расчете в чекауте, корзине вместе с CalculatеShipping
            для сквозных способов доставки делать GetDeliveryPoints
            на ПВЗ отгрузки с фактическими параметрами заказа и флагом self_pick_up
            Если ID сохраненного в профиле ПВЗ нет в ответе метода, то метод скрывать из списка
            */
            if ($method->isTransparent() && $method->shipmentType() != 'courier') {
                $default_address = $method->sender_address;
                $connect->set_kladr_id($method->sender_city ?: $kladr_id_from);
                $connect->set_shipping_method($method->method_id);
                $points = $connect->get_delivery_points(array(
                    'self_pick_up' => true,
                ));

                $result = array_filter($points, function($point) use ($default_address) {
                    return $point['address'] == $default_address;
                });

                // возвращаем переменную обратно
                $connect->set_kladr_id($package['destination']['kladr_id']);

                if (empty($result)) {
                    return $rates;
                }
            }
        } elseif( $method->isInternational() ) {
            $shipping = $connect->get_international_shipping();
        }

        if ( empty($shipping) ) {
            return $rates;
        }

        foreach ($shipping as $shipping_method) {
            if ('ok' !== $shipping_method['status']) {
                continue;
            }

            if ( $shipping_method['method']['group'] != $method->data['group_courier'] ) {
                continue;
            }

            $label = $method->title ? $method->title : $shipping_method['method']['name'];
            $meta_data = array(
                'shiptor_rate_hash' => $rate_hash,
                'shiptor_method' => array_merge($shipping_method['method'], array(
                    'label' => $method->get_shipping_method_label($label, $shipping_method['days'], $package),
                    'declared_cost' => $connect->get_declared_cost(),
                    'show_time' => wc_bool_to_string($method->show_delivery_time)
                ))
            );

            if ('date' == wc_shiptor_get_option('sorting_type')) {
                $meta_data['shiptor_method']['date'] = wc_shiptor_get_shipping_delivery_time($shipping_method['days'], $method->additional_time);
            }

            foreach (array('sender_city', 'sender_address', 'sender_name', 'sender_email', 'sender_phone') as $sender_prop) {
                if (isset($method->{$sender_prop}) && !empty($method->{$sender_prop})) {
                    $meta_data['shiptor_method'][$sender_prop] = $method->{$sender_prop};
                }
            }

            $cost = $shipping_method['cost']['total']['sum'];
            if ($method->free > 0 && $cod >= $method->free) {
                $cost = 0;
            } elseif ($method->fix_cost > 0) {
                $cost = $method->fix_cost;
            } elseif (!empty($method->fee) && $method->fee !== 0) {
                switch ($method->fee_type) {
                    case 'order' :
                        $cost += $this->get_fee($method->fee, $cod);
                        break;
                    case 'shipping' :
                        $cost += $this->get_fee($method->fee, $cost);
                        break;
                    case 'both' :
                        $cost += $this->get_fee($method->fee, $cod + $cost);
                        break;
                }
            }
            $posts = get_option('woocommerce_shiptor-integration_settings');
            $round_delivery_cost = $posts['round_delivery_cost'];
            $round_type = $posts['round_type'];
            if ($round_delivery_cost != '2' && $round_delivery_cost != null):
                $round = $round_type == 0 ? 'round' : ( $round_type == 1 ? 'floor' : 'ceil' );
                $cost = $round_delivery_cost == 0 ? ( $round($cost) ) : ( $round_delivery_cost == -1 ? ( $round($cost/10) )*10 : ( $round($cost/100) )*100);
            endif;

            $rates[] = array(
                'id' => $this->get_rate_id($shipping_method['method']['category'] . '_' . $shipping_method['method']['group']),
                'label' => $label,
                'cost' => $cost,
                'meta_data' => $meta_data
            );
        }

        return $rates;
    }

    public function calculate_shipping($packages = array()) {
        if ($this->available_rates && !empty($this->available_rates)) {
            foreach ($this->available_rates as $rate) {
                $this->add_rate($rate);
            }
        }
    }

    public function is_shiptor() {
        return in_array($this->id, wc_get_chosen_shipping_method_ids());
    }

    public function disable_cod_payment($gateways) {

        if (is_admin()) {
            return $gateways;
        }

        $rate = wc_shiptor_chosen_shipping_rate();
        $rate = $rate ?: false;
        $country = WC()->customer->get_billing_country();

        if ($this->is_shiptor()) {

            if ('RU' !== $country) {
                unset($gateways['cod']);
                unset($gateways['cod_card']);
            }

            if (isset($gateways['cod_card']) && false !== $rate && 'RU' == $country && ( in_array($rate->meta_data['shiptor_method']['group'], array('russian_post', 'dpd_economy_courier', 'dpd_economy_delivery')) )) {
                unset($gateways['cod_card']);
            }

            if (isset($gateways['cod']) && false !== $rate && 'RU' == $country && ( in_array($rate->meta_data['shiptor_method']['group'], array('dpd_economy_courier', 'dpd_economy_delivery')) )) {
                unset($gateways['cod']);
            }
        }

        return $gateways;
    }

    public function validate_checkout($data, $errors) {
        if (!$this->is_shiptor()) {
            return;
        }

        $country = WC()->customer->get_billing_country();
        if (!in_array($country,  array('RU', 'KZ', 'BY'))) {
            $latin = '/[\p{Latin}]+/u';

            if (!preg_match($latin, $data['billing_first_name'])) {
                $errors->add( 'validation',  esc_html__('First name must be latin', 'woocommerce-shiptor'));
            }

            if (!preg_match($latin, $data['billing_last_name'])) {
                $errors->add( 'validation',  esc_html__('Last name must be latin', 'woocommerce-shiptor'));
            }

            if (!preg_match($latin, $data['billing_city'])) {
                $errors->add( 'validation',  esc_html__('City must be latin', 'woocommerce-shiptor'));
            }

            if (!preg_match($latin, $data['billing_address_1'])) {
                $errors->add( 'validation',  esc_html__('Address must be latin', 'woocommerce-shiptor'));
            }
        }

        $chosen_package = wc_shiptor_chosen_shipping_package();
        $chosen_rate = wc_shiptor_chosen_shipping_rate();

        $chosen_delivery_point = WC()->session->get('chosen_delivery_point');
        $chosen_payment_method = WC()->session->get('chosen_payment_method');
        $shiptor_method = $chosen_rate->meta_data['shiptor_method'];

        if ( $this->methodShoudHaveDeliveryPoints($shiptor_method) ) {
            if (is_null($chosen_delivery_point) && !in_array('empty-delivery-point', $errors->get_error_codes())) {
                $errors->add('empty-delivery-point',  esc_html__("You didn't select a delivery point.", 'woocommerce-shiptor'));
            } elseif (in_array($chosen_payment_method, array('cod', 'cod_card')) && !is_null($chosen_delivery_point) && !in_array('delivery-point-cod', $errors->get_error_codes())) {

                $delivery_points = $this->get_delivery_points($chosen_rate, $chosen_package);
                $delivery_point = wp_list_filter($delivery_points, array('id' => $chosen_delivery_point));

                if (is_array($delivery_point) && !empty($delivery_point)) {
                    $delivery_point = current(array_values($delivery_point));
                    if (isset($delivery_point['cod']) && !$delivery_point['cod']) {
                        $errors->add('delivery-point-cod',  esc_html__('The delivery point of issue selected by you does not accept payment by cash on delivery. Please choose a different payment method or other delivery point.', 'woocommerce-shiptor'));
                    }
                }
            }
        }
    }
}
