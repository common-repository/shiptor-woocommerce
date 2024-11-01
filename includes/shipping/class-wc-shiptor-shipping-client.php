<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Shipping_Client_Method {
    public $is_new = true;

    protected $data = array(
        'method_id'             => 0,
        'enable_method'         => 'no',
        'title'                 => '',
        'behavior_options'      => '',
        'shiptor_warehouse'     => 0,
        'is_fulfilment'         => 'yes',
        'shipping_class_id'     => '-1',
        'show_delivery_time'    => 'no',
        'additional_time'       => 0,
        'fee'                   => 0,
        'fee_type'              => 'order',
        'min_cost'              => 0,
        'free'                  => 0,
        'fix_cost'              => 0,
        'enable_declared_cost'  => 'yes',
        'cityes_limit'          => '-1',
        'cityes_list'           => '',
        'sender_city'           => '',
        'sender_address'        => '',
        'sender_name'           => '',
        'sender_email'          => '',
        'sender_phone'          => '',
        'data'                  => array()
    );

    public function __isset( $key ) {
        return array_key_exists ( $key, $this->data );
    }

    public function __get( $key ) {
        if ( array_key_exists ( $key, $this->data ) ) {
            return $this->data[ $key ];
        } else {
            return null;
        }
    }

    public function __set( $key, $value ) {
        if ( array_key_exists ( $key, $this->data ) ) {
            $this->data[ $key ] = $value;
        }
    }

    public function to_array() {
        return $this->data;
    }

    public function has_only_selected_shipping_class($package) {
        $only_selected = true;

        if ('-1' == $this->shipping_class_id) {
            return $only_selected;
        }

        foreach ($package['contents'] as $item_id => $values) {
            $product = $values['data'];
            $qty = $values['quantity'];
            if ($qty > 0 && $product->needs_shipping()) {
                if ($this->shipping_class_id != $product->get_shipping_class_id()) {
                    $only_selected = false;
                    break;
                }
            }
        }

        return $only_selected;
    }

    public function get_additional_time($package = array()) {
        return apply_filters('woocommerce_shiptor_shipping_additional_time', $this->additional_time, $package);
    }

    public function get_shipping_method_label($label, $days, $package) {
        if ('yes' === $this->show_delivery_time) {
            return wc_shiptor_get_estimating_delivery($label, $days, $this->get_additional_time($package));
        }
        return $label;
    }

    public function isInternational() {
        $is_intenational = false;

        if ( isset($this->data['data']['courier']) && $this->data['data']['courier'] == 'shiptor-international' ) {
            $is_intenational = true;
        }

        return $is_intenational;
    }

    public function shipmentType($category = '') {
        $shipment_type = 'standard';

        if (!$category) {
            if ( !empty($this->data['data']['category']) ) {
                $category = $this->data['data']['category'];
            }
        }

        switch ($category) {
            case 'delivery-point-to-delivery-point' :
            case 'delivery-point-to-door' :
                $shipment_type = 'delivery-point';
                break;
            case 'door-to-door' :
            case 'door-to-delivery-point' :
                $shipment_type = 'courier';
                break;
            default :
                $shipment_type = 'standard';
                break;
        }

        return $shipment_type;
    }

    /**
     * Сквозной метод или нет
     *
     * @return bool
     */
    public function isTransparent($group = '')
    {
        if (!$group) {
            $group = $this->data['data']['group_courier'];
        }

        $categories = array(
            'dpd_dd',  // DPD Дверь — Дверь  Прямой  dpd_dd
            'dpd_dt',  // DPD Дверь — ПВЗ  Прямой  dpd_dt
            'dpd_tt',  // DPD ПВЗ — ПВЗ  Прямой  dpd_tt
            'dpd_td',  // DPD ПВЗ — Дверь  Прямой  dpd_td
            'cdek_dd', // CDEK Дверь — Дверь  Прямой  cdek_dd
            'cdek_dt', // CDEK Дверь — ПВЗ  Прямой  cdek_dt
            'cdek_tt', // CDEK ПВЗ — ПВЗ  Прямой  cdek_tt
            'cdek_td', // CDEK ПВЗ — Дверь  Прямой  cdek_td
        );

        return in_array($group, $categories);
    }
}
