<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!$product || !$product->needs_shipping()) {
    return;
}


$kladr_id = wc_shiptor_get_customer_kladr();
$city = WC_Shiptor_Autofill_Addresses::get_city_by_id($kladr_id);
?>
<div class="shiptor-calculate-product clear">
    <div class="shiptor-calculate-product__label"><?php _e('Shipping: ', 'woocommerce-shiptor'); ?></div>
    <div class="shiptor-calculate-product__select">
        <select name="calculate-city" class="wc-enhanced-select">
        <?php if (isset($city['city_name']) && $city['city_name']) : ?>
            <option value="<?php echo esc_attr($city['kladr_id']); ?>"><?php echo esc_html($city['city_name']); ?></option>
        <?php endif; ?>
        </select>
    </div>
    <div class="clear"></div>
    <ul class="shiptor-calculate-product__result" data-simplebar-direction="vertical"></ul>
</div>

<script type="text/template" id="tmpl-shiptor-calculate-product">
    <li class="shiptor-calculate-product__elem" data-id="{{ data.term_id }}">
    <div>
    <span class="shiptor-method-name">{{ data.name }}</span>
    <span class="shiptor-method-cost">{{ data.cost }} <small>{{ data.days }}</small></span>
    </div>
    </li>
</script>