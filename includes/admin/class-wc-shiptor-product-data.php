<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Product_Data {

    public function __construct() {
        add_filter('woocommerce_product_data_tabs', array($this, 'product_data_tabs'));
        add_action('woocommerce_product_data_panels', array($this, 'product_data_panels'));
        //add_action( 'manage_product_posts_custom_column', array( $this, 'product_column' ), 10, 2 );
        add_filter('woocommerce_admin_stock_html', array($this, 'admin_stock_html'), 10, 2);
        add_action('woocommerce_process_product_meta_simple', array($this, 'save'));
        add_action('woocommerce_process_product_meta_variable', array($this, 'save'));

        add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
    }

    public function product_data_tabs($tabs) {
        $tabs['shiptor'] = array(
            'label' =>  esc_html__('Shiptor', 'woocommerce-shiptor'),
            'target' => 'shiptor_product_data',
            'class' => array('hide_if_virtual', 'hide_if_grouped', 'hide_if_external'),
            'priority' => 90,
        );
        return $tabs;
    }

    public function product_data_panels() {
        global $product_object;
        include( 'views/html-product-data.php' );
    }

    public function admin_stock_html($stock_html, $product) {

        if ($is_added_to_shiptor = get_post_meta($product->get_id(), '_added_shiptor', true)) {
            $text_in_stock =  esc_html__('On Shiptor stock', 'woocommerce-shiptor');
            $text_in_expected =  esc_html__('Expected in stock', 'woocommerce-shiptor');

            $this->update_stock_availability($product->get_id());

            $total = get_post_meta($product->get_id(), '_fulfilment_total', true);
            $total = !empty($total) ? intval($total) : 0;

            $waiting = get_post_meta($product->get_id(), '_fulfilment_waiting', true);
            $waiting = !empty($waiting) ? intval($waiting) : 0;

            $stock_html .= sprintf('<div class="wc-shiptor-stock"><span title="%s">%s</span><span title="%s">%s</span></div>', $text_in_stock, $total, $text_in_expected, $waiting);
        }

        return $stock_html;
    }

    public function update_stock_availability($product_id = 0) {

        $product = wc_get_product($product_id);

        $last_time = get_post_meta($product->get_id(), '_added_shiptor', true);

        if (current_time('timestamp', true) < ( $last_time + HOUR_IN_SECONDS )) {
            $connect = new WC_Shiptor_Connect('stock_availability');
            $shopArticle = $product->get_sku() ? $product->get_sku() : $product->get_id();
            $get_products = $connect->get_products($shopArticle);
            $get_product = wp_list_filter($get_products, array('shopArticle' => $shopArticle));
            $get_product = current($get_product);
            if (!empty($get_product) && isset($get_product['fulfilment']['total'], $get_product['fulfilment']['waiting'])) {
                update_post_meta($product->get_id(), '_fulfilment_total', $get_product['fulfilment']['total']);
                update_post_meta($product->get_id(), '_fulfilment_waiting', $get_product['fulfilment']['waiting']);
            }

            update_post_meta($product->get_id(), '_added_shiptor', time());
        }
    }

    public function save($post_id) {

        if (isset($_POST['_eng_name'])) {
            update_post_meta($post_id, '_eng_name', apply_filters('the_title', sanitize_text_field($_POST['_eng_name']) ));
        }

        if (isset($_POST['_article'])) {
            update_post_meta($post_id, '_article', sanitize_text_field($_POST['_article']));
        }

        foreach (array('_fulfilment', '_fragile', '_danger', '_perishable', '_need_box') as $field) {
            $value = isset($_POST[$field]) && 'yes' === $_POST[$field] ? 'yes' : null;
            update_post_meta($post_id, $field, $value);
        }
    }

    public function admin_styles() {

        $screen = get_current_screen();

        if (in_array($screen->id, array('product', 'edit-product'))) {
            wp_enqueue_style('woocommerce-shiptor-products', plugins_url('assets/admin/css/products.css', WC_SHIPTOR_PLUGIN_FILE), null, WC_SHIPTOR_VERSION);
        }
    }

}

new WC_Shiptor_Product_Data();
?>