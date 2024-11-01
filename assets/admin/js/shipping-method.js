(function ($) {
    var WC_Shiptor_Shipping_Method = {

        init: function () {
            $(document.body).on('click', '#synchronize_methods_with_api', this.synchronize_methods_with_api);

            if( ! $('.tabs-wrapper').length ){
                $('.submit').remove();
            }

            $(':input.city-ajax-load').filter(':not(.enhanced)').each(function (index, $select) {
                var $self = $($select);
                var select2_args = {
                    placeholder: wc_shiptor_admin_params.placeholder,
                    minimumInputLength: 2,
                    allowClear: false,
                    escapeMarkup: function (m) {
                        return m;
                    },
                    ajax: {
                        url: wc_shiptor_admin_params.ajax_url.toString().replace('%%endpoint%%', 'shiptor_autofill_address'),
                        method: 'GET',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                city_name: params.term,
                                country: wc_shiptor_admin_params.country_iso
                            };
                        },
                        processResults: function (data) {
                            var terms = [];
                            if (data.success) {
                                $.each(data.data, function (id, item) {
                                    if (item && item.country !== null && item.country == wc_shiptor_admin_params.country_iso) {
                                        terms.push({id: item.kladr_id, text: item.city_name + ' (' + item.state + ')'});
                                    }
                                });
                            }
                            return {
                                results: terms
                            };
                        },
                        cache: true
                    }
                };

                select2_args = $.extend(select2_args, getEnhancedSelectFormatString());

                $(this).selectWoo(select2_args).addClass('enhanced');
            });

            $('html').on('click', function (event) {
                if (this === event.target) {
                    $(':input.city-ajax-load').filter('.select2-hidden-accessible').selectWoo('close');
                }
            });

            $('select[id*="_allowed_group"]').on('change', function (e) {
                $selected = $(e.target).val() || [];
                $('input.group-titles-input').closest('tr').hide();
                $selected.map(function (value) {
                    $('input.group-titles-input').filter(function () {
                        return $(this).attr('data-group') === value;
                    }).closest('tr').show();

                });
            });

            $('.tabs-wrapper .tab > a').on('click', function(event){
                event.preventDefault();
                var current_tab = $(this);

                if( current_tab.parent().is('.active') ){
                    return;
                }

                current_tab.parent().siblings().removeClass('active');
                current_tab.parent().addClass('active');
                var target_id = current_tab.attr('href');

                var current_tab_pane = $(target_id);
                current_tab_pane.siblings().removeClass('active');
                current_tab_pane.addClass('active');

                wpCookies.set('shiptor_admin_shipping_method_tab_current', target_id, 3600);

                $(document.body).trigger('tab_changed');
            });

            this.may_be_init_tabs();

            $(document.body).on('change', '.shiptor_warehouse', this.change_warehouse);
        },

        change_warehouse: function() {
            var id = this.value;
            var option = $('.shiptor_warehouse_fulfillment:visible');

            if (id == 0) {
                option.prop('checked', true);
                option.prop('disabled', false);

                return;
            }

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'shiptor_warehouse_info',
                    nonce: wc_shiptor_admin_params.shiptor_warehouse_info_nonce,
                    id,
                },
                success: function(response) {
                    if (!response.success) {
                        return alert(response.data.message);
                    }

                    if (response.data.roles.includes('logistic') && response.data.roles.includes('fulfilment')) {
                        option.prop('disabled', false);
                    } else {
                        option.prop('checked', false);
                        option.prop('disabled', true);
                    }
                }
            })
        },

        may_be_init_tabs: function() {
            var tabs = $('.tabs-wrapper .tab');

            if (tabs.length !== 0) {

                if (!tabs.filter('.active')) {
                    tabs.first().click();
                }

                var active_tab = tabs.filter('.active');
                var $parent = active_tab.parent();
                $parent.scrollTop($parent.scrollTop() - $parent.offset().top + active_tab.offset().top);
            }
        },

        synchronize_methods_with_api: function(event) {
            event.preventDefault();

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
                    action: 'shiptor_synchronize_methods_with_api',
                    nonce: wc_shiptor_admin_params.synchronize_methods_with_api_nonce
                },
                success: function (response) {
                    if( response.data.message ) {
                        window.alert(response.data.message);
                    } else {
                        location.reload();
                    }
                },
                complete: function (response) {
                    $('#mainform').unblock();
                }
            });
        }
    }

    WC_Shiptor_Shipping_Method.init();

    function getEnhancedSelectFormatString() {
        return {
            'language': {
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
        };
    }

})(window.jQuery);