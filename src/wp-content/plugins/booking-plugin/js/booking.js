/**
 * Snippen Booking Plugin JavaScript
 */

jQuery(document).ready(function ($) {
    'use strict';

    var currentStartDate = new Date();
    // Round to start of day
    currentStartDate.setHours(0, 0, 0, 0);

    // Adjust to Monday
    var day = currentStartDate.getDay();
    var diff = currentStartDate.getDate() - day + (day === 0 ? -6 : 1);
    currentStartDate.setDate(diff);

    var selectedDate = null;
    var selectedSlotId = null;
    var selectedSlotName = null;
    var selectedSlotDescription = null;
    var selectedSlotStartTime = null;
    var selectedSlotEndTime = null;

    var $container = $('.snippen-booking-container');
    var objectId = $container.data('object-id');

    /**
     * Initialize the calendar
     */
    function init() {
        if (!$('#calendar-container').length) return;

        renderCalendar();

        // Handle next/prev week
        $(document).on('click', '.week-nav-next', function () {
            currentStartDate.setDate(currentStartDate.getDate() + 7);
            renderCalendar();
            hideForm();
        });

        $(document).on('click', '.week-nav-prev', function () {
            currentStartDate.setDate(currentStartDate.getDate() - 7);
            renderCalendar();
            hideForm();
        });

        // Toggle Week Picker
        $(document).on('click', '.current-week-range', function (e) {
            e.stopPropagation();
            $('.week-picker-dropdown').fadeToggle(200);
        });

        // Select Week from Picker
        $(document).on('click', '.week-picker-dropdown li', function () {
            var startStr = $(this).data('start');
            currentStartDate = new Date(startStr);
            renderCalendar();
            hideForm();
        });

        // Close dropdown when clicking outside
        $(document).click(function () {
            $('.week-picker-dropdown').fadeOut(200);
        });

        // Handle day click (for mobile expansion)
        $(document).on('click', '.day-header', function () {
            var $column = $(this).closest('.day-column');
            if ($column.hasClass('past')) return;

            $('.day-column').not($column).removeClass('expanded selected-day');
            $column.toggleClass('expanded selected-day');
        });

        // Handle slot click
        $(document).on('click', '.slot-item.available', function () {
            var $slot = $(this);
            selectedDate = $slot.data('date');
            selectedSlotId = $slot.data('slot-id');
            selectedSlotName = $slot.data('slot-name');
            selectedSlotDescription = $slot.data('slot-description');
            selectedSlotStartTime = $slot.data('start-time');
            selectedSlotEndTime = $slot.data('end-time');

            // UI Feedback
            $('.slot-item').removeClass('selected');
            $slot.addClass('selected');

            showForm();
        });

        // Close form
        $(document).on('click', '.close-form', function () {
            hideForm();
        });

        // Form submission
        $('#booking-form').on('submit', handleFormSubmit);
    }

    /**
     * Fetch availability and render
     */
    function renderCalendar() {
        var $calendar = $('#calendar-container');
        $calendar.html('<div class="calendar-loader">Oppdaterer tilgjengelighet...</div>');

        var startDateStr = formatDateISO(currentStartDate);

        $.ajax({
            url: snippenBookingAjax.ajaxurl,
            type: 'GET',
            data: {
                action: 'snippen_get_availability',
                object_id: objectId,
                start_date: startDateStr
            },
            success: function (response) {
                if (response.success) {
                    drawWeek(response.data);
                } else {
                    $calendar.html('<div class="error">Kunne ikke laste kalender.</div>');
                }
            }
        });
    }

    /**
     * Draw the grid
     */
    function drawWeek(data) {
        var $calendar = $('#calendar-container');
        var slots = data.slots;
        var booked = data.booked;
        var offsetDays = data.offset_days;

        var today = new Date();
        today.setHours(0, 0, 0, 0);

        var limitDate = new Date(today);
        limitDate.setDate(limitDate.getDate() + offsetDays);

        var weekHtml = '<div class="calendar-header">';
        weekHtml += '<button class="week-nav-btn week-nav-prev" title="Forrige uke">&larr;</button>';
        weekHtml += '<div class="current-week-range">' + formatDateRange(currentStartDate) + '</div>';
        weekHtml += generateWeekPicker();
        weekHtml += '<button class="week-nav-btn week-nav-next" title="Neste uke">&rarr;</button>';
        weekHtml += '</div>';

        weekHtml += '<div class="week-grid">';

        var tempDate = new Date(currentStartDate);
        var dayNames = ['Man', 'Tir', 'Ons', 'Tor', 'Fre', 'Lør', 'Søn'];

        for (var i = 0; i < 7; i++) {
            var dateStr = formatDateISO(tempDate);
            var isPast = tempDate < today; // Use today for "past" visual state
            var isSelected = selectedDate === dateStr;

            weekHtml += '<div class="day-column ' + (isPast ? 'past' : '') + (isSelected ? ' selected-day' : '') + '">';
            weekHtml += '<div class="day-header">';
            weekHtml += '<span class="day-name">' + dayNames[i] + '</span>';
            weekHtml += '<span class="day-date">' + tempDate.getDate() + '.' + (tempDate.getMonth() + 1) + '</span>';
            weekHtml += '</div>';

            weekHtml += '<div class="slots-container">';

            var dayBookings = booked[dateStr] || [];

            slots.forEach(function (slot) {
                var existing = dayBookings.find(b => b.slot_id === parseInt(slot.id));
                var isBooked = !!existing;
                var isCurrentlySelected = isSelected && selectedSlotId === parseInt(slot.id);

                if (isBooked) {
                    weekHtml += '<div class="slot-item booked">';
                    weekHtml += '<span class="slot-name">' + slot.name + '</span>';
                    weekHtml += '<span class="booking-info">' + existing.start_time.substring(0, 5) + ' - ' + existing.end_time.substring(0, 5) + '</span>';
                    if (existing.cleanup_hours > 0) {
                        weekHtml += '<span class="cleanup-tag">+' + existing.cleanup_hours + 't vask</span>';
                    }
                    weekHtml += '</div>';
                } else {
                    var statusClass = isPast ? 'disabled' : 'available';
                    if (isCurrentlySelected) statusClass += ' selected';

                    weekHtml += '<div class="slot-item ' + statusClass + '" ';
                    weekHtml += 'data-date="' + dateStr + '" ';
                    weekHtml += 'data-slot-id="' + slot.id + '" ';
                    weekHtml += 'data-slot-description="' + (slot.description || '') + '" ';
                    weekHtml += 'data-start-time="' + slot.start_time + '" ';
                    weekHtml += 'data-end-time="' + slot.end_time + '" ';
                    weekHtml += 'data-slot-name="' + slot.name + '">';
                    weekHtml += '<span class="slot-name">' + slot.name + '</span>';
                    weekHtml += '</div>';
                }
            });

            weekHtml += '</div>'; // slots-container
            weekHtml += '</div>'; // day-column

            tempDate.setDate(tempDate.getDate() + 1);
        }

        weekHtml += '</div>'; // week-grid

        $calendar.html(weekHtml);
    }

    /**
     * Generate the week picker dropdown HTML
     */
    function generateWeekPicker() {
        var html = '<div class="week-picker-dropdown"><ul>';
        var tempDate = new Date();
        // Adjust to Monday
        var day = tempDate.getDay();
        var diff = tempDate.getDate() - day + (day === 0 ? -6 : 1);
        tempDate.setDate(diff);
        tempDate.setHours(0, 0, 0, 0);

        for (var i = 0; i < 52; i++) {
            var weekStart = new Date(tempDate);

            var isActive = formatDateISO(weekStart) === formatDateISO(currentStartDate);
            var weekNr = getWeekNumber(weekStart);

            html += '<li class="' + (isActive ? 'active' : '') + '" data-start="' + formatDateISO(weekStart) + '">';
            html += '<span>' + formatDateRange(weekStart) + '</span>';
            html += '<span class="week-nr-badge">Uke ' + weekNr + '</span>';
            html += '</li>';

            tempDate.setDate(tempDate.getDate() + 7);
        }

        html += '</ul></div>';
        return html;
    }

    /**
     * Get ISO week number
     */
    function getWeekNumber(d) {
        d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
        d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        var weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        return weekNo;
    }

    /**
     * Show booking form
     */
    function showForm() {
        $('#event-date').val(selectedDate);
        $('#slot-id').val(selectedSlotId);

        var dateObj = new Date(selectedDate);
        var formattedDate = dateObj.toLocaleDateString('nb-NO', { weekday: 'long', day: 'numeric', month: 'long' });

        var timeStr = (selectedSlotStartTime || '').substring(0, 5) + ' - ' + (selectedSlotEndTime || '').substring(0, 5);
        $('#selected-info-display').text(formattedDate + ' | ' + selectedSlotName + ' (' + timeStr + ')');
        $('#selected-slot-description').text(selectedSlotDescription || '');

        $('#booking-form-container').slideDown(400);

        $('html, body').animate({
            scrollTop: $("#booking-form-container").offset().top - 40
        }, 600);
    }

    /**
     * Hide booking form
     */
    function hideForm() {
        $('#booking-form-container').slideUp(300);
        $('.slot-item').removeClass('selected');
        selectedDate = null;
        selectedSlotId = null;
    }

    /**
     * Handle AJAX form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('.booking-submit');
        var $response = $('#booking-response');

        $submitBtn.prop('disabled', true).text('Sender forespørsel...');
        $response.hide();

        var formData = {
            action: 'snippen_booking_submit',
            nonce: snippenBookingAjax.nonce,
            booking_object_id: objectId,
            event_date: $('#event-date').val(),
            slot_id: $('#slot-id').val(),
            name: $('#name').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            description: $('#description').val()
        };

        $.ajax({
            url: snippenBookingAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    $response.removeClass('error').addClass('success').html(response.data.message).fadeIn();
                    $form[0].reset();
                    setTimeout(function () {
                        hideForm();
                        renderCalendar();
                    }, 3000);
                } else {
                    $response.removeClass('success').addClass('error').html(response.data.message || 'Noe gikk galt.').fadeIn();
                    $submitBtn.prop('disabled', false).text('Prøv igjen');
                }
            },
            error: function () {
                $response.removeClass('success').addClass('error').html('Tilkoblingsfeil.').fadeIn();
                $submitBtn.prop('disabled', false).text('Prøv igjen');
            }
        });
    }

    /**
     * Helper: Format Date to ISO string (YYYY-MM-DD)
     */
    function formatDateISO(date) {
        var d = new Date(date);
        var month = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        var year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    /**
     * Helper: Format date range for header
     */
    function formatDateRange(start) {
        var end = new Date(start);
        end.setDate(end.getDate() + 6);

        var startOptions = { day: 'numeric', month: 'short' };
        var endOptions = { day: 'numeric', month: 'short', year: 'numeric' };

        return start.toLocaleDateString('nb-NO', startOptions) + ' - ' + end.toLocaleDateString('nb-NO', endOptions);
    }

    // Initialize
    init();
});