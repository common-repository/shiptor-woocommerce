(function ($) {

    function ShiptorPickpointsMap() {
        this.points = [];
        this.currentPoint = null;
        this.modal_tmpl = '#tmpl-wc-shiptor-pickpoints-map';

        this.open = function() {
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
            var payment_method = $('input[name="payment_method"]:checked').val()

            this.points = $(this.modal_tmpl).data('points');
            this.points = this.points.filter((point) => {
                if (payment_method != 'cod' && payment_method != 'cod_card') {
                    return true;
                }

                if (payment_method == 'cod' && point.cod) {
                    return true;
                }

                if (payment_method == 'cod_card' && point.card) {
                    return true;
                }

                return false;
            })

            if(this.points && this.points.length > 0){
                this.modal.jmodal();
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

                    if( shiptor_checkout_params["ym_apikey"] != "" ) {
                        config.controls.push('searchControl')
                    }
                    _this.map  = new ymaps.Map(map_container, config ,{
                        suppressMapOpenBlock: true
                    });

                    ButtonLayout = ymaps.templateLayoutFactory.createClass([
                        '<button disabled title="{{ data.title }}" class="wc-shiptor-jquery-save_full">',
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

        var cod_title = shiptor_checkout_params.cod_title;
        var cod_card_title = shiptor_checkout_params.cod_card_title;

        this.getPointHtml = function(point){
            var pointHtml = '';

            pointHtml += '<div class="delivery-point" data-point-id="'+point.id+'">';
            pointHtml +=    '<div class="delivery-point-address">' + point.address + '</div>';
            pointHtml +=    '<div class="delivery-point-description">';
            pointHtml +=        '<div class="delivery-point-schedule">Режим работы:' + point.work_schedule + '</div>';
            pointHtml +=        '<div class="delivery-point-trip-description">' + point.trip_description + '</div>';
            pointHtml +=        '<div class="delivery-point-payment">';
            pointHtml +=            '<div class="delivery-point-paiment-cod">'+cod_title+': ';
            pointHtml +=                point.cod ? '<span style="color:green;">Есть</span>' : '<span style="color:red;">Нет</span>';
            pointHtml +=            '</div>';
            pointHtml +=            '<div class="delivery-point-paiment-card">'+cod_card_title+': ';
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

            if(this.currentPoint){
                $("#billing_address_1").val(this.currentPoint.address);

                $.post(shiptor_checkout_params.delivery_point_url, {
                    'delivery_point': this.currentPoint.id
                }, function (data) {
                    $(document.body).trigger('update_checkout', {update_shipping_method: true});
                    _this.closeModal();
                });
            }
        }

        this.closeModal = function() {
            this.modal.find('.close-modal').click();
            this.modal.remove();
        }
    }

    $(document.body).on('click', '.wc-shiptor-delivery-point-selector', function (event) {
        event.preventDefault();

        if(window.shiptorPickpointsMap){
            window.shiptorPickpointsMap.closeModal();
        }

        window.shiptorPickpointsMap = new ShiptorPickpointsMap;
        window.shiptorPickpointsMap.open();
    });


    $(document.body).on('updated_checkout', function () {
        $('.shiptor-delivery-points').removeClass('processing').unblock();

        $('#billing_address_1').bind('keyup blur', function (e) {
            $('#billing_to_door_address').val(e.target.value);
        });
    });

    $(document.body).on('update_checkout', function () {
        $('.shiptor-delivery-points').addClass('processing').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    });

    $(document.body).on('payment_method_selected', function (e) {
        var payment_method = $('input[name="payment_method"]:checked').val()

        $(document.body).trigger('update_checkout', {update_shipping_method: true});

        if (window.shiptorPickpointsMap && window.shiptorPickpointsMap.currentPoint) {
            if (payment_method == 'cod' && !window.shiptorPickpointsMap.currentPoint.cod ||
                payment_method == 'cod_card' && !window.shiptorPickpointsMap.currentPoint.card) {

                $("#billing_address_1").val('');
                $.post(shiptor_checkout_params.delivery_point_url, {
                    'delivery_point': 0
                }, function (data) {
                    $(document.body).trigger('update_checkout', {update_shipping_method: true});
                });
            }
        }
    });

    $(document).ready(function () {
        $("#billing_postcode, #shipping_postcode").removeClass("input-text");
    });

})(window.jQuery);