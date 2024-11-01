<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 0:04
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WC_Shiptor_Package {

    protected $package = array();

    public function __construct($package = array()) {
        $this->package = $package;
        $this->log = new WC_Logger();
    }

    protected function get_package_data() {
        $count = 0;
        $height = array();
        $width = array();
        $length = array();
        $weight = array();
        if ( !empty($this->package['contents']) ) {
            foreach ($this->package['contents'] as $item_id => $values) {
    
                $product = $values['data'];
                $qty = $values['quantity'];
    
                if ($qty > 0 && $product->needs_shipping()) {
                    $_height = wc_get_dimension($product->get_length(), 'cm');
                    $_width = wc_get_dimension($product->get_width(), 'cm');
                    $_length = wc_get_dimension($product->get_height(), 'cm');
                    $_weight = wc_get_weight($product->get_weight(), 'kg');
    
                    $_height = $_height > 0 ? $_height : wc_shiptor_get_option('minimum_height');
                    $_width = $_width > 0 ? $_width : wc_shiptor_get_option('minimum_width');
                    $_length = $_length > 0 ? $_length : wc_shiptor_get_option('minimum_length');
                    $_weight = $_weight > 0 ? $_weight : wc_shiptor_get_option('minimum_weight');
    
                    $height[$count] = $_height;
                    $width[$count] = $_width;
                    $length[$count] = $_length;
                    $weight[$count] = $_weight;
    
                    if ($qty > 1) {
                        $n = $count;
                        for ($i = 0; $i < $qty; $i++) {
                            $height[$n] = $_height;
                            $width[$n] = $_width;
                            $length[$n] = $_length;
                            $weight[$n] = $_weight;
                            $n++;
                        }
                        $count = $n;
                    }
    
                    $count++;
                }
            }
        }

        return array(
            'height' => array_values($height),
            'length' => array_values($length),
            'width' => array_values($width),
            'weight' => array_sum($weight),
        );
    }

    /*
     * Вычисляем общий объём посылки
     */

    protected function cubage_total($height, $width, $length) {
        $total = 0;
        $total_items = count($height);
        for ($i = 0; $i < $total_items; $i++) {
            $total += $height[$i] * $width[$i] * $length[$i];
        }
        return $total;
    }

    /*
     * Получаем максимальные значения всех сторон в посылке.
     */

    protected function get_max_values($height, $width, $length) {
        return array(
            'height' => max($height),
            'width' => max($width),
            'length' => max($length),
        );
    }

    /*
     * Вычисляем квадратный корень из всех сторон.
     */

    protected function calculate_root($height, $width, $length, $max_values) {
        $cubage_total = $this->cubage_total($height, $width, $length);
        $root = 0;
        $biggest = max($max_values);
        if (0 !== $cubage_total && 0 < $biggest) {
            $division = $cubage_total / $biggest;
            $root = round(sqrt($division), 1);
        }

        return $root;
    }

    /*
     * Вычисляем кубический корень из сумарного объёма посылки.
     */

    protected function calculate_cubic($height, $width, $length) {
        $cubage_total = $this->cubage_total($height, $width, $length);
        return round(pow($cubage_total, 1 / 3), 1);
    }

    /*
     * Основная функция вычисление объёма посылки.
     */

    protected function get_cubage($height, $width, $length) {
        $cubage = array();
        $max_values = $this->get_max_values($height, $width, $length); //Получили максимальные значения сторон.
        $cubic = $this->calculate_cubic($height, $width, $length); //Вычислели кубичский корень
        //Если кубический корень больше максимального значения любой из сторон, то каждая сторона равна кубическому корню.
        if ($cubic > max($max_values)) {
            $cubage = array(
                'height' => $cubic,
                'width' => $cubic,
                'length' => $cubic,
            );
        } else {
            //иначе если кубический корень меньше максимального значения любой из сторон
            $root = $this->calculate_root($height, $width, $length, $max_values); //Получаем квадратный корень каждой из сторон
            $greatest = array_search(max($max_values), $max_values, true); //Узнаём какая из сторон является самой большой.
            //Если максимальное значение сторон не длина, то присваивам длине это значение. Остальным сторонам присваиваем значение квадратного корня.
            switch ($greatest) {
                case 'height' :
                    $cubage = array(
                        'height' => count($length) > 1 ? $root : max($length),
                        'width' => count($width) > 1 ? $root : max($width),
                        'length' => max($height),
                    );
                    break;
                case 'width' :
                    $cubage = array(
                        'height' => count($height) > 1 ? $root : max($height),
                        'width' => count($length) > 1 ? $root : max($length),
                        'length' => max($width),
                    );
                    break;
                case 'length' :
                    $cubage = array(
                        'height' => count($height) > 1 ? $root : max($height),
                        'width' => count($width) > 1 ? $root : max($width),
                        'length' => max($length),
                    );
                    break;
                default :
                    $cubage = array(
                        'height' => 0,
                        'width' => 0,
                        'length' => 0,
                    );
                    break;
            }
        }

        return $cubage;
    }

    public function get_data() {

        $data = apply_filters('woocommerce_shiptor_default_package', $this->get_package_data());
        if (!empty($data['height']) && !empty($data['width']) && !empty($data['length'])) {
            $cubage = $this->get_cubage($data['height'], $data['width'], $data['length']);
        } else {
            $cubage = array(
                'height' => 0,
                'width' => 0,
                'length' => 0,
            );
        }

        return array(
            'height' => apply_filters('woocommerce_shiptor_package_height', $cubage['height']),
            'width' => apply_filters('woocommerce_shiptor_package_width', $cubage['width']),
            'length' => apply_filters('woocommerce_shiptor_package_length', $cubage['length']),
            'weight' => apply_filters('woocommerce_shiptor_package_weight', $data['weight']),
        );
    }

}
