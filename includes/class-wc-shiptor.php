<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 27.11.2017
 * Time: 23:34
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit;
}

final class WC_Shiptor {

    public static function init() {
        if(!is_dir( WC_SHIPTOR_UPLOAD_DIR )){
            mkdir( WC_SHIPTOR_UPLOAD_DIR, 0775);
        }

        add_action('init', array(__CLASS__, 'load_plugin_textdomain'), -1);

        if (class_exists('WC_Integration')) {
            self::includes();
            if (is_admin()) {
                self::admin_includes();
            }

            add_action('admin_menu', array(__CLASS__, 'woocomerce_menu'), 99);

            add_filter('woocommerce_integrations', array(__CLASS__, 'include_integrations'));
            add_filter('woocommerce_shipping_methods', array(__CLASS__, 'include_methods'));
            add_filter('woocommerce_payment_gateways', array(__CLASS__, 'payment_cod_via_card'));
            add_filter('woocommerce_email_classes', array(__CLASS__, 'include_emails'));
            add_filter('plugin_row_meta', array(__CLASS__, 'plugin_row_meta'), 10, 2);
            add_filter('plugin_action_links_' . plugin_basename(WC_SHIPTOR_PLUGIN_FILE), array(__CLASS__, 'plugin_action_links'));
        } else {
            add_action('admin_notices', array(__CLASS__, 'woocommerce_missing_notice'));
        }
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
    }

    public static function activate() {
        add_option('woocommerce_shiptor_activation_redirect', true);
    }

    public static function admin_init() {
        global $pagenow;

        if (get_option('woocommerce_shiptor_activation_redirect', false)) {
            delete_option('woocommerce_shiptor_activation_redirect');
            wp_redirect(self::get_integration_url());
        }

        if( !is_ajax() ) {
            $is_shipping_instance_page = false;
            if ( !is_ajax() && ($pagenow == 'admin.php') &&
                ( isset($_GET['page']) && $_GET['page'] === 'wc-settings' ) &&
                ( isset($_GET['tab']) && $_GET['tab'] === 'shipping' ) &&
                ( isset($_GET['instance_id']) && intval($_GET['instance_id']) ) ) {

                $is_shipping_instance_page = true;
            }

            if (!$is_shipping_instance_page) {
                setcookie('shiptor_admin_shipping_method_tab_current', null, 1);
            }
        }

    }

    public static function cron_schedules($schedules) {
        $schedules['one_min'] = array(
            'interval' => 60,
            'display' =>  esc_html__('Every minute', 'woocommerce-shiptor'),
        );
        $schedules['five_min'] = array(
            'interval' => HOUR_IN_SECONDS / 12,
            'display' =>  esc_html__('Every five minutes', 'woocommerce-shiptor'),
        );
        $schedules['fifteen_min'] = array(
            'interval' => HOUR_IN_SECONDS / 4,
            'display' =>  esc_html__('Every fifteen minutes', 'woocommerce-shiptor'),
        );
        $schedules['half_hour'] = array(
            'interval' => HOUR_IN_SECONDS / 2,
            'display' =>  esc_html__('Every an half hour', 'woocommerce-shiptor'),
        );
        $schedules['one_hour'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display' =>  esc_html__('Every hour', 'woocommerce-shiptor'),
        );
        $schedules['three_hours'] = array(
            'interval' => HOUR_IN_SECONDS * 3,
            'display' =>  esc_html__('Every three hours', 'woocommerce-shiptor'),
        );
        $schedules['six_hours'] = array(
            'interval' => HOUR_IN_SECONDS * 6,
            'display' =>  esc_html__('Every six hours', 'woocommerce-shiptor'),
        );
        $schedules['twelve_hours'] = array(
            'interval' => HOUR_IN_SECONDS * 6,
            'display' =>  esc_html__('Every twelve hours', 'woocommerce-shiptor'),
        );

        return $schedules;
    }

    public static function load_plugin_textdomain() {
        load_plugin_textdomain('woocommerce-shiptor', false, dirname(plugin_basename(WC_SHIPTOR_PLUGIN_FILE)) . '/languages/');
    }

    private static function includes() {
        include_once dirname(__FILE__) . '/wc-shiptor-functions.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-install.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-cache.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-log.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-package.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-connect.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-autofill-addresses.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-checkout.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-cart.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-tracking-history.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-rest-api.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-single-product.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-gateway-cod.php';
        include_once dirname(__FILE__) . '/class-wc-shiptor-integration.php';

        foreach (glob(plugin_dir_path(__FILE__) . '/shipping/*.php') as $filename) {
            include_once $filename;
        }
    }

    private static function admin_includes() {
        include_once dirname(__FILE__) . '/admin/class-wc-shiptor-admin-orders.php';
        include_once dirname(__FILE__) . '/admin/class-wc-shiptor-product-data.php';
        include_once dirname(__FILE__) . '/admin/class-wc-shiptor-admin-help.php';

        if (!empty($_GET['page'])) {
            $page = sanitize_key($_GET['page']);

            switch ($page) {
                case 'shiptor-setup' :
                    include_once( dirname(__FILE__) . '/admin/class-wc-shiptor-setup-wizard.php' );
                    break;
            }
        }
    }

    public static function include_integrations($integrations) {
        return array_merge($integrations, array('WC_Shiptor_Integration'));
    }

    public static function include_methods($methods) {
        $shiptor_methods = array(
            'shiptor-shipping-host' => 'WC_Shiptor_Shipping_Host_Method'
        );

        $methods = array_merge($methods, $shiptor_methods);

        return apply_filters('woocommerce_shiptor_shipping_methods', $methods);
    }

    public static function payment_cod_via_card($methods) {
        if (class_exists('WC_Gateway_COD')) {
            array_push($methods, 'WC_Gateway_COD_Card');
        }
        return $methods;
    }

    public static function include_emails($emails) {
        if (!isset($emails['WC_Shiptor_Tracking_Email'])) {
            $emails['WC_Shiptor_Tracking_Email'] = include dirname(__FILE__) . '/emails/class-wc-shiptor-tracking-email.php';
        }
        return $emails;
    }

    public static function plugin_row_meta($links, $file) {
        if ($file === plugin_basename(WC_SHIPTOR_PLUGIN_FILE)) {
            $row_meta = array(
                'help' => '<a href="https://shiptor.ru/help/integration/woo#woocommerce-about" title="Посмотреть документацию" target="_blank">Документация</a>',
                'demo' => '<a href="http://woo.shiptor.ru" title="Посмотреть демо сайт">Демо</a>'
            );
            return array_merge($links, $row_meta);
        }
        return (array) $links;
    }

    public static function plugin_action_links($links) {
        return array_merge(array(
            'settings' => '<a href="' . esc_url(self::get_integration_url()) . '">'. esc_html__('Settings', 'woocommerce-shiptor').'</a>',
                ), $links);
    }

    public static function woocommerce_missing_notice() {
        include_once dirname(__FILE__) . '/admin/views/html-admin-missing-dependencies.php';
    }

    public static function get_main_file() {
        return WC_SHIPTOR_PLUGIN_FILE;
    }

    public static function get_plugin_path() {
        return plugin_dir_path(WC_SHIPTOR_PLUGIN_FILE);
    }

    public static function get_templates_path() {
        return self::get_plugin_path() . 'templates/';
    }

    public static function woocomerce_menu() {
        add_submenu_page( 'woocommerce', '', 'Shiptor', 'manage_options', self::get_integration_url());
    }

    public static function get_integration_url() {
        return admin_url('admin.php?page=wc-settings&tab=integration&section=shiptor-integration');
    }

}