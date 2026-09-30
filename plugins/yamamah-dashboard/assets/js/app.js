/**
 * اليمامة للحلول التقنية - WordPress Plugin Dashboard
 * ============================================
 * يتكامل مع WordPress و WooCommerce
 * يستخدم wp_localize_script لتمرير البيانات من PHP
 * ============================================
 */

(function($) {
    'use strict';

    // ---- SVG Icons ----
    const icons = {
        home: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        settings: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
        package: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
        creditCard: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>',
        truck: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>',
        barChart: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>',
        users: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        store: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></svg>',
        palette: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>',
        image: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>',
        helpCircle: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>',
        chevronDown: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
        search: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
        moon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>',
        sun: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>',
        externalLink: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>',
        bell: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
        mail: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
        menu: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>',
        x: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
        eye: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>',
        printer: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>',
    };

    // ---- State ----
    let isDark = localStorage.getItem('yamamah_dark') === 'true';
    let sidebarOpen = false;

    // ---- Initialize ----
    $(document).ready(function() {
        setupIcons();
        setupTopBar();
        setupSidebarSearch();
        setupDarkMode();
        setupForms();
        setupPeriodButtons();
        setupDashboardCharts();
        setupNotifications();
        setupDateInputsRtlFix();
    });

    /**
     * Chrome على صفحات RTL يعكس حروف placeholder العربية لحقول date
     * (يوم/شهر/سنة → موي/رهش/ةنس). نفرض LTR + lang=en على كل حقول التاريخ.
     */
    function setupDateInputsRtlFix() {
        var selector = 'input[type="date"], input[type="datetime-local"], input[type="month"], input[type="week"], input[type="time"]';

        function fix($root) {
            $root.find(selector).addBack(selector).each(function() {
                this.setAttribute('dir', 'ltr');
                this.setAttribute('lang', 'en');
                this.classList.add('ltr');
            });
        }

        var $wrap = $('.yamamah-wrap');
        if (!$wrap.length) {
            $wrap = $(document.body);
        }
        fix($wrap);

        if (typeof MutationObserver === 'undefined') {
            return;
        }
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (!m.addedNodes || !m.addedNodes.length) {
                    return;
                }
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) {
                        return;
                    }
                    fix($(node));
                });
            });
        });
        $wrap.each(function() {
            observer.observe(this, { childList: true, subtree: true });
        });
    }

    // ---- Inject SVG Icons ----
    function setupIcons() {
        // Sidebar menu icons
        $('[data-icon]').each(function() {
            const iconName = $(this).data('icon');
            if (icons[iconName]) {
                $(this).html(icons[iconName]);
            }
        });

        // TopBar icons
        $('#darkToggle').html(isDark ? icons.sun : icons.moon);
        $('#hamburgerBtn').html(icons.menu);
        $('#sidebarClose').html(icons.x);

        // Search icon
        $('.search-box .search-icon').html(icons.search);

        // TopBar left icons
        var $notifBtn = $('#yamamahNotifBtn');
        if ($notifBtn.length) {
            var countEl = $notifBtn.find('.yamamah-notif-count').detach();
            $notifBtn.html(icons.bell);
            $notifBtn.append(countEl);
        }
        var $mailBtn = $('#yamamahMailBtn');
        if ($mailBtn.length) {
            $mailBtn.html(icons.mail);
        }
        // Store link
        const storeLink = $('.btn-store');
        if (storeLink.length) {
            const text = storeLink.find('span').text() || 'اذهب إلى المتجر';
            storeLink.html(icons.externalLink + '<span>' + text + '</span>');
        }
    }

    // ---- Top Bar Setup ----
    function setupTopBar() {
        $('#hamburgerBtn').on('click', function() {
            sidebarOpen = !sidebarOpen;
            updateSidebarVisibility();
        });

        $('#sidebarClose, #sidebarOverlay').on('click', function() {
            sidebarOpen = false;
            updateSidebarVisibility();
        });

        // Account dropdown
        $('#yamamahAvatarBtn').on('click', function(e) {
            e.stopPropagation();
            $('#yamamahNotifPanel').removeClass('open');
            $('#yamamahAccountDropdown').toggleClass('open');
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#yamamahAccountMenu').length) {
                $('#yamamahAccountDropdown').removeClass('open');
            }
        });
    }

    // ---- Sidebar text filter ----
    function setupSidebarSearch() {
        var $input = $('#yamamahSearch');
        if (!$input.length) return;

        $input.on('input', function() {
            var q = String($(this).val() || '').trim().toLowerCase();
            var $menu = $('#sidebarMenu');
            if (!$menu.length) return;

            $menu.children().each(function() {
                var $el = $(this);
                if ($el.is('.submenu')) {
                    return;
                }
                if ($el.is('.menu-item')) {
                    var label = $el.find('.menu-label').text().toLowerCase() || $el.text().toLowerCase();
                    var $submenu = $el.next('.submenu');
                    if ($submenu.length) {
                        var childMatch = false;
                        $submenu.find('.sub-item').each(function() {
                            var st = $(this).text().toLowerCase();
                            var sm = !q || st.indexOf(q) !== -1;
                            $(this).toggle(sm);
                            if (sm) childMatch = true;
                        });
                        var parentMatch = !q || label.indexOf(q) !== -1 || childMatch;
                        $el.toggle(parentMatch);
                        if (parentMatch && childMatch && q) {
                            $submenu.addClass('open').show();
                        } else if (!q) {
                            $submenu.toggle($submenu.hasClass('open'));
                            $submenu.find('.sub-item').show();
                        } else if (!childMatch) {
                            $submenu.hide();
                        } else {
                            $submenu.show();
                        }
                    } else {
                        $el.toggle(!q || label.indexOf(q) !== -1);
                    }
                }
            });
        });
    }

    function updateSidebarVisibility() {
        $('#sidebar').toggleClass('open', sidebarOpen);
        $('#sidebarOverlay').toggleClass('show', sidebarOpen);
    }

    // ---- Dark Mode ----
    function setupDarkMode() {
        applyDarkMode();
        document.documentElement.removeAttribute('data-yamamah-dark');

        $('#darkToggle').on('click', function() {
            isDark = !isDark;
            localStorage.setItem('yamamah_dark', String(isDark));
            applyDarkMode();

            $.post(yamamahData.ajax_url, {
                action: 'yamamah_toggle_dark_mode',
                nonce:  yamamahData.nonce,
                dark:   isDark ? 'yes' : 'no'
            });
        });
    }

    function applyDarkMode() {
        $('.yamamah-wrap').toggleClass('dark', isDark);
        $('#darkToggle').html(isDark ? icons.sun : icons.moon);
    }

    // ---- Submenu Toggle ----
    window.yamamahToggleSubmenu = function(submenuId, btn) {
        const submenu = document.getElementById(submenuId);
        if (!submenu) return;

        submenu.classList.toggle('open');
        const arrow = btn.querySelector('.menu-arrow');
        if (arrow) arrow.classList.toggle('expanded');
    };

    // ---- Forms & AJAX ----
    function setupForms() {
        // Settings form
        $('#yamamahSettingsForm').on('submit', function(e) {
            e.preventDefault();
            saveSettings($(this));
        });

        // Appearance form
        $('#yamamahAppearanceForm').on('submit', function(e) {
            e.preventDefault();
            saveSettings($(this));
        });

        // Home content form — تسلسل النموذج ليصل home_content كمصفوفة متداخلة في PHP (توافق مع باقي الثيمات)
        $('#yamamahHomeContentForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (typeof yamamahData === 'undefined') {
                $('#homeContentSaveResult').fadeIn().delay(2500).fadeOut();
                return;
            }
            var data = form.serialize();
            if (data.length) {
                data += '&';
            }
            data += 'action=yamamah_save_home_content&nonce=' + encodeURIComponent(yamamahData.nonce);
            $.ajax({
                url: yamamahData.ajax_url,
                type: 'POST',
                data: data,
                dataType: 'json'
            }).done(function(res) {
                if (res && res.success) {
                    $('#homeContentSaveResult').fadeIn();
                    setTimeout(function() { $('#homeContentSaveResult').fadeOut(); }, 2500);
                } else {
                    alert((res && res.data) ? res.data : 'حدث خطأ');
                }
            }).fail(function() { alert('حدث خطأ في الاتصال'); });
        });

        // Support form
        $('#yamamahSupportForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var btn = form.find('button[type="submit"]');
            var origText = btn.text();
            btn.text(yamamahData.i18n.loading || 'جاري الإرسال...').prop('disabled', true);

            if (typeof yamamahData !== 'undefined') {
                $.ajax({
                    url: yamamahData.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'yamamah_submit_support',
                        nonce: yamamahData.nonce,
                        support_type: form.find('[name="support_type"]').val(),
                        support_priority: form.find('[name="support_priority"]').val(),
                        support_subject: form.find('[name="support_subject"]').val(),
                        support_message: form.find('[name="support_message"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#supportSuccess').fadeIn();
                            form[0].reset();
                            setTimeout(function() { $('#supportSuccess').fadeOut(); }, 4000);
                        } else {
                            alert(response.data || 'حدث خطأ');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ في الاتصال');
                    },
                    complete: function() {
                        btn.text(origText).prop('disabled', false);
                    }
                });
            } else {
                $('#supportSuccess').fadeIn();
                setTimeout(function() { $('#supportSuccess').fadeOut(); }, 4000);
                btn.text(origText).prop('disabled', false);
            }
        });

        // Color picker sync
        $('input[type="color"]').on('input', function() {
            $(this).closest('.flex').find('input[type="text"]').val($(this).val());
        });

        // Toggle switches with checkboxes
        // preventDefault يمنع النقر المزدوج على الـ checkbox داخل <label> (كان يحفظ دائماً "لا").
        $('.toggle-label .toggle').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $toggle = $(this);
            var $label = $toggle.closest('.toggle-label');
            var $checkbox = $label.find('input[type="checkbox"]').first();
            var on = !$toggle.hasClass('on');
            $toggle.toggleClass('on', on);
            $checkbox.prop('checked', on);
        });
    }

    function saveSettings(form) {
        const formData = form.serializeArray();
        const settings = {};

        formData.forEach(function(item) {
            if (item.name !== 'yamamah_settings_nonce' && item.name !== '_wp_http_referer') {
                settings[item.name] = item.value;
            }
        });

        // مصدر الحقيقة لمفاتيح التفعيل: حالة الـ toggle المرئية (وليس hidden+checkbox معاً).
        form.find('.toggle-label').each(function() {
            var $label = $(this);
            var $checkbox = $label.find('input[type="checkbox"]').first();
            var name = $checkbox.attr('name');
            if (!name) {
                return;
            }
            var on = $label.find('.toggle').hasClass('on');
            settings[name] = on ? ( $checkbox.val() || 'yes' ) : 'no';
            $checkbox.prop('checked', on);
        });

        // لا حاجة لـ yamamahData إذا لم يكن موجوداً (بيئة غير ووردبريس)
        if (typeof yamamahData === 'undefined') {
            showSaveSuccess();
            return;
        }

        $.ajax({
            url: yamamahData.ajax_url,
            method: 'POST',
            data: {
                action: 'yamamah_save_settings',
                nonce: yamamahData.nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    showSaveSuccess();
                } else {
                    alert(yamamahData.i18n.save_error || 'حدث خطأ');
                }
            },
            error: function() {
                alert(yamamahData.i18n.save_error || 'حدث خطأ في الاتصال');
            }
        });
    }

    function showSaveSuccess() {
        const el = $('#settingsSaveResult');
        if (el.length) {
            el.fadeIn();
            setTimeout(function() { el.fadeOut(); }, 2500);
        }
    }

    // ---- Period Buttons (Dashboard) ----
    function setupPeriodButtons() {
        $('.yamamah-period-btn').on('click', function() {
            const period = $(this).data('period');
            $('.yamamah-period-btn').removeClass('btn-purple active').addClass('btn-accent');
            $(this).removeClass('btn-accent').addClass('btn-purple active');

            // تحديث الإحصائيات عبر AJAX
            if (typeof yamamahData !== 'undefined') {
                refreshDashboardStats(period);
            }
        });
    }

    function refreshDashboardStats(period) {
        var grid = $('#yamamahStatsGrid');
        if (grid.length) {
            grid.css('opacity', '0.5');
        }

        $.ajax({
            url: yamamahData.ajax_url,
            method: 'GET',
            data: {
                action: 'yamamah_get_dashboard_data',
                nonce: yamamahData.nonce,
                period: period
            },
            success: function(response) {
                if (response.success) {
                    var stats = response.data.stats;
                    var cards = grid.find('.stat-card');

                    if (cards.eq(0).length) {
                        cards.eq(0).find('.stat-value').html(stats.total_sales.formatted);
                    }
                    if (cards.eq(1).length) {
                        cards.eq(1).find('.stat-value').text(parseInt(stats.total_orders.value).toLocaleString('ar-SA'));
                    }
                    if (cards.eq(2).length) {
                        cards.eq(2).find('.stat-value').text(parseInt(stats.visits.value).toLocaleString('ar-SA'));
                    }
                    if (cards.eq(3).length) {
                        cards.eq(3).find('.stat-value').text(stats.conversion_rate.value);
                    }
                    if (cards.eq(4).length) {
                        cards.eq(4).find('.stat-value').text(parseInt(stats.new_customers.value).toLocaleString('ar-SA'));
                    }

                    grid.css('opacity', '1');
                }
            },
            error: function() {
                grid.css('opacity', '1');
            }
        });
    }

    // ---- Media Selection ----
    $(document).on('click', '.media-item', function() {
        $(this).toggleClass('selected');
    });

    // ---- Media Uploader ----
    window.yamamahOpenMediaUploader = function() {
        if (typeof wp !== 'undefined' && wp.media) {
            var frame = wp.media({
                title: 'رفع ملف جديد',
                multiple: true,
                library: { type: '' },
                button: { text: 'إدراج' }
            });
            frame.on('select close', function() {
                location.reload();
            });
            frame.open();
        }
    };

    // ============================================
    //  Payment Methods - Toggle
    // ============================================
    window.yamamahTogglePayment = function(methodId, btn) {
        var $btn = $(btn);
        var isOn = $btn.hasClass('on');
        var newState = !isOn;

        $btn.toggleClass('on');

        var $card = $btn.closest('.yamamah-method-card');
        $card.toggleClass('enabled', newState).toggleClass('disabled', !newState);
        var $bar = $card.find('.method-status-bar');
        $bar.toggleClass('active', newState);
        $bar.find('span:first').text(newState ? 'مفعّلة' : 'معطّلة');
        $bar.find('.text-muted').text(newState ? 'متاحة للعملاء' : 'غير متاحة للعملاء');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_toggle_payment_method',
            nonce: yamamahData.nonce,
            method_id: methodId,
            enabled: newState ? 1 : 0
        });
    };

    // ============================================
    //  Entity Type Selector
    // ============================================
    window.yamamahSelectEntity = function(type, btn) {
        $('.entity-option').removeClass('selected');
        $(btn).addClass('selected');
        $('#entityTypeInput').val(type);
        $('.entity-upload-group').hide();
        $('.entity-upload-group[data-entity="' + type + '"]').show();
    };

    // ============================================
    //  File Upload via WP Media
    // ============================================
    window.yamamahUploadFile = function(fieldName, btn) {
        if (typeof wp === 'undefined' || !wp.media) {
            alert('مكتبة الوسائط غير متاحة');
            return;
        }

        var frame = wp.media({
            title: 'اختر ملف',
            multiple: false,
            library: { type: 'image,application/pdf' },
            button: { text: 'اختيار' }
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var $zone = $(btn).closest('.yamamah-upload-zone');
            $zone.find('input[type="hidden"]').val(attachment.url);

            var filename = attachment.filename || attachment.url.split('/').pop();
            $zone.html(
                '<div class="upload-preview">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                    '<span class="upload-filename">' + filename + '</span>' +
                    '<button type="button" class="upload-remove" onclick="yamamahRemoveFile(\'' + fieldName + '\', this)">&times;</button>' +
                '</div>' +
                '<input type="hidden" name="' + fieldName + '" value="' + attachment.url + '" />'
            );
        });

        frame.open();
    };

    window.yamamahRemoveFile = function(fieldName, btn) {
        var $zone = $(btn).closest('.yamamah-upload-zone');
        $zone.html(
            '<div class="upload-empty">' +
                '<button type="button" class="btn btn-orange btn-sm" onclick="yamamahUploadFile(\'' + fieldName + '\', this)">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>' +
                    ' اختر ملف' +
                '</button>' +
                '<span class="upload-hint">' + fieldName.replace(/_/g, ' ') + '</span>' +
            '</div>' +
            '<input type="hidden" name="' + fieldName + '" value="" />'
        );
    };

    // ============================================
    //  Save Payment Settings
    // ============================================
    window.yamamahSavePaymentSettings = function() {
        var $form = $('#paymentSettingsForm');
        var data = {};
        $form.find('input, select').each(function() {
            var name = $(this).attr('name');
            if (name) data[name] = $(this).val();
        });

        var $status = $('#paymentSaveStatus');
        $status.text('جاري الحفظ...').css('color', '');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_payment_settings',
            nonce: yamamahData.nonce,
            payment_settings: data
        }, function(res) {
            if (res.success) {
                $status.text('تم الحفظ بنجاح ✓').css('color', '#2D9B83');
            } else {
                $status.text(res.data || 'حدث خطأ').css('color', '#ef4444');
            }
            setTimeout(function() { $status.text(''); }, 3000);
        }).fail(function() {
            $status.text('خطأ في الاتصال').css('color', '#ef4444');
        });
    };

    // ============================================
    //  Save Shipping Settings
    // ============================================
    window.yamamahSaveShippingSettings = function() {
        var $form = $('#shippingSettingsForm');
        var shipper = {};
        $form.find('input, select').each(function() {
            var name = $(this).attr('name');
            if (name) {
                var key = name.replace('shipper_', '');
                shipper[key] = $(this).val();
            }
        });

        var $status = $('#shippingSaveStatus');
        $status.text('جاري الحفظ...').css('color', '');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_shipping_settings',
            nonce: yamamahData.nonce,
            shipper: shipper
        }, function(res) {
            if (res.success) {
                $status.text('تم الحفظ بنجاح ✓').css('color', '#2D9B83');
            } else {
                $status.text(res.data || 'حدث خطأ').css('color', '#ef4444');
            }
            setTimeout(function() { $status.text(''); }, 3000);
        }).fail(function() {
            $status.text('خطأ في الاتصال').css('color', '#ef4444');
        });
    };

    // ============================================
    //  Re-register Shipping
    // ============================================
    window.yamamahReRegisterShipping = function() {
        var $btn = $('#reRegisterBtn');
        var $status = $('#reRegisterStatus');
        $btn.prop('disabled', true).css('opacity', '0.6');
        $status.text('جاري إعادة التسجيل...').css('color', '');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_reregister_shipping',
            nonce: yamamahData.nonce
        }, function(res) {
            $btn.prop('disabled', false).css('opacity', '1');
            if (res.success) {
                $status.html('<span style="color:#2D9B83;">✓ ' + (res.data.message || 'تم بنجاح') + '</span>');
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                $status.html('<span style="color:#ef4444;">✗ ' + (res.data && res.data.message ? res.data.message : 'فشل') + '</span>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).css('opacity', '1');
            $status.html('<span style="color:#ef4444;">✗ خطأ في الاتصال</span>');
        });
    };

    // ============================================
    //  Copy Text
    // ============================================
    window.yamamahCopyText = function(elementId) {
        var text = document.getElementById(elementId).textContent;
        navigator.clipboard.writeText(text).then(function() {
            var btn = document.getElementById(elementId).nextElementSibling;
            if (btn) {
                var orig = btn.innerHTML;
                btn.innerHTML = '<span style="font-size:11px;color:#2D9B83;">تم النسخ</span>';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            }
        });
    };

    // ============================================
    //  Fetch Shipping Label (for dashboard)
    // ============================================
    $(document).on('click', '.yamamah-fetch-label-btn', function() {
        var $btn = $(this);
        var oid = $btn.data('order-id');
        if (!oid || $btn.prop('disabled')) return;

        $btn.prop('disabled', true).css('opacity', '0.5');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_fetch_label',
            nonce: yamamahData.nonce,
            order_id: oid
        }, function(res) {
            $btn.prop('disabled', false).css('opacity', '1');
            if (res.success && res.data) {
                if (res.data.pdf_base64) {
                    var byteChars = atob(res.data.pdf_base64);
                    var byteNumbers = new Array(byteChars.length);
                    for (var i = 0; i < byteChars.length; i++) {
                        byteNumbers[i] = byteChars.charCodeAt(i);
                    }
                    var blob = new Blob([new Uint8Array(byteNumbers)], { type: 'application/pdf' });
                    window.open(URL.createObjectURL(blob), '_blank');
                } else if (res.data.label_url) {
                    window.open(res.data.label_url, '_blank');
                }
            } else {
                alert((res.data && res.data.message) || 'فشل جلب البوليصة');
            }
        }).fail(function() {
            $btn.prop('disabled', false).css('opacity', '1');
            alert('خطأ في الاتصال');
        });
    });

    /* ========================================
     * صفحات المنتجات المخصصة
     * ======================================== */

    window.yamamahOpenModal = function(id) {
        $('#' + id).css('display', 'flex').hide().fadeIn(200);
    };

    window.yamamahCloseModal = function(id) {
        $('#' + id).fadeOut(200);
    };

    /* Product functions are now defined in add-product.php template */

    /* --- إضافة تصنيف/وسم --- */
    window.yamamahAddTerm = function(taxonomy) {
        var prefix = taxonomy === 'product_cat' ? 'yCat' : 'yTag';
        var data = {
            action:      'yamamah_add_term',
            nonce:       yamamahData.nonce,
            taxonomy:    taxonomy,
            name:        $('#' + prefix + 'Name').val(),
            slug:        $('#' + prefix + 'Slug').val(),
            description: $('#' + prefix + 'Desc').val(),
            parent:      taxonomy === 'product_cat' ? ($('#yCatParent').val() || 0) : 0,
            image_id:    taxonomy === 'product_cat' ? ($('#yCatImageId').val() || 0) : 0
        };

        if (!data.name) { alert('الاسم مطلوب'); return; }

        $.post(yamamahData.ajax_url, data, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    /* --- حذف تصنيف/وسم --- */
    window.yamamahDeleteTerm = function(termId, taxonomy, el) {
        if (!confirm('هل أنت متأكد من حذف هذا العنصر؟')) return;

        $.post(yamamahData.ajax_url, {
            action:   'yamamah_delete_term',
            nonce:    yamamahData.nonce,
            taxonomy: taxonomy,
            term_id:  termId
        }, function(res) {
            if (res.success) {
                var $row = $(el).closest('tr, .yamamah-cat-card');
                $row.fadeOut(300, function(){ $row.remove(); });
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    /* --- رفع صورة محتوى الصفحة الرئيسية --- */
    $(document).on('click', '.yamamah-content-upload-btn', function() {
        var btn = this;
        var $wrap = $(btn).closest('.yamamah-content-image-upload');
        var fieldName = $wrap.data('field');
        var $input = $wrap.find('input[type="hidden"]');
        var $preview = $wrap.find('.yamamah-content-image-preview');
        if (typeof wp === 'undefined' || !wp.media) {
            alert('مكتبة الوسائط غير متاحة');
            return;
        }
        var frame = wp.media({ title: 'اختر صورة', library: { type: 'image' }, multiple: false });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            $input.val(att.id);
            var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            $preview.html('<img src="' + url + '" style="max-width:150px;height:auto;" alt="">');
        });
        frame.open();
    });

    /* --- استعادة محتوى الصفحة الرئيسية --- */
    window.yamamahRestoreHomeContent = function() {
        if (typeof yamamahData === 'undefined') return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_restore_home_content',
            nonce: yamamahData.nonce
        }, function(res) {
            if (res.success) {
                alert(res.data || 'تم الاستعادة');
                location.reload();
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function() { alert('حدث خطأ في الاتصال'); });
    };

    /* --- رفع صورة التصنيف --- */
    window.yamamahUploadCatImage = function() {
        var frame = wp.media({ title: 'اختر صورة التصنيف', multiple: false });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#yCatImageId').val(attachment.id);
            var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            $('#yCatImageWrap').html(
                '<img src="' + url + '" style="max-width:80px;border-radius:6px;" alt="">' +
                '<button type="button" class="btn btn-outline-green btn-sm" onclick="yamamahUploadCatImage()" style="margin-right:8px;">تغيير</button>'
            );
        });
        frame.open();
    };

    /* --- إضافة سمة --- */
    window.yamamahSaveAttribute = function() {
        var data = {
            action:   'yamamah_save_attribute',
            nonce:    yamamahData.nonce,
            name:     $('#yAttrName').val(),
            slug:     $('#yAttrSlug').val(),
            type:     $('#yAttrType').val(),
            order_by: $('#yAttrOrderBy').val(),
            archive:  $('#yAttrArchive').is(':checked') ? 1 : 0
        };

        if (!data.name) { alert('الاسم مطلوب'); return; }

        $.post(yamamahData.ajax_url, data, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    /* --- حذف سمة --- */
    window.yamamahDeleteAttribute = function(attrId, el) {
        if (!confirm('هل أنت متأكد من حذف هذه السمة؟')) return;

        $.post(yamamahData.ajax_url, {
            action:       'yamamah_delete_attribute',
            nonce:        yamamahData.nonce,
            attribute_id: attrId
        }, function(res) {
            if (res.success) {
                var $row = $(el).closest('tr.yamamah-expandable-row');
                $row.next('.yamamah-attr-values-row').fadeOut(300, function(){ $(this).remove(); });
                $row.fadeOut(300, function(){ $row.remove(); });
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    /* --- توسيع قيم السمة --- */
    window.yamamahToggleAttrValues = function(el) {
        var $row = $(el).closest('tr');
        var $valuesRow = $row.next('.yamamah-attr-values-row');
        var $svg = $(el).find('svg');
        if ($valuesRow.is(':visible')) {
            $valuesRow.slideUp(200);
            $svg.css('transform', 'rotate(0)');
        } else {
            $valuesRow.slideDown(200);
            $svg.css('transform', 'rotate(180deg)');
        }
    };

    /* ==========================================
     *  Analytics Settings
     * ========================================= */

    window.yamamahToggleExcludedBadge = function(checkbox) {
        var badge = $(checkbox).closest('.yamamah-status-row').find('.yamamah-status-badge');
        if (checkbox.checked) {
            badge.addClass('badge-active').text('مستبعدة');
        } else {
            badge.removeClass('badge-active').text('مشمولة');
        }
    };

    window.yamamahToggleActionableBadge = function(checkbox) {
        var badge = $(checkbox).closest('.yamamah-status-row').find('.yamamah-status-badge');
        if (checkbox.checked) {
            badge.addClass('badge-active').text('مفعّل');
        } else {
            badge.removeClass('badge-active').text('غير مفعّل');
        }
    };

    window.yamamahSaveAnalyticsSettings = function() {
        var excluded = [];
        $('#excludedStatusList input[name="excluded[]"]:checked').each(function(){ excluded.push($(this).val()); });

        var actionable = [];
        $('#actionableStatusList input[name="actionable[]"]:checked').each(function(){ actionable.push($(this).val()); });

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_analytics_settings',
            nonce: yamamahData.nonce,
            excluded: excluded,
            actionable: actionable,
            date_range: $('#analyticsDateRange').val(),
            date_type: $('#analyticsDateType').val()
        }, function(res) {
            if (res.success) {
                alert(yamamahData.i18n.save_success);
            } else {
                alert(yamamahData.i18n.save_error);
            }
        });
    };

    /* =========================================
     * Users Page — Search & View Modal
     * ======================================== */
    window.yamamahFilterUsersTable = function() {
        var q = $('#usersSearchInput').val().toLowerCase();
        $('#usersTable tbody .yamamah-user-row').each(function() {
            var name = $(this).data('name').toLowerCase();
            var email = $(this).data('email').toLowerCase();
            $(this).toggle(name.indexOf(q) > -1 || email.indexOf(q) > -1);
        });
    };

    window.yamamahViewUser = function(id, name, email, reg, last, orders, spent, initials) {
        $('#modalUserAvatar').text(initials);
        $('#modalUserName').text(name);
        $('#modalUserEmail').text(email);
        $('#modalUserReg').text(reg);
        $('#modalUserLastLogin').text(last);
        $('#modalUserOrders').text(orders);
        $('#modalUserSpent').text(spent);
        $('#userDetailModal').fadeIn(200);
    };

    /* =========================================
     * Admins Page — Search, CRUD, Permissions
     * ======================================== */
    window.yamamahFilterAdminsTable = function() {
        var q = $('#adminsSearchInput').val().toLowerCase();
        $('#adminsTable tbody .yamamah-admin-row').each(function() {
            var name = $(this).data('name').toLowerCase();
            var email = $(this).data('email').toLowerCase();
            var role = $(this).data('role');
            $(this).toggle(name.indexOf(q) > -1 || email.indexOf(q) > -1 || role.indexOf(q) > -1);
        });
    };

    window.yamamahOpenAdminModal = function() {
        $('#adminEditId').val('');
        $('#adminName').val('');
        $('#adminEmail').val('');
        $('#adminPhone').val('');
        $('#adminPassword').val('');
        $('#adminPasswordLabel').html('كلمة المرور <span class="required">*</span>');
        $('input[name="admin_role"][value="editor"]').prop('checked', true);
        $('.yamamah-role-option').removeClass('active');
        $('.yamamah-role-option[data-role="editor"]').addClass('active');
        $('#permGroupsContainer input[type="checkbox"]').prop('checked', false);
        yamamahUpdateAllPermCounts();
        $('#adminModalTitle').text('إضافة مدير جديد');
        $('#adminSaveBtn').html('<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-left:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> إنشاء المدير');
        $('#adminModal').css('display', 'flex').hide().fadeIn(200);
    };

    window.yamamahEditAdmin = function(userId) {
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_get_admin',
            nonce: yamamahData.nonce,
            user_id: userId
        }, function(res) {
            if (!res.success) { alert(res.data.message); return; }
            var d = res.data;
            $('#adminEditId').val(d.id);
            $('#adminName').val(d.name);
            $('#adminEmail').val(d.email);
            $('#adminPhone').val(d.phone || '');
            $('#adminPassword').val('');
            $('#adminPasswordLabel').text('كلمة مرور جديدة');
            $('input[name="admin_role"][value="' + d.role + '"]').prop('checked', true);
            $('.yamamah-role-option').removeClass('active');
            $('.yamamah-role-option[data-role="' + d.role + '"]').addClass('active');
            $('#permGroupsContainer input[type="checkbox"]').prop('checked', false);
            if (d.perms && d.perms.length) {
                d.perms.forEach(function(p) {
                    $('#permGroupsContainer input[value="' + p + '"]').prop('checked', true);
                });
            }
            yamamahUpdateAllPermCounts();
            $('#adminModalTitle').text('تعديل المدير');
            $('#adminSaveBtn').html('<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-left:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> حفظ التعديلات');
            $('#adminModal').css('display', 'flex').hide().fadeIn(200);
        });
    };

    window.yamamahSaveAdmin = function() {
        var formData = {
            action: 'yamamah_save_admin',
            nonce: yamamahData.nonce,
            edit_id: $('#adminEditId').val(),
            name: $('#adminName').val(),
            email: $('#adminEmail').val(),
            phone: $('#adminPhone').val(),
            password: $('#adminPassword').val(),
            role: $('input[name="admin_role"]:checked').val(),
            perms: []
        };
        $('#permGroupsContainer input[name="perms[]"]:checked').each(function() {
            formData.perms.push($(this).val());
        });
        $.post(yamamahData.ajax_url, formData, function(res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message);
            }
        });
    };

    window.yamamahDeleteAdmin = function(userId) {
        if (!confirm('هل أنت متأكد من حذف هذا المدير؟')) return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_admin',
            nonce: yamamahData.nonce,
            user_id: userId
        }, function(res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message);
            }
        });
    };

    window.yamamahTogglePermGroup = function(btn) {
        var body = $(btn).next('.yamamah-perm-group-body');
        var chevron = $(btn).find('.yamamah-perm-chevron');
        body.slideToggle(200);
        chevron.toggleClass('rotated');
    };

    window.yamamahUpdatePermCount = function(groupId) {
        var total = $('#permGroupsContainer input[data-group="' + groupId + '"]').length;
        var checked = $('#permGroupsContainer input[data-group="' + groupId + '"]:checked').length;
        var $badge = $('.yamamah-perm-count[data-group="' + groupId + '"]');
        $badge.text(checked + '/' + total);
        $badge.toggleClass('has-checked', checked > 0);

        var $groupToggle = $('input[data-group-toggle="' + groupId + '"]');
        $groupToggle.prop('checked', checked === total && total > 0);

        yamamahUpdateTotalLocked();
    };

    function yamamahUpdateAllPermCounts() {
        var groups = [];
        $('#permGroupsContainer input[name="perms[]"]').each(function() {
            var g = $(this).data('group');
            if (g && groups.indexOf(g) === -1) groups.push(g);
        });
        groups.forEach(function(g) { yamamahUpdatePermCount(g); });
    }

    function yamamahUpdateTotalLocked() {
        var total = $('#permGroupsContainer input[name="perms[]"]').length;
        var checked = $('#permGroupsContainer input[name="perms[]"]:checked').length;
        var locked = total - checked;
        $('#totalPermLocked').text(locked > 0 ? locked + ' صلاحية مقفلة' : 'جميع الصلاحيات مفعّلة');
    }

    window.yamamahToggleGroupAll = function(el, groupId) {
        var isChecked = $(el).is(':checked');
        $('#permGroupsContainer input[data-group="' + groupId + '"]').prop('checked', isChecked);
        yamamahUpdatePermCount(groupId);
        yamamahCheckCustomRole();
    };

    window.yamamahCheckCustomRole = function() {
        var currentRole = $('input[name="admin_role"]:checked').val();
        if (currentRole !== 'custom') {
            $('input[name="admin_role"][value="custom"]').prop('checked', true);
            $('.yamamah-role-option').removeClass('active');
            $('.yamamah-role-option[data-role="custom"]').addClass('active');
        }
    };

    // Role selector click
    $(document).on('change', 'input[name="admin_role"]', function() {
        $('.yamamah-role-option').removeClass('active');
        $(this).closest('.yamamah-role-option').addClass('active');
    });

    /* ======================================
     * Logo Upload / Remove
     * ====================================== */
    window.yamamahUploadLogo = function(type) {
        var frame = wp.media({ title: 'اختر صورة', multiple: false, library: { type: 'image' } });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var previewId = type === 'header' ? 'headerLogoPreview'
                          : type === 'footer' ? 'footerLogoPreview'
                          : 'faviconPreview';
            var inputId   = type === 'header' ? 'headerLogoId'
                          : type === 'footer' ? 'footerLogoId'
                          : 'faviconId';
            $('#' + previewId).html('<img src="' + attachment.url + '" style="max-width:100%;max-height:100%;object-fit:contain;">');
            $('#' + inputId).val(attachment.id);

            var $field = $('#' + previewId).closest('.yamamah-logo-field');
            $field.find('.text-xs.text-muted[style]').remove();
            $field.find('.btn').first().text('تغيير');
            if (!$field.find('.btn[style*="ef4444"]').length) {
                $('<button type="button" class="btn btn-outline btn-sm" style="color:#ef4444;" onclick="yamamahRemoveLogo(\'' + type + '\')">إزالة</button>')
                    .insertAfter($field.find('.btn').first());
            }

            $.post(yamamahData.ajax_url, {
                action: 'yamamah_save_logo',
                nonce: yamamahData.nonce,
                type: type,
                attachment_id: attachment.id
            });
        });
        frame.open();
    };

    window.yamamahRemoveLogo = function(type) {
        var previewId = type === 'header' ? 'headerLogoPreview'
                      : type === 'footer' ? 'footerLogoPreview'
                      : 'faviconPreview';
        var inputId   = type === 'header' ? 'headerLogoId'
                      : type === 'footer' ? 'footerLogoId'
                      : 'faviconId';

        var defaults = (typeof yamamahData !== 'undefined' && yamamahData.theme_defaults) ? yamamahData.theme_defaults : {};
        var fallbackUrl = (type === 'favicon') ? defaults.icon : defaults.logo;

        if (fallbackUrl) {
            var alt = (type === 'favicon') ? 'أيقونة الموقع' : 'شعار';
            $('#' + previewId).html('<img src="' + fallbackUrl + '" alt="' + alt + '" style="max-width:100%;max-height:100%;object-fit:contain;">');
        } else {
            var label = type === 'favicon' ? 'لا يوجد أيقونة' : 'لا يوجد شعار';
            $('#' + previewId).html('<span class="text-xs text-muted">' + label + '</span>');
        }

        $('#' + inputId).val(0);

        var $field = $('#' + previewId).closest('.yamamah-logo-field');
        $field.find('.btn[style*="ef4444"]').remove();
        $field.find('.btn').first().text('رفع صورة');
        if (fallbackUrl) {
            if (!$field.find('.text-xs.text-muted[style]').length) {
                var note = type === 'favicon' ? 'الأيقونة الافتراضية من القالب' : 'الشعار الافتراضي من القالب';
                $('<span class="text-xs text-muted" style="display:block;margin-top:4px;">' + note + '</span>').insertAfter('#' + previewId);
            }
        }

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_logo',
            nonce: yamamahData.nonce,
            type: type,
            attachment_id: 0
        });
    };

    /* ======================================
     * Delete Media Attachment
     * ====================================== */
    window.yamamahDeleteMedia = function(id, el) {
        if ( ! confirm('هل أنت متأكد من حذف هذا الملف؟') ) return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_media',
            nonce: yamamahData.nonce,
            attachment_id: id
        }, function(res) {
            if (res.success) {
                $(el).closest('.media-card').fadeOut(300, function(){ $(this).remove(); });
            } else {
                alert(res.data.message || 'فشل الحذف');
            }
        });
    };

    /* ======================================
     * Cleanup old non-theme media
     * ====================================== */
    /* ======================================
     * Products — Select All & Bulk Actions
     * ====================================== */
    (function() {
        var $selectAll = $('#selectAllProducts');
        var $table = $selectAll.closest('table');
        if (!$selectAll.length) return;

        function getRowCheckboxes() {
            return $table.find('tbody input[name="product_ids[]"]');
        }

        function updateBulkBar() {
            var checked = getRowCheckboxes().filter(':checked');
            var count = checked.length;
            var $bar = $('#bulkActionsBar');
            if (count > 0) {
                $bar.slideDown(150);
                $('#bulkSelectedCount').text(count);
            } else {
                $bar.slideUp(150);
            }
            var total = getRowCheckboxes().length;
            $selectAll.prop('indeterminate', count > 0 && count < total);
            $selectAll.prop('checked', count === total && total > 0);
        }

        $selectAll.on('change', function() {
            getRowCheckboxes().prop('checked', this.checked);
            updateBulkBar();
        });

        $table.on('change', 'tbody input[name="product_ids[]"]', function() {
            updateBulkBar();
        });
    })();

    window.yamamahBulkAction = function(action) {
        var ids = [];
        $('#page-products tbody input[name="product_ids[]"]:checked').each(function() {
            ids.push($(this).val());
        });
        if (!ids.length) return;

        var labels = { publish: 'نشر', draft: 'تحويل لمسودة', trash: 'حذف' };
        var catLabels = (window.yamamahData && yamamahData.catalog_labels) ? yamamahData.catalog_labels : {};
        var itemWord = catLabels.items_indef_plural || 'منتجات';
        if (!confirm('هل تريد ' + labels[action] + ' ' + ids.length + ' ' + itemWord + '؟')) return;

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_bulk_products',
            nonce: yamamahData.nonce,
            product_ids: ids,
            bulk_action: action
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'حدث خطأ');
            }
        });
    };

    window.yamamahDeleteSingleProduct = function(productId, btn) {
        var catLabels = (window.yamamahData && yamamahData.catalog_labels) ? yamamahData.catalog_labels : {};
        var msg = catLabels.delete_confirm || 'هل أنت متأكد من حذف هذا المنتج نهائياً؟';
        if (!confirm(msg)) return;
        jQuery.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_product',
            nonce: yamamahData.nonce,
            product_id: productId
        }, function(res) {
            if (res.success) {
                jQuery(btn).closest('tr').fadeOut(300, function(){ jQuery(this).remove(); });
            } else {
                var msg = res.data && res.data.message ? res.data.message : (typeof res.data === 'string' ? res.data : 'حدث خطأ');
                alert(msg);
            }
        }).fail(function(xhr) { alert('خطأ في الاتصال (' + xhr.status + ')'); });
    };

    window.yamamahCleanupMedia = function() {
        if ( ! confirm('سيتم حذف جميع الصور القديمة غير المستخدمة في تصميم الثيم.\nهل تريد المتابعة؟') ) return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_cleanup_media',
            nonce: yamamahData.nonce
        }, function(res) {
            if (res.success) {
                alert('تم حذف ' + res.data.deleted + ' ملف.');
                if (res.data.deleted > 0) location.reload();
            } else {
                alert(res.data.message || 'حدث خطأ');
            }
        });
    };

    // Clickable table rows
    $(document).on('click', '.yamamah-clickable-row', function(e) {
        if ( $(e.target).closest('a, button, .yamamah-action-btns').length ) return;
        var href = $(this).data('href');
        if (href) window.location.href = href;
    });

    // ---- Dashboard Charts ----
    function setupDashboardCharts() {
        if (typeof Chart === 'undefined') return;

        var lineCanvas  = document.getElementById('yamamahLineChart');
        var donutCanvas = document.getElementById('yamamahDonutChart');

        // --- Line Chart ---
        if (lineCanvas && typeof yamamahMonthlyChart !== 'undefined') {
            var months = yamamahMonthlyChart.map(function(r){ return r.month; });
            var sales  = yamamahMonthlyChart.map(function(r){ return r.sales; });
            var orders = yamamahMonthlyChart.map(function(r){ return r.orders; });

            new Chart(lineCanvas, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'المبيعات',
                            data: sales,
                            borderColor: '#E67E22',
                            backgroundColor: 'rgba(230,126,34,0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#E67E22',
                            borderWidth: 2.5,
                            yAxisID: 'y'
                        },
                        {
                            label: 'الطلبات',
                            data: orders,
                            borderColor: '#6C3FA0',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#6C3FA0',
                            borderWidth: 2,
                            borderDash: [5, 3],
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            rtl: true,
                            textDirection: 'rtl',
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.datasetIndex === 0) return 'المبيعات: ' + ctx.parsed.y.toLocaleString() + ' ر.س';
                                    return 'الطلبات: ' + ctx.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        },
                        y: {
                            position: 'left',
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                font: { size: 11 },
                                callback: function(v) { return v.toLocaleString(); }
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        }

        // --- Donut Chart ---
        if (donutCanvas && typeof yamamahCategoryChart !== 'undefined' && yamamahCategoryChart.length > 0) {
            var catLabels  = yamamahCategoryChart.map(function(r){ return r.name; });
            var catValues  = yamamahCategoryChart.map(function(r){ return r.total; });
            var catColors  = (typeof yamamahCategoryColors !== 'undefined') ? yamamahCategoryColors : ['#6C3FA0','#E91E63','#E67E22','#2D9B83','#3B82F6','#8B5CF6'];

            new Chart(donutCanvas, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catValues,
                        backgroundColor: catColors.slice(0, catValues.length),
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            rtl: true,
                            textDirection: 'rtl',
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.label + ': ' + ctx.parsed.toLocaleString() + ' ر.س';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // ---- Notifications ----
    function setupNotifications() {
        var $wrap  = $('#yamamahNotifWrap');
        var $btn   = $('#yamamahNotifBtn');
        var $panel = $('#yamamahNotifPanel');
        var $body  = $('#yamamahNotifBody');
        var $count = $('#yamamahNotifCount');
        var $markAll = $('#yamamahMarkAllRead');

        if (!$btn.length) return;

        $btn.on('click', function(e) {
            e.stopPropagation();
            $('#yamamahAccountDropdown').removeClass('open');
            var isOpen = $panel.hasClass('open');
            $panel.toggleClass('open');
            if (!isOpen) fetchNotifications();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#yamamahNotifWrap').length) {
                $panel.removeClass('open');
            }
        });

        $markAll.on('click', function() {
            $.post(yamamahData.ajax_url, {
                action: 'yamamah_mark_all_notifications_read',
                nonce: yamamahData.nonce
            }, function() {
                $body.find('.yamamah-notif-item').removeClass('unread');
                updateCount(0);
            });
        });

        $body.on('click', '.yamamah-notif-item.unread', function(e) {
            var $item = $(this);
            var nid   = $item.data('id');
            $item.removeClass('unread');
            var current = parseInt($count.text()) || 0;
            updateCount(Math.max(0, current - 1));
            $.post(yamamahData.ajax_url, {
                action: 'yamamah_mark_notification_read',
                nonce: yamamahData.nonce,
                notification_id: nid
            });
        });

        function updateCount(n) {
            if (n > 0) {
                $count.text(n).show();
            } else {
                $count.hide();
            }
        }

        function timeAgo(dateStr) {
            var now  = new Date();
            // Bug #109: parse stored timestamp as UTC (server stores gmdate, suffix Z forces UTC parse)
            var utcStr = String(dateStr || '').replace(' ', 'T');
            if (utcStr && utcStr.slice(-1) !== 'Z' && utcStr.indexOf('+') === -1) {
                utcStr += 'Z';
            }
            var then = new Date(utcStr);
            var diff = Math.floor((now - then) / 1000);
            if (diff < 60) return 'الآن';
            if (diff < 3600) return Math.floor(diff / 60) + ' د';
            if (diff < 86400) return Math.floor(diff / 3600) + ' س';
            if (diff < 604800) return Math.floor(diff / 86400) + ' ي';
            return then.toLocaleDateString('ar-SA');
        }

        function fetchNotifications() {
            $.post(yamamahData.ajax_url, {
                action: 'yamamah_get_notifications',
                nonce: yamamahData.nonce
            }, function(res) {
                if (!res.success) return;
                var data = res.data;
                updateCount(data.unread);
                if (!data.notifications.length) {
                    $body.html('<div class="yamamah-notif-empty">لا توجد إشعارات</div>');
                    return;
                }
                var html = '';
                data.notifications.forEach(function(n) {
                    var cls = n.read ? '' : ' unread';
                    var dotType = n.type || 'info';
                    var tag = n.url ? 'a' : 'div';
                    var href = n.url ? ' href="' + n.url + '"' : '';
                    html += '<' + tag + href + ' class="yamamah-notif-item' + cls + '" data-id="' + n.id + '">';
                    html += '<span class="yamamah-notif-dot ' + dotType + '"></span>';
                    html += '<div class="yamamah-notif-content">';
                    html += '<div class="yamamah-notif-title">' + n.title + '</div>';
                    html += '<div class="yamamah-notif-msg">' + n.message + '</div>';
                    html += '<div class="yamamah-notif-time">' + timeAgo(n.time) + '</div>';
                    html += '</div>';
                    html += '</' + tag + '>';
                });
                $body.html(html);
            });
        }

        fetchNotifications();
        setInterval(fetchNotifications, 60000);
    }

})(jQuery);
