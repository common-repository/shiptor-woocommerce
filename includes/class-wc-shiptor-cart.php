<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Cart {

    public function __construct() {
        add_filter('woocommerce_cart_totals_before_shipping', array($this, 'calculate_shipping'));
    }

    public function calculate_shipping() {
        if(!is_ajax()){
            $packages = WC()->shipping()->get_packages();
            foreach($packages as $key => $package){
                $packages[$key] = $this->calculate_shipping_for_package($package, $key);
            }

            WC()->shipping()->packages = $packages;
        }
    }


    protected function calculate_shipping_for_package( $package = array(), $package_key = 0 ) {
        $wc_shipping = WC()->shipping();

        if ( ! $wc_shipping->enabled || empty( $package ) ) {
            return false;
        }

        if ( $this->is_package_shippable( $package ) ) {
            $package_to_hash = $package;
            $package_to_hash['rates'] = array();

            foreach ( $package_to_hash['contents'] as $item_id => $item ) {
                unset( $package_to_hash['contents'][ $item_id ]['data'] );
            }

            $wc_session_key = 'shipping_for_package_' . $package_key;
            $package_hash = 'wc_ship_' . md5( wp_json_encode( $package_to_hash ) . WC_Cache_Helper::get_transient_version( 'shipping' ) );

            $package_rates = $package['rates'];
            foreach ( $wc_shipping->load_shipping_methods( $package ) as $shipping_method ) {
                if(!($shipping_method instanceof WC_Shiptor_Shipping_Host_Method)){
                    continue;
                }

                if ( ! $shipping_method->supports( 'shipping-zones' ) || $shipping_method->get_instance_id() ) {
                    $method_shipping_rates = $shipping_method->get_rates_for_package( $package );
                    
                    foreach($method_shipping_rates as $method_shipping_rates_key => $method_shipping_rate){
                        $package_rates[$method_shipping_rates_key] = $method_shipping_rate;
                    }
                }
            }

            $package['rates'] = $package_rates;
            
            WC()->session->set(
                $wc_session_key,
                array(
                    'package_hash' => $package_hash,
                    'rates'        => $package['rates'],
                )
            );

            WC()->session->save_data();
        }

        return $package;
    }

    protected function is_package_shippable( $package ) {

        // Packages are shippable until proven otherwise.
        if ( empty( $package['destination']['country'] ) ) {
            return true;
        }

        $allowed = array_keys( WC()->countries->get_shipping_countries() );
        return in_array( $package['destination']['country'], $allowed, true );
    }
    
}

return new WC_Shiptor_Cart();
