(function ($, options, woocommerce_admin) {

    var woocommerce_admin = woocommerce_admin || {};

    woocommerce_admin.empty_value = 'Это поле обязательно для заполнения';


    $(document.body).on('items_saved', reload);
    $(document.body).on('order-totals-recalculate-complete', reload);

    function reload() {
        location.reload();
    }

    $(document.body).on('click', '.shiptor-order-details button[type=submit]', function (event) {
        event.preventDefault();
        var $inputs = $('.shiptor-order-details :input[name]'),
                $has_error = false;

        $.each($inputs, function (i, input) {
            if ($(input).is('[required]') && '' == $(input).val()) {
                $(document.body).triggerHandler('wc_add_error_tip', [$(input), 'empty_value']);
                $has_error = true;
            }
        });

        if ($has_error) {
            return false;
        }

        $.ajax({
            url: wp.ajax.settings.url,
            method: 'POST',
            //dataType: 'json',
            data: {
                action: 'woocommerce_shiptor_create_order',
                security: options.nonces.cleate,
                data: $inputs.serialize()
            },
            beforeSend: function () {
                $('#wc-shiptor-result').empty();
                $('.shiptor-order-details').block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            }
        })
                .done(function (response) {
                    if (response && response.data && response.data.html) {
                        $('.shiptor-order-details').html(response.data.html)
                    } else if (response && response.data && response.data.error) {
                        $error = $('<div />', {
                            class: 'error-notice',
                            text: response.data.error
                        });
                        $('#wc-shiptor-result').html($error);
                    }
                    $('.shiptor-order-details').unblock();

                })
                .fail(function (err) {
                    console.log(err);
                });
    });


    $(document).ready(function () {

        if ($("input#sender_order_date.sender-order-date").length !== 0) {

            nonAvailableDays = [];
            $.ajax({
                url: wp.ajax.settings.url,
                method: 'POST',
                data: {
                    action: 'getWeekendsHolidays' // call to Shiptor API getDaysOff method to get non working days
                }

            })
                    .done(function (res) {
                        var obj = JSON.parse(res);
                        $.each(obj, function (i, field) {
                            nonAvailableDays.push(field);
                        });
                    })
                    .fail(function (err) {
                        console.log(err.message);
                    });

            $('.sender-order-date').datepicker({

                dateFormat: 'yy-mm-dd',
                numberOfMonths: 1,
                showButtonPanel: true,
                minDate: '+1D',
                maxDate: '+7D',

                beforeShowDay: function (date) {
                    var ymd = '';
                    var y = date.getFullYear();
                    var m = (date.getMonth() + 1);
                    var d = date.getDate();

                    if (m < 10) {
                        m = "0" + m;
                    }

                    if (d < 10) {
                        d = "0" + d;
                    }

                    ymd = y + "-" + m + "-" + d;

                    if ($.inArray(ymd, nonAvailableDays) !== -1) {
                        return [false, "", ""];
                    } else {
                        return [true, "", ""];
                    }
                }
            });
        }
    }); //end document ready

})(window.jQuery, shiptor_order_params, woocommerce_admin);