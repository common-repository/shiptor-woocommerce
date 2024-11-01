(function () {
    if (typeof window.CustomEvent !== "function") {
        function CustomEvent(event, params) {
            params = params || {
                bubbles: false,
                cancelable: false,
                detail: undefined
            };
            var evt = document.createEvent('CustomEvent');
            evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
            return evt;
        }
        CustomEvent.prototype = window.Event.prototype;
        window.CustomEvent = CustomEvent;
    }
    if (!String.prototype.trim) {
        (function () {
            var rtrim = /^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g;
            String.prototype.trim = function () {
                return this.replace(rtrim, '');
            };
        })();
    } !window.addEventListener && function (e, t, n, r, i, s, o) {
        e[r] = t[r] = n[r] = function (e, t) {
            var n = this;
            o.unshift([n, e, t, function (e) {
                e.currentTarget = n, e.preventDefault = function () {
                    e.returnValue = !1
                }, e.stopPropagation = function () {
                    e.cancelBubble = !0
                }, e.target = e.srcElement || n, t.call(n, e)
            }]), this.attachEvent("on" + e, o[0][3])
        }, e[i] = t[i] = n[i] = function (e, t) {
            for (var n = 0, r; r = o[n]; ++n)
                if (r[0] == this && r[1] == e && r[2] == t) return this.detachEvent("on" + e, o.splice(n, 1)[0][3])
        }, e[s] = t[s] = n[s] = function (e) {
            return this.fireEvent("on" + e.type, e)
        }
    }(Window.prototype, HTMLDocument.prototype, Element.prototype, "addEventListener", "removeEventListener", "dispatchEvent", []);
})();
function JCShiptorWidgetLogging() {
    if (JCShiptorWidgetLogging.instance) {
        return JCShiptorWidgetLogging.instance;
    }
    JCShiptorWidgetLogging.instance = this;
    var XMLHTTPREQUEST_READY = 4,
        HTTP_SUCCESS = "200",
        VERSION = "0.0.3",
        LANG = {
            title: "Анализатор лог-файлов Shiptor",
            help: "Инструкция к анализатору",
            file_text: "В анализатор загружен файл",
            file_size: "размером",
            file_unit: "Кб",
            header: "Всего запросов к Shiptor:",
            header_include: "в том числе",
            header_errors: "с ошибками",
            date: "Время запроса",
            method: "Запрос",
            description: "Пояснение",
            error: "Ошибка",
            tooltip: "Показать/скрыть лог",
            more: "Загрузить eще",
            global_error: 'произошла ошибка',
            shiptor_terminal: 'Shiptor',
            no_head: 'Верстка страницы некорректна! Не найдена секция head.',
            filters: {
                calendar: "Найдены события за",
                errors: "Показывать только ошибки",
                scroll_down: "Развернуть фильтры",
                scroll_up: "Свернуть фильтры",
                set_all: "Выбрать все"
            },
            methods: {
                // SHIPTOR PRIVATE METHODS
                "confirmShipment": "Подтверждение отгрузки",
                // SHIPTOR PUBLIC METHODS
                "calculatePickUp": "Расчет стоимости забора",
                "calculateShipping": "Расчет стоимости доставки",
                "getCountries": "Получение списка стран и их кодов",
                "getDaysOff": "Получение списка нерабочих дней",
                "getDeliveryPoints": "Получение списка ПВЗ",
                "getSettlements": "Получение справочника населенных пунктов",
                "getTracking": "Отслеживание посылки",
                "suggestSettlement": "Получение населенного пункта по части названия",
                // SHIPTOR SHIPING METHODS
                "addPackage (Export)": "Добавление посылки экспорт",
                "addPackage": "Добавление посылки",
                "addPackages (courier)": "Добавить несколько пакетов c забором курьерской службы",
                "addPackages (delivery-point)": "Добавить несколько пакетов c отгрузкой в терминал курьерской службы",
                "addPackages (standard)": "Добавить несколько пакетов",
                "addPackages (standard-pick-up)": "Добавить несколько пакетов и повязать к одному забору",
                "addPickUp": "Оформление забора груза со склада",
                "addPickUpShipment": "Оформление забора груза от поставщика",
                "addProduct": "Добавление товара",
                "addProvider": "Добавление нового поставщика",
                "addService": "Добавление услуги",
                "addShipment": "Добавление поставки",
                "addWarehouse": "Добавление нового склада",
                "calculateShipping": "Расчет стоимости доставки",
                "calculateShippingInternational": "Расчет стоимости доставки экспорт",
                "cancelPickUp": "Отмена забора груза",
                "deleteProduct": "Удаление товара",
                "deleteService": "Удаление услуги",
                "editPackage (Export)": "Изменение посылки экспорт",
                "editPackage": "Изменение посылки",
                "editPickUp": "Изменение забора груза со склада",
                "editProduct": "Редактирование товара",
                "editShipment (courier)": "Изменить отгрузку",
                "editShipment (delivery-point)": "Изменить отгрузку",
                "getCountries": "Получение списка стран и их кодов",
                "getCourierPickUpTime": "Получение списка интервалов забора по курьеру",
                "getDeliveryPoints": "Получения списка ПВЗ",
                "getDeliveryTime": "Получение списка интервалов доставки",
                "getPackage": "Получение статуса посылки",
                "getPackagesStatuses": "Получение списка статусов посылок",
                "getPackages": "Получение списка посылок",
                "getPackagesCount": "Получение колличества посылок",
                "getPickUp": "Получение информации о заборе груза",
                "getPickUpTime": "Получение списка интервалов забора",
                "getProducts": "Получение товаров",
                "getProvider": "Получение поставщика по номеру",
                "getProviders": "Получение списка поставщиков",
                "getServices": "Получение списка услуг",
                "getSettlements": "Получение справочника населенных пунктов",
                "getShipment": "Получить поставку",
                "getShipments": "Получить список поставок",
                "getShippingMethods": "Получение справочника способов доставки",
                "getStocks": "Получение списка доступных складов",
                "getWarehouse": "Получение склада по номеру",
                "getWarehouses": "Получение списка складов",
                "prolongLifetimePackage": "Продлить время жизни посылки",
                "recoverPackage": "Восстановление посылки",
                "recoverProduct": "Восстановление товара",
                "recoverService": "Восстановление услуги",
                "recoverShipment": "Восстановление поставки",
                "removePackage": "Удаление посылки",
                "removeProvider": "Удаление поставщика",
                "removeShipment": "Удалить поставку",
                "removeWarehouse": "Удаление склада",
                "revertProvider": "Восстановление поставщика из удалённых",
                "revertWarehouse": "Восстановление склада из удалённых",
                "searchPackages": "Поиск посылок",
                "suggestSettlement": "Получение населенного пункта по части названия",
                //  CHECKOUT METHODS
                "getDeliveryPoints": "Получение списка ПВЗ для конкретной курьерской службы",
                "suggestSettlement": "Получение списка НП, подходящих под поисковый запрос",
                "simpleSuggestSettlement": "Получение списка НП, упрощенное",
                "calculateShipping": "Расчет доставки",
                "simpleCalculate": "Расчет доставки с учетом настроек из оболочки КЧ",
                "getWare": "Получение информации о товаре по его артикулу",
                "addOrder": "Добавление заказа",
                "setProduct": "Добавляет или обновляет информацию о товаре"
            },
            errors: {
                "InvalidDimension": "Габариты должны быть числом больше нуля или null",
                "InvalidDeclaredCost": "Неправильная объявленная стоимость: должно быть указано число большее или равное нулю",
                "InvalidKladrId": "Неверный формат Кладр - он должен быть пустым или содержать только цифры",
                "InvalidCountryCode": 'Неверный формат кода страны.Допустимые значения: "RU", "KZ", "BY"',
                "InvalidCourierName": 'Неверный строковый идентификатор службы доставки. Допустимые значения: "shiptor", "pickpoint" , "boxberry", "dpd", "iml", "russian - post", "cdek", "shiptor - area"',
                "InvalidPickUpType": "Указан недопустимый тип забора",
                "InvalidCod": "Указан наложенный платеж, но он недоступен для физических лиц",
                "InvalidAddress": "Адрес не может быть пустым",
                "InvalidCashlessPayment": "Признак безналичной оплаты должен быть true или false",
                "InvalidProduct": "Указанный товар не существует",
                "InvalidProductCount": "Неправильное количество товаров в посылке",
                "InvalidDelayedDeliveryAtDate": "Неправильная дата отложенной доставки.Допустимые значения: +1.. + 7 дней от сегодняшней",
                "InvalidDelayedDeliveryAtMethod": "Для указанного метода доставки невозможно указать отложенную дату отправления",
                "InvalidDeliveryPointDoesNotMatchMethod": "Пункт самовывоза не соответствует выбранному методу доставки",
                "InvalidDeliveryPointMushBeDefined": "Для данного метода доставки необходимо указать пункт самовывоза",
                "InvalidLength": "Недопустимая длина текста",
                "InvalidReceiver": "Требуются имя и фамилия, если поле получателя пусто",
                "InvalidDeliveryPointDoesNotCash": "Пункт самовывоза не принимает наложенный платеж",
                "InvalidVat": "Недопустимый НДС. Доступные значения: null, 0, 10 ,18.",
                "InvalidCourierType": "Неверно указан тип доставки",
                "InvalidPhone": "Неверно указан номер телефона или неверный формат его представления",
                "InvalidEmail": "Неверно указан e-mail или неверный формат его представления",
                "InvalidKladr": "Недопустимый Кладр: он должен содержать скалярное значение (быть числом или строкой).",
                "InvalidDate": "Неверно указана дата или неверный формат её представления",
                "InvalidEarlyDate": "Дата должна быть позже чем сегодня",
                "InvalidTimePeriod": "Не указан период времени",
                "InvalidCourier": "Неверно указано имя службы доставки",
                "InvalidType": "Неверно указан тип доставки",
                "InvalidDeliveryPoint": "Указан неверный ПВЗ",
                "InvalidWarehouse": "Указанный склад не найден",
                "InvalidDateRange": "Неверно указана дата. Она должна быть в промежутке с %s по %s.",
                "InvalidPackages": "Неправильные посылки. Список посылок не должен быть пустым и допустимы только новые посылки без заборов",
                "InvalidCommentShort": "Комментарий слишком короткий. Минимальная допустима длина: %s",
                "InvalidCommentLong": "Комментарий слишком длинный. Максимальная допустима длина: %s",
                "InvalidNumeric": "Должно быть указано число большее или равное нулю",
                "InvalidIssetName": "Товар с таким именем уже существует",
                "InvalidIssetSku": "Товар с таким уникальным номером SKU уже существует",
                "InvalidIssetArticle": "Продукт с таким артикулом уже существует",
                "InvalidIssetShopArticle": "Продукт с таким артикулом магазина уже существует",
                "InvalidStock": "У вас нет доступа к указанному складу",
                "InvalidIssetProduct": "Указанный товар не существует",
                "InvalidNumber": "Неправильный номер: должно быть указано число большее нуля или null",
                "InvalidProductStock": "Невозможно удалить товар, который ожидается или имеет не нулевой остаток",
                "InvalidRequired": "Обязательное поле не может быть пустым",
                "InvalidBoolean": "Для переменной разрешены только логические значения (true, false)",
                "InvalidParamException": "Вы должны указать как минимум один из параметров.",
                "InvalidShippingCourier": "Указанная курьерская доставка не найдена",
                "InvalidPickUp": "Указан некорректный забор",
                "InvalidFulfilmentAccess": "У вас нет доступа к фулфилменту.",
                "InvalidShipment": "Указанная поставка не найдена",
                "InvalidProductRemoved": "Указанная посылка уже удалена",
                "InvalidProductAccess": "Нельзя удалить посылку с таким статусом",
                "InvalidPackage": "Указанная посылка не найдена",
                "InvalidPackageRemoved": "Указанная посылка не удалена",
                "InvalidPackageRecover": "Указанная посылка не может быть восстановлена",
                "InvalidProductDeleted": "Указанный товар не удален",
                "InvalidShipmentRemoved": "Указанная поставка не удалена",
                "InvalidShipmentDelete": "Указанная поставка не может быть удалена"
            }
        },
        id = 'shiptor_widget_logging',
        params = {
            lang: 'RU',
            url: 'https://widget.shiptor.ru/api',
            file_name: null,
            file_size: 0,
            current: 0,
            limit: 5,
            step: 5,
            total: 0,
            errors: 0,
            filters: 0,
            log: {},
            mode: 'popup'
        },
        allowedLangs = ['RU', 'KZ', 'UA'],
        isEmptyObj = function (obj) {
            return Boolean(JSON.stringify(obj) == JSON.stringify({}));
        },
        sendRequest = function (callback) {
            var xhr = new XMLHttpRequest();
            params.file_name = params.url.substring(params.url.lastIndexOf('/') + 1);
            xhr.open("GET", params.url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(null);
            xhr.onreadystatechange = function (response) {
                if (xhr.readyState == XMLHTTPREQUEST_READY && xhr.status == HTTP_SUCCESS) {
                    var jsonResponse = JSON.parse(response.target.responseText);
                    if (!!jsonResponse.error) {
                        console.warn(jsonResponse.error.message);
                        JCShiptorWidgetLogging.instance.error(LANG.global_error);
                    } else {
                        var result = {
                            result: jsonResponse
                        };
                        callback.call(JCShiptorWidgetLogging.instance, result);
                    }
                }
            };
            xhr.onprogress = function (e) {
                if (e.lengthComputable) {
                    params.file_size = (e.total / 1024).toFixed(2);
                }
            }
            xhr.onerror = function (error) {
                console.warn(jsonResponse.error.message);
                JCShiptorWidgetLogging.instance.error(LANG.global_error);
            };
        },
        handlers = {
            toggleJson: function (event) {
                var target = event.target.parentNode,
                    toggle_class = '_shiptor_widget_toggled';
                if (target.classList.contains(toggle_class)) {
                    target.classList.remove(toggle_class);
                } else {
                    target.classList.add(toggle_class);
                }
                scrollAnimate(htmlBuilder.conts.content, event.target.offsetTop - 90, 200);
                event.preventDefault();
            },
            toggleMethods: function (event) {
                var target = htmlBuilder.conts.filterMethods,
                    toggle_class = '_shiptor_widget_toggled';
                if (target.classList.contains(toggle_class)) {
                    target.classList.remove(toggle_class);
                } else {
                    target.classList.add(toggle_class);
                }
                scrollAnimate(htmlBuilder.conts.content, event.target.offsetTop - 90, 200);
                event.preventDefault();
            },
            filterErrors: function (event) {
                htmlBuilder.conts.filterList.innerHTML = '';
                htmlBuilder.create.filterMethodSet();
                for (var log in params.log) {
                    if (event.target.checked === true) {
                        if (!!params.log[log].response && !!params.log[log].response.error) {
                            htmlBuilder.create.filterMethod(params.log[log]);
                        }
                    } else {
                        htmlBuilder.create.filterMethod(params.log[log]);
                    }
                }
                logging.filter();
                event.preventDefault();
            },
            filterCalendar: function (event) {
                logging.filter();
                event.preventDefault();
            },
            filterMethodsAll: function (event) {
                event.preventDefault();
                htmlBuilder.conts.filterMethods.querySelectorAll('[type="checkbox"]').forEach(element => {
                    element.checked = event.target.checked;
                });
                logging.filter();
            },
            filterMethods: function (event) {
                logging.filter();
                if (event.target.checked) {
                    params.filters++;
                } else {
                    params.filters--;
                }
                if (params.filters === 0) {
                    htmlBuilder.conts.filterMethodSet.checked = event.target.checked;
                }
                event.preventDefault();
            },
            more: function (event) {
                logging.add();
                event.preventDefault();
            },
            close: {
                click: function (event) {
                    logging.close();
                    event.preventDefault();
                }
            },
            overlay: {
                click: function (event) {
                    logging.close();
                    event.preventDefault();
                }
            }
        },
        fireEvent = function (eventName, data) {
            var event = new CustomEvent(eventName, {
                detail: data
            });
            if (!!htmlBuilder.conts.widget.dispatchEvent) {
                htmlBuilder.conts.widget.dispatchEvent(event);
            } else {
                htmlBuilder.conts.widget.fireEvent(event.eventType, event);
            }
        },
        addEventListener = function (eventName, node, handler) {
            if (typeof jQuery != 'undefined') {
                $(node).on(eventName, handler);
            } else {
                node.addEventListener(eventName, handler);
            }
        },
        logging = {
            open: function (data) {
                params.log = data.result;
                if (!!params.log) {
                    htmlBuilder.show.widget();
                    htmlBuilder.show.wait();
                    setTimeout(function () {
                        htmlBuilder.create.help();
                        htmlBuilder.create.info();
                        htmlBuilder.create.filterCalendar(params.log);
                        htmlBuilder.create.title();
                        htmlBuilder.create.filterErrors();
                        htmlBuilder.create.filterMethods();
                        htmlBuilder.create.logs();
                        logging.add();
                        htmlBuilder.hide.wait();
                    }, 600);
                    fireEvent('onLoggingOpen', {});
                } else {
                    return false;
                }
            },
            close: function () {
                htmlBuilder.hide.wait();
                htmlBuilder.hide.widget();
                htmlBuilder.conts.contentDefault.innerHTML = '';
                getDefaultParams();
                fireEvent('onLoggingClose', {});
            },
            renderJson: function (log) {
                var json = (typeof log != 'string') ? JSON.stringify(log, undefined, '\t') : log,
                    json = json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'),
                    errorClass = '';
                return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (m) {
                    var typeClass = '_shiptor_widget_json_number';
                    if (/^"/.test(m)) {
                        typeClass = (/:$/.test(m)) ? '_shiptor_widget_json_key' : '_shiptor_widget_json_string';
                    } else if (/true|false/.test(m)) {
                        typeClass = '_shiptor_widget_json_boolean';
                    } else if (/null/.test(m)) {
                        typeClass = '_shiptor_widget_json_null';
                    } else if (/[-!$%^&*()_+|~=`{}\[\]:";'<>?,.\/]/.test(m)) {
                        typeClass = '_shiptor_widget_json_symbol';
                    }
                    if (/^"request":|^"response":/.test(m)) {
                        typeClass = '_shiptor_widget_json_header';
                    }
                    if (/^"error":/.test(m)) {
                        errorClass = ' _shiptor_widget_json_error';
                    }
                    return '<span class="' + typeClass + errorClass + '">' + m + '</span>';
                });
            },
            filter: function () {
                htmlBuilder.show.wait();
                htmlBuilder.conts.logList.innerHTML = "";
                params.current = 0;
                setTimeout(function () {
                    logging.add();
                    htmlBuilder.hide.wait();
                }, 600);
                fireEvent('onLoggingFiltered', {});
            },
            add: function () {
                var keys = Object.keys(params.log);
                params.errors = 0;
                params.total = keys.length;
                if (params.current + params.step >= params.total) {
                    params.step = params.total
                } else {
                    params.step = params.current + params.step;
                }
                htmlBuilder.conts.logsTotal.setAttribute('data-quantity', params.total);
                htmlBuilder.conts.logsMore.disabled = true;
                for (var log in params.log) {
                    var filter = htmlBuilder.conts.filterList.querySelector('[name="_shiptor_widget_filter_' + params.log[log].request.method + '"]');
                    if (!!params.log[log].response && !!params.log[log].response.error) {
                        params.errors++;
                    }
                    if (params.log[log].request.ts.substr(0, 10) != htmlBuilder.conts.calendarTrigger.value) {
                        continue;
                    }
                    if (htmlBuilder.conts.errorsTrigger.checked == true && (!params.log[log].response || !params.log[log].response.error)) {
                        continue;
                    }
                    if (filter && filter.checked === false) {
                        continue;
                    }
                    if (params.current > keys.indexOf(log)) {
                        continue;
                    }
                    if (params.current < params.step) {
                        htmlBuilder.create.logsItem(params.log[log]);
                        params.current++
                        continue;
                    }
                    if (params.current < params.total) {
                        htmlBuilder.conts.logsMore.disabled = false;
                    }
                }
                htmlBuilder.conts.errorsTotal.innerHTML = params.errors;
            }
        },
        scrollAnimate = function (element, to, duration) {
            if (duration <= 0) {
                return;
            }
            var difference = to - element.scrollTop,
                perTick = difference / duration * 5;
            setTimeout(function () {
                element.scrollTop = element.scrollTop + perTick;
                if (element.scrollTop === to) return;
                scrollAnimate(element, to, duration - 5);
            }, 5);
        },
        getDefaultParams = function () {
            var eWidget = document.querySelector("#" + id),
                dMode = eWidget.getAttribute("data-mode"),
                dLimit = eWidget.getAttribute("data-limit"),
                dLang = eWidget.getAttribute("data-lang");
            params.log = {};
            params.current = 0;
            params.filters = 0;
            params.step = params.limit;
            if (dMode !== null) {
                if (["popup", "inline"].indexOf(dMode) !== -1) {
                    params.mode = dMode;
                }
            }
            if (dLimit !== null) {
                params.step = params.limit = parseInt(dLimit);
            }
            if (dLang !== null) {
                params.lang = dLang.toString();
                if (allowedLangs.indexOf(params.lang) === -1) {
                    params.lang = 'RU';
                }
            }
        },
        htmlBuilder = {
            stylesHref: "https://widget.shiptor.ru/embed/styles/css/styles.css",
            conts: {
                widget: document.querySelector("#" + id)
            },
            create: {
                tag: function (tag) {
                    var eTag = document.createElement(tag.name);
                    for (var field in tag.attrs) {
                        if (tag.attrs.hasOwnProperty(field)) {
                            switch (field) {
                                default: eTag.setAttribute(field, tag.attrs[field]);
                                    break;
                                case "value":
                                    eTag.value = tag.attrs[field];
                                    break;
                            }
                        }
                    }
                    if (!!tag.childs) {
                        for (var i = 0; i < tag.childs.length; i++) {
                            eTag.appendChild(this.tag(tag.childs[i]));
                        }
                    }
                    if (!!tag.text) {
                        eTag.innerText = tag.text;
                    }
                    if (!!tag.html) {
                        eTag.innerHTML = tag.html;
                    }
                    if (!!tag.styles) {
                        for (var name in tag.styles) {
                            if (tag.styles.hasOwnProperty(name)) {
                                eTag.style[name] = tag.styles[name];
                            }
                        }
                    }
                    return eTag;
                },
                styles: function () {
                    var head = document.querySelector("head"),
                        isStyle = document.querySelector('[href="' + htmlBuilder.stylesHref + '"]');
                    if (!!head) {
                        if (!isStyle) {
                            head.appendChild(this.tag({
                                name: "link",
                                attrs: {
                                    "rel": "stylesheet",
                                    "href": htmlBuilder.stylesHref
                                }
                            }));
                        }
                    } else {
                        error(LANG.no_head);
                    }
                },
                widget: function () {
                    htmlBuilder.conts.widget = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget",
                            id: id
                        }
                    });
                    document.body.appendChild(htmlBuilder.conts.widget);
                },
                overlay: function () {
                    if (!htmlBuilder.conts.overlay) {
                        htmlBuilder.conts.overlay = this.tag({
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_overlay"
                            }
                        });
                        htmlBuilder.conts.widget.appendChild(htmlBuilder.conts.overlay);
                    }
                },
                frame: function () {
                    if (!htmlBuilder.conts.frame) {
                        htmlBuilder.conts.frame = this.tag({
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_frame_logging"
                            }
                        });
                        htmlBuilder.conts.widget.appendChild(htmlBuilder.conts.frame);
                    } else {
                        htmlBuilder.show.frame();
                    }
                    this.header();
                    this.content();
                },
                header: function () {
                    htmlBuilder.conts.header = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_header"
                        },
                        childs: [{
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_group"
                            },
                            childs: [{
                                name: "div",
                                attrs: {
                                    "class": "_shiptor_widget_title"
                                },
                                text: LANG.title
                            }, {
                                name: "div",
                                attrs: {
                                    "class": "_shiptor_widget_close"
                                },
                                html: "&#10005;"
                            }]
                        }]
                    });
                    htmlBuilder.conts.frame.appendChild(htmlBuilder.conts.header);
                    htmlBuilder.conts.close = htmlBuilder.conts.header.querySelector("._shiptor_widget_close");
                },
                content: function () {
                    htmlBuilder.conts.content = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_content"
                        },
                        childs: [{
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_default"
                            }
                        }]
                    });
                    htmlBuilder.conts.frame.appendChild(htmlBuilder.conts.content);
                    htmlBuilder.conts.contentDefault = htmlBuilder.conts.content.querySelector("._shiptor_widget_default");
                    this.wait();
                },
                help: function () {
                    htmlBuilder.conts.help = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_help"
                        },
                        childs: [{
                            name: "a",
                            attrs: {
                                "class": "_shiptor_widget_link",
                                "href": "https://shiptor.ru/help/integration/e-shop-widgets/log-analyzer-widget-settings",
                                "rel": "noopener",
                                "target": "_blank"
                            },
                            text: LANG.help
                        }]
                    });
                    htmlBuilder.conts.contentDefault.appendChild(htmlBuilder.conts.help);
                },
                info: function () {
                    htmlBuilder.conts.info = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_info"
                        },
                        childs: [{
                            name: "span",
                            text: LANG.file_text
                        }, {
                            name: "a",
                            attrs: {
                                "href": params.url,
                                "rel": "noopener",
                                "target": "_blank",
                                "class": "_shiptor_widget_file _shiptor_widget_link"
                            },
                            text: params.file_name
                        }, {
                            name: "span",
                            attrs: {
                                "data-size": LANG.file_size,
                                "data-unit": LANG.file_unit,
                                "class": "_shiptor_widget_size"
                            },
                            text: params.file_size
                        }]
                    });
                    htmlBuilder.conts.contentDefault.appendChild(htmlBuilder.conts.info);
                },
                title: function () {
                    htmlBuilder.conts.logsTitle = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_logs_title"
                        },
                        childs: [{
                            name: "span",
                            attrs: {
                                "data-quantity": 0,
                                "class": "_shiptor_widget_logs_total"
                            },
                            text: LANG.header
                        }, {
                            name: "span",
                            attrs: {
                                "data-include": '(' + LANG.header_include,
                                "data-errors": LANG.header_errors + ')',
                                "class": "_shiptor_widget_errors_total"
                            },
                            text: '0'
                        }]
                    });
                    htmlBuilder.conts.errorsTotal = this.tag({
                    });
                    htmlBuilder.conts.contentDefault.appendChild(htmlBuilder.conts.logsTitle);
                    htmlBuilder.conts.logsTotal = htmlBuilder.conts.logsTitle.querySelector("._shiptor_widget_logs_total");
                    htmlBuilder.conts.errorsTotal = htmlBuilder.conts.logsTitle.querySelector("._shiptor_widget_errors_total");
                },
                filterCalendar: function (logs) {
                    var log = Object.values(logs);
                    var min = log[log.length - 1].request.ts.substr(0, 10),
                        max = log[0].request.ts.substr(0, 10);
                    htmlBuilder.conts.filterCalendar = this.tag({
                        name: "div",
                        attrs: {
                            "data-title": LANG.filters.calendar,
                            "class": "_shiptor_widget_filter _shiptor_widget_filter_calendar"
                        },
                        childs: [{
                            name: "input",
                            attrs: {
                                type: "date",
                                name: "_shiptor_widget_filter_calendar",
                                min: min,
                                max: max,
                                value: max,
                                readonly: (min === max) ? true : false,
                                required: "required"
                            }
                        }]
                    });
                    htmlBuilder.conts.contentDefault.appendChild(htmlBuilder.conts.filterCalendar);
                    htmlBuilder.conts.calendarTrigger = htmlBuilder.conts.filterCalendar.querySelector('[name="_shiptor_widget_filter_calendar"]');
                    htmlBuilder.conts.calendarTrigger.addEventListener('click', handlers.toggleCalendar);
                },
                filterErrors: function () {
                    htmlBuilder.conts.filterErrors = this.tag({
                        name: "label",
                        attrs: {
                            "class": "_shiptor_widget_filter _shiptor_widget_filter_errors"
                        },
                        childs: [{
                            name: "input",
                            attrs: {
                                "class": "_shiptor_widget_trigger",
                                "type": "checkbox"
                            },
                        }, {
                            name: "span",
                            attrs: {
                                "class": "_shiptor_widget_filter_title"
                            },
                            text: LANG.filters.errors
                        }]
                    });
                    htmlBuilder.conts.contentDefault.appendChild(htmlBuilder.conts.filterErrors);
                    htmlBuilder.conts.errorsTrigger = htmlBuilder.conts.filterErrors.querySelector('._shiptor_widget_trigger');
                    htmlBuilder.conts.errorsTrigger.addEventListener('change', handlers.filterErrors);
                },
                filterMethods: function () {
                    htmlBuilder.conts.filterMethods = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_filters"
                        },
                        childs: [{
                            name: "a",
                            attrs: {
                                "class": "_shiptor_widget_toggle _shiptor_widget_link"
                            },
                            text: LANG.filters.scroll_down
                        }, {
                            name: "ul",
                            attrs: {
                                "class": "_shiptor_widget_filter_list"
                            }
                        }]
                    });
                    htmlBuilder.conts.contentDefault.appendChild(htmlBuilder.conts.filterMethods);
                    htmlBuilder.conts.filterList = htmlBuilder.conts.filterMethods.querySelector('._shiptor_widget_filter_list');
                    htmlBuilder.conts.methodsToggle = htmlBuilder.conts.filterMethods.querySelector('._shiptor_widget_toggle');
                    htmlBuilder.conts.methodsToggle.addEventListener('click', handlers.toggleMethods);
                    this.filterMethodSet();
                    for (var log in params.log) {
                        this.filterMethod(params.log[log]);
                    }
                },
                filterMethodSet: function () {
                    if (!!params.log && !isEmptyObj(params.log)) {
                        htmlBuilder.conts.filterList.appendChild(this.tag({
                            name: "li",
                            attrs: {
                                "class": "_shiptor_widget_filter _shiptor_widget_filter_all"
                            },
                            childs: [{
                                name: "label",

                                attrs: {
                                    "class": "_shiptor_widget_filter_label"
                                },
                                childs: [{
                                    name: "input",
                                    attrs: {
                                        "class": "_shiptor_widget_trigger",
                                        "type": "checkbox",
                                        "name": "_shiptor_widget_filter_all",
                                        "checked": true,
                                        "value": "all"
                                    }
                                }, {
                                    name: "span",
                                    attrs: {
                                        "class": "_shiptor_widget_filter_method"
                                    },
                                    text: LANG.filters.set_all
                                }]
                            }]
                        }));
                        htmlBuilder.conts.filterMethodSet = htmlBuilder.conts.filterList.querySelector('[name="_shiptor_widget_filter_all"]');
                        htmlBuilder.conts.filterMethodSet.addEventListener('change', handlers.filterMethodsAll);
                    }
                },
                filterMethod: function (log) {
                    var method = log.request.method,
                        error = log.response.error,
                        elem = htmlBuilder.conts.filterList.querySelector('[data-method=' + method + ']');
                    if (!elem) {
                        htmlBuilder.conts.filterList.appendChild(this.tag({
                            name: "li",
                            attrs: {
                                "class": "_shiptor_widget_filter",
                                "data-method": method
                            },
                            childs: [{
                                name: "label",
                                attrs: {
                                    "class": "_shiptor_widget_filter_label"
                                },
                                childs: [{
                                    name: "input",
                                    attrs: {
                                        "class": "_shiptor_widget_trigger",
                                        "type": "checkbox",
                                        "name": "_shiptor_widget_filter_" + method,
                                        "checked": true,
                                        "value": method
                                    }
                                }, {
                                    name: "div",
                                    attrs: {
                                        "class": "_shiptor_widget_filter_title"
                                    },
                                    childs: [{
                                        name: "span",
                                        attrs: {
                                            "class": "_shiptor_widget_filter_method"
                                        },
                                        text: method
                                    }, {
                                        name: "span",
                                        attrs: {
                                            "class": "_shiptor_widget_filter_quantity"
                                        },
                                        text: 1
                                    }]
                                }, {
                                    name: "div",
                                    attrs: {
                                        "class": "_shiptor_widget_filter_info"
                                    },
                                    text: LANG.methods[method]
                                }]
                            }]
                        }));
                        params.filters++;
                    } else {
                        var total_counter = htmlBuilder.conts.filterList.querySelector('[data-method=' + method + '] ._shiptor_widget_filter_quantity');
                        total_counter.innerHTML = parseInt(total_counter.innerHTML) + 1;
                    }
                    if (!!error) {
                        var error_counter = htmlBuilder.conts.filterList.querySelector('[data-method=' + method + '] ._shiptor_widget_filter_quantity_errors');
                        if (!error_counter) {
                            htmlBuilder.conts.filterList.querySelector('[data-method=' + method + '] ._shiptor_widget_filter_title').appendChild(this.tag({
                                name: "span",
                                attrs: {
                                    "class": "_shiptor_widget_filter_quantity_errors"
                                },
                                text: 1
                            }));
                        } else {
                            error_counter.innerHTML = parseInt(error_counter.innerHTML) + 1;
                        }
                    }
                    htmlBuilder.conts.filterList.querySelector('input[name="_shiptor_widget_filter_' + method + '"]').addEventListener('change', handlers.filterMethods);
                },
                logs: function () {
                    htmlBuilder.conts.logs = this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_logs"
                        }
                    });
                    htmlBuilder.conts.contentDefault.appendChild(htmlBuilder.conts.logs);
                    this.logsTable();
                },
                logsTable: function () {
                    htmlBuilder.conts.logs.appendChild(this.tag({
                        name: "div",
                        attrs: {
                            "class": "_shiptor_widget_logs_table"
                        },
                        childs: [{
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_log _shiptor_widget_logs_header"
                            },
                            childs: [{
                                name: "div",
                                attrs: {
                                    "class": "_shiptor_widget_log_elem _shiptor_widget_log_toggle"
                                }
                            }, {
                                name: "div",
                                attrs: {
                                    "class": "_shiptor_widget_log_elem _shiptor_widget_log_date"
                                },
                                text: LANG.date
                            }, {
                                name: "div",
                                attrs: {
                                    "class": "_shiptor_widget_log_elem _shiptor_widget_log_method"
                                },
                                text: LANG.method
                            }, {
                                name: "div",
                                attrs: {
                                    "class": "_shiptor_widget_log_elem _shiptor_widget_log_description"
                                },
                                text: LANG.description
                            }, {
                                name: "div",
                                attrs: {
                                    "class": "_shiptor_widget_log_elem _shiptor_widget_log_error"
                                },
                                text: LANG.error
                            }]
                        }, {
                            name: "ul",
                            attrs: {
                                "class": "_shiptor_widget_log_list"
                            }
                        }, {
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_buttons"
                            },
                            childs: [{
                                name: "button",
                                attrs: {
                                    "class": "_shiptor_widget_button _shiptor_widget_button_default _shiptor_widget_button_more",
                                    "type": "button"
                                },
                                text: LANG.more
                            }]
                        }]
                    }));
                    htmlBuilder.conts.logsTitle = htmlBuilder.conts.logs.querySelector('._shiptor_widget_logs_title');
                    htmlBuilder.conts.logsMore = htmlBuilder.conts.logs.querySelector('._shiptor_widget_button_more');
                    htmlBuilder.conts.logsMore.addEventListener('click', handlers.more);
                    htmlBuilder.conts.logList = htmlBuilder.conts.logs.querySelector('._shiptor_widget_log_list');
                },
                logsItem: function (log) {
                    var error = '';
                    if ((log).hasOwnProperty('response') && (log.response).hasOwnProperty('error')) {
                        error = log.response.error;
                    }
                    if (!!LANG.errors[error]) {
                        error = LANG.errors[error];
                    }
                    htmlBuilder.conts.logItem = this.tag({
                        name: "li",
                        attrs: {
                            "class": "_shiptor_widget_log",
                            "data-method": log.request.method
                        },
                        childs: [{
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_log_elem _shiptor_widget_log_toggle"
                            }
                        }, {
                            name: "div",
                            attrs: {
                                "class": "_shiptor_widget_log_elem _shiptor_widget_log_date"
                            },
                            text: log.request.ts
                        }, {
                            name: "div",
                            attrs: {
                                "title": log.request.method,
                                "class": "_shiptor_widget_log_elem _shiptor_widget_log_method"
                            },
                            text: log.request.method
                        }, {
                            name: "div",
                            attrs: {
                                "title": LANG.methods[log.request.method],
                                "class": "_shiptor_widget_log_elem _shiptor_widget_log_description"
                            },
                            text: LANG.methods[log.request.method]
                        }, {
                            name: "div",
                            attrs: {
                                "title": error,
                                "class": "_shiptor_widget_log_elem _shiptor_widget_log_error"
                            },
                            text: error
                        }, {
                            name: "pre",
                            attrs: {
                                "class": "_shiptor_widget_json_pre"
                            },
                            childs: [{
                                name: "code",
                                attrs: {
                                    "class": "_shiptor_widget_json_code"
                                },
                                html: logging.renderJson(log)
                            }]
                        }]
                    });
                    htmlBuilder.conts.logItem.querySelector('._shiptor_widget_log_toggle').addEventListener('click', handlers.toggleJson);
                    htmlBuilder.conts.json = htmlBuilder.conts.logItem.querySelector('._shiptor_widget_json_code');
                    htmlBuilder.conts.logList.appendChild(htmlBuilder.conts.logItem);
                },
                error: function (errorText) {
                    htmlBuilder.conts.content.innerHTML = '<h3 class="shiptor_widget_error">' + errorText + '</h3>';
                },
                wait: function () {
                    if (!htmlBuilder.conts.wait) {
                        htmlBuilder.conts.wait = this.tag({
                            name: 'div',
                            attrs: {
                                'class': '_shiptor_widget_wait_overlay'
                            }
                        });
                        htmlBuilder.conts.content.appendChild(htmlBuilder.conts.wait);
                    }
                }
            },
            show: {
                generic: function (container) {
                    if (!!container) {
                        container.style.display = "block";
                    }
                },
                widget: function () {
                    this.generic(htmlBuilder.conts.widget);
                    if (!params.overflow) {
                        params.overflow = document.body.style.overflow;
                        document.body.style.overflow = 'hidden';
                    }
                },
                wait: function () {
                    this.generic(htmlBuilder.conts.wait);
                }
            },
            hide: {
                generic: function (container) {
                    if (!!container) {
                        container.style.display = "none";
                    }
                },
                widget: function () {
                    this.generic(htmlBuilder.conts.widget);
                    if (!!params.overflow) {
                        params.overflow = null;
                        document.body.style.overflow = params.overflow;
                    } else {
                        document.body.style.overflow = 'unset';
                    }
                },
                wait: function () {
                    this.generic(htmlBuilder.conts.wait);
                }
            },
            setHandlers: function (eventHandlers) {
                if (!eventHandlers) {
                    eventHandlers = handlers;
                }
                for (var elem in eventHandlers) {
                    if (eventHandlers.hasOwnProperty(elem) && !!htmlBuilder.conts[elem]) {
                        for (var eventName in eventHandlers[elem]) {
                            if (eventHandlers[elem].hasOwnProperty(eventName)) {
                                htmlBuilder.conts[elem].addEventListener(eventName, eventHandlers[elem][eventName]);
                            }
                        }
                    }
                }
            }
        },
        addHandlers = function () {
            var loggingButtons = document.querySelectorAll('[data-role="shiptor_widget_logging_show"]');
            if (loggingButtons.length > 0) {
                for (var i = 0; i < loggingButtons.length; i++) {
                    loggingButtons[i].addEventListener('click', JCShiptorWidgetLogging.instance.addHandler);
                }
            }

        },
        init = function () {
            if (!htmlBuilder.conts.widget) {
                htmlBuilder.create.widget();
            } else {
                getDefaultParams();
            }
            htmlBuilder.create.styles();
            htmlBuilder.create.overlay();
            htmlBuilder.create.frame();
            htmlBuilder.setHandlers();
            htmlBuilder.hide.widget();
            fireEvent('onLoggingWidgetInit', {});
            addHandlers();
        };
    this.destroy = function () {
        var parent = htmlBuilder.conts.widget.parentNode;
        if (!!parent) {
            parent.removeChild(htmlBuilder.conts.widget);
            htmlBuilder.conts.widget = null;
        }
    };
    this.error = function (errorText) {
        console.trace();
        console.warn(errorText);
        if (!!htmlBuilder.conts.content) {
            htmlBuilder.create.error(errorText);
        }
    };
    this.show = function () {
        if (!!htmlBuilder.conts.widget) {
            htmlBuilder.show.widget();
        } else {
            console.warn('Nothing to show');
        }
    };
    this.hide = function () {
        if (!!htmlBuilder.conts.widget) {
            htmlBuilder.hide.widget();
        } else {
            console.warn('Nothing to hide');
        }
    };
    this.addHandler = function (event) {
        var url = this.getAttribute('data-url'),
            limit = this.getAttribute('data-limit');
        if (!!limit) {
            params.step = params.limit = parseInt(limit);
        }
        if (!url) {
            console.warn('Лог файл не определен');
        } else {
            params.url = url;
            sendRequest(function (data) {
                logging.open(data);
            });
        }
        event.preventDefault();
    };
    init();
};
window.addEventListener('load', function () {
    try {
        window.ShiptorWidgetLogging = new JCShiptorWidgetLogging();
    } catch (error) {
        console.trace();
        console.warn(error.message);
    }
});