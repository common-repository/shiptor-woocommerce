(function ($) {

    window.sender_address = $('.js_sender_address:visible');

    function ShiptorEditOrderDetails() {
        this.modal;

        this.init = () => {
            $(document.body).on('submit', '#js_edit_order_details', this.submit_order_details);
            $(document.body).on('click', '.shiptor_edit_address', this.shiptor_edit_address);
            $(document.body).on('change', '.js_shipping_method', this.change_shipping_method);
        }

        this.change_shipping_method = (event) => {
            var value = event.target.value;
            var categories = [
                'to-door',
                'post-office',
                'door-to-door',
                'delivery-point-to-door'
            ];
            var current = $(event.target).find(':selected').data('category');
            var by_courier = categories.indexOf(current) != -1 ? true : false;

            if (by_courier) {
                $('.js_by_pvz').addClass('hidden');
                $('.js_by_courier').removeClass('hidden');
                $('.js_sender_address, .js_sender_address_id').attr('disabled', true);
                $('.js_address_line').attr('disabled', false);
            } else {
                $('.js_by_pvz').removeClass('hidden');
                $('.js_by_courier').addClass('hidden');
                $('.js_sender_address, .js_sender_address_id').attr('disabled', false);
                $('.js_address_line').attr('disabled', true);
            }

            $('.js_ajax_load_points:visible').attr('data-id', value);
            $('.js_sender_address:visible').val('');
        }

        this.shiptor_edit_address = (event) => {
            event.preventDefault();

            var order_id = $(event.target).data('order_id');
            var type = $(event.target).data('type');

            $.ajax({
                url: wc_shiptor_admin_params.admin_url.toString(),
                data: {
                    action: 'shiptor_get_order_info',
                    order_id,
                    type,
                },
                success: (response) => {
                    this.modal = $(response.data.html);

                    this.modal.jmodal({
                        closeExisting: false // nesting modals
                    });
                    window.sender_address = $('.js_sender_address:visible');

                    city_autocomplete();

                    $('.js_shipping_method').trigger('change');
                    $(this.modal).on('modal:close', function() {
                        $(this).remove()
                    })
                }
            })
        }

        this.submit_order_details = (event) => {
            event.preventDefault();

            var data = $(event.target).serializeArray();
            data.push({
                name: 'action',
                value: 'shiptor_update_order_info',
            })
            data.push({
                name: 'nonce',
                value: shiptor_order_params.nonces.edit_order
            })

            $.ajax({
                url: wc_shiptor_admin_params.admin_url.toString(),
                method: 'post',
                data: data,
                success: (response) => {
                    try {
                        if (!response.success) {
                            return alert(response.data.message);
                        }

                        // close active modal
                        $.jmodal.close();
                        location.reload();
                    } catch(e) {
                        location.reload();
                    }
                }
            })
        }
    }

    var details_edit = new ShiptorEditOrderDetails();
    details_edit.init();


    $(document.body).on('click', '.js_ajax_load_points', function(event) {
        event.preventDefault();

        var btn = $(this);
        var shipping_method = $(this).attr('data-id');
        var order_id = $(this).data('type') == 'order' ? $('input[name="order_id"]').val() : 0;

        if ($(`.js_city_kladr:visible`).length == 1) {
            var kladr_id = $(`.js_city_kladr:visible`).val();
            window.sender_address = $('.js_sender_address:visible');
        } else {
            var kladr_id = $(this).closest('table').find(`.js_city_kladr`).val();
            window.sender_address = $(this).closest('table').find('.js_sender_address');
        }

        $.ajax({
            url: wc_shiptor_admin_params.ajax_url.toString().replace('%%endpoint%%', 'get_delivery_points'),
            data: {
                shipping_method,
                kladr_id,
                order_id,
            },
            beforeSend: function() {
                btn.prop('disabled', true);
            },
            success: function(response) {
                if (!response.success) {
                    return alert(response.data.message);
                }

                var points = response.data;
                initMap(points);
            },
            complete: function() {
                btn.prop('disabled', false);
            },
            cache: true,
        });
    });


    $(document.body).on('change', '.js_city_kladr', function(event) {
        $(window.sender_address).val('');
        $('.js_sender_address_id').val('');
        $('.js_ajax_load_points:visible').prop('disabled', false);
    });

    $(document.body).on('ready', disableButton);
    $(document.body).on('tab_changed', disableButton);
    function disableButton() {
        if (!$(`.js_city_kladr:visible`).val()) {
            $('.js_ajax_load_points:visible').prop('disabled', true);
        }
    }

    function initMap(points) {
        if(window.shiptorPickpointsMap){
            window.shiptorPickpointsMap.closeModal();
        }

        window.shiptorPickpointsMap = new ShiptorPickpointsMap;
        window.shiptorPickpointsMap.open(points);
    }

    function ShiptorPickpointsMap() {
        this.points = [];
        this.currentPoint = null;
        this.modal_tmpl = '#tmpl-wc-shiptor-pickpoints-map';

        this.open = function(points) {
            this.points = points;
            this.modal = $( $(this.modal_tmpl).html() );
            this.init();
            this.renderMap();
        },

        this.init = function (){
            var _this = this;

            $(this.modal).on('click', '.delivery-point', function(event){
                event.preventDefault();

                $pointId = $(this).attr('data-point-id');
                _this.setCurrentPoint($pointId);
            });

            $(this.modal).on('click', '.wc-shiptor-jquery-save', function(event){
                event.preventDefault();

                $pointId = $(this).attr('data-point-id');
                _this.saveCurrentPoint();
            });

            $(this.modal).on('click', '.wc-shiptor-jquery-view-switcher', function(event){
                event.preventDefault();

                var role = $(this).attr('role');
                var otherSwitchers = $(this).siblings();

                $(this).addClass('hidden');
                otherSwitchers.removeClass('hidden');

                var targetSelector = `.pickpoints-column.pickpoints-${role}`;
                var target = $(_this.modal).find(targetSelector);
                target.attr('mobile-view', 'show');
                target.siblings().attr('mobile-view', 'hidden');

                if (role == 'map') {
                    if ( !_this.map.getZoom() ) {
                        _this.map.setZoom(10);
                    }
                }
            });
        }

        this.renderMap = function(){
            var _this = this;

            if(this.points && this.points.length > 0){
                this.modal.jmodal({
                    closeExisting: false // nesting modals
                });
                this.points.forEach(function(point, i){
                    _this.addPointToList(point, i);
                });

                ymaps.ready(function () {
                    var map_container = 'delivery_points'
                    let config = {
                        center: _this.centerCoords(_this.points),
                        zoom: 10,
                        behaviors: ['default', 'scrollZoom'],
                        controls: ['zoomControl', 'fullscreenControl',],
                    }
                        
                    if( wc_shiptor_admin_params["ym_apikey"] != "" ) {
                        config.controls.push('searchControl')
                    }
                    _this.map  = new ymaps.Map(map_container, config ,{
                        suppressMapOpenBlock: true
                    });

                    ButtonLayout = ymaps.templateLayoutFactory.createClass([
                        '<button disabled title="{{ data.title }}" class="wc-shiptor-jquery-save_full button-primary">',
                            '<span class="my-button__text">{{ data.content }}</span>',
                        '</button>'
                    ].join('')),

                    firstButton = new ymaps.control.Button({
                        data: {
                            title: "Сохранить пункт выдачи",
                            content: "Сохранить пункт выдачи",
                        },
                        options: {
                            layout: ButtonLayout,
                            maxWidth: [170, 190, 220]
                        }
                    });
                    firstButton.events.add('click', function(event) {
                        _this.map.container.exitFullscreen();
                        $('.wc-shiptor-jquery-save').click();
                    });
                    _this.map.controls.add(firstButton);

                    _this.points.forEach(function(point, i){
                        _this.addPointToMap(point, i);
                    });

                    if (typeof (_this.map.geoObjects.getLength()) != 'undefined' && _this.map.geoObjects.getLength() > 1) {
                        _this.map.setBounds(_this.map.geoObjects.getBounds());
                    } else {
                        _this.map.setCenter(_this.map.geoObjects.get(0).geometry.getCoordinates());
                    }
                });
            }
        },

        this.centerCoords = function(points){
            var center = {lat: null, lon: null},
                current = {lat: null, lon: null},
                diminished = 0;
            for (var i = 0; i < points; i++) {
                current.lat = parseFloat(points[i].gps_location.latitude);
                current.lon = parseFloat(points[i].gps_location.longitude);
                if (isNaN(current.lat) || isNaN(current.lon)) {
                    diminished++;
                } else {
                    center.lat += current.lat;
                    center.lon += current.lon;
                }
            }
            center.lat = parseFloat(center.lat / (points.length - diminished));
            center.lon = parseFloat(center.lon / (points.length - diminished));
            return [center.lat, center.lon];
        }

        this.addPointToMap = function(point, i){
            var _this = this;
            var placemark = new ymaps.Placemark([point.gps_location.latitude, point.gps_location.longitude], {
                balloonContentBody: [
                    '<strong>[' + (point.name ? point.name.toUpperCase() : point.courier.toUpperCase()) + ']</strong>',
                    '<address>' + point.address + '</address>',
                ].join(''),
                shiptorElemValue: point.id,
                shiptorElemIndex: i
            }, {
                preset: "islands#blueCircleDotIcon",
                iconColor: '#2b7788',
                balloonCloseButton: true,
                hideIconOnBalloonOpen: false
            });

            placemark.events.add('click', function (event) {
                _this.setCurrentPoint(point.id);
            })

            _this.map.geoObjects.add(placemark);
        };

        this.changePointMarker = function(point){
            var _this = this;

            var pointPlacemark = null;
            _this.map.geoObjects.each(function(placemark){
                if(placemark.properties.get('shiptorElemValue') == point.id){
                    placemark.options.set("preset", "default#truckIcon");
                    placemark.options.set("iconColor", "#ba0022");
                    pointPlacemark = placemark;
                } else {
                    placemark.options.set("preset", "islands#blueCircleDotIcon");
                    placemark.options.set("iconColor", "#2b7788");
                }
            });

            if(pointPlacemark){
                _this.map.setCenter(pointPlacemark.geometry.getCoordinates(), 14, {
                    duration: 500,
                    checkZoomRange: true
                });
            }
        }

        this.addPointToList = function(point, i){
            var pointHtml = this.getPointHtml(point);
            var $pointElem = $(pointHtml);
            this.modal.find('.pickpoints-list').append($pointElem);
        };

        this.getPointHtml = function(point){
            var pointHtml = '';

            pointHtml += '<div class="delivery-point" data-point-id="'+point.id+'">';
            pointHtml +=    '<div class="delivery-point-address">' + point.address + '</div>';
            pointHtml +=    '<div class="delivery-point-description">';
            pointHtml +=        '<div class="delivery-point-schedule">Режим работы:' + point.work_schedule + '</div>';
            pointHtml +=        '<div class="delivery-point-trip-description">' + point.trip_description + '</div>';
            pointHtml +=        '<div class="delivery-point-payment">';
            pointHtml +=            '<div class="delivery-point-paiment-cod">Наложенный платёж: ';
            pointHtml +=                point.cod ? '<span style="color:green;">Есть</span>' : '<span style="color:red;">Нет</span>';
            pointHtml +=            '</div>';
            pointHtml +=            '<div class="delivery-point-paiment-card">Оплата картой: ';
            pointHtml +=                point.card ? '<span style="color:green;">Есть</span>' : '<span style="color:red;">Нет</span>';
            pointHtml +=            '</div>';
            pointHtml +=        '</div>';
            pointHtml +=    '</div>';
            pointHtml += '</div>';

            return pointHtml;
        }

        this.setCurrentPoint = function($pointId){
            var _this = this;

            this.points.forEach(function(point){
                if(point.id == $pointId){
                    _this.currentPoint = point;
                }
            })

            _this.checkCurrentPoint();

            if(_this.currentPoint){
                _this.setPointActive(_this.currentPoint);
                _this.changePointMarker(_this.currentPoint);
            }
        }

        this.setPointActive = function(point){
            var _this = this;

            var $point = _this.modal.find('.delivery-point[data-point-id="'+point.id+'"]');

            $point.siblings().removeClass('active');
            $point.addClass('active');
            var $parent = $point.parent();

            $parent[0].scrollTop = $point[0].offsetTop - 150;
        }

        this.checkCurrentPoint = function(){
            if(this.currentPoint){
                this.modal.find('.wc-shiptor-jquery-save').prop('disabled', false);
                $('.wc-shiptor-jquery-save_full').prop('disabled', false);
            } else {
                this.modal.find('.wc-shiptor-jquery-save').prop('disabled', true);
                $('.wc-shiptor-jquery-save_full').prop('disabled', true);
            }
        }

        this.saveCurrentPoint = function(){
            var _this = this;

            this.checkCurrentPoint();

            if (this.currentPoint) {
                $(window.sender_address).val(this.currentPoint.address);
                $('.js_sender_address_id').val(this.currentPoint.id);
                _this.closeModal();
            }
        }

        this.closeModal = function() {
            this.modal.find('.close-modal').click();
            this.modal.remove();
        }
    }

})(window.jQuery);