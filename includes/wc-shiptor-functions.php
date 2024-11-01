<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 01.11.2017
 * Time: 15:44
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit;
}

function wc_shiptor_get_customer_kladr() {
    $kladr_id = wc_shiptor_get_option('city_origin');

    if (!WC()->session) {
        return $kladr_id;
    }

    $session_kladr_id = WC()->session->get('billing_kladr_id', null);

    if (is_null($session_kladr_id)) {
        wc_shiptor_set_customer_kladr($kladr_id);
    } else{
        $city = WC_Shiptor_Autofill_Addresses::get_city_by_id($session_kladr_id);
        if ($city && isset($city['kladr_id']) && $city['kladr_id']) {
            $kladr_id = $session_kladr_id;
        }else{
            wc_shiptor_set_customer_kladr($kladr_id);
        }
    }

    return $kladr_id;
}

function wc_shiptor_set_customer_kladr($kladr_id = 0) {
    $kladr_id = wc_clean($kladr_id);
    WC()->session->set('billing_kladr_id', $kladr_id);
}

/**
 * Добавляет текст "количество дней доставки" к названию метода доставки
 *
 * @param $name - Название метода в чистом виде
 * @param $days - количество дней
 * @param int $additional_days - количество добавочных дней
 *
 * @return mixed
 */
function wc_shiptor_get_estimating_delivery($name, $days, $additional_days = 0) {
    $days = (string)$days;
    if ($days && false !== strpos('-', $days)) {
        $periods = explode('-', $days);
        $total = intval($periods[1]) + intval($additional_days);
    } else {
        $total = intval($days) + intval($additional_days);
    }

    if ($days !== null) {
        $additional_days = wc_shiptor_get_working_days(intval($total));
    } else {
        $additional_days = 0;
    }

    if ($additional_days > 0) {
        $name .= ' (' . sprintf(_n('Delivery time - %s day', 'Delivery time - %s days', $additional_days, 'woocommerce-shiptor'), $additional_days) . ')';
    }

    return apply_filters('woocommerce_shiptor_get_estimating_delivery', $name, $days, $additional_days);
}

/**
 * Возвращает количество надбавочных дней к доставке с учётом не рабочих дней компании.
 *
 * @param int $additional_days - Количество дней которые нужно прибавить
 *
 * @return int
 */
function wc_shiptor_get_working_days($additional_days = 0) {

    $connect = new WC_Shiptor_Connect('get_working_days');
    $days_off = $connect->get_days_off();

    for ($i = 1; $i <= $additional_days; $i++) {
        $current = date('Y-m-d', strtotime("+{$i}day"));
        if (in_array($current, $days_off)) {
            $additional_days++;
        }
    }

    return $additional_days;
}

function wc_shiptor_get_shipping_delivery_time($days = '', $additional_days = 0) {

    $total = 0;

    if (false !== strpos('-', $days)) {
        $periods = explode('-', $days);
        $total = intval($periods[1]) + intval($additional_days);
    } else {
        $total = intval($days) + intval($additional_days);
    }

    $additional_days = wc_shiptor_get_working_days(intval($total));

    return time() + ( DAY_IN_SECONDS * $additional_days );
}

function wc_shiptor_get_option($option_name, $default = null) {
    $settings = get_option('woocommerce_shiptor-integration_settings');
    if ($option_name && isset($settings[$option_name])) {
        return $settings[$option_name];
    }

    return $default;
}

/**
 * Форматирование строки стоимости доставки
 *
 * @param $value
 *
 * @return mixed
 */
function wc_shiptor_normalize_price($value) {
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return $value;
}

/**
 * Возвращает ошибку по её номеру
 *
 * @param $code - код ошибки
 *
 * @return string
 */
function wc_shiptor_get_error_message($code) {
    $code = (string) $code;
    $messages = apply_filters('woocommerce_shiptor_available_error_messages', array()); //TODO: Добавить коды ошибок с их описанием.
    return isset($messages[$code]) ? $messages[$code] : '';
}

/**
 * Вешает событие на WC_Mailer для отправки трекинг-кода на емайл пользователя
 *
 * @param $order - Номер заказа
 * @param $tracking_code - Трекинг-код
 */
