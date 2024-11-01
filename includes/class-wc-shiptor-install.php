<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 0:00
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Shiptor_Install {

    private static $db_updates = array();

    public static function init() {
        add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
    }

    public static function check_version() {
        $version = self::get_wc_shiptor_version();

        if ( !$version || version_compare( $version, WC_SHIPTOR_VERSION, '<' ) ) {
            self::install();
        }
    }

    public static function install() {
        if ( ! is_blog_installed() ) {
            return;
        }

        // Check if we are not already running this routine.
        if ( 'yes' === get_transient( 'wc_shiptor_installing' ) ) {
            return;
        }

        // If we made it till here nothing is running yet, lets set the transient now.
        set_transient( 'wc_shiptor_installing', 'yes', MINUTE_IN_SECONDS * 10 );

        self::update_db_before_124();
        self::create_tables();
        self::update_wc_shiptor_version();
        self::maybe_update_db_version();

        delete_transient( 'wc_shiptor_installing' );
    }

    private static function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( self::get_schema() );
    }

    private static function get_schema() {
        global $wpdb;

        $charset_collate = '';
        if ( $wpdb->has_cap( 'collation' ) ) {
            $charset_collate = $wpdb->get_charset_collate();
        }

        $tables = "
        CREATE TABLE {$wpdb->prefix}shiptor_address (
            kladr_id char(20) NOT NULL,
            city_name longtext NULL,
            state longtext NULL,
            country char(2) NULL,
            last_query datetime NULL,
            PRIMARY KEY  (kladr_id)
        ) $charset_collate;
        CREATE TABLE {$wpdb->prefix}shiptor_shipping_methods ( 
            id BIGINT NOT NULL , 
            name VARCHAR(255) NOT NULL , 
            category VARCHAR(255) NOT NULL , 
            group_courier VARCHAR(255) NOT NULL , 
            courier VARCHAR(255) NOT NULL , 
            courier_code VARCHAR(255) NOT NULL , 
            description TEXT NULL , 
            help_url TEXT NOT NULL , 
            PRIMARY KEY (id)
        ) $charset_collate;";

        return $tables;
    }

    private static function maybe_update_db_version() {
        if ( self::needs_db_update() ) {
            self::update();
        } else {
            self::update_wc_shiptor_db_version();
        }
    }

    public static function needs_db_update() {
        $current_db_version = get_option( 'woocommerce_shiptor_db_version', null );
        $updates            = self::get_db_update_callbacks();

        return ! is_null( $current_db_version ) && count( $updates ) && version_compare( $current_db_version, max( array_keys( $updates ) ), '<' );
    }

    public static function get_db_update_callbacks() {
        return self::$db_updates;
    }

    private static function update() {
        $current_db_version = get_option( 'woocommerce_shiptor_version' );
        $loop               = 0;

        foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
            if ( version_compare( $current_db_version, $version, '<' ) ) {
                foreach ( $update_callbacks as $update_callback ) {
                    self::run_update_callback($update_callback);
                }
            }
        }
    }

    public static function run_update_callback( $callback ) {
        include_once dirname( __FILE__ ) . '/wc-shiptor-update-functions.php';

        if ( is_callable( $callback ) ) {
            $result = (bool) call_user_func( $callback );
        }
    }

    private static function get_wc_shiptor_version() {
        return get_option( 'woocommerce_shiptor_version' );
    }

    private static function update_wc_shiptor_version() {
        delete_option( 'woocommerce_shiptor_version' );
        add_option( 'woocommerce_shiptor_version', WC_SHIPTOR_VERSION );
    }

    private static function get_wc_shiptor_db_version() {
        return get_option( 'woocommerce_shiptor_db_version' );
    }

    private static function update_wc_shiptor_db_version() {
        delete_option( 'woocommerce_shiptor_db_version' );
        add_option( 'woocommerce_shiptor_db_version', WC_SHIPTOR_VERSION );
    }

    private static function update_db_before_124(){
        global $wpdb;

        $wpdb->hide_errors();

        $version = get_option('woocommerce_shiptor_autofill_addresses_db_version', null);
        if($version && version_compare( $version, WC_SHIPTOR_VERSION, '<' )){

            $table = "{$wpdb->prefix}shiptor_address";
            $sql = "DELETE a FROM $table a
                INNER JOIN $table b
                WHERE 
                    a.ID < b.ID AND 
                    a.kladr_id = b.kladr_id";
            $wpdb->query( $sql );

            $wpdb->query( "ALTER TABLE $table DROP INDEX `kladr_id`");
            $wpdb->query( "ALTER TABLE $table MODIFY COLUMN `kladr_id`  char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL AFTER `city_name`" );
            $wpdb->query( "ALTER TABLE $table DROP COLUMN `ID`" );
            $wpdb->query( "ALTER TABLE $table DROP COLUMN `count_query`" );
            $wpdb->query( "ALTER TABLE $table ADD PRIMARY KEY (`kladr_id`)" );

            delete_option( 'woocommerce_shiptor_autofill_addresses_db_version' );
        }

        $version = get_option('woocommerce_shiptor_cache_request_db_version', null);
        if($version && version_compare( $version, WC_SHIPTOR_VERSION, '<' )){
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}shiptor_cache_request" );

            delete_option( 'woocommerce_shiptor_cache_request_db_version' );
        }
    }

    public static function get_tables() {
        global $wpdb;

        $tables = array(
            "{$wpdb->prefix}shiptor_address",
            "{$wpdb->prefix}shiptor_shipping_methods",
        );

        return $tables;
    }

    public static function drop_tables() {
        global $wpdb;

        $tables = self::get_tables();

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }

}

WC_Shiptor_Install::init();