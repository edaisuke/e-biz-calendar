(function ($) {
    'use strict';

    function updateTitle($wrap) {
        const $calendar = $wrap.find('.ebc-calendar');
        const title = $calendar.data('title');

        $wrap.find('.ebc-calendar-title').text(title);
    }

    function loadCalendar($wrap, year, month) {
        const $content = $wrap.find('.ebc-calendar-content');
        const $loading = $wrap.find('.ebc-calendar-loading');

        $loading.show();

        $.ajax({
            url: EBC_Ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ebc_get_calendar',
                nonce: EBC_Ajax.nonce,
                year: year,
                month: month,
                holiday_enabled: $wrap.attr('data-holiday-enabled')
            }
        })
            .done(function (response) {
                if (!response.success) {
                    alert('カレンダーの取得に失敗しました。');
                    return;
                }

                $content.html(response.data.html);

                $wrap.attr('data-year', response.data.year);
                $wrap.attr('data-month', response.data.month);

                $wrap.find('.ebc-calendar-title').text(response.data.title);
            })
            .fail(function () {
                alert('通信エラーが発生しました。');
            })
            .always(function () {
                $loading.hide();
            });
    }

    function moveMonth(year, month, diff) {
        month += diff;

        if (month < 1) {
            month = 12;
            year -= 1;
        }

        if (month > 12) {
            month = 1;
            year += 1;
        }

        return {
            year: year,
            month: month
        };
    }

    $(function () {
        $('.ebc-calendar-wrap').each(function () {
            const $wrap = $(this);

            updateTitle($wrap);

            $wrap.on('click', '.ebc-calendar-prev', function () {
                const year = parseInt($wrap.attr('data-year'), 10);
                const month = parseInt($wrap.attr('data-month'), 10);

                const result = moveMonth(year, month, -1);

                loadCalendar($wrap, result.year, result.month);
            });

            $wrap.on('click', '.ebc-calendar-next', function () {
                const year = parseInt($wrap.attr('data-year'), 10);
                const month = parseInt($wrap.attr('data-month'), 10);

                const result = moveMonth(year, month, 1);

                loadCalendar($wrap, result.year, result.month);
            });
        });
    });

})(jQuery);