function wc_shiptor_trigger_tracking_code_email($order, $tracking_code) {
    $mailer = WC()->mailer();
    $notification = $mailer->emails['WC_Shiptor_Tracking_Email'];
    if ('yes' === $notification->enabled) {
        if (method_exists($order, 'get_id')) {
            $notification->trigger($order->get_id(), $order, $tracking_code);
        } else {
            $notification->trigger($order->id, $order, $tracking_code);
        }
    }
}

/**
 * Возвращает все трекинги по заказу
 *
 * @param $order - номер заказа
 * @return array
 */
function wc_shiptor_get_tracking_codes($order) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }
    if (method_exists($order, 'get_meta')) {
        $code = $order->get_meta('_shiptor_tracking_code');
    } else {
        $code = $order->shiptor_tracking_code;
    }
    return $code;
}

/**
 * Обновляет трекинг код.
 *
 * @param  WC_Order|int $order         ID заказа или дата.
 * @param  string       $tracking_code Трекинг код.
 * @param  bool         $remove        Если передать true то удалит указанный трекинг.
 *
 * @return bool
 */
function wc_shiptor_update_tracking_code($order, $tracking_code, $remove = false) {

    $tracking_code = sanitize_text_field($tracking_code);

    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if ('' === $tracking_code) {
        if (method_exists($order, 'delete_meta_data')) {
            $order->delete_meta_data('_shiptor_tracking_code');
            $order->save();
        } else {
            delete_post_meta($order->id, '_shiptor_tracking_code');
        }

        return true;
    } elseif (!$remove && !empty($tracking_code)) {
        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_shiptor_tracking_code', $tracking_code);
            $order->save();
        } else {
            update_post_meta($order->id, '_shiptor_tracking_code', $tracking_code);
        }

        $order->add_order_note(sprintf( esc_html__('Added a Shiptor tracking code: %s', 'woocommerce-shiptor'), $tracking_code));

        wc_shiptor_trigger_tracking_code_email($order, $tracking_code);

        return true;
    } elseif ($remove) {

        if (method_exists($order, 'delete_meta_data')) {
            $order->delete_meta_data('_shiptor_tracking_code');
            $order->save();
        } else {
            delete_post_meta($order->id, '_shiptor_tracking_code');
        }

        $order->add_order_note(sprintf( esc_html__('Removed a Shiptor tracking code: %s', 'shiptor-shiptor'), $tracking_code));
        return true;
    }
    return false;
}

/**
 * Возвращает список НП по части названия города.
 *
 * @param string $city_name.
 *
 * @return array
 */
function wc_shiptor_get_address_by_name($city_name, $country = 'RU') {
    return WC_Shiptor_Autofill_Addresses::get_address($city_name, $country);
}

/**
 * Возвращает массив строковых идентификаторов курьерских служб в системе Shiptor.
 * @return array
 */
function wc_shiptor_get_couriers() {
    return apply_filters('woocommerce_shiptor_couriers', array(
        'shiptor',
        'boxberry',
        'dpd',
        'iml',
        'russian-post',
        'pickpoint',
        'cdek',
        'shiptor-one-day'
            ));
}

function wc_shiptor_statuses() {
    return apply_filters('wc_shiptor_statuses', array(
        'new' =>  esc_html__('New', 'woocommerce-shiptor'),
        'checking-declaration' =>  esc_html__('Check declaration', 'woocommerce-shiptor'),
        'declaration-checked' =>  esc_html__('Declaration Verified', 'woocommerce-shiptor'),
        'waiting-pickup' =>  esc_html__('Waiting for pick-up', 'woocommerce-shiptor'),
        'arrived-to-warehouse' =>  esc_html__('Arrived at the warehouse', 'woocommerce-shiptor'),
        'packed' =>  esc_html__('Packed', 'woocommerce-shiptor'),
        'prepared-to-send' =>  esc_html__('Prepared to send', 'woocommerce-shiptor'),
        'sent' =>  esc_html__('Sent', 'woocommerce-shiptor'),
        'delivered' =>  esc_html__('Delivered', 'woocommerce-shiptor'),
        'removed' =>  esc_html__('Removed', 'woocommerce-shiptor'),
        'recycled' =>  esc_html__('Recycled', 'woocommerce-shiptor'),
        'returned' =>  esc_html__('Waiting in line for return', 'woocommerce-shiptor'),
        'reported' =>  esc_html__('Returned to the sender', 'woocommerce-shiptor'),
        'lost' =>  esc_html__('Lost', 'woocommerce-shiptor'),
        'resend' =>  esc_html__('Resubmitted', 'woocommerce-shiptor'),
        'waiting-on-delivery-point' =>  esc_html__('Waiting at the delivery point', 'woocommerce-shiptor')
    ));
}

