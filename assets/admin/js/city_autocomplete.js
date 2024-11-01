function city_autocomplete() {
    jQuery('#city_origin, #woocommerce_shiptor-integration_city_origin, #woocommerce_shiptor-cdek_sender_city, #woocommerce_shiptor-dpd_sender_city').select2({
        placeholder: 'Выберите город',
        minimumInputLength: 2,
        multiple: false,
        ajax: {
            url: wc_shiptor_admin_params.ajax_url.toString().replace('%%endpoint%%', 'shiptor_autofill_address'),
            method: 'GET',
            dataType: "json",
            delay: 350,
            data: function (params) {
                return {
                    city_name: params.term,
                    country: wc_shiptor_admin_params.country_iso
                }
            },
            processResults: function (data) {
                if (data.success) {
                    return {
                        results: jQuery.map(data.data, function (item) {
                            if (!item || item.country == null || item.country !== wc_shiptor_admin_params.country_iso)
                                return;
                            return {
                                id: item.kladr_id,
                                text: item.city_name + ' (' + item.state + ')'
                            }
                        })
                    }
                }
            },
            cache: true
        },
        current: function (element, callback) {
            callback({
                'id': element.val(),
                'text': element.text()
            });
        },
        formatSelection: function (data) {
            return data.text;
        },
        language: {
            errorLoading: function () {
                return wc_enhanced_select_params.i18n_searching;
            },
            inputTooLong: function (args) {
                var overChars = args.input.length - args.maximum;

                if (1 === overChars) {
                    return wc_enhanced_select_params.i18n_input_too_long_1;
                }

                return wc_enhanced_select_params.i18n_input_too_long_n.replace('%qty%', overChars);
            },
            inputTooShort: function (args) {
                var remainingChars = args.minimum - args.input.length;

                if (1 === remainingChars) {
                    return wc_enhanced_select_params.i18n_input_too_short_1;
                }

                return wc_enhanced_select_params.i18n_input_too_short_n.replace('%qty%', remainingChars);
            },
            loadingMore: function () {
                return wc_enhanced_select_params.i18n_load_more;
            },
            maximumSelected: function (args) {
                if (args.maximum === 1) {
                    return wc_enhanced_select_params.i18n_selection_too_long_1;
                }

                return wc_enhanced_select_params.i18n_selection_too_long_n.replace('%qty%', args.maximum);
            },
            noResults: function () {
                return wc_enhanced_select_params.i18n_no_matches;
            },
            searching: function () {
                return wc_enhanced_select_params.i18n_searching;
            }
        }
    });

}