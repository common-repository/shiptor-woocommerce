<?php
/**
* Plugin Name: Логистическая платформа Shiptor для WooCommerce
* Plugin URI: https://shiptor.ru/integration/modules/woo
* Description: Официальный плагин Shiptor для WooCommerce. Добавляет на сайт автоматизированный расчет доставки и возможность передавать заказы в логистическую платформу Shiptor.
* Author: Shiptor
* Author URI: http://shiptor.ru
* Version: 1.4.2
* Date: 27.03.2020
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WC_SHIPTOR_VERSION', '1.4.2' );

define( 'WC_SHIPTOR_PLUGIN_FILE', __FILE__ );
$upload_dir = wp_get_upload_dir();
define( 'WC_SHIPTOR_DIR', __DIR__);
define( 'WC_SHIPTOR_TEMPLATE_DIR', WC_SHIPTOR_DIR . '/templates');
define( 'WC_SHIPTOR_INCLUDES_DIR', WC_SHIPTOR_DIR . '/includes');
define( 'WC_SHIPTOR_LIBRARIES_DIR', WC_SHIPTOR_INCLUDES_DIR . '/libraries');

define( 'WC_SHIPTOR_UPLOAD_DIR', $upload_dir['basedir'] . '/shiptor' );
define( 'WC_SHIPTOR_LOG_DIR', WC_SHIPTOR_UPLOAD_DIR. '/log' );
define( 'WC_SHIPTOR_CACHE_DIR', WC_SHIPTOR_UPLOAD_DIR . '/cache' );
define( 'WC_SHIPTOR_LOG_DIR_URL', wp_upload_dir()['baseurl'] . '/shiptor/log' );

const WC_SHIPTOR_CACHE_CONFIG_TIME = array(
    'defined_methods' => array(
        'calculateShipping' => 24 * 60 * 60,
        'calculateShippingInternational' => 24 * 60 * 60,
        'getDeliveryPoints' => 24 * 60 * 60,
        'getShippingMethods' => 24 * 60 * 60,
    ),
    'common_cache_time' => 24 * 60 * 60,
);

const WC_SHIPTOR_DEFAULT_CITY = array(
    'kladr_id' => '77000000000',
    'city_name' => 'Москва',
    'state' => 'город. Москва',
    'country' => 'RU',
);

if ( ! class_exists( 'WC_Shiptor' ) ) {
    include_once dirname( __FILE__ ) . '/includes/class-wc-shiptor.php';
    register_activation_hook(__FILE__, array('WC_Shiptor', 'activate'));
    add_action( 'plugins_loaded', array( 'WC_Shiptor', 'init' ) );
    add_action('admin_init', array('WC_Shiptor', 'admin_init'));
}

register_deactivation_hook( __FILE__, 'shiptor_deactivation' );
function shiptor_deactivation(){
	wp_clear_scheduled_hook( 'shiptor_orders_cron' );
}