function wc_shiptor_get_status($key) {
    $statuses = wc_shiptor_statuses();
    if (in_array($key, array_keys($statuses))) {
        return $statuses[$key];
    }

    return  esc_html__('N/A', 'woocommerce');
}

function wc_shiptor_chosen_shipping_rate() {
    $chosen_rate = null;

    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    foreach (WC()->shipping->get_packages() as $i => $package) {
        if ( isset($chosen_shipping_methods[$i], $package['rates'][$chosen_shipping_methods[$i]]) ){
            $chosen_rate = $package['rates'][$chosen_shipping_methods[$i]];
            break;
        }
    }

    return $chosen_rate;
}

function wc_shiptor_chosen_shipping_package() {
    $chosen_package = null;

    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    foreach (WC()->shipping->get_packages() as $i => $package) {
        if ( isset($chosen_shipping_methods[$i], $package['rates'][$chosen_shipping_methods[$i]]) ){
            $chosen_package = $package;
            break;
        }
    }

    return $chosen_package;
}

add_action('shiptor_update_order_statuses', 'wc_shiptor_update_order_statuses_action');

function wc_shiptor_update_order_statuses_action() {

    $orders = wc_get_orders(array(
        'limit' => -1,
        'status' => array('pending', 'processing', 'on-hold'),
        'shiptor' => true
            ));

    $connect = new WC_Shiptor_Connect('order_status');
    $connect->set_debug('yes');

    foreach ($orders as $order) {
        if (!is_a($order, 'WC_Order')) {
            continue;
        }

        $package = $connect->get_package($order->get_meta('_shiptor_id'));

        if (is_array($package)) {
            if ($package['status'] !== $order->get_meta('_shiptor_status')) {
                if ('delivered' == $package['status']) {
                    $order->set_status('completed');
                }
                $order->update_meta_data('_shiptor_status', $package['status']);
                $order->save();
            }
        }
    }
}


// add_action('init', 'wc_shiptor_cron_add_packages');
add_action('shiptor_orders_cron', 'wc_shiptor_cron_add_packages');
function wc_shiptor_cron_add_packages() {
    $statuses = wc_shiptor_get_option( 'cron_order_status' );

    if (!$statuses) {
        return;
    }

    $args = array(
        'limit' => -1,
        'status' => $statuses,
    );
    $orders = wc_get_orders($args);
    foreach ($orders as $order) {
        wc_shiptor_add_package($order);
    }
}

