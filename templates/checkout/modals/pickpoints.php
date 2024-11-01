<script type="text/template" id="tmpl-wc-shiptor-pickpoints-map" data-points="<?php echo htmlspecialchars($points);//esc_attr does not fit because it results in an error ?>">
    <div class="wc-shiptor-jquery-modal">
        <div class="wc-shiptor-jquery-modal-content">
            <section class="wc-shiptor-jquery-modal-main" role="main">
                <div class="wc-shiptor-jquery-modal-header">
                    <div class="wc-shiptor-jquery-modal-title"><?php esc_html_e( 'Choose pickpoint', 'woocommerce-shiptor' ); ?></div>
                </div>
                <div class="wc-shiptor-jquery-modal-body">
                    <div class="wc-shiptor-pickpoints">
                        <div class="pickpoints-column pickpoints-list" mobile-view="show">
                        </div>
                        <div class="pickpoints-column pickpoints-map" mobile-view="hidden">
                            <div id='delivery_points' class='shiptor-delivery-points'></div>
                        </div>
                    </div>
                </div>
                <div class="wc-shiptor-jquery-modal-footer">
                    <div class="inner">
                        <button disabled class="wc-shiptor-jquery-save"><?php esc_html_e('Save pickpoint', 'woocommerce-shiptor') ?></button>
                    </div>
                    <div class="inner inner-mobile">
                        <button role="map" class="wc-shiptor-jquery-view-switcher"><?php esc_html_e('Open map', 'woocommerce-shiptor') ?></button>
                        <button role="list" class="wc-shiptor-jquery-view-switcher hidden"><?php esc_html_e('Open list', 'woocommerce-shiptor') ?></button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</script>
