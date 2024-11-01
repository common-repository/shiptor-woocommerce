<?php
if (!defined('ABSPATH')) {
    exit;
}

switch ($shiptor_method['category']) {
    case 'delivery-point-to-delivery-point' :
    case 'delivery-point-to-door' :
    case 'door-to-door' :
    case 'door-to-delivery-point' :
        $is_delivery = true;
        break;
    default :
        $is_delivery = false;
        break;
}

//get meta values from wp_postmeta table
$shipment_id = $the_order->get_meta('_shipment_id');
$shipment_confirmed = $the_order->get_meta('_shipment_confirmed');
$shiptor_status = $the_order->get_meta('_shiptor_status');

$status_classes = array('badge');
switch ($shiptor_status) {
    case 'lost' :
    case 'recycled' :
    case 'removed' :
        $status_classes[] = 'badge-danger';
        break;
    case 'checking-declaration' :
    case 'waiting-pickup' :
    case 'waiting-on-delivery-point' :
        $status_classes[] = 'badge-warning';
        break;
    case 'resend' :
    case 'returned' :
    case 'reported' :
        $status_classes[] = 'badge-info';
        break;
    case 'delivered' :
        $status_classes[] = 'badge-success';
        break;
    default :
        $status_classes[] = 'badge-secondary';
        break;
}
?>

<div class="shiptor-table-order">
    <table>
        <tbody>
            <tr>
                <td><small><?php esc_html_e('Delivery method:', 'woocommerce-shiptor'); ?></small></td>
                <td><small><span class="badge badge-primary"><?php echo esc_html($shiptor_method['name']); ?></span></small></td>
            </tr>
            <tr>
                <td><small><?php esc_html_e('Tracking code:', 'woocommerce-shiptor'); ?></small></td>
                <td><small><span class="badge badge-primary"><?php echo esc_html($tracking_code); ?></span></small></td>
            </tr>
            <tr>
                <td><small><?php esc_html_e('Status:', 'woocommerce-shiptor'); ?></small></td>
                <td><small><span class="<?php echo esc_attr(implode(' ', $status_classes)); ?>"><?php echo esc_html(wc_shiptor_get_status($shiptor_status)); ?></span></small></td>
            </tr>
            <?php if ($is_delivery && $shipment_id) : ?>
                <tr>
                    <td><small><?php esc_html_e('Shipping number:', 'woocommerce-shiptor'); ?></small></td>
                    <td><small>
                            <span class="badge <?php echo esc_attr(($shipment_confirmed) ? 'badge-success' : 'badge-danger'); ?>">
                                <?php echo esc_html($shipment_id); ?>
                            </span>
                        </small>
                    </td>
                </tr>
<?php endif; ?>
        </tbody>
    </table>
</div>