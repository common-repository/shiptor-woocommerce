<?php
if (!defined('ABSPATH')) {
    exit;
}
$order = wc_get_order($order);
?>

<div class="shiptor-order-details">
    <div class="shiptor-order-edit">
        <h3><?php printf(esc_html__('Status: %s', 'woocommerce-shiptor'), esc_html(wc_shiptor_get_status($order->get_meta('_shiptor_status')))); ?></h3>
        <p><?php printf(esc_html__('Tracking code: %s', 'woocommerce-shiptor'), esc_html(wc_shiptor_get_tracking_codes($order))); ?></p>
        <div class="clear"></div>
        <div class="action">
            <a download class="button button-hero download-label" target="_blank" href="<?php echo esc_url($order->get_meta('_shiptor_label_url')); ?>"><?php esc_html_e('Print Barcode', 'woocommerce-shiptor'); ?></a>
            <?php /*
              <button class="button button-hero remove"><?php _e( 'Remove order', 'woocommerce-shiptor' );?></button>
             */ ?>
        </div>
    </div>
</div>