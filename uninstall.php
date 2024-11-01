<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 27.11.2017
 * Time: 23:36
 * Project: shiptor-woo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
global $wpdb;

include_once dirname( __FILE__ ) . '/includes/class-wc-shiptor-install.php';

WC_Shiptor_Install::drop_tables();
$optionShiptorPrefix = '%' . $wpdb->esc_like( 'woocommerce_shiptor_' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $optionShiptorPrefix
) );

$upload_dir = wp_get_upload_dir();
$upload_shiptor_dir = $upload_dir['basedir'] . '/shiptor';
if ( is_dir($upload_shiptor_dir) ) {
    clearShiptorUploadDir($upload_shiptor_dir);
}

function clearShiptorUploadDir($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        $fileName = sanitize_file_name($file);
        (is_dir("$dir/$fileName")) ? clearShiptorUploadDir("$dir/$fileName") : unlink("$dir/$fileName");
    }
    return rmdir($dir);
}