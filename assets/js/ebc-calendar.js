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

            $wrap.on('click', '.ebc-event', function (e) {
                e.preventDefault();

                const eventId = $(this).data('event-id');

                if (!eventId) {
                    window.location.href = $(this).attr('href');
                    return;
                }

                openEventModal($wrap, eventId);
            });

            $wrap.on('click', '.ebc-event-modal-close, .ebc-event-modal-backdrop', function () {
                closeEventModal($wrap);
            });
        });
    });

    function openEventModal($wrap, eventId) {
        const $modal = $wrap.find('.ebc-event-modal');

        $.ajax({
            url: EBC_Ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ebc_get_event',
                nonce: EBC_Ajax.nonce,
                event_id: eventId
            }
        })
        .done(function (response) {
            if (!response.success) {
                alert('イベント情報の取得に失敗しました。');
                return;
            }

            const event = response.data;

            $modal.find('.ebc-event-modal-title').text(event.title);
            $modal.find('.ebc-event-modal-content').html(event.content);

            let datesText = '';

            if (event.event_dates && event.event_dates.length > 0) {
                datesText = event.event_dates.join(' / ');
            } else if (event.start_date && event.end_date && event.start_date !== event.end_date) {
                datesText = event.start_date + ' 〜 ' + event.end_date;
            } else if (event.start_date) {
                datesText = event.start_date;
            }

            $modal.find('.ebc-event-modal-dates').text(datesText);

            $modal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('ebc-modal-open');
        })
        .fail(function () {
            alert('通信エラーが発生しました。');
        });
    }

    function closeEventModal($wrap) {
        const $modal = $wrap.find('.ebc-event-modal');

        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('ebc-modal-open');
    }

})(jQuery);
