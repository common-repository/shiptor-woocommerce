var data = {};
jQuery(document).ready(function ($) {

    variationPrice = '';

    if (shiptor_product_shipping.is_variable_product == 1) {

        //If no option selected, hide the other fields
        $("input.input-text.qty").attr("disabled", true);
        $(".simplebar-content").hide();
        $(".shiptor-calculate-product").hide();

        $(".variations_form").on("woocommerce_variation_select_change", function () {

            //If  selected option is cleared, also hide other fields
            if ($(".variations select option:selected").val() == '') {

                $("input.input-text.qty").attr("disabled", true);
                $(".simplebar-content").hide();
                $("button#update_shipping_info").hide();
                $("input.input-text.qty").val('1');
                $(".shiptor-calculate-product").hide();
            }
        });

        $(".single_variation_wrap").on("show_variation", function (event, variation) {
            $("input.input-text.qty").attr("disabled", false);
            $(".shiptor-calculate-product").show();

            variationPrice = variation.display_price;

            var qty = $("input.input-text.qty").val();

            $(".simplebar-content").addClass("processing").block({
                message: null,
                overlayCSS: {
                    background: '#ffffff',
                    opacity: 0.6
                }
            });

            var variationData = {
                action: 'catch_check_box_val',
                varPrice: variationPrice,
                postId: shiptor_product_shipping.post_id,
                qty: qty
            };

            $.when(ajaxRequest(shiptor_product_shipping.myajax.url, 'POST', variationData)).then(
                    function (res) {
                        var text = shiptor_product_shipping.i18n.no_shipping_methods_text;
                        successCallback(res, text, '.simplebar-content');
                    },
                    function (error) {
                        console.log(error.statusText);
                    }
            );
        });

    }


    //refresh button click functionality
    $("button#update_shipping_info").hide();

    $("div.quantity").on("change", "input.qty", function () {

        $("button#update_shipping_info").show();
        var qty = $(".input-text.qty").val();

        data = {
            action: 'shipping_methods_depend_product_count',
            qty: qty,
            postId: shiptor_product_shipping.post_id
        };
    });
    $("button#update_shipping_info").on("click", function (e) {
        e.preventDefault();

        $(".simplebar-content").addClass("processing").block({
            message: null,
            overlayCSS: {
                background: '#ffffff',
                opacity: 0.6
            }
        });

        $.when(
                ajaxRequest(shiptor_product_shipping.myajax.url, 'POST', data)
                ).then(
                function (res) {
                    var text = shiptor_product_shipping.i18n.no_shipping_methods_text;
                    successCallback(res, text, '.simplebar-content');
                },
                function (error) {
                    console.log(error.statusText);
                }
        );
    });


    //ajax call and success function as a separate functions
    function successCallback(res, text, updatedContent) {
        var responseData = JSON.parse(res);
        $(updatedContent).html('');
        if (typeof responseData.shipping !== 'undefined' && responseData.shipping.length > 0) {

            $.each(responseData, function (key, val) {

                $.each(val, function (k, v) {
                    var days = v.days ? v.days : "";
                    var shipping_data =
                            "<li class='shiptor-calculate-product__elem' data-id='" + v.term_id + "'>" +
                            "<div>" +
                            "<span class='shiptor-method-name'>" + v.name + "</span>" +
                            "<span class='shiptor-method-cost'>" + v.cost + " <small>" + days + "</small></span>" +
                            "</div>" +
                            "</li>";
                    $(updatedContent).append(shipping_data);

                });
            });
        } else {
            $(updatedContent).append("<p><b>" + text + "</b></p>");
        }
        $(updatedContent).removeClass('processing').unblock().show();
    }

    function ajaxRequest(url, type, data) {
        return $.ajax({
            url: url,
            type: type,
            data: data,
        });
    }

});
