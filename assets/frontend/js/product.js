(function ($, shipping_params, wp) {
    $(function () {
        var $container = $('.shiptor-calculate-product__result'),
                $template = wp.template('shiptor-calculate-product'),
                $input = $(':input[name=calculate-city]'),
                i18n = {
                    'language': {
                        errorLoading: function () {
                            return shipping_params.i18n.searching;
                        },
                        inputTooLong: function (args) {
                            var overChars = args.input.length - args.maximum;

                            if (1 === overChars) {
                                return shipping_params.i18n.input_too_long_1;
                            }

                            return shipping_params.i18n.input_too_long_n.replace('%qty%', overChars);
                        },
                        inputTooShort: function (args) {
                            var remainingChars = args.minimum - args.input.length;

                            if (1 === remainingChars) {
                                return shipping_params.i18n.input_too_short_1;
                            }

                            return shipping_params.i18n.input_too_short_n.replace('%qty%', remainingChars);
                        },
                        loadingMore: function () {
                            return shipping_params.i18n.load_more;
                        },
                        maximumSelected: function (args) {
                            if (args.maximum === 1) {
                                return shipping_params.i18n.selection_too_long_1;
                            }

                            return shipping_params.i18n.selection_too_long_n.replace('%qty%', args.maximum);
                        },
                        noResults: function () {
                            return shipping_params.i18n.no_matches;
                        },
                        searching: function () {
                            return shipping_params.i18n.searching;
                        }
                    }
                },
                ShippingMethods = Backbone.Model.extend({
                    changes: {},
                    save: function () {
                        var changes = this.changedAttributes();
                        var qty = $(".input-text.qty").val();
                        if (_.size(changes) && changes.location) {
                            var self = this;
                            Backbone.ajax({
                                url: wp.ajax.settings.url,
                                method: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'shiptor_get_shipping_methods',
                                    params: changes.location,
                                    post_id: shipping_params.post_id,
                                    security: shipping_params.nonce,
                                    qty: qty
                                },
                                success: self.onSaveResponse
                            });
                        }
                    },
                    onSaveResponse: function (response) {
                        if (response.success) {
                            shippingMethods.set('methods', response.data.methods);
                            shippingMethods.trigger('change:methods');
                        }
                        shippingMethodsView.unblock();
                    },
                    getCity: function (params, success, failure) {
                        Backbone.ajax({
                            url: shipping_params.ajax_url,
                            method: 'GET',
                            dataType: 'json',
                            data: {
                                action: 'shiptor_autofill_address',
                                city_name: params.data.term
                            },
                            success: function (response) {
                                if (response.success) {
                                    success(response.data);
                                } else if (response.data.message) {
                                    failure(response.data.message);
                                }
                            },
                            error: function (error) {
                                failure(error);
                            }
                        });

                    },
                    processResults: function (response) {
                        var terms = [];

                        $.each(response, function (id, item) {
                            terms.push({
                                id: item.kladr_id,
                                state: item.state,
                                country: item.country,
                                text: item.city_name + ' (' + item.state + ')'
                            });
                        });

                        return {results: terms};
                    }
                }),
                ShippingMethodsView = Backbone.View.extend({
                    template: $template,
                    initialize: function () {
                        this.listenTo(this.model, 'change:location', this.onChangeLocation);
                        this.listenTo(this.model, 'change:methods', this.render);
                        this.listenTo(this.model, 'change:methods', this.onChangeMethods);
                        $(document.body).on('wc-enhanced-select-init', {view: this}, this.onEnhancedInit);
                        $input.on('select2:selecting', {view: this}, this.onSelecting);
                    },
                    block: function () {
                        $(this.el).block({
                            message: null,
                            overlayCSS: {
                                background: '#fff',
                                opacity: 0.6
                            }
                        });
                    },
                    unblock: function () {
                        $(this.el).unblock();
                    },
                    render: function () {
                        $(document.body).trigger('wc-enhanced-select-init');
                        var methods = _.indexBy(this.model.get('methods'), 'term_id'),
                                view = this;

                        this.$el.empty();
                        this.unblock();

                        if (_.size(methods)) {
                            methods = _.sortBy(methods, function (shipping_class) {
                                return shipping_class.price;
                            });

                            $.each(methods, function (id, rowData) {
                                view.$el.append(view.template(rowData));
                            });

                            this.simpleBar = new SimpleBar(view.el);
                        }
                    },
                    onSelecting: function (event) {
                        shippingMethods.set('location', event.params.args.data);
                        event.data.view.model.save();
                    },
                    onChangeMethods: function () {
                        if(this.simpleBar){
                            this.simpleBar.recalculate();
                        }
                    },
                    onChangeLocation: function () {
                        this.block();
                    },
                    onEnhancedInit: function (event) {

                        $input.filter(':not(.enhanced)').each(function () {
                            var select2_args = $.extend({
                                minimumInputLength: 2,
                                placeholder: shipping_params.i18n.choose_city_text,
                                escapeMarkup: function (markup) {
                                    return markup;
                                },
                                ajax: {
                                    transport: event.data.view.model.getCity,
                                    processResults: event.data.view.model.processResults,
                                    cache: true
                                }
                            }, i18n);

                            $(this).selectWoo(select2_args).addClass('enhanced');
                        });
                    }
                }),
                shippingMethods = new ShippingMethods({
                    methods: shipping_params.methods,
                    location: shipping_params.location
                }),
                shippingMethodsView = new ShippingMethodsView({
                    model: shippingMethods,
                    el: $container
                });

        shippingMethodsView.render();
    });
})(window.jQuery, shiptor_product_shipping, wp);