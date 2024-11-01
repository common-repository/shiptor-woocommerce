<?php
if (!defined('ABSPATH')) {
    exit;
}

$need_delivery_pount = false;
$name = '';
if (isset($shiptor_method['category']) && isset($shiptor_method['name'])) {
    $name = $shiptor_method['name'];
    $need_delivery_pount = in_array($shiptor_method['category'], array('delivery-point-to-delivery-point', 'delivery-point-to-door'), true) === true;
} else {
    $order = wc_get_order($the_order->get_id());
    foreach ($order->get_shipping_methods() as $key => $value) {
        $shipping_method_title = $value->get_method_title();
    }
}
?>
<div class="shiptor-table-order">
    <table>
        <tbody>
            <?php if (!$need_delivery_pount) : ?>
                <tr>
                    <td><small><?php esc_html_e('Delivery method:', 'woocommerce-shiptor'); ?></small></td>
                    <td>
                        <small>
                            <span class="badge <?php echo esc_attr((!$name) ? 'badge-danger' : 'badge-primary'); ?>">
                                <?php echo esc_html($name ? $name : $shipping_method_title); ?>
                            </span>
                        </small>
                    </td>
                </tr>
                <?php if ($name) : ?>
                    <tr>
                        <td></td>
                        <td>
                            <a class="button button-primary" href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=shiptor_send_order&order_id=' . $the_order->get_id()), 'shiptor-send-order'); ?>">
                                <?php esc_html_e("Send order", "woocommerce-shiptor") ?>
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php else : ?>
                <tr>
                    <td><small><?php esc_html_e('Delivery method:', 'woocommerce-shiptor'); ?></small></td>
                    <td><small><span class="badge badge-primary tips" data-tip="<?php esc_html_e("To send the order you need to select the Sender's issue point", "woocommerce-shiptor"); ?>"><?php echo esc_html($name); ?></span></small></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
