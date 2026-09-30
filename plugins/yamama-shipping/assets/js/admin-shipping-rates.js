(function ($) {
    'use strict';

    if (typeof yamamaShipping === 'undefined') return;

    var ajaxUrl = yamamaShipping.ajaxUrl;
    var nonce = yamamaShipping.nonce;
    var timeoutMs = 15000;
    var walletReady = false;

    function setWalletLoading(loading) {
        $('#walletSection').toggleClass('is-loading', loading);
    }

    function setWalletActionsEnabled(enabled) {
        $('#walletTopupBtn, #walletWithdrawBtn').prop('disabled', !enabled);
    }

    function showModalStatus($el, message, type) {
        $el.removeClass('is-error is-success').addClass('is-visible');
        if (type === 'error') $el.addClass('is-error');
        if (type === 'success') $el.addClass('is-success');
        $el.html('<span class="yamama-wallet-spinner"></span><span>' + message + '</span>');
    }

    function hideModalStatus($el) {
        $el.removeClass('is-visible is-error is-success').empty();
    }

    function setButtonLoading($btn, loading, loadingLabel) {
        if (loading) {
            $btn.addClass('is-loading').prop('disabled', true);
            $btn.find('.yamama-btn-label').text(loadingLabel || 'جاري المعالجة...');
        } else {
            $btn.removeClass('is-loading').prop('disabled', false);
        }
    }

    function openModal(id) {
        var $modal = $('#' + id);
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function closeModal(id) {
        var $modal = $('#' + id);
        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        if (!$('.yamama-modal.is-open').length) {
            $('body').css('overflow', '');
        }
    }

    function walletErrorHint(msg) {
        if (/relation|StoreWallet|does not exist|404|500|wallet/i.test(msg || '')) {
            return 'جداول المحفظة غير مُفعّلة على منصة الشحن — طبّق migration ثم أعد المحاولة';
        }
        return msg || 'تعذّر تحميل الرصيد';
    }

    var FIELD_DEFS = [
        { key: 'shippingFee', label: 'رسوم الشحن', unit: 'ر.س', color: '#059669', format: 'money' },
        { key: 'freeWeightLimit', label: 'حد الوزن المجاني', unit: 'كجم', color: '#1d4ed8', format: 'weight' },
        { key: 'extraWeightFee', label: 'رسوم الوزن الإضافي', unit: 'ر.س/كجم', color: '#7c3aed', format: 'money' },
        { key: 'returnFee', label: 'رسوم الإرجاع', unit: 'ر.س', color: '#dc2626', format: 'money' },
        { key: 'codFee', label: 'رسوم COD', unit: 'ر.س', color: '#d97706', format: 'money' },
        { key: 'posFee', label: 'رسوم POS', unit: 'ر.س', color: '#0891b2', format: 'money' },
        { key: 'taxRate', label: 'الضريبة', unit: '%', color: '#6b7280', format: 'tax' }
    ];

    function formatMoney(value) {
        var num = parseFloat(value);
        if (!num || num <= 0) return '—';
        return num.toFixed(2);
    }

    function formatWeight(value) {
        var num = parseFloat(value);
        if (!num || num <= 0) return '—';
        return num;
    }

    function cityLabel(cityName) {
        return cityName === '*' ? 'جميع المدن (افتراضي)' : cityName;
    }

    function formatFieldValue(rule, field) {
        var raw = rule[field.key];
        if (field.format === 'tax') {
            var tax = parseFloat(raw || 0);
            return tax > 0 ? tax : '—';
        }
        if (field.format === 'weight') return formatWeight(raw);
        return formatMoney(raw);
    }

    function showFieldUnit(rule, field) {
        var val = formatFieldValue(rule, field);
        if (val === '—') return '';
        return field.unit;
    }

    function showError(msg) {
        $('#shippingRatesLoading').hide();
        $('#shippingRatesError p').text(msg);
        $('#shippingRatesError').show();
    }

    function renderRuleFields(rule) {
        var html = '<div class="yamama-pricing-fields">';
        FIELD_DEFS.forEach(function (field) {
            var val = formatFieldValue(rule, field);
            var unit = showFieldUnit(rule, field);
            html += '<div class="yamama-pricing-field">';
            html += '<div class="yamama-pricing-field-label">' + field.label + '</div>';
            html += '<div class="yamama-pricing-field-value" style="color:' + field.color + ';">';
            html += val;
            if (unit) {
                html += ' <span class="yamama-pricing-field-unit">' + unit + '</span>';
            }
            html += '</div></div>';
        });
        html += '</div>';
        return html;
    }

    function renderCarriers(carriers) {
        if (!carriers || !carriers.length) {
            return '<div class="yamamah-connection-card mb-5" style="text-align:center;padding:32px;">' +
                '<p class="text-muted" style="margin:0;">لا توجد أسعار مُعدّة حالياً على المنصة.</p></div>';
        }

        var html = '';
        carriers.forEach(function (carrier) {
            var rules = carrier.rules || [];
            html += '<div class="yamama-pricing-carrier">';
            html += '<div class="yamama-pricing-carrier-header">';
            html += '<div>';
            html += '<h2>' + (carrier.carrierName || '') + '</h2>';
            html += '<p class="yamama-pricing-meta">' + rules.length + ' ' + (rules.length === 1 ? 'مدينة' : 'مدن') + '</p>';
            html += '</div>';
            if (carrier.supportsCod) {
                html += '<span class="yamama-pricing-cod-badge">يدعم الدفع عند الاستلام</span>';
            }
            html += '</div>';

            rules.forEach(function (rule) {
                html += '<div class="yamama-pricing-city-block">';
                html += '<h4>' + cityLabel(rule.cityName || '') + '</h4>';
                html += renderRuleFields(rule);
                html += '</div>';
            });

            html += '</div>';
        });
        return html;
    }

    function txTypeLabel(type) {
        var labels = {
            topup: 'شحن رصيد',
            shipment_debit: 'دفع شحنة',
            withdraw_request: 'طلب سحب',
            withdraw_completed: 'سحب مكتمل',
            withdraw_rejected: 'سحب مرفوض'
        };
        return labels[type] || type || '—';
    }

    function renderWallet(data) {
        var balance = parseFloat((data && data.balance) || 0);
        $('#walletBalanceAmount').text(balance.toFixed(2));

        var txs = (data && data.transactions) || [];
        if (!txs.length) {
            $('#walletTransactionsBody').html('<tr><td colspan="4" class="text-muted">لا توجد حركات بعد</td></tr>');
            return;
        }

        var rows = '';
        txs.forEach(function (tx) {
            rows += '<tr>';
            rows += '<td>' + txTypeLabel(tx.type) + '</td>';
            rows += '<td>' + formatMoney(tx.amount) + ' ر.س</td>';
            rows += '<td>' + (tx.status || '—') + '</td>';
            rows += '<td>' + (tx.createdAt ? tx.createdAt.slice(0, 10) : '—') + '</td>';
            rows += '</tr>';
        });
        $('#walletTransactionsBody').html(rows);
    }

    function loadWallet() {
        setWalletLoading(true);
        setWalletActionsEnabled(false);
        walletReady = false;

        return $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'yamama_get_wallet', nonce: nonce },
            timeout: timeoutMs
        }).always(function (res) {
            setWalletLoading(false);

            if (res === 0 || res === '0') {
                $('#walletBalanceAmount').text('—');
                $('#walletTransactionsBody').html(
                    '<tr><td colspan="4" style="color:var(--red);">انتهت الجلسة — أعد تحميل الصفحة</td></tr>'
                );
                setWalletActionsEnabled(false);
                return;
            }
            if (res && res.success && res.data) {
                renderWallet(res.data);
                $('#walletLoadError').hide();
                walletReady = true;
                setWalletActionsEnabled(true);
                return;
            }
            var msg = (res && res.data && res.data.message) ? res.data.message : '';
            var hint = walletErrorHint(msg);
            $('#walletBalanceAmount').text('—');
            $('#walletTransactionsBody').html(
                '<tr><td colspan="4" style="color:var(--red);">' + hint + '</td></tr>'
            );
            $('#walletLoadError').text(hint).show();
            setWalletActionsEnabled(false);
        }).fail(function () {
            setWalletLoading(false);
            var hint = 'تعذّر الاتصال بمنصة الشحن لتحميل الرصيد';
            $('#walletBalanceAmount').text('—');
            $('#walletTransactionsBody').html(
                '<tr><td colspan="4" style="color:var(--red);">' + hint + '</td></tr>'
            );
            $('#walletLoadError').text(hint).show();
            setWalletActionsEnabled(false);
        });
    }

    function loadPricing() {
        var pricingRequest = $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'yamama_get_platform_pricing', nonce: nonce },
            timeout: timeoutMs
        });

        var carriersRequest = $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'yamama_get_carriers', nonce: nonce },
            timeout: timeoutMs
        });

        var timer = setTimeout(function () {
            if (pricingRequest.readyState !== 4) {
                pricingRequest.abort();
                showError('انتهت مهلة تحميل الأسعار. تأكد من نشر API المنصة ثم أعد المحاولة.');
            }
        }, timeoutMs);

        $.when(pricingRequest, carriersRequest).always(function () {
            clearTimeout(timer);
        });

        $.when(pricingRequest, carriersRequest).done(function (pricingRes, carriersRes) {
            $('#shippingRatesLoading').hide();

            var res = pricingRes[0];
            if (res === 0) {
                showError('انتهت الجلسة أو غير مصرح. أعد تحميل الصفحة.');
                return;
            }
            if (!res.success || !res.data) {
                showError((res.data && res.data.message) ? res.data.message : 'تعذّر تحميل الأسعار');
                return;
            }

            var nameMap = {};
            var codMap = {};
            var carriersPayload = carriersRes[0];
            if (carriersPayload && carriersPayload.success && carriersPayload.data && carriersPayload.data.carriers) {
                carriersPayload.data.carriers.forEach(function (c) {
                    var id = String(c.carrier_id);
                    if (c.name) nameMap[id] = c.name;
                    codMap[id] = !!c.has_cod;
                });
            }

            var carriers = res.data.carriers || [];
            carriers.forEach(function (carrier) {
                var id = String(carrier.carrierId);
                var placeholder = /^شركة #\d+$/.test(carrier.carrierName || '');
                if (nameMap[id] && (!carrier.carrierName || placeholder)) {
                    carrier.carrierName = nameMap[id];
                }
                if (codMap[id]) carrier.supportsCod = true;
            });

            $('#shippingRatesContent').html(renderCarriers(carriers)).show();
        }).fail(function (_p, _c, status) {
            if (status === 'abort') return;
            showError('خطأ في الاتصال بمنصة الشحن');
        });
    }

    $(document).ready(function () {
        var $root = $('#shippingRatesRoot');
        if (!$root.length || $root.data('connected') !== 1) return;

        loadWallet();
        loadPricing();

        if (window.location.search.indexOf('wallet_topup=1') !== -1) {
            $('#walletTopupNotice').show();
            loadWallet();
        }

        $('#walletTopupBtn').on('click', function () {
            if (!walletReady) {
                alert($('#walletLoadError').text() || 'الرصيد غير جاهز بعد. انتظر التحميل أو راجع رسالة الخطأ.');
                return;
            }
            $('#walletTopupAmount').val('');
            $('#walletTopupError').hide();
            hideModalStatus($('#walletTopupStatus'));
            setButtonLoading($('#walletTopupSubmit'), false);
            $('#walletTopupSubmit .yamama-btn-label').text('متابعة للدفع');
            openModal('walletTopupModal');
            setTimeout(function () { $('#walletTopupAmount').trigger('focus'); }, 100);
        });

        $('#walletWithdrawBtn').on('click', function () {
            if (!walletReady) {
                alert($('#walletLoadError').text() || 'الرصيد غير جاهز بعد.');
                return;
            }
            $('#walletWithdrawForm')[0].reset();
            $('#walletWithdrawError').hide();
            hideModalStatus($('#walletWithdrawStatus'));
            setButtonLoading($('#walletWithdrawSubmit'), false);
            $('#walletWithdrawSubmit .yamama-btn-label').text('إرسال الطلب');
            openModal('walletWithdrawModal');
        });

        $('[data-modal-close]').on('click', function () {
            var id = $(this).data('modal-close');
            if (id) closeModal(id);
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('.yamama-modal.is-open').each(function () {
                    closeModal(this.id);
                });
            }
        });

        $('#walletTopupSubmit').on('click', function () {
            var $btn = $(this);
            var amount = parseFloat($('#walletTopupAmount').val());

            if (!amount || amount <= 0) {
                $('#walletTopupError').text('يرجى إدخال مبلغ صالح.').show();
                hideModalStatus($('#walletTopupStatus'));
                return;
            }

            $('#walletTopupError').hide();
            showModalStatus($('#walletTopupStatus'), 'جاري تجهيز صفحة الدفع Tap...', 'info');
            setButtonLoading($btn, true, 'جاري التجهيز...');

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'yamama_create_wallet_topup',
                    nonce: nonce,
                    amount: amount
                },
                timeout: timeoutMs
            }).done(function (res) {
                if (res === 0 || res === '0') {
                    showModalStatus($('#walletTopupStatus'), 'انتهت الجلسة — أعد تحميل الصفحة', 'error');
                    setButtonLoading($btn, false);
                    $btn.find('.yamama-btn-label').text('متابعة للدفع');
                    return;
                }
                if (res.success && res.data && res.data.checkout_url) {
                    showModalStatus($('#walletTopupStatus'), 'تم! جاري التحويل إلى Tap...', 'success');
                    setButtonLoading($btn, true, 'جاري التحويل...');
                    window.location.href = res.data.checkout_url;
                    return;
                }
                var msg = walletErrorHint((res.data && res.data.message) || 'فشل شحن الرصيد');
                $('#walletTopupError').text(msg).show();
                showModalStatus($('#walletTopupStatus'), msg, 'error');
                setButtonLoading($btn, false);
                $btn.find('.yamama-btn-label').text('متابعة للدفع');
            }).fail(function (_x, status) {
                var msg = status === 'timeout'
                    ? 'انتهت مهلة الاتصال — حاول مرة أخرى'
                    : 'خطأ في الاتصال بمنصة الشحن';
                $('#walletTopupError').text(msg).show();
                showModalStatus($('#walletTopupStatus'), msg, 'error');
                setButtonLoading($btn, false);
                $btn.find('.yamama-btn-label').text('متابعة للدفع');
            });
        });

        $('#walletWithdrawSubmit').on('click', function () {
            var $btn = $(this);
            var amount = parseFloat($('#walletWithdrawAmount').val());
            var bank = $('#walletWithdrawBank').val();
            var holder = $('#walletWithdrawHolder').val();
            var iban = $('#walletWithdrawIban').val();
            var notes = $('#walletWithdrawNotes').val();

            if (!amount || amount <= 0 || !bank || !holder || !iban) {
                $('#walletWithdrawError').text('يرجى تعبئة جميع الحقول المطلوبة.').show();
                hideModalStatus($('#walletWithdrawStatus'));
                return;
            }

            $('#walletWithdrawError').hide();
            showModalStatus($('#walletWithdrawStatus'), 'جاري إرسال طلب السحب...', 'info');
            setButtonLoading($btn, true, 'جاري الإرسال...');

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'yamama_request_wallet_withdraw',
                    nonce: nonce,
                    amount: amount,
                    bank_name: bank,
                    account_holder: holder,
                    iban: iban,
                    notes: notes
                },
                timeout: timeoutMs
            }).done(function (res) {
                if (res.success) {
                    showModalStatus($('#walletWithdrawStatus'), 'تم إرسال الطلب بنجاح', 'success');
                    setTimeout(function () {
                        closeModal('walletWithdrawModal');
                        loadWallet();
                    }, 900);
                    return;
                }
                var msg = (res.data && res.data.message) || 'فشل الطلب';
                $('#walletWithdrawError').text(msg).show();
                showModalStatus($('#walletWithdrawStatus'), msg, 'error');
                setButtonLoading($btn, false);
                $btn.find('.yamama-btn-label').text('إرسال الطلب');
            }).fail(function (_x, status) {
                var msg = status === 'timeout' ? 'انتهت مهلة الاتصال' : 'خطأ في الاتصال';
                $('#walletWithdrawError').text(msg).show();
                showModalStatus($('#walletWithdrawStatus'), msg, 'error');
                setButtonLoading($btn, false);
                $btn.find('.yamama-btn-label').text('إرسال الطلب');
            });
        });
    });
})(jQuery);
