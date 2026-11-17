/**
 * app.js — CSP-safe, dependency-free UI layer (script-src 'self').
 * All behavior is data-attribute driven so views stay markup-only.
 * No jQuery, no framework — vanilla JS only.
 */
(function () {
    'use strict';

    /* ─── Persian (Jalali) date & digit helpers ─────────────── */

    var FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function toFaDigits(text) {
        return String(text).replace(/[0-9]/g, function (d) {
            return FA_DIGITS[+d];
        });
    }

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    // Weekday names indexed like Date.getDay() (0=Sun..6=Sat)
    var WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

    var J_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /**
     * Gregorian → Jalali (civil 33-year rule, mirrors server
     * CalendarService). Returns {jy, jm, jd}.
     */
    function g2j(gy, gm, gd) {
        var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var gy2 = gm > 2 ? gy + 1 : gy;
        var days = 355666 + 365 * gy + ~~((gy2 + 3) / 4) - ~~((gy2 + 99) / 100) + ~~((gy2 + 399) / 400) + gd + gdm[gm - 1];
        var jy = -1595 + 33 * ~~(days / 12053);
        days %= 12053;
        jy += 4 * ~~(days / 1461);
        days %= 1461;
        if (days > 365) {
            jy += ~~((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var jm;
        var jd;
        if (days < 186) {
            jm = 1 + ~~(days / 31);
            jd = 1 + (days % 31);
        } else {
            jm = 7 + ~~((days - 186) / 30);
            jd = 1 + ((days - 186) % 30);
        }
        return { jy: jy, jm: jm, jd: jd };
    }

    function jalaliToday() {
        var now = new Date();
        var j = g2j(now.getFullYear(), now.getMonth() + 1, now.getDate());
        j.weekday = WEEKDAYS[now.getDay()];
        return j;
    }

    /* ─── Live clock + Jalali date ──────────────────────────── */

    function tickClock() {
        var els = document.querySelectorAll('[data-clock]');
        if (!els.length) return;
        var now = new Date();
        var hhmmss = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
        els.forEach(function (el) {
            el.textContent = toFaDigits(hhmmss);
        });
    }

    function fillDates() {
        document.querySelectorAll('[data-jalali-date]').forEach(function (el) {
            var j = jalaliToday();
            el.textContent = j.weekday + ' ' + toFaDigits(j.jd) + ' ' + J_MONTHS[j.jm - 1] + ' ' + toFaDigits(j.jy);
        });
    }

    /* ─── Flash toast auto-dismiss ──────────────────────────── */

    function initFlash() {
        document.querySelectorAll('[data-flash]').forEach(function (el) {
            setTimeout(function () {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-6px)';
                el.style.transition = 'opacity .4s, transform .4s';
                setTimeout(function () {
                    el.remove();
                }, 450);
            }, 5000);
        });
    }

    /* ─── Sidebar mobile drawer ───────────────────────────────
       The drawer is driven purely by CSS (:has() on #sidebar-state),
       so its open state survives page navigations via bfcache and
       the checkbox stays checked when the browser restores the page.
       JS only closes it on backdrop/toggle clicks — no state here. */

    function initSidebar() {
        var state = document.getElementById('sidebar-state');
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var backdrop = document.querySelector('[data-sidebar-backdrop]');
        if (!state || !toggle || !backdrop) return;

        // Toggle button is a plain <button>; sync the checkbox manually.
        toggle.addEventListener('click', function () {
            state.checked = !state.checked;
        });
        // Backdrop is a <label for="sidebar-state"> in the markup; clicking
        // it un-checks natively, but also close when navigating is not needed.
        // Close drawer with Escape for keyboard users.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && state.checked) {
                state.checked = false;
            }
        });
    }

    /* ─── Punch method toggle + geolocation ─────────────────── */
    /* Works with both UIs:
       - radio cards  → <input type="radio" data-punch-method name="method">
       - legacy select → <select data-punch-method name="method"> */

    function currentMethod() {
        var checked = document.querySelector('input[type="radio"][data-punch-method]:checked');
        if (checked) return checked.value;
        var sel = document.querySelector('select[data-punch-method]');
        if (sel) return sel.value;
        var hidden = document.querySelector('input[type="hidden"][name="method"]');
        return hidden ? hidden.value : '';
    }

    function initPunchForm() {
        var radios = document.querySelectorAll('input[type="radio"][data-punch-method]');
        var sel = document.querySelector('select[data-punch-method]');
        if (!radios.length && !sel) return;

        var panels = {
            location: document.querySelector('[data-panel="location"]'),
            qrcode: document.querySelector('[data-panel="qrcode"]')
        };

        function sync() {
            var v = currentMethod();
            Object.keys(panels).forEach(function (key) {
                if (panels[key]) {
                    panels[key].classList.toggle('hidden', v !== key);
                }
            });
        }

        radios.forEach(function (r) {
            r.addEventListener('change', sync);
        });
        if (sel) sel.addEventListener('change', sync);
        sync();

        // Geolocation capture for the "location" method
        var latInput = document.getElementById('lat');
        var lngInput = document.getElementById('lng');
        var geoStatus = document.getElementById('geo-status');
        var geoRequested = false;

        function requestGeo() {
            if (geoRequested || !navigator.geolocation) return;
            geoRequested = true;
            if (geoStatus) geoStatus.textContent = 'در حال دریافت موقعیت…';
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    if (latInput) latInput.value = pos.coords.latitude;
                    if (lngInput) lngInput.value = pos.coords.longitude;
                    if (geoStatus) {
                        geoStatus.textContent =
                            'موقعیت ثبت شد: ' +
                            toFaDigits(pos.coords.latitude.toFixed(4)) +
                            '، ' +
                            toFaDigits(pos.coords.longitude.toFixed(4));
                    }
                },
                function () {
                    geoRequested = false;
                    if (geoStatus) geoStatus.textContent = 'دسترسی به موقعیت ممکن نیست — دوباره تلاش کنید.';
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        // As soon as the location panel is visible, start capturing
        if (panels.location && !panels.location.classList.contains('hidden')) {
            requestGeo();
        }
        radios.forEach(function (r) {
            r.addEventListener('change', function () {
                if (currentMethod() === 'location') requestGeo();
            });
        });
        if (sel) {
            sel.addEventListener('change', function () {
                if (currentMethod() === 'location') requestGeo();
            });
        }
    }

    /* ─── Bar chart heights (data-percent driven) ───────────── */

    function initBars() {
        document.querySelectorAll('[data-percent]').forEach(function (el) {
            var pct = Math.max(4, Math.min(100, parseFloat(el.getAttribute('data-percent')) || 0));
            el.style.height = pct + '%';
        });
    }

    /* ─── Neshan map picker (admin settings) ────────────────── */
    /* The SDK is self-hosted and loaded ONLY on the settings page
       (inline <script defer> in that view). Here we just wire the
       [data-neshan-map] container to the lat/lng/radius inputs. */

    function initNeshanMap() {
        var el = document.querySelector('[data-neshan-map]');
        if (!el || typeof L === 'undefined') return;

        var key = el.getAttribute('data-key') || '';
        if (key.length < 12) return; // SDK refuses short keys

        var lat = parseFloat(el.getAttribute('data-lat')) || 35.6997;
        var lng = parseFloat(el.getAttribute('data-lng')) || 51.3380;
        var radius = parseInt(el.getAttribute('data-radius'), 10) || 150;

        var latInput = document.getElementById('office_lat');
        var lngInput = document.getElementById('office_lng');
        var radiusInput = document.getElementById('radius_m');

        var map = new L.Map(el, {
            key: key,
            maptype: 'dreamy',
            center: [lat, lng],
            zoom: 14
        });

        /* Pure-CSS pin: no image dependency on the SDK's CDN hashes. */
        var icon = L.divIcon({
            className: 'neshan-pin',
            iconSize: [26, 34],
            iconAnchor: [13, 32],
            html: '<svg viewBox="0 0 24 34" width="26" height="34" aria-hidden="true">' +
                  '<path d="M12 1C6.5 1 2 5.5 2 11c0 7.2 8.5 17.5 9.2 18.3.4.5 1.2.5 1.6 0C13.5 28.5 22 18.2 22 11c0-5.5-4.5-10-10-10z" ' +
                  'fill="var(--color-primary)" stroke="var(--color-base-100)" stroke-width="1.5"/>' +
                  '<circle cx="12" cy="11" r="3.6" fill="var(--color-base-100)"/></svg>'
        });

        var marker = L.marker([lat, lng], { icon: icon }).addTo(map);
        var circle = L.circle([lat, lng], {
            radius: radius,
            color: 'var(--color-primary)',
            weight: 2,
            fillColor: 'var(--color-primary)',
            fillOpacity: 0.12
        }).addTo(map);

        /* Some SDK builds wipe overlays when the async key check re-renders
           tiles — re-assert our layers once the map settles. */
        function reassert() {
            if (!map.hasLayer(marker)) marker.addTo(map);
            if (!map.hasLayer(circle)) circle.addTo(map);
        }
        map.on('load', reassert);
        setTimeout(reassert, 1500);
        map.on('click', function (e) { setPoint(e.latlng); });

        function setPoint(p) {
            marker.setLatLng(p);
            circle.setLatLng(p);
            if (latInput) latInput.value = p.lat.toFixed(6);
            if (lngInput) lngInput.value = p.lng.toFixed(6);
        }

        if (radiusInput) {
            radiusInput.addEventListener('input', function () {
                var r = parseInt(radiusInput.value, 10);
                if (r >= 50 && r <= 5000) circle.setRadius(r);
            });
        }
    }

    /* ─── QR display page (/admin/qr) ─────────────────────── */
    /* Rotating mode: countdown + auto page reload so a fresh
       token+image is rendered server-side. CSP-safe (no fetch of
       JSON APIs needed — a reload re-renders the whole page). */

    function initQrPage() {
        var page = document.querySelector('[data-qr-page]');
        if (!page) return;

        var countdownEl = document.querySelector('[data-qr-countdown]');
        var progressEl = document.querySelector('[data-qr-progress]');
        var soundToggle = document.querySelector('[data-qr-sound]');
        var total = parseInt((countdownEl && countdownEl.getAttribute('data-seconds')) || (progressEl && progressEl.getAttribute('max')) || '0', 10);

        // Read the initial value from the rendered text (Persian digits)
        if (countdownEl && !total) {
            var faToEn = { '۰': 0, '۱': 1, '۲': 2, '۳': 3, '۴': 4, '۵': 5, '۶': 6, '۷': 7, '۸': 8, '۹': 9 };
            var txt = countdownEl.textContent.trim();
            total = 0;
            for (var i = 0; i < txt.length; i++) {
                if (faToEn.hasOwnProperty(txt[i])) total = total * 10 + faToEn[txt[i]];
            }
        }
        if (!total) return;

        var left = total;

        function beep() {
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.08, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch (e) { /* audio blocked — silent */ }
        }

        var timer = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(timer);
                if (soundToggle && soundToggle.checked) beep();
                setTimeout(function () { window.location.reload(); }, 600);
                return;
            }
            if (countdownEl) countdownEl.textContent = toFaDigits(String(left));
            if (progressEl) progressEl.value = left;
        }, 1000);

        var printBtn = document.querySelector('[data-qr-print]');
        if (printBtn) {
            printBtn.addEventListener('click', function () { window.print(); });
        }
        var refreshBtn = document.querySelector('[data-qr-refresh]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () { window.location.reload(); });
        }
    }

    /* ─── Settings page: QR static-token generator ─────────── */

    function initSettingsForm() {
        var gen = document.querySelector('[data-gen-token]');
        if (!gen) return;
        var target = document.querySelector('input[name="qr_static_token"]');
        gen.addEventListener('click', function () {
            if (!target) return;
            var chars = 'abcdef0123456789';
            var out = '';
            for (var i = 0; i < 32; i++) out += chars[Math.floor(Math.random() * chars.length)];
            target.value = out;
        });

        // Enable/disable TTL + static token fields based on qr_mode radios
        var ttl = document.querySelector('input[name="qr_ttl_seconds"]');
        var staticField = document.querySelector('[data-qr-static-field]');
        function syncQrFields() {
            var mode = (document.querySelector('input[name="qr_mode"]:checked') || {}).value || 'rotating';
            if (ttl) ttl.disabled = mode !== 'rotating';
            if (staticField) {
                var input = staticField.querySelector('input');
                if (input) input.disabled = mode !== 'static';
            }
        }
        var qrRadios = document.querySelectorAll('input[name="qr_mode"]');
        if (qrRadios.length) {
            qrRadios.forEach(function (r) { r.addEventListener('change', syncQrFields); });
            syncQrFields();
        }
    }

    /* ─── Boot ──────────────────────────────────────────────── */

    function init() {
        fillDates();
        tickClock();
        setInterval(tickClock, 1000);
        initFlash();
        initSidebar();
        initPunchForm();
        initBars();
        initNeshanMap();
        initQrPage();
        initSettingsForm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
