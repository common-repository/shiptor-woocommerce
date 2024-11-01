<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 17.12.2017
 * Time: 22:53
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Tracking_History {

    public function __construct() {
        add_action('woocommerce_order_details_after_order_table', array($this, 'view'), 1);
    }

    protected function logger($data) {
        if ('yes' === wc_shiptor_get_option('enable_tracking_debug')) {
            $logger = new WC_Logger();
            $logger->add('tracking_history', $data);
        }
    }

    protected function get_tracking_history($shiptor_id = 0) {
        $transient_name = 'wc_shiptor_order_checkpoints_' . $shiptor_id;

        $this->logger(sprintf( esc_html__('Fetching tracking history for "%s" on Shiptor API...', 'woocommerce-shiptor'), $shiptor_id));

        if (false === ( $history = get_transient($transient_name) )) {
            $connect = new WC_Shiptor_Connect('order_checkpoints');
            $package = $connect->get_package($shiptor_id);

            if ($package && isset($package['checkpoints'])) {
                $history = $package['checkpoints'];
                set_transient($transient_name, $history, HOUR_IN_SECONDS);
            }
        }

        $this->logger(sprintf( esc_html__('Tracking history data: %s', 'woocommerce-shiptor'), print_r($history, true)));

        return $history;
    }

    public function view($order) {

        if ('yes' !== wc_shiptor_get_option('tracking_enable')) {
            return;
        }

        if (!$order->get_meta('_shiptor_id')) {
            return;
        }

        $history = $this->get_tracking_history($order->get_meta('_shiptor_id'));

        wc_get_template('myaccount/tracking-title.php', array(), '', WC_Shiptor::get_templates_path());

        if ($history && !empty($history) && is_array($history)) {
            wc_get_template('myaccount/tracking-history-list.php', array(
                'history' => $history
                    ), '', WC_Shiptor::get_templates_path());
        } else {
            wc_get_template('myaccount/tracking-codes.php', array(
                'codes' => wc_shiptor_get_tracking_codes($order)
                    ), '', WC_Shiptor::get_templates_path());
        }
    }

}

new WC_Shiptor_Tracking_History();