function wc_shiptor_add_package($order) {
    $connect = new WC_Shiptor_Connect('cron_orders');
    if ($order->get_meta('_shiptor_id')) {
        return;
    }

    $package = $products = array();
    foreach (array_values($order->get_items('line_item')) as $item_index => $item) {
        $product = wc_get_product($item->get_product_id());
        $package['contents'][] = array(
            'data' => $product,
            'quantity' => $item->get_quantity()
        );

        $_article = get_post_meta($product->get_id(), '_article', true);
        $article = !empty($_article) ? esc_attr($_article) : ( $product->get_sku() ? $product->get_sku() : $product->get_id() );

        $products[$item_index] = array(
            'shopArticle' => $article,
            'count' => $item->get_quantity(),
            'price' => $product->get_price()
        );

        if (!in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
            $products[$item_index]['englishName'] = $product->get_meta('_eng_name') ? $product->get_meta('_eng_name') : sanitize_title($product->get_name());
        }
    }

    $shipping = current($order->get_items('shipping'));
    if ($shipping && method_exists($shipping, 'get_meta')) {
        $shiptor_method = $shipping->get_meta('shiptor_method');
        if ($shiptor_method) {
            $connect = new WC_Shiptor_Connect('cron_add_package');
            $connect->set_kladr_id($shiptor_method['sender_city']);
            $connect->set_courier($shiptor_method['courier']);
            $connect->set_shipping_method($shiptor_method['id']);
            $connect->set_cod($order->get_billing_country() === 'RU' && !$order->is_paid() ? $order->get_total() : 0 );
            $connect->set_package($package);

            $shiptor_method = $shipping->get_meta('shiptor_method');
            $instance_id = $shipping->get_instance_id();

            $option = new WC_Shiptor_Shipping_Host_Method($instance_id);
            $is_fulfilment = wc_string_to_bool($option->get_option("client_methods[{$shiptor_method['id']}][is_fulfilment]"));
            $is_export = 'shiptor-international' === $shiptor_method['courier'];

            $atts = array(
                'external_id' => $order->get_order_number(),
                'is_fulfilment' => $is_fulfilment,
                'no_gather' => false,
                'departure' => array(
                    'shipping_method' => intval($shiptor_method['id']),
                    'address' => array(
                        'country' => $order->get_billing_country(),
                        'receiver' => $order->get_formatted_billing_full_name(),
                        'email' => $order->get_billing_email(),
                        'phone' => $order->get_billing_phone(),
                        'settlement' => $order->get_billing_city(),
                        'administrative_area' => $order->get_billing_state()
                    )
                ),
                'products' => $products
            );

            if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
                if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) {
                    $cost = in_array($order->get_payment_method(), array('cod', 'cod_card')) ? $order->get_total() : 0;
                } else {
                    $cost = 0;
                }

                $atts['declared_cost'] = $shiptor_method['declared_cost'] < 10 ? 10 : $order->get_total();

                $atts['departure']['address']['name'] = $order->get_billing_first_name();
                $atts['departure']['address']['surname'] = $order->get_billing_last_name();
                $atts['departure']['address']['kladr_id'] = $order->get_meta('_billing_kladr_id');
                $atts['cod'] = $cost;

                if ($cost > 0) {

                    $atts['departure']['cashless_payment'] = $order->get_payment_method() == 'cod_card';

                    $service_id = sprintf('shipping_%s_%s', $shiptor_method['courier'], $shiptor_method['category']);
                    $found = false;
                    $get_services = $connect->get_service();

                    if ($get_services && isset($get_services['services'])) {
                        $found_service = wp_list_filter($get_services['services'], array('shop_article' => $service_id));
                        if (!empty($found_service)) {
                            $found = true;
                        }
                    }

                    if (!$found) {
                        $add_service = $connect->add_service(sprintf( esc_html__('Shipping via %s', 'woocommerce'), $shiptor_method['method_name']), $service_id);
                        if ($add_service && isset($add_service['shop_article']) && $add_service['shop_article'] == $service_id) {
                            $found = true;
                        }
                    }

                    if ($found) {
                        $atts['services'] = array(
                            array(
                                'shopArticle' => $service_id,
                                'count' => 1,
                                'price' => $order->get_shipping_total()
                            )
                        );
                    }
                }
            } else {
                $atts['departure']['address']['address_line_1'] = $order->get_billing_address_1();
            }

            if (!in_array($shiptor_method['category'], array('to-door', 'post-office', 'door-to-door', 'delivery-point-to-door'))) {
                $atts['departure']['delivery_point'] = intval($order->get_meta('_chosen_delivery_point'));
            }

            if ($note = $order->get_customer_note()) {
                $atts['departure']['comment'] = $note;
            }


            switch ($shiptor_method['category']) {
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

            $shipment = array(
                'type' => $shipment_type
            );


            if ($shipment_type == 'standard') {
                $stock = $option->get_option("client_methods[{$shiptor_method['id']}][shiptor_warehouse]");
                $stock = $stock ? $stock : wc_shiptor_get_option('shiptor_warehouse');
                if ($stock) {
                    $atts['stock'] = (int)$stock;
                    $stock_info = $connect->get_stock($stock);
                    if (in_array('fulfilment', $stock_info['roles']) && in_array('logistic', $stock_info['roles'])) {
                        $is_fulfilment = wc_string_to_bool($option->get_option("client_methods[{$shiptor_method['id']}][is_fulfilment]"));
                    } else if (in_array('fulfilment', $stock_info['roles'])) {
                        $is_fulfilment = true;
                    } else if (in_array('fulfilment', $stock_info['roles'])) {
                        $is_fulfilment = false;
                    } else {
                        $is_fulfilment = false;
                    }
                } else {
                    $is_fulfilment = wc_string_to_bool($option->get_option("client_methods[{$shiptor_method['id']}][is_fulfilment]"));
                }

                $atts['is_fulfilment'] = $is_fulfilment;
            }


            if (in_array($shipment_type, array('courier', 'delivery-point'))) {
                $shipment['courier'] = $shiptor_method['courier'];
                $shipment['address'] = array(
                    'receiver' => $shiptor_method['sender_name'],
                    'email' => $shiptor_method['sender_email'],
                    'phone' => $shiptor_method['sender_phone'],
                    'country' => WC()->countries->get_base_country(),
                    'kladr_id' => $shiptor_method['sender_city'] ?: wc_shiptor_get_option('city_origin')
                );

                $shipment['date'] = date('d.m.Y', strtotime(getNextWorkingDay()));
            }

            if ('delivery-point' == $shipment_type) {
                $conn = new WC_Shiptor_Connect('shiptor_delivery_points');
                $conn->set_shipping_method(intval($shiptor_method['id']));
                $conn->set_kladr_id($shiptor_method['sender_city']);
                $points = $conn->get_delivery_points(array(
                    'self_pick_up' => true,
                ));
                $point = array_filter($points, function($item) use ($shiptor_method){
                    return $item['address'] == $shiptor_method['sender_address'];
                });
                $point = current($point);

                $shipment['delivery_point'] = intval($point['id']);
            } elseif (in_array($shipment_type, array('courier', 'standard'))) {
                if (isset($shiptor_method['sender_address'])) {
                    $shipment['address']['street'] = $shiptor_method['sender_address'];
                }
                if ($order->get_billing_address_1()) {
                    $atts['departure']['address']['address_line_1'] = $order->get_billing_address_1();
                }
            }

            $result = $connect->add_packages($atts, $shipment, $is_export);
            if (( $is_export && isset($result['result']) ) || (!$is_export && isset($result['result']['packages']) )) {
                $package = $is_export ? $result['result'] : current($result['result']['packages']);

                $order->update_meta_data('_shiptor_id', intval($package['id']));
                $order->update_meta_data('_shiptor_status', $package['status']);
                $order->update_meta_data('_shiptor_label_url', $package['label_url']);

                if (!$is_export && isset($result['result']['shipment'])) {
                    $shipment_id = $result['result']['shipment']['id'];
                    $order->update_meta_data('_shipment_id', $shipment_id);
                }

                if (isset($result['result']['tracking_number'])) {
                    wc_shiptor_update_tracking_code($order, $package['tracking_number']);
                } else {
                    $order->save();
                }

                maybe_update_order_status(intval($package['id']), $order, true);
            } elseif (isset($result['error'])) {
                add_post_meta($order->ID, 'shiptor_order_error', 1, true);
                WC_Admin_Notices::add_custom_notice(
                    'shiptor_order_error', sprintf(
                        'Во время отправки заказа %1$s произошла ошибка: (%2$s). <a href="%3$s">Перейдите в заказ чтобы отправить его.</a>', $order->get_order_number(), $result['error'], esc_url(admin_url('post.php?post=' . absint($order->get_id())) . '&action=edit')
                    )
                );
            }
        }
    }
}

