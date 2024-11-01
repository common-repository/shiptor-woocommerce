<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="shiptor-order-details">
    <div id="wc-shiptor-result"></div>
    <form id="create-shiptor-order" method="post">
        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_order_number()); ?>"/>
        <?php if ($shiptor_method != null) : ?>
            <input type="hidden" name="method_id" value="<?php echo esc_attr($shiptor_method['id']); ?>"/>
            <input type="hidden" name="courier" value="<?php echo esc_attr($shiptor_method['courier']); ?>"/>
            <input type="hidden" name="category" value="<?php echo esc_attr($shiptor_method['category']); ?>"/>
            <input type="hidden" name="method_name" value="<?php echo esc_attr($shiptor_method['name']); ?>"/>
        <?php endif; ?>
        <div class="col">
            <h3>
                <?php _e('Order details', 'woocommerce-shiptor'); ?>
                <a href="#"
                   class="shiptor_edit_address"
                   data-order_id="<?php echo esc_attr($order->get_order_number()); ?>"
                   data-type="order_details"
                ></a>
            </h3>
            <table>
                <tbody>
                <tr>
                    <td><p class="form-field"><strong><?php _e('Method name: ', 'woocommerce-shiptor'); ?></strong></p>
                    </td>
                    <?php if ($shiptor_method != null) : ?>
                        <td><p class="form-field"><?php echo esc_html($shiptor_method['name']); ?></p></td>
                    <?php endif; ?>
                </tr>
                <tr>
                    <td><p class="form-field"><strong><?php _e('Country: ', 'woocommerce-shiptor'); ?></strong></p></td>
                    <?php if ($order->get_billing_country() != null) : ?>
                        <td>
                            <p class="form-field"><?php echo esc_html(WC()->countries->countries[$order->get_billing_country()]); ?></p>
                        </td>
                    <?php endif; ?>
                </tr>
                <tr>
                    <td><p class="form-field"><strong><?php _e('City: ', 'woocommerce-shiptor'); ?></strong></p></td>
                    <td>
                        <input type="hidden" class="js_city_kladr"
                               value="<?php echo esc_attr($order->get_meta('_billing_kladr_id')) ?>"/>
                        <p class="form-field"><?php echo esc_html($order->get_billing_city()); ?></p>
                    </td>
                </tr>
                <?php
                if ($shiptor_method != null && $delivery_points != null) :
                    if (!in_array($shiptor_method['category'], array('to-door', 'post-office', 'door-to-door', 'delivery-point-to-door'))) :
                        ?>
                        <tr>
                            <td><p class="form-field">
                                    <strong><?php _e('Delivery point: ', 'woocommerce-shiptor'); ?></strong></p></td>
                            <td>
                                <?php
                                $_chosen_delivery_point = $order->get_meta('_chosen_delivery_point');
                                $point = array_filter($delivery_points, function ($item) use ($_chosen_delivery_point) {
                                    return $item['id'] == $_chosen_delivery_point;
                                });
                                $point = reset($point);

                                woocommerce_wp_text_input(array(
                                    'id' => 'js_sender_address',
                                    'value' => !empty($point['address']) ? $point['address'] : '',
                                    'class' => 'input-text js_sender_address',
                                    'label' => null,
                                    'custom_attributes' => array(
                                        'readonly' => true,
                                    ),
                                ));
                                woocommerce_wp_hidden_input(array(
                                    'id' => 'chosen_delivery_point',
                                    'value' => $_chosen_delivery_point,
                                    'class' => 'js_sender_address_id',
                                ));
                                ?>

                                <button type="button" class="button-primary js_ajax_load_points" data-type="order"
                                        data-id="<?php echo esc_attr($shiptor_method['id']); ?>"><?php esc_html_e('Select', 'woocommerce-shiptor') ?></button>
                            </td>
                        </tr>
                    <?php elseif (in_array($shiptor_method['category'], array('to-door', 'post-office', 'door-to-door', 'delivery-point-to-door'))) : ?>
                        <tr>
                            <td><p class="form-field"><strong><?php esc_html_e('Address: ', 'woocommerce-shiptor'); ?></strong>
                                </p></td>
                            <td>
                                <?php
                                woocommerce_wp_text_input(array(
                                    'id' => 'address_line',
                                    'value' => $order->get_billing_address_1(),
                                    'class' => 'input-text',
                                    'label' => null
                                ));
                                ?>
                            </td>
                        </tr>
                    <?php
                    endif;
                endif;
                ?>
                <tr>
                    <td><p class="form-field"><strong><?php esc_html_e('Payment method: ', 'woocommerce-shiptor'); ?></strong>
                        </p></td>
                    <td><p class="form-field"><?php echo esc_html($order->get_payment_method_title('edit')); ?></p></td>
                </tr>
                <?php if (in_array($order->get_billing_country(), array('RU', 'BY', 'KZ'))) : $cod = in_array($order->get_payment_method(), array('cod', 'cod_card')) ? $order->get_total() : 0; ?>
                    <tr>
                        <td>
                            <p class="form-field">
                                <strong><?php esc_html_e('C.O.D. : ', 'woocommerce-shiptor'); ?></strong>
                            </p>
                        </td>
                        <?php if ($shiptor_method != null) :?>
                        <td>
                            <p class="form-field">
                                <input class="input-text" type="text" name="cod" readonly value="<?php echo esc_attr($cod); ?>"/>
                            </p>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <tr>
                        <td>
                            <p class="form-field">
                                <strong><?php esc_html_e('Declared cost : ', 'woocommerce-shiptor'); ?></strong>
                            </p>
                        </td>
                        <?php if ($shiptor_method != null) :?>
                        <td>
                            <p class="form-field">
                                <?php $declared_cost = in_array($order->get_payment_method(), array('cod', 'cod_card')) ? $order->get_total() : $order->get_total() - $order->get_shipping_total();?>
                                <input class="input-text" type="text" name="declared_cost" readonly value="<?php echo esc_attr($declared_cost); ?>"/>
                            </p>
                        </td>
                        <?php endif;?>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td><p class="form-field"><strong><?php esc_html_e('Order note : ', 'woocommerce-shiptor'); ?></strong></p>
                    </td>
                    <td>
                        <?php
                        woocommerce_wp_textarea_input(array(
                            'id' => 'comment',
                            'value' => $order->get_customer_note('edit'),
                            'label' => null,
                            'class' => 'input-text',
                            'rows' => 5
                        ));
                        ?>
                    </td>
                </tr>
                </tbody>
            </table>

        </div>
        <div class="col">
            <h3>
                <?php esc_html_e('Details of the sender', 'woocommerce-shiptor'); ?>
                <!--a href="#"
                   class="shiptor_edit_address"
                   data-order_id="<?php echo esc_attr($order->get_order_number()); ?>"
                   data-type="sender_details"
                ></a-->
            </h3>
            <table>
                <tbody>
                <?php
                if (($shiptor_method != null) && (strstr($shiptor_method['category'], '-to-'))) :
                    ?>
                    <tr>
                        <td><p class="form-field">
                                <strong><?php esc_html_e('Sender name', 'woocommerce-shiptor'); ?></strong></p></td>
                        <td>
                            <?php
                            woocommerce_wp_text_input(array(
                                'id' => 'sender_name',
                                'value' => isset($shiptor_method['sender_name']) ? $shiptor_method['sender_name'] : null,
                                'class' => 'input-text',
                                'label' => null,
                                'custom_attributes' => array('required' => 'required')
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><p class="form-field">
                                <strong><?php esc_html_e('Sender phone', 'woocommerce-shiptor'); ?></strong></p></td>
                        <td>
                            <?php
                            woocommerce_wp_text_input(array(
                                'id' => 'sender_phone',
                                'value' => isset($shiptor_method['sender_phone']) ? $shiptor_method['sender_phone'] : null,
                                'class' => 'input-text',
                                'label' => null,
                                'custom_attributes' => array(
                                    'required' => 'required',
                                )
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><p class="form-field">
                                <strong><?php esc_html_e('Sender E-mail', 'woocommerce-shiptor'); ?></strong></p></td>
                        <td>
                            <?php
                            woocommerce_wp_text_input(array(
                                'id' => 'sender_email',
                                'value' => isset($shiptor_method['sender_email']) ? $shiptor_method['sender_email'] : null,
                                'class' => 'input-text',
                                'label' => null,
                                'custom_attributes' => array('required' => 'required')
                            ));
                            ?>
                        </td>
                    </tr>
                    <?php if (isset($shiptor_method['sender_city'])) : ?>
                    <tr>
                        <input type="hidden" class="js_city_kladr" name="sender_city"
                               value="<?php echo esc_attr($shiptor_method['sender_city']); ?>"/>
                        <td><p class="form-field">
                                <strong><?php esc_html_e('Sender city', 'woocommerce-shiptor'); ?></strong></p></td>
                        <td>
                            <?php
                            $sender_city = WC_Shiptor_Autofill_Addresses::get_city_by_id($shiptor_method['sender_city']);
                            woocommerce_wp_text_input(array(
                                'id' => 'sender_city_name',
                                'value' => $sender_city['city_name'],
                                'class' => 'input-text',
                                'custom_attributes' => array('readonly' => 'readonly'),
                                'label' => null
                            ));
                            ?>
                        </td>
                    </tr>
                    <?php if (0 === strpos($shiptor_method['category'], 'delivery-point-to-')) : ?>
                        <tr>
                            <td><p class="form-field">
                                    <strong><?php esc_html_e('Departure point: ', 'woocommerce-shiptor'); ?></strong></p>
                            </td>
                            <td>
                                <?php
                                woocommerce_wp_text_input(array(
                                    'id' => 'sender_delivery_point',
                                    'label' => null,
                                    'class' => 'input-text',
                                    'custom_attributes' => array(
                                        'required' => 'required',
                                        'readonly' => true,
                                    ),
                                    'value' => $shiptor_method['sender_address'],
                                ));
                                ?>
                                <button type="button" class="button-primary js_ajax_load_points"
                                        data-id="<?php echo esc_attr($shiptor_method['id']); ?>"><?php esc_html_e('Select', 'woocommerce-shiptor') ?></button>
                            </td>
                        </tr>
                    <?php elseif (0 === strpos($shiptor_method['category'], 'door-to-')) : ?>
                        <tr>
                            <td><p class="form-field">
                                    <strong><?php esc_html_e('Sender address', 'woocommerce-shiptor'); ?></strong></p></td>
                            <td><?php
                                woocommerce_wp_text_input(array(
                                    'id' => 'sender_address',
                                    'value' => isset($shiptor_method['sender_address']) ? $shiptor_method['sender_address'] : null,
                                    'class' => 'input-text',
                                    'label' => null,
                                    'custom_attributes' => array(
                                        'required' => 'required'
                                    )
                                ));
                                ?>
                            </td>
                        </tr>
                    <?php endif; endif; ?>
                    <tr>
                        <td><p class="form-field"><strong><?php esc_html_e('Date', 'woocommerce-shiptor'); ?></strong></p>
                        </td>
                        <td><?php
                            $obj = new WC_Shiptor_Admin_Order();

                            woocommerce_wp_text_input(array(
                                'id' => 'sender_order_date',
                                'value' => $obj->getNextWorkingDay(),
                                'class' => 'sender-order-date input-text',
                                'label' => null,
                                'placeholder' => 'YYYY-MM-DD'
                            ));
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <td><p class="form-field">
                                <strong><?php esc_html_e('Confirm Automatically?', 'woocommerce-shiptor'); ?></strong></p>
                        </td>
                        <td><?php
                            woocommerce_wp_checkbox(array(
                                'id' => 'confirm_shipment',
                                'label' => null,
                                'class' => 'form-field--checkbox',
                                'value' => 'no'
                            ));
                            ?>
                        </td>
                    </tr>
                    <?php if ($order->get_billing_country() === 'RU' && in_array($order->get_payment_method(), array('cod', 'cod_card'))) : ?>
                    <tr>
                        <td><p class="form-field">
                                <strong><?php esc_html_e('Payment by card?', 'woocommerce-shiptor'); ?></strong></p></td>
                        <td><?php
                            woocommerce_wp_checkbox(array(
                                'id' => 'cashless_payment',
                                'label' => null,
                                'class' => 'form-field--checkbox',
                                'value' => $order->get_payment_method() == 'cod_card' ? 'yes' : 'no'
                            ));
                            ?>
                        </td>
                    </tr>
                <?php endif; endif;
                if (($shiptor_method != null) && false === strstr($shiptor_method['category'], '-to-')) : ?>
                    <tr>
                        <td><p class="form-field">
                                <strong><?php esc_html_e('Do not collect the parcel (only fullfilment)', 'woocommerce-shiptor'); ?></strong>
                            </p></td>
                        <td><?php
                            woocommerce_wp_checkbox(array(
                                'id' => 'no_gather',
                                'label' => null,
                                'class' => 'form-field--checkbox',
                            ));
                            ?></td>
                    </tr>
                    <?php if ($order->get_billing_country() === 'RU' && in_array($order->get_payment_method(), array('cod', 'cod_card'))) : ?>
                        <tr>
                            <td><p class="form-field">
                                    <strong><?php esc_html_e('Payment by card', 'woocommerce-shiptor'); ?></strong></p></td>
                            <td><?php
                                woocommerce_wp_checkbox(array(
                                    'id' => 'cashless_payment',
                                    'label' => null,
                                    'class' => 'form-field--checkbox',
                                    'value' => $order->get_payment_method() == 'cod_card' ? 'yes' : 'no'
                                ));
                                ?></td>
                        </tr>
                    <?php endif;
                endif;
                ?>
                </tbody>
            </table>
        </div>
        <div class="clear"></div>
        <div class="action">
            <button type="submit"
                    class="button button-primary"><?php esc_html_e('Create order', 'woocommerce-shiptor'); ?></button>
        </div>
    </form>
</div>