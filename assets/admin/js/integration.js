jQuery(function ($) {
    var WC_Shiptor_Integration_Admin = {

        init: function () {
            $(document.body).on('click', '#woocommerce_shiptor-integration_autofill_empty_database', this.empty_database);
            $(document.body).on('click', '#woocommerce_shiptor-integration_requests_caching_empty_database', this.empty_cached_requests);
            $(document.body).on('click', '#woocommerce-shiptor-shiptor-log-clear', this.empty_logs_requests);

            city_autocomplete();

            $(document.body).on('change', '#woocomerce-shiptor-log-analyzer-logs-select', function(event){
                event.preventDefault();
                $('#woocommerce-shiptor-shiptor-log-analyze').attr('data-url', $(this).val())
            });

            $(document.body).on('click', '.js_shiptor_tabs .nav-tab', this.change_tabs);
        },

        change_tabs: function(event) {
            event.preventDefault();

            $('.js_shiptor_tabs .nav-tab-active').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            var tab = '#' + ($(this).data('id'));
            $('.js_shiptor_tab').addClass('hidden');
            $(tab).removeClass('hidden');
        },

        empty_database: function () {
            if (!window.confirm(wc_shiptor_admin_params.i18n.confirm_message)) {
                return;
            }

            $('#mainform').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'shiptor_autofill_addresses_empty_addresses',
                    nonce: wc_shiptor_admin_params.empty_autofill_addresses_nonce
                },
                success: function (response) {
                    window.alert(response.data.message);
                    $('#mainform').unblock();
                }
            });
        },

        empty_cached_requests: function(){
            if (!window.confirm(wc_shiptor_admin_params.i18n.confirm_cached_requests_deletion_message)) {
                return;
            }

            $('#mainform').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'shiptor_clear_cache',
                    nonce: wc_shiptor_admin_params.empty_shiptor_cache_nonce
                },
                success: function (response) {
                    window.alert(response.data.message);
                    $('#mainform').unblock();
                }
            });
        },

        empty_logs_requests: function(event){
            event.preventDefault();

            if (!window.confirm(wc_shiptor_admin_params.i18n.confirm_logs_requests_deletion_message)) {
                return;
            }

            $('#mainform').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'shiptor_clear_logs',
                    nonce: wc_shiptor_admin_params.empty_shiptor_logs_nonce
                },
                success: function (response) {
                    window.alert(response.data.message);
                    $('.woocomerce-shiptor-log-analyzer').remove();
                    $('#mainform').unblock();
                }
            });
        }
    };

    WC_Shiptor_Integration_Admin.init();
});