function getNextWorkingDay() {
    $connect = new WC_Shiptor_Connect('cron_add_package');
    $todayPlus1 = date('Y-m-d', strtotime('+1days') + wc_timezone_offset());
    $daysOff = $connect->get_days_off();
    if ($daysOff) {
        for ($i = 0, $count = count($daysOff); $i < $count; $i++) {
            if (in_array($todayPlus1, $daysOff)) {
                $todayPlus1 = date('Y-m-d', strtotime($todayPlus1 . ' + 1days') + wc_timezone_offset());
            }
        }
    }

    return $todayPlus1;
}
function maybe_update_order_status($shiptor_id = 0, $order, $force = false) {
    $connect = new WC_Shiptor_Connect('cron_add_package');
    $transient_name = 'wc_shiptor_order_status_' . $shiptor_id;
    if (false === ( $package = get_transient($transient_name) ) || true === $force) {
        $package = $connect->get_package($shiptor_id);
        if ($package && isset($package['status'])) {
            set_transient($transient_name, $package, HOUR_IN_SECONDS);
            if (is_a($order, 'WC_Order')) {
                $order->update_meta_data('_shiptor_status', $package['status']);
                $order->update_meta_data('_shiptor_label_url', $package['label_url']);
                if (isset($package['shipment'])) {
                    $order->update_meta_data('_shipment_confirmed', $package['shipment']['confirmed']);
                }
                if (isset($package['tracking_number']) && $package['tracking_number'] !== $order->get_meta('_shiptor_tracking_code')) {
                    wc_shiptor_update_tracking_code($order, $package['tracking_number']);
                } else {
                    $order->save();
                }
            }
        }
    }
}

