<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 0:35
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<nav class="nav-tab-wrapper js_shiptor_tabs">
    <a href="#" data-id="tab_1" class="nav-tab nav-tab-active"><?php _e('Shiptor integration API', 'woocommerce-shiptor') ?></a>
    <a href="#" data-id="tab_2" class="nav-tab"><?php _e('Product options', 'woocommerce-shiptor') ?></a>
    <a href="#" data-id="tab_3" class="nav-tab"><?php _e('Delivery options', 'woocommerce-shiptor') ?></a>
    <a href="#" data-id="tab_4" class="nav-tab"><?php _e('Autofill Addresses', 'woocommerce-shiptor') ?></a>
    <a href="#" data-id="tab_5" class="nav-tab"><?php _e('Cache and logs', 'woocommerce-shiptor') ?></a>
</nav>