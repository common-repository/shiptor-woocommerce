<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!is_array($codes)) {
    $codes = array($codes);
}
?>

<p class="wc-shiptor-tracking__description"><?php echo esc_html(_n('Tracking code:', 'Tracking codes:', count($codes), 'woocommerce-shiptor')); ?></p>

<table class="wc-shiptor-tracking__table woocommerce-table shop_table shop_table_responsive">
    <tbody>
        <?php foreach ($codes as $code) : ?>
            <tr>
                <th><?php echo esc_html($code); ?></th>
                <td>
                    <form method="POST" target="_blank" action="https://shiptor.ru/tracking" class="wc-shiptor-tracking__form">
                        <input type="hidden" name="tracking" value="<?php echo esc_attr($code); ?>">
                        <input class="wc-shiptor-tracking__button button" type="submit" value="<?php esc_attr_e('View on Shiptor', 'woocommerce-shiptor'); ?>">
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
