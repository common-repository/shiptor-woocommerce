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
if (apply_filters('woocommerce_shiptor_help_message', true)) :
    ?>
    <div class="updated woocommerce-message inline">
        <p><?php _e('Thank you for using our WooCommerce Shiptor plugin free!', 'woocommerce-shiptor'); ?></p>
        <p>
            <a href="mailto:Integration@shiptor.ru?subject=модуль Shiptor для WooCommerce" target="_blank" rel="nofollow noopener noreferrer" class="button button-primary"><?php esc_html_e('Contact us', 'woocommerce-shiptor'); ?></a>
            <a href="https://shiptor.ru/help/integration/woo/woocommerce-setting" target="_blank" class="button button-secondary">Инструкция к плагину</a>
        </p>
    </div>
    <?php

endif;