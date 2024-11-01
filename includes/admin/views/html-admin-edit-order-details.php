<div class="jquery-modal">
    <div class="jquery-modal-content">
        <form id="js_edit_order_details">
        <table style="width: 100%" class="edit_order_details">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
            <tbody>
                <tr>
                    <td><p class="form-field"><strong><?php esc_html_e('Method name: ', 'woocommerce-shiptor'); ?></strong></p></td>
                    <td>
                        <p class="form-field">
                        <select class="select wc-enhanced-select js_shipping_method" name="shipping_method">
                            <?php foreach ( (array) $shipping_methods as $option_key => $option_value ) : ?>
                                <option
                                    data-category="<?php echo esc_attr( isset($option_value->data['category']) ? $option_value->data['category'] : '' ); ?>"
                                    value="<?php echo esc_attr( $option_key ); ?>"
                                    <?php
                                    if ( !empty($shiptor_method['id']) ) {
                                        selected( (string) $option_key, esc_attr( $shiptor_method['id'] ) );
                                    }
                                    ?>>
                                    <?php echo esc_attr( $option_value->title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('City: ', 'woocommerce-shiptor'); ?></strong></td>
                    <td>
                        <select id="city_origin" class="select wc-enhanced-select js_city_kladr" name="kladr_id">
                            <option value="<?php echo esc_attr($order->get_meta('_billing_kladr_id')) ?>"><?php echo esc_attr($order->get_billing_city()) ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="js_by_pvz <?php echo esc_attr($by_courier ? 'hidden' : ''); ?>">
                    <td><p class="form-field"><strong><?php esc_html_e('Delivery point: ', 'woocommerce-shiptor'); ?></strong></p></td>
                    <td>
                        <?php
                        $_chosen_delivery_point = $order->get_meta('_chosen_delivery_point');
                        $point = array();
                        if (is_array($_chosen_delivery_point)) {
                            $point = array_filter($delivery_points, function($item) use ($_chosen_delivery_point) {
                                return $item['id'] == $_chosen_delivery_point;
                            });
                            $point = reset($point);
                        }
                        ?>
                        <p class="form-field">
                            <input
                                id="sender_address"
                                type="text"
                                name="sender_address"
                                class="input-text js_sender_address"
                                readonly
                                <?php echo esc_attr($by_courier ? 'disabled' : ''); ?>
                            />

                            <input
                                id="chosen_delivery_point"
                                name="chosen_delivery_point"
                                type="hidden"
                                class="js_sender_address_id"
                                value="<?php echo esc_attr($_chosen_delivery_point); ?>"
                                <?php echo esc_attr($by_courier ? 'disabled' : ''); ?>
                            />
                        </p>

                        <button
                            type="button"
                            class="button-primary js_ajax_load_points"
                            data-type="order"
                            data-id="<?php echo (!empty($shiptor_method['id'])) ? esc_attr( $shiptor_method['id'] ) : ''; ?>">
                            <?php esc_html_e('Select', 'woocommerce-shiptor') ?>
                        </button>
                    </td>
                </tr>


                <tr class="js_by_courier <?php echo esc_attr(!$by_courier ? 'hidden' : ''); ?>">
                    <td><p class="form-field"><strong><?php esc_html_e('Address: ', 'woocommerce-shiptor'); ?></strong></p></td>
                    <td>
                        <p class="form-field address_line">
                            <input
                                type="text"
                                id="address_line"
                                name="address_line"
                                value="<?php echo esc_attr($order->get_billing_address_1()); ?>"
                                class="input-text js_address_line"
                                <?php echo esc_attr(!$by_courier ? 'disabled' : ''); ?>
                            />
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <button class="button-primary" type="submit"><?php esc_html_e('Save', 'woocommerce-shiptor');?></button>
        </form>
    </div>
</div>