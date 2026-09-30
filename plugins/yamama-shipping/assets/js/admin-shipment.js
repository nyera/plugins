(function ($) {
    'use strict';

    var CARRIER_COLORS = {
        'smsa':     '#0066B3',
        'aramex':   '#E35205',
        'dhl':      '#FFCC00',
        'spl':      '#006633',
        'j&t':      '#D32F2F',
        'naqel':    '#003366',
        'fetchr':   '#00BCD4',
        'imile':    '#FF6B00',
        'redbox':   '#C62828',
        'default':  '#2271b1'
    };

    var CARRIER_ICONS = {
        'smsa':   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="15" height="13" rx="2"/><path d="M16 10h4l3 4v5h-7V10z"/><circle cx="5.5" cy="19.5" r="2"/><circle cx="18.5" cy="19.5" r="2"/></svg>',
        'aramex': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="15" height="13" rx="2"/><path d="M16 10h4l3 4v5h-7V10z"/><circle cx="5.5" cy="19.5" r="2"/><circle cx="18.5" cy="19.5" r="2"/></svg>',
    };

    var DEFAULT_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="15" height="13" rx="2"/><path d="M16 10h4l3 4v5h-7V10z"/><circle cx="5.5" cy="19.5" r="2"/><circle cx="18.5" cy="19.5" r="2"/></svg>';

    /* ──────────────────────────────────────────────
     *  Fetch Label Button
     * ────────────────────────────────────────────── */

    $(document).on('click', '.yamama-fetch-label', function () {
        var $btn = $(this);
        var $status = $btn.siblings('.yamama-label-status');
        var oid = $btn.data('order-id');

        if (!oid || $btn.prop('disabled')) return;

        var originalText = $btn.text();
        $btn.prop('disabled', true).text('جاري الجلب...');
        $status.text('');

        var url = (typeof yamamaShipping !== 'undefined') ? yamamaShipping.ajaxUrl : ajaxurl;
        var nc = (typeof yamamaShipping !== 'undefined') ? yamamaShipping.nonce : '';
        if (window.location.protocol === 'https:' && url.indexOf('http:') === 0) {
            url = url.replace('http:', 'https:');
        }

        $.post(url, {
            action: 'yamama_fetch_label',
            nonce: nc,
            order_id: oid
        }, function (res) {
            if (res.success && res.data) {
                if (res.data.pdf_base64) {
                    var byteChars = atob(res.data.pdf_base64);
                    var byteNumbers = new Array(byteChars.length);
                    for (var i = 0; i < byteChars.length; i++) {
                        byteNumbers[i] = byteChars.charCodeAt(i);
                    }
                    var blob = new Blob([new Uint8Array(byteNumbers)], { type: 'application/pdf' });
                    var blobUrl = URL.createObjectURL(blob);
                    window.open(blobUrl, '_blank');
                } else if (res.data.label_url) {
                    window.open(res.data.label_url, '_blank');
                }
                $btn.prop('disabled', false).text('تحميل PDF');
                $status.text('');
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'فشل جلب البوليصة';
                $btn.prop('disabled', false).text(originalText);
                $status.text(' ' + msg).css('color', '#d63638');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(originalText);
            $status.text(' خطأ في الاتصال').css('color', '#d63638');
        });
    });

    /* ──────────────────────────────────────────────
     *  Shipment Form
     * ────────────────────────────────────────────── */

    var $form = $('#yamama-shipment-form');
    if (!$form.length) {
        return;
    }

    var orderId  = $form.data('order-id');
    var ajaxUrl  = yamamaShipping.ajaxUrl;
    var nonce    = yamamaShipping.nonce;
    var returnTo = yamamaShipping.returnTo || '';

    if (window.location.protocol === 'https:' && ajaxUrl.indexOf('http:') === 0) {
        ajaxUrl = ajaxUrl.replace('http:', 'https:');
    }

    var quoteCost    = 0;
    var quoteReady   = false;
    var walletBalance = 0;
    var carriersData = [];
    var selectedCarrierId = '';

    /* ──────────────────────────────────────────────
     *  Helpers
     * ────────────────────────────────────────────── */

    function showSpinner() { $form.find('#yamama-spinner').addClass('is-active'); }
    function hideSpinner() { $form.find('#yamama-spinner').removeClass('is-active'); }

    function showResult(type, msg) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        $form.find('#yamama-result').html('<div class="notice ' + cls + ' inline"><p>' + msg + '</p></div>').show();
    }

    function getCarrierColor(name) {
        var lower = (name || '').toLowerCase();
        for (var key in CARRIER_COLORS) {
            if (key !== 'default' && lower.indexOf(key) !== -1) {
                return CARRIER_COLORS[key];
            }
        }
        return CARRIER_COLORS['default'];
    }

    function getCarrierIcon(name) {
        var lower = (name || '').toLowerCase();
        for (var key in CARRIER_ICONS) {
            if (lower.indexOf(key) !== -1) {
                return CARRIER_ICONS[key];
            }
        }
        return DEFAULT_ICON;
    }

    function refreshWalletBalance(callback) {
        $.post(ajaxUrl, {
            action: 'yamama_get_wallet',
            nonce: nonce
        }, function (res) {
            if (res.success && res.data) {
                walletBalance = parseFloat(res.data.balance || 0);
                $form.find('#yamama-wallet-balance').text(walletBalance.toFixed(2));
                $form.find('#yamama-wallet-balance-row').show();
            }
            if (typeof callback === 'function') callback();
        }).fail(function () {
            if (typeof callback === 'function') callback();
        });
    }

    function updatePaymentChoice() {
        if (quoteCost <= 0) {
            $form.find('#yamama-payment-choice').hide();
            return;
        }
        $form.find('#yamama-payment-choice').show();
        var canWallet = walletBalance + 0.0001 >= quoteCost;
        var $walletRadio = $form.find('#yamama-pay-wallet-radio');
        $walletRadio.prop('disabled', !canWallet);
        $form.find('#yamama-wallet-insufficient').toggle(!canWallet);
        if (!canWallet && $walletRadio.is(':checked')) {
            $form.find('input[name="yamama_payment_source"][value="tap"]').prop('checked', true);
        }
    }

    function getPaymentSource() {
        return $form.find('input[name="yamama_payment_source"]:checked').val() || 'tap';
    }

    /* ──────────────────────────────────────────────
     *  Load Carriers as Cards
     * ────────────────────────────────────────────── */

    function loadCarriers() {
        var $container = $form.find('#yamama-carriers-container');

        $.post(ajaxUrl, {
            action: 'yamama_get_carriers',
            nonce: nonce
        }, function (res) {
            $container.empty();

            if (res.success && res.data && res.data.carriers) {
                carriersData = res.data.carriers;

                if (carriersData.length === 0) {
                    $container.html('<p class="yamama-no-carriers">لا توجد شركات شحن متاحة حالياً.</p>');
                    return;
                }

                for (var i = 0; i < carriersData.length; i++) {
                    var c = carriersData[i];
                    var name = c.name || 'Carrier ' + c.carrier_id;
                    var color = getCarrierColor(name);
                    var icon = getCarrierIcon(name);
                    var initial = name.charAt(0).toUpperCase();

                    var badges = '';
                    if (c.has_cod) badges += '<span class="yamama-carrier-badge yamama-badge-cod">COD</span>';
                    if (c.has_parcel) badges += '<span class="yamama-carrier-badge yamama-badge-parcel">طرود</span>';
                    if (c.has_cancel) badges += '<span class="yamama-carrier-badge yamama-badge-cancel">إلغاء</span>';

                    var $card = $('<div>', {
                        'class': 'yamama-carrier-card',
                        'data-carrier-id': c.carrier_id,
                        'data-carrier-name': name,
                        'data-has-cod': c.has_cod ? '1' : '0'
                    });

                    $card.html(
                        '<div class="yamama-carrier-icon" style="background-color:' + color + '">' +
                            '<div class="yamama-carrier-svg">' + icon + '</div>' +
                        '</div>' +
                        '<div class="yamama-carrier-info">' +
                            '<div class="yamama-carrier-name">' + name + '</div>' +
                            (badges ? '<div class="yamama-carrier-badges">' + badges + '</div>' : '') +
                        '</div>' +
                        '<div class="yamama-carrier-check">' +
                            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' +
                        '</div>'
                    );

                    $container.append($card);
                }
            } else {
                $container.html('<p class="yamama-no-carriers">فشل تحميل شركات الشحن. يرجى تحديث الصفحة.</p>');
            }
        }).fail(function () {
            $container.html('<p class="yamama-no-carriers">خطأ في الاتصال. يرجى تحديث الصفحة.</p>');
        });
    }

    loadCarriers();

    /* ──────────────────────────────────────────────
     *  Carrier Card Selection → Auto-Calculate
     * ────────────────────────────────────────────── */

    var REQUIRED_FIELDS_FOR_QUOTE = [
        { name: 'customer_city',    label: 'مدينة العميل' },
        { name: 'shipper_city',     label: 'مدينة المرسل' }
    ];

    var REQUIRED_FIELDS_FOR_SHIP = [
        { name: 'customer_name',     label: 'اسم العميل' },
        { name: 'customer_phone1',   label: 'جوال العميل' },
        { name: 'customer_city',     label: 'مدينة العميل' },
        { name: 'customer_address1', label: 'عنوان العميل' },
        { name: 'shipper_name',      label: 'اسم المرسل' },
        { name: 'shipper_phone',     label: 'جوال المرسل' },
        { name: 'shipper_city',      label: 'مدينة المرسل' },
        { name: 'shipper_address1',  label: 'عنوان المرسل' }
    ];

    function validateFields(fields) {
        var missing = [];
        for (var i = 0; i < fields.length; i++) {
            var $field = $form.find('[name="' + fields[i].name + '"]');
            var val = $field.val();
            if (!val || val.trim() === '') {
                missing.push(fields[i]);
                $field.css('border-color', '#d63638');
            } else {
                $field.css('border-color', '');
            }
        }
        return missing;
    }

    function showCarrierWarning(msg) {
        var $warning = $form.find('#yamama-carrier-warning');
        $warning.find('p').html(msg);
        $warning.show();
        $warning[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideCarrierWarning() {
        $form.find('#yamama-carrier-warning').hide();
    }

    $form.on('click', '.yamama-carrier-card', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $card = $(this);
        var carrierId = String($card.attr('data-carrier-id') || $card.data('carrier-id'));

        $form.find('.yamama-carrier-card').removeClass('yamama-carrier-selected');
        $card.addClass('yamama-carrier-selected');
        selectedCarrierId = carrierId;
        $form.find('#yamama-carrier-input').val(carrierId);

        var hasCod = String($card.attr('data-has-cod')) === '1';
        var paymentVal = $form.find('[name="payment_method"]').val();

        if (paymentVal === 'cod' && !hasCod) {
            showCarrierWarning('شركة الشحن المختارة لا تدعم الدفع عند الاستلام (COD). يرجى اختيار شركة أخرى أو تغيير طريقة الدفع.');
            return;
        }

        var missingQuote = validateFields(REQUIRED_FIELDS_FOR_QUOTE);
        if (missingQuote.length > 0) {
            var labels = [];
            for (var i = 0; i < missingQuote.length; i++) { labels.push('<strong>' + missingQuote[i].label + '</strong>'); }
            showCarrierWarning('لحساب التكلفة، يرجى تعبئة الحقول التالية: ' + labels.join('، '));
            return;
        }

        var missingShip = validateFields(REQUIRED_FIELDS_FOR_SHIP);
        if (missingShip.length > 0) {
            var shipLabels = [];
            for (var j = 0; j < missingShip.length; j++) { shipLabels.push('<strong>' + missingShip[j].label + '</strong>'); }
            showCarrierWarning('⚠ حقول ناقصة لإنشاء الشحنة: ' + shipLabels.join('، ') + '<br><small>سيتم حساب التكلفة، لكن يجب تعبئتها قبل الدفع.</small>');
        } else {
            hideCarrierWarning();
        }

        fetchQuote();
    });

    $form.on('change', '[name="payment_method"]', function () {
        if (selectedCarrierId) {
            var $card = $form.find('.yamama-carrier-card[data-carrier-id="' + selectedCarrierId + '"]');
            var hasCod = String($card.attr('data-has-cod')) === '1';

            if ($(this).val() === 'cod' && !hasCod) {
                showCarrierWarning('شركة الشحن المختارة لا تدعم الدفع عند الاستلام (COD). يرجى اختيار شركة أخرى أو تغيير طريقة الدفع.');
                return;
            }

            hideCarrierWarning();
            fetchQuote();
        }
    });

    /* ──────────────────────────────────────────────
     *  Collect Form Data
     * ────────────────────────────────────────────── */

    function collectFormData() {
        var data = {};
        $form.find('input, select, textarea').each(function () {
            var name = $(this).attr('name');
            if (!name || $(this).is(':checkbox')) return;
            data[name] = $(this).val();
        });

        data.create_shippment = '1';

        var items = [];
        $form.find('.yamama-items-table tbody tr').each(function () {
            var item = {};
            $(this).find('input').each(function () {
                var n = $(this).attr('name');
                var match = n.match(/items\[\d+\]\[(\w+)\]/);
                if (match) {
                    item[match[1]] = $(this).val();
                }
            });
            if (item.name) {
                items.push(item);
            }
        });
        data.items = items;
        data.quote_cost = String(quoteCost);

        return data;
    }

    function calculateTotalWeight() {
        var total = 0;
        $form.find('.yamama-items-table tbody tr').each(function () {
            var qty = parseFloat($(this).find('input[name*="[quantity]"]').val()) || 1;
            var w   = parseFloat($(this).find('input[name*="[weight]"]').val()) || 0.5;
            total += qty * w;
        });
        return total > 0 ? total : 1;
    }

    /* ──────────────────────────────────────────────
     *  Fetch Quote (auto-triggered on carrier select)
     * ────────────────────────────────────────────── */

    function fetchQuote() {
        var city          = $form.find('[name="customer_city"]').val();
        var paymentMethod = $form.find('[name="payment_method"]').val();
        var weight        = calculateTotalWeight();

        if (!selectedCarrierId || !city) {
            return;
        }

        showSpinner();
        $form.find('#yamama-result').hide();
        quoteReady = false;
        $form.find('#yamama-pay-ship').hide();

        var $selectedCard = $form.find('.yamama-carrier-card.yamama-carrier-selected');
        $selectedCard.addClass('yamama-carrier-loading');

        $.post(ajaxUrl, {
            action: 'yamama_get_quote',
            nonce: nonce,
            carrier_id: selectedCarrierId,
            city: city,
            weight: weight,
            payment_method: paymentMethod
        }, function (res) {
            hideSpinner();
            $selectedCard.removeClass('yamama-carrier-loading');

            if (res.success && res.data) {
                var d = res.data;
                if (d.data && typeof d.data === 'object') {
                    d = d.data;
                }
                var cost = d.shipping_cost || d.shippingCost || d.cost || d.price || 0;
                quoteCost = parseFloat(cost);
                $form.find('#yamama-quote-cost').text(quoteCost.toFixed(2));
                $form.find('#yamama-quote-section').show();
                quoteReady = true;

                refreshWalletBalance(function () {
                    updatePaymentChoice();
                    if (quoteCost > 0) {
                        $form.find('#yamama-pay-ship').text('الدفع وإنشاء الشحنة').show();
                    } else {
                        $form.find('#yamama-pay-ship').text('إنشاء الشحنة').show();
                    }
                });
                $form.find('#yamama-result').hide();
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'فشل الحصول على التكلفة';
                showResult('error', msg);
            }
        }).fail(function () {
            hideSpinner();
            $selectedCard.removeClass('yamama-carrier-loading');
            showResult('error', 'خطأ في الاتصال بالخادم');
        });
    }

    /* ──────────────────────────────────────────────
     *  Pay & Ship
     * ────────────────────────────────────────────── */

    $form.on('click', '#yamama-pay-ship', function () {
        if (!quoteReady) {
            showCarrierWarning('يرجى اختيار شركة الشحن لحساب التكلفة أولاً');
            return;
        }

        var missingShip = validateFields(REQUIRED_FIELDS_FOR_SHIP);
        if (missingShip.length > 0) {
            var labels = [];
            for (var i = 0; i < missingShip.length; i++) { labels.push('<strong>' + missingShip[i].label + '</strong>'); }
            showCarrierWarning('يرجى تعبئة الحقول التالية قبل الدفع: ' + labels.join('، '));
            missingShip[0].field = $form.find('[name="' + missingShip[0].name + '"]');
            if (missingShip[0].field.length) {
                missingShip[0].field[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                missingShip[0].field.focus();
            }
            return;
        }

        if (!selectedCarrierId) {
            showCarrierWarning('يرجى اختيار شركة الشحن');
            return;
        }

        showSpinner();
        $form.find('#yamama-result').hide();
        $(this).prop('disabled', true);

        var formData = collectFormData();

        $.post(ajaxUrl, {
            action: 'yamama_save_pending',
            nonce: nonce,
            order_id: orderId,
            form_data: formData
        }, function (res) {
            if (!res.success) {
                hideSpinner();
                showResult('error', (res.data && res.data.message) || 'فشل حفظ البيانات');
                $form.find('#yamama-pay-ship').prop('disabled', false);
                return;
            }

            if (quoteCost <= 0) {
                createShipmentOrder('', 'tap');
            } else if (getPaymentSource() === 'wallet') {
                createShipmentOrder('', 'wallet');
            } else {
                initTapPayment();
            }
        }).fail(function () {
            hideSpinner();
            showResult('error', 'خطأ في الاتصال بالخادم');
            $form.find('#yamama-pay-ship').prop('disabled', false);
        });
    });

    /* ──────────────────────────────────────────────
     *  Tap Payment
     * ────────────────────────────────────────────── */

    function initTapPayment() {
        showResult('success', 'جاري تجهيز صفحة الدفع...');

        $.post(ajaxUrl, {
            action: 'yamama_create_tap_charge',
            nonce: nonce,
            order_id: orderId,
            amount: quoteCost,
            return_to: returnTo
        }, function (res) {
            hideSpinner();

            if (res.success && res.data && res.data.payment_url) {
                showResult('success', 'جاري التحويل لصفحة الدفع Tap...');
                window.location.href = res.data.payment_url;
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'فشل إنشاء فاتورة الدفع';
                showResult('error', msg);
                $form.find('#yamama-pay-ship').prop('disabled', false);
            }
        }).fail(function () {
            hideSpinner();
            showResult('error', 'خطأ في الاتصال بخادم الدفع');
            $form.find('#yamama-pay-ship').prop('disabled', false);
        });
    }

    /* ──────────────────────────────────────────────
     *  Create Shipment Order
     * ────────────────────────────────────────────── */

    function createShipmentOrder(paymentId, paymentSource) {
        paymentSource = paymentSource || 'tap';
        showSpinner();
        $form.find('#yamama-result').hide();

        $.post(ajaxUrl, {
            action: 'yamama_create_order',
            nonce: nonce,
            order_id: orderId,
            payment_id: paymentId,
            payment_source: paymentSource
        }, function (res) {
            hideSpinner();

            if (res.success) {
                var d = res.data;
                var html = '<div class="notice notice-success inline">';
                html += '<p><strong>تم إنشاء الشحنة بنجاح!</strong></p>';
                if (d.lamha_order_id) html += '<p>Yamama Order: <code>' + d.lamha_order_id + '</code></p>';
                if (d.tracking_number) html += '<p>رقم التتبع: <code>' + d.tracking_number + '</code></p>';
                if (d.tracking_link) html += '<p><a href="' + d.tracking_link + '" target="_blank">رابط التتبع</a></p>';
                html += '<p><em>سيتم تحديث الصفحة خلال ثوان...</em></p>';
                html += '</div>';

                $form.find('#yamama-result').html(html).show();
                $form.find('.yamama-section, .yamama-actions').hide();

                setTimeout(function () { location.reload(); }, 3000);
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'فشل إنشاء الشحنة';
                showResult('error', msg);
                $form.find('#yamama-pay-ship').prop('disabled', false);
            }
        }).fail(function () {
            hideSpinner();
            showResult('error', 'خطأ في الاتصال بالخادم');
            $form.find('#yamama-pay-ship').prop('disabled', false);
        });
    }

    /* ──────────────────────────────────────────────
     *  Handle Tap Redirect Result
     * ────────────────────────────────────────────── */

    (function handleTapResult() {
        var params = new URLSearchParams(window.location.search);

        function getCleanUrl() {
            var url = new URL(window.location.href);
            url.searchParams.delete('yamama_success');
            url.searchParams.delete('yamama_tap_complete');
            url.searchParams.delete('yamama_error');
            url.searchParams.delete('orderNumber');
            url.searchParams.delete('tap_id');
            url.searchParams.delete('transactionNo');
            return url.toString();
        }

        if (params.get('yamama_success') === '1') {
            showResult('success', 'تم إنشاء الشحنة بنجاح عبر الدفع الإلكتروني!');
            $form.find('.yamama-section, .yamama-actions').hide();
            setTimeout(function () { window.location.href = getCleanUrl(); }, 2000);
            return;
        }

        if (params.get('yamama_tap_complete') === '1') {
            showResult('success', 'تم الدفع بنجاح. جاري إنشاء الشحنة...');
            showSpinner();

            $.post(ajaxUrl, {
                action: 'yamama_complete_tap',
                nonce: nonce,
                order_id: orderId
            }, function (res) {
                hideSpinner();
                if (res.success) {
                    var d = res.data;
                    var html = '<div class="notice notice-success inline">';
                    html += '<p><strong>تم إنشاء الشحنة بنجاح!</strong></p>';
                    if (d.lamha_order_id) html += '<p>Yamama Order: <code>' + d.lamha_order_id + '</code></p>';
                    if (d.tracking_number) html += '<p>رقم التتبع: <code>' + d.tracking_number + '</code></p>';
                    html += '<p><em>سيتم تحديث الصفحة خلال ثوان...</em></p>';
                    html += '</div>';
                    $form.find('#yamama-result').html(html).show();
                    $form.find('.yamama-section, .yamama-actions').hide();
                    setTimeout(function () { window.location.href = getCleanUrl(); }, 3000);
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'فشل إنشاء الشحنة';
                    showResult('error', msg);
                }
            }).fail(function () {
                hideSpinner();
                showResult('error', 'خطأ في الاتصال بالخادم');
            });
            return;
        }

        var error = params.get('yamama_error');
        if (error) {
            var errorMessages = {
                'missing_params': 'بيانات الدفع غير مكتملة.',
                'order_not_found': 'الطلب غير موجود.',
                'payment_failed': 'فشل التحقق من الدفع.',
                'no_pending_data': 'لم يتم العثور على بيانات الشحنة المعلقة.',
                'create_failed': 'فشل إنشاء الشحنة بعد الدفع. راجع ملاحظات الطلب.'
            };
            var msg = errorMessages[error] || 'حدث خطأ غير معروف.';
            showResult('error', msg);
        }
    })();

})(jQuery);