function wc_handle_shiptor_query_var($query, $query_vars) {
    if (!empty($query_vars['shiptor'])) {
        $query['meta_query'][] = array(
            'key' => '_shiptor_id'
        );
    }

    return $query;
}

add_filter('woocommerce_order_data_store_cpt_get_orders_query', 'wc_handle_shiptor_query_var', 10, 2);

function wc_shiptor_has_virtual_product_package($package) {

    foreach ($package['contents'] as $item_id => $product) {
        if ($product['data']->is_virtual()) {
            return true;
        }
    }
    return false;
}

add_filter('wc_add_to_cart_message_html', 'wc_shiptor_add_to_cart_virtual_product_message_html', 10, 2);

function wc_shiptor_add_to_cart_virtual_product_message_html($message, $products) {
    $added_text = '';
    foreach ((array) $products as $product_id => $qty) {
        $product = wc_get_product($product_id);
        if ($product->is_virtual()) {
            $added_text = sprintf( esc_html__('"%s" is a <strong>virtual item</strong>. It will not be possible to place it as one order together with ordinary items.', 'woocommerce-shiptor'), strip_tags(get_the_title($product_id)));
        }
    }

    if (!empty($added_text)) {
        $message = $message . sprintf('<p style="margin-bottom:0;">%s</p>', $added_text);
    }
    return $message;
}

function wc_shiptor_find_mixed_product_type_in_cart() {

    if (!WC()->cart->is_empty()) {
        $has_virtual = $has_simple = array();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['data']->is_virtual()) {
                $has_virtual[] = $cart_item['product_id'];
            } else {
                $has_simple[] = $cart_item['product_id'];
            }
        }

        return ( count($has_virtual) > 0 && count($has_simple) > 0 ) === true;
    }
    return false;
}

add_action('woocommerce_after_checkout_validation', 'wc_shiptor_after_checkout_validation', 10, 2);
add_action('woocommerce_check_cart_items', 'wc_shiptor_add_notice_before_checkout_form', 10);

function wc_shiptor_add_notice_before_checkout_form() {
    if (wc_shiptor_find_mixed_product_type_in_cart()) {
        wc_print_notice( esc_html__('There are items in the basket which do not require delivery. Please make them as separate orders.', 'woocommerce-shiptor'), 'error');
    }
}

function wc_shiptor_after_checkout_validation($data, $errors) {
    if (wc_shiptor_find_mixed_product_type_in_cart()) {
        $errors->add('mixed_product_in_cart',  esc_html__('You cannot continue placing your order because there are "miscellaneous" items in your basket. Please delete either real or virtual items from the basket and continue placing the order.', 'woocommerce-shiptor'));
    }
}

/**
 * Get all enabled shipping methods
 *
 * @return array
 */
function get_enabled_methods() {
    global $wpdb;
    $enabled_methods = array();
    $query = "SELECT zone_id, instance_id, method_id, is_enabled FROM wp_woocommerce_shipping_zone_methods";
    $result = $wpdb->get_results($query);
    if (!empty($result) && is_array($result)) {

        foreach ($result as $row) {
            if ($row->is_enabled == 1) {
                $enabled_methods[] = array(
                    "instance_id" => $row->instance_id,
                    "method_id" => $row->method_id,
                    "zone_id" => $row->zone_id
                );
            }
        }
    }

    return $enabled_methods;
}

