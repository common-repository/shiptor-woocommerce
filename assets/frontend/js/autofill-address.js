jQuery(function ($) {

    if (typeof wc_shiptor_autofill_address_params === 'undefined') {
        return false;
    }

    if ($().select2) {
        var select2_args = $.extend({
            placeholderOption: 'first',
            width: '100%',
            ajax: {
                url: wc_shiptor_autofill_address_params.url,
                method: 'GET',
                dataType: "json",
                data: function (params) {
                    return {
                        city_name: params.term,
                        country: $('#billing_country').val() || $('#shipping_country').val() || $('#calc_shipping_country').val()
                    }
                },
                processResults: function (data) {
                    var terms = [];

                    if (data.success && data.data) {
                        $.each(data.data, function (id, item) {
                            terms.push({
                                id: item.city_name + ' (' + item.state + ')',
                                kladr_id: item.kladr_id,
                                state: item.state,
                                city: item.city_name,
                                country: item.country,
                                text: item.city_name + ' (' + item.state + ')'
                            });
                        });
                    }

                    return {
                        results: terms
                    };
                },
                cache: false
            },
            tags: false,
            current: function (element, callback) {
                callback({
                    id: element.val(),
                    text: element.text()
                });
            },
            minimumInputLength: 2,
            language: {
                errorLoading: function () {
                    return wc_country_select_params.i18n_searching;
                },
                formatAjaxError: function () {
                    return wc_country_select_params.i18n_ajax_error;
                },
                inputTooLong: function (args) {
                    var overChars = args.input.length - args.maximum;

                    if (1 === overChars) {
                        return wc_country_select_params.i18n_input_too_long_1;
                    }

                    return wc_country_select_params.i18n_input_too_long_n.replace('%qty%', overChars);
                },
                inputTooShort: function (args) {
                    var remainingChars = args.minimum - args.input.length;

                    if (1 === remainingChars) {
                        return wc_country_select_params.i18n_input_too_short_1;
                    }

                    return wc_country_select_params.i18n_input_too_short_n.replace('%qty%', remainingChars);
                },
                noResults: function () {
                    return wc_country_select_params.i18n_no_matches;
                },
                searching: function () {
                    return wc_country_select_params.i18n_searching;
                }
            }
        }, {});

        var wc_city_select_select2 = function () {
            $('select.city_select').each(function () {
                $(this).select2(select2_args).on('select2:selecting', function (event) {
                    $('#billing_kladr_id, #shipping_kladr_id, #calc_shipping_kladr_id').val(event.params.args.data.kladr_id);
                    $('#billing_state, #shipping_state, #calc_shipping_state').val(event.params.args.data.state);
                    $('#billing_city, #shipping_city, #calc_shipping_city').val(event.params.args.data.city_name).trigger('change');
                    $('#billing_country, #shipping_country, #calc_shipping_country').val(event.params.args.data.country).trigger('change');
                    $('[name=calc_shipping]').prop('disabled', false);
                    $(document.body).trigger('update_checkout');
                });
            });
        };

        wc_city_select_select2();

        $(document.body).bind('city_to_select', function () {
            wc_city_select_select2();
        });

        $('body').on('country_to_state_changing', function (e, country, $container) {
            var $statebox = $container.find('#billing_state, #shipping_state, #calc_shipping_state');
            var state = $statebox.val();
            $(document.body).trigger('state_changing', [country, state, $container]);
        });

        $('body').on('change', 'select.state_select, #calc_shipping_state', function () {
            var $container = $(this).closest('div');
            var country = $container.find('#billing_country, #shipping_country, #calc_shipping_country').val();
            var state = $(this).val();

            $(document.body).trigger('state_changing', [country, state, $container]);
        });

        $('body').on('state_changing', function (e, country, state, $container) {
            var $citybox = $container.find('#billing_city, #shipping_city, #calc_shipping_city');
            var input_name = $citybox.attr('name');
            var input_id = $citybox.attr('id');
            var placeholder = $citybox.attr('placeholder') || 'Введите название населённого пункта.';
            var value = $citybox.val();

            if ($.inArray(country, ['RU', 'KZ', 'BY']) > -1 && $citybox.is('input')) {
                $citybox.replaceWith('<select name="' + input_name + '" id="' + input_id + '" class="city_select" placeholder="' + placeholder + '"></select>');
                $citybox = $('#' + input_id);
                $citybox.html('<option value="">' + value + '</option>');
                $citybox.after('<p class="hide hidden" id="calc_shipping_kladr_id_field"><input type="hidden" name="calc_shipping_kladr_id" id="calc_shipping_kladr_id" value="" /></p>');
                $('[name=calc_shipping]').prop('disabled', true);
                $(document.body).trigger('city_to_select');

            } else if ($.inArray(country, ['RU', 'KZ', 'BY']) == -1 && $citybox.is('select')) {
                $citybox.parent().find('.select2-container').remove();
                $citybox.replaceWith('<input type="text" class="input-text" name="' + input_name + '" id="' + input_id + '" placeholder="' + placeholder + '" />');
            }

        });

        if ($('.cart-collaterals').length && $('#calc_shipping_state').length) {
            var calc_observer = new MutationObserver(function () {
                $('#calc_shipping_state').change();
            });
            calc_observer.observe(document.querySelector('.cart-collaterals'), {childList: true});
        }
    }
});