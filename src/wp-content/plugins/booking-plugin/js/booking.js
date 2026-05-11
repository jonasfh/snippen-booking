/**
 * Snippen Booking Plugin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    var currentStartDate = new Date();
    // Round to start of day
    currentStartDate.setHours(0, 0, 0, 0);
    
    // Adjust to Monday
    var day = currentStartDate.getDay();
    var diff = currentStartDate.getDate() - day + (day === 0 ? -6 : 1);
    currentStartDate.setDate(diff);

    var selectedDate = null;
    var selectedSlot = null;
    var facility = $('#facility').val() || 'spisestuen';

    /**
     * Initialize the calendar
     */
    function initCalendar() {
        if (!$('#calendar-container').length) return;
        
        renderCalendar();
        
        // Handle next/prev week
        $(document).on('click', '.week-nav-next', function() {
            currentStartDate.setDate(currentStartDate.getDate() + 7);
            renderCalendar();
        });
        
        $(document).on('click', '.week-nav-prev', function() {
            currentStartDate.setDate(currentStartDate.getDate() - 7);
            renderCalendar();
        });
        
        // Handle slot click
        $(document).on('click', '.slot-item.available', function() {
            var $slot = $(this);
            selectedDate = $slot.data('date');
            selectedSlot = $slot.data('slot-id');
            var slotName = $slot.data('slot-name');
            
            // Highlight selected
            $('.slot-item').removeClass('selected');
            $slot.addClass('selected');
            
            // Populate and show form
            $('#event-date').val(selectedDate);
            
            // Populate slot dropdown
            var $slotSelect = $('#slot-id');
            $slotSelect.empty();
            $slotSelect.append($('<option>', {
                value: selectedSlot,
                text: slotName
            }));
            $slotSelect.val(selectedSlot);
            
            $('.snippen-booking-form').slideDown();
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $(".snippen-booking-form").offset().top - 50
            }, 500);
        });
    }

    /**
     * Render the calendar for the current week
     */
    function renderCalendar() {
        var $container = $('#calendar-container');
        $container.html('<div class="calendar-loader">Laster tilgjengelighet...</div>');

        var startDateStr = currentStartDate.toISOString().split('T')[0];
        
        $.ajax({
            url: snippenBookingAjax.ajaxurl,
            type: 'GET',
            data: {
                action: 'snippen_get_availability',
                facility: facility,
                start_date: startDateStr
            },
            success: function(response) {
                if (response.success) {
                    drawWeek(response.data);
                } else {
                    $container.html('<div class="error">Kunne ikke laste kalender.</div>');
                }
            }
        });
    }

    /**
     * Draw the week grid
     */
    function drawWeek(data) {
        var $container = $('#calendar-container');
        var slots = data.slots;
        var booked = data.booked;
        var offsetDays = data.offset_days;
        
        var today = new Date();
        today.setHours(0,0,0,0);
        
        var limitDate = new Date(today);
        limitDate.setDate(limitDate.getDate() + offsetDays);

        var weekHtml = '<div class="calendar-header">';
        weekHtml += '<button class="week-nav-prev">&larr; Forrige uke</button>';
        weekHtml += '<span class="current-week-range">' + formatDateRange(currentStartDate) + '</span>';
        weekHtml += '<button class="week-nav-next">Neste uke &rarr;</button>';
        weekHtml += '</div>';

        weekHtml += '<div class="week-grid">';
        
        var tempDate = new Date(currentStartDate);
        var dayNames = ['Man', 'Tir', 'Ons', 'Tor', 'Fre', 'Lør', 'Søn'];

        for (var i = 0; i < 7; i++) {
            var dateStr = tempDate.toISOString().split('T')[0];
            var isPast = tempDate < limitDate;
            
            weekHtml += '<div class="day-column ' + (isPast ? 'past' : '') + '">';
            weekHtml += '<div class="day-header">';
            weekHtml += '<span class="day-name">' + dayNames[i] + '</span>';
            weekHtml += '<span class="day-date">' + tempDate.getDate() + '.' + (tempDate.getMonth() + 1) + '</span>';
            weekHtml += '</div>';
            
            weekHtml += '<div class="slots-list">';
            slots.forEach(function(slot) {
                var isBooked = booked[dateStr] && booked[dateStr].indexOf(parseInt(slot.id)) !== -1;
                var statusClass = isBooked ? 'booked' : (isPast ? 'disabled' : 'available');
                var statusText = isBooked ? 'Opptatt' : (isPast ? 'Utilgjengelig' : 'Ledig');
                
                weekHtml += '<div class="slot-item ' + statusClass + '" ';
                weekHtml += 'data-date="' + dateStr + '" ';
                weekHtml += 'data-slot-id="' + slot.id + '" ';
                weekHtml += 'data-slot-name="' + slot.name + '">';
                weekHtml += '<span class="slot-name">' + slot.name + '</span>';
                weekHtml += '<span class="slot-status">' + statusText + '</span>';
                weekHtml += '</div>';
            });
            weekHtml += '</div>'; // slots-list
            weekHtml += '</div>'; // day-column
            
            tempDate.setDate(tempDate.getDate() + 1);
        }
        
        weekHtml += '</div>'; // week-grid
        
        $container.html(weekHtml);
    }

    /**
     * Format date range for header
     */
    function formatDateRange(start) {
        var end = new Date(start);
        end.setDate(end.getDate() + 6);
        
        var options = { month: 'short', day: 'numeric' };
        return start.toLocaleDateString('nb-NO', options) + ' - ' + end.toLocaleDateString('nb-NO', options);
    }

    // Handle facility change
    $('#facility').on('change', function() {
        facility = $(this).val();
        if ($('#calendar-container').length) {
            renderCalendar();
        }
        // Hide form when facility changes? Maybe.
        $('.snippen-booking-form').hide();
    });

    // Handle booking form submission
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('.booking-submit');
        var $response = $('#booking-response');

        // Disable submit button
        $submitBtn.prop('disabled', true).text('Sender...');

        // Hide previous response
        $response.hide();

        // Collect form data
        var formData = {
            action: 'snippen_booking_submit',
            nonce: snippenBookingAjax.nonce,
            facility: $('#facility').val(),
            event_date: $('#event-date').val(),
            slot_id: $('#slot-id').val(),
            name: $('#name').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            description: $('#description').val()
        };

        // Send AJAX request
        $.ajax({
            url: snippenBookingAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $response.removeClass('error').addClass('success')
                            .html(response.data.message)
                            .show();

                    // Reset form and hide
                    $form[0].reset();
                    setTimeout(function() {
                        $('.snippen-booking-form').slideUp();
                        renderCalendar(); // Refresh calendar
                    }, 3000);
                } else {
                    $response.removeClass('success').addClass('error')
                            .html(response.data.message || 'En feil oppstod. Vennligst prøv igjen.')
                            .show();
                }
            },
            error: function() {
                $response.removeClass('success').addClass('error')
                        .html('Tilkoblingsfeil. Vennligst prøv igjen.')
                        .show();
            },
            complete: function() {
                // Re-enable submit button
                $submitBtn.prop('disabled', false).text('Send bookingforespørsel');
            }
        });
    });

    // Initial setup
    if ($('#calendar-container').length) {
        $('.snippen-booking-form').hide(); // Hide form initially in calendar mode
        initCalendar();
    }
});