/**
 * @param $arr
 * @return array|null
 */
function substring_enabled_methods($arr) {

    $enabled_method_names = array();
    if (!empty($arr)) {
        $count = count($arr);
        for ($i = 0; $i < $count; $i++) {
            if (isset($arr[$i]['method_id']) && strpos($arr[$i]['method_id'], 'shiptor') !== false) {
                $enabled_method_names[$arr[$i]['instance_id']] = substr($arr[$i]['method_id'], 8);
            } else {
                array_push($enabled_method_names, $arr[$i]['method_id']);
            }
        }
    }

    return $enabled_method_names;
}

/**
 * @param $end_result
 * @param $simplified_shipping_method
 * @param $courier
 * @param $key
 */
function createEndResult(&$end_result, $simplified_shipping_method, $courier, $key) {
    foreach ($simplified_shipping_method as $method) {

        foreach ($method as $k => $val) {
            if ($method[$k]['courier'] === $courier) {
                $end_result[] = array(
                    'fix_cost' => '',
                    'fee' => '',
                    'additional_time' => '',
                    'free' => '',
                    'enable_declared_cost' => '',
                    'status' => $method[$k]['status'],
                    'method_id' => $method[$k]['method_id'] . '_' . $key,
                    'name' => $method[$k]['name'],
                    'total' => $method[$k]['total'],
                    'currency' => $method[$k]['currency'],
                    'readable' => $method[$k]['readable'],
                    'days' => $method[$k]['days'],
                    'free_shipping_text' => '',
                    'cost' => $method[$k]['cost']
                );
            }
        }
    }
}

add_action('woocommerce_after_add_to_cart_button', 'update_shipping_price_button');

function update_shipping_price_button() {
    if ('yes' === wc_shiptor_get_option('calculate_in_product')) {
        echo '<button id="update_shipping_info" class="update_shipping_price button alt">' . esc_html__('Update prices', 'woocommerce-shiptor') . ' <i class="fa fa-refresh" aria-hidden="true"></i></button>';
    }
}

add_action('woocommerce_shipping_calculator_enable_city', 'add_hint_shipping_calculate');

function add_hint_shipping_calculate() {
    echo '<span>' . esc_html__('Enter a locality', 'woocommerce-shiptor') . '</span>';
}

function get_page_by_referer(){
    $page = null;

    if ( wp_get_referer() ){
        $page_id = url_to_postid( wp_get_referer() );
        $page = get_post( $page_id );
        if(!$page){
            $page = get_post(get_option( 'page_on_front' ));
        }
    }

    return $page;
}

function is_ajax_referer_from_cart(){
    if(!is_ajax()){
        return false;
    }

    $page = get_page_by_referer();

    if(!$page){
        return false;
    }

    if($page->ID == wc_get_page_id( 'cart' )){
        return true;
    }

    if(has_shortcode($page->post_content, 'woocommerce_cart')){
        return true;
    }

    return false;
}

function is_ajax_referer_from_checkout(){
    if(!is_ajax()){
        return false;
    }

    $page = get_page_by_referer();

    if(!$page){
        return false;
    }

    if($page->ID == wc_get_page_id( 'checkout' )){
        return true;
    }

    if(has_shortcode($page->post_content, 'woocommerce_checkout')){
        return true;
    }

    return false;
}

function get_shiptor_wc_instanced_methods() {
    global $wpdb;

    $shipping_methods = array();

    $raw_methods_sql = "SELECT method_id, method_order, instance_id, is_enabled FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = %s AND is_enabled = 1";
    $raw_methods = $wpdb->get_results( $wpdb->prepare( $raw_methods_sql, 'shiptor-shipping-host' ) );
    foreach($raw_methods as $raw_method) {
        $shipping_methods[] = new WC_Shiptor_Shipping_Host_Method( $raw_method->instance_id );
    }

    return $shipping_methods;
}

/**
 * Getting info about shipping method
 */
function get_shipping_method_info($id) {
    global $wpdb;

    $id = intval($id);
    $method = array();

    $query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}shiptor_shipping_methods WHERE id = %d", $id);
    $result = $wpdb->get_row($query);
    if (!empty($result)) {
        $method = (array)$result;
    }

    return $method;
}
