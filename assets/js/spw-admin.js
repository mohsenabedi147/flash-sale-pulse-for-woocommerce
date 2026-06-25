/**
 * مدیریت تعاملات پنل مدیریت نبض حراجی
 */
(function ($) {
    'use strict';

    $(function () {
        // ۱. مدیریت تب‌های صفحه تنظیمات
        $('.spw-tab-link').on('click', function (e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $('.spw-tab-link').removeClass('is-active');
            $(this).addClass('is-active');

            $('.spw-tab-content').removeClass('is-active');
            $(target).addClass('is-active');

            // بروزرسانی URL بدون رفرش برای حفظ تب فعال
            window.history.pushState(null, null, target);
        });

        // فعال‌سازی تب از روی URL در صورت وجود
        var hash = window.location.hash;
        if (hash && $('.spw-tab-link[href="' + hash + '"]').length) {
            $('.spw-tab-link[href="' + hash + '"]').trigger('click');
        }

        // ۲. توابع کمکی برای ماسک کردن ورودی‌ها
        function normalizePersian(str) {
            var p = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            var e = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            for (var i = 0; i < p.length; i++) {
                str = str.replace(new RegExp(p[i], 'g'), e[i]);
            }
            return str;
        }

        // ماسک تاریخ (YYYY/MM/DD)
        $('.spw-date-input').on('input', function () {
            var val = normalizePersian($(this).val().replace(/\D/g, ''));
            var newVal = '';
            if (val.length > 4) {
                newVal += val.substr(0, 4) + '/';
                if (val.length > 6) {
                    newVal += val.substr(4, 2) + '/' + val.substr(6, 2);
                } else {
                    newVal += val.substr(4);
                }
            } else {
                newVal = val;
            }
            $(this).val(newVal);
        });

        // ماسک ساعت (HH:MM)
        $('.spw-time-input').on('input', function () {
            var val = normalizePersian($(this).val().replace(/\D/g, ''));
            var newVal = '';
            if (val.length > 2) {
                newVal += val.substr(0, 2) + ':' + val.substr(2, 2);
            } else {
                newVal = val;
            }
            $(this).val(newVal);
        });

        // جلوگیری از ورود حروف غیرعددی
        $('.spw-date-input, .spw-time-input').on('keypress', function (e) {
            var charCode = (e.which) ? e.which : e.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57) && (charCode < 1632 || charCode > 1785)) {
                return false;
            }
            return true;
        });
    });

})(jQuery);
