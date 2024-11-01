<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="shiptor_product_data" class="panel woocommerce_options_panel hidden">
    <div class="options_group">
        <?php
        woocommerce_wp_text_input(array(
            'id' => '_eng_name',
            'label' =>  esc_html__('English product name', 'woocommerce-shiptor'),
            'placeholder' =>  esc_html__('Enter product name in English.', 'woocommerce-shiptor'),
            'desc_tip' => true,
            'description' =>  esc_html__('Enter product name in English. It is necessary for international delivery.', 'woocommerce-shiptor'),
            'type' => 'text'
        ));

        woocommerce_wp_text_input(array(
            'id' => '_article',
            'label' =>  esc_html__('Article', 'woocommerce-shiptor'),
            'placeholder' => $product_object->get_sku(),
            'desc_tip' => true,
            'description' =>  esc_html__('Enter original article on the product.', 'woocommerce-shiptor'),
            'type' => 'text'
        ));
        ?>
    </div>
    <div class="options_group">
        <?php
        woocommerce_wp_checkbox(array(
            'id' => '_fragile',
            'label' =>  esc_html__('Is fragile', 'woocommerce-shiptor'),
            'description' =>  esc_html__('Is fragile product?', 'woocommerce-shiptor'),
        ));

        woocommerce_wp_checkbox(array(
            'id' => '_danger',
            'label' =>  esc_html__('Is danger', 'woocommerce-shiptor'),
            'description' =>  esc_html__('Is danger product?', 'woocommerce-shiptor'),
        ));

        woocommerce_wp_checkbox(array(
            'id' => '_perishable',
            'label' =>  esc_html__('Is perishable', 'woocommerce-shiptor'),
            'description' =>  esc_html__('Is product perishable?', 'woocommerce-shiptor'),
        ));

        woocommerce_wp_checkbox(array(
            'id' => '_need_box',
            'label' =>  esc_html__('Need box', 'woocommerce-shiptor'),
            'description' =>  esc_html__('Need to pack this product?', 'woocommerce-shiptor'),
        ));
        ?>
    </div>
</div>