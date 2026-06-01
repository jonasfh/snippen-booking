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
    var isAdmin = $container.data('is-admin') === true;

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

        var isLoggedIn = $container.data('logged-in') === true;



        // Handle slot click
        $(document).on('click', '.slot-item.available', function () {
            if (!isLoggedIn) {
                // Highlight login prompt if trying to click while logged out
                $('.snippen-login-prompt').fadeOut(150).fadeIn(150);
                return;
            }

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

        if (isAdmin) {
            initUserSearch();

            // Handle booked slot click for admins
            $(document).on('click', '.slot-item.booked', function() {
                var $slot = $(this);
                var bookingData = $slot.data('booking-info');
                if (bookingData) {
                    showBookingDetails(bookingData);
                }
            });

            // Close modal
            $(document).on('click', '.close-modal, .modal-overlay', function() {
                $('#booking-info-modal').fadeOut(200);
            });
        }
    }

    /**
     * Fetch availability and render
     */
    function renderCalendar() {
        var $calendar = $('#calendar-container');
        $calendar.html('<div class="calendar-loader">' + snippenBookingAjax.strings.updatingAvailability + '</div>');

        var startDateStr = formatDateISO(currentStartDate);

        $.ajax({
            url: snippenBookingAjax.ajaxurl,
            type: 'GET',
            data: {
                action: 'snippen_get_availability',
                object_id: objectId,
                start_date: startDateStr,
                nonce: snippenBookingAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    drawWeek(response.data);
                } else {
                    $calendar.html('<div class="error">' + snippenBookingAjax.strings.errorLoadingCalendar + '</div>');
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
        var unavailable = data.unavailable || {};
        var applicableSlots = data.applicable_slots || {};
        var prices = data.prices || {};
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
        var dayNames = snippenBookingAjax.strings.weekdays;

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
            var dayUnavailable = unavailable[dateStr] || [];
            var dayApplicable = applicableSlots[dateStr] || [];

            slots.forEach(function (slot) {
                var slotIds = String(slot.id).split(',').map(Number);
                
                // If the slot is not applicable for this day, don't render it at all
                if (!dayApplicable.includes(Number(slot.id))) {
                    return;
                }

                var existing = dayBookings.find(b => 
                    b.slot_name === slot.name && 
                    b.start_time === slot.start_time && 
                    b.end_time === slot.end_time
                );
                var isBooked = !!existing;
                var isBlocked = dayUnavailable.some(id => slotIds.includes(parseInt(id)));
                var isCurrentlySelected = isSelected && selectedSlotId === slot.id;

                var price = (prices[dateStr] && prices[dateStr][slot.name]) ? prices[dateStr][slot.name] : null;

                if (isBooked) {
                    var bookingInfoStr = isAdmin ? JSON.stringify(existing).replace(/"/g, '&quot;') : '';
                    weekHtml += '<div class="slot-item booked" ' + (isAdmin ? 'data-booking-info="' + bookingInfoStr + '"' : '') + '>';
                    weekHtml += '<span class="slot-name">' + slot.name + '</span>';
                    
                    if (isAdmin && existing.customer_name) {
                        weekHtml += '<span class="customer-name-label">' + existing.customer_name + '</span>';
                    }

                    weekHtml += '<span class="booking-info">' + existing.start_time.substring(0, 5) + ' - ' + existing.end_time.substring(0, 5) + '</span>';
                    if (existing.cleanup_hours > 0) {
                        weekHtml += '<span class="cleanup-tag">+' + existing.cleanup_hours + 't vask</span>';
                    }
                    weekHtml += '</div>';
                } else if (isBlocked && !isPast) {
                    weekHtml += '<div class="slot-item unavailable" title="' + snippenBookingAjax.strings.blockedByCleanup + '">';
                    weekHtml += '<span class="slot-name">' + slot.name + '</span>';
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
                    weekHtml += 'data-price="' + (price || '') + '" ';
                    weekHtml += 'data-slot-name="' + slot.name + '">';
                    weekHtml += '<span class="slot-name">' + slot.name + '</span>';
                    if (price && !isPast) {
                        weekHtml += '<span class="slot-price">kr. ' + Math.round(price) + ',-</span>';
                    }
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
            html += '<span class="week-nr-badge">' + snippenBookingAjax.strings.weekLabel + ' ' + weekNr + '</span>';
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

        var $selectedSlot = $('.slot-item.selected');
        var price = $selectedSlot.data('price');

        var timeStr = (selectedSlotStartTime || '').substring(0, 5) + ' - ' + (selectedSlotEndTime || '').substring(0, 5);
        var infoText = formattedDate + ' | ' + selectedSlotName + ' (' + timeStr + ')';
        
        if (price) {
            infoText += ' - Pris: kr. ' + Math.round(price) + ',-';
        }

        $('#selected-info-display').text(infoText);
        $('#selected-slot-description').text(selectedSlotDescription || '');

        // Reset form state for new slot selection
        clearErrorMessages();
        $('#description').val('');
        
        if (isAdmin) {
            resetToCurrentUser();
        }
        
        // Validate phone on form load
        validatePhone($('#phone').val());

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
        
        if (isAdmin) {
            resetToCurrentUser();
        }
        clearErrorMessages();
    }

    /**
     * Handle AJAX form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('.booking-submit');
        var $response = $('#booking-response');

        $submitBtn.prop('disabled', true).text(snippenBookingAjax.strings.sendingRequest);
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
            description: $('#description').val(),
            user_id: $('#selected-user-id').val(),
            accept_terms: $('#accept_terms').length ? ($('#accept_terms').is(':checked') ? 1 : 0) : 1
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
                    $response.removeClass('success').addClass('error').html(response.data.message || snippenBookingAjax.strings.somethingWentWrong).fadeIn();
                    $submitBtn.prop('disabled', false).text(snippenBookingAjax.strings.tryAgain);
                }
            },
            error: function () {
                $response.removeClass('success').addClass('error').html(snippenBookingAjax.strings.connectionError).fadeIn();
                $submitBtn.prop('disabled', false).text(snippenBookingAjax.strings.tryAgain);
            }
        });
    }

    /**
     * Admin: Initialize user search
     */
    function initUserSearch() {
        var $search = $('#user-search');
        var $results = $('#user-search-results');
        var $userId = $('#selected-user-id');
        var $name = $('#name');
        var $email = $('#email');
        var searchTimer;

        $search.on('input', function() {
            clearTimeout(searchTimer);
            var term = $(this).val();
            
            // Clear previous errors when starting new search
            clearErrorMessages();

            if (term.length < 2) {
                $results.hide();
                return;
            }

            searchTimer = setTimeout(function() {
                $.ajax({
                    url: snippenBookingAjax.ajaxurl,
                    data: {
                        action: 'snippen_search_users',
                        nonce: snippenBookingAjax.admin_nonce,
                        term: term
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var html = '';
                            response.data.forEach(function(user) {
                                html += '<div class="user-result-item" data-id="' + escHtml(user.id) + '" data-name="' + escHtml(user.name) + '" data-email="' + escHtml(user.email) + '" data-phone="' + escHtml(user.phone || '') + '">';
                                html += '<strong>' + escHtml(user.name) + '</strong><br><small>' + escHtml(user.email) + (user.phone ? ' | ' + escHtml(user.phone) : ' | <span style="color:red">' + snippenBookingAjax.strings.missingPhoneShort + '</span>') + '</small>';
                                html += '</div>';
                            });
                            $results.html(html).show();
                        } else {
                            $results.html('<div class="no-results">' + snippenBookingAjax.strings.noResidentsFound + '</div>').show();
                        }
                    }
                });
            }, 300);
        });

        $(document).on('click', '.user-result-item', function() {
            var $item = $(this);
            var phone = $item.data('phone') || '';
            $userId.val($item.data('id'));
            $name.val($item.data('name'));
            $email.val($item.data('email'));
            $('#phone').val(phone);
            $search.val($item.data('name'));
            
            // Handle submit button state and error message
            var $submitBtn = $('.booking-submit');
            var $phoneGroup = $('#phone').closest('.form-group');
            clearErrorMessages();
            
            validatePhone(phone);

            $results.hide();
        });

        $(document).click(function(e) {
            if (!$(e.target).closest('.user-search-wrapper').length) {
                $results.hide();
            }
        });

        // Revert to current user if search field is cleared and loses focus
        $search.on('blur', function() {
            if ($(this).val().trim() === '') {
                resetToCurrentUser();
            }
        });
    }

    /**
     * Helper: Escape HTML special characters to prevent XSS
     */
    function escHtml(str) {
        if (!str) return '-';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Admin: Show booking details in modal
     */
    function showBookingDetails(data) {
        var html = '<div class="booking-details-grid">';
        
        html += '<div class="detail-item"><strong>Kunde:</strong><br>' + escHtml(data.customer_name) + '</div>';
        html += '<div class="detail-item"><strong>E-post:</strong><br>' + escHtml(data.customer_email) + '</div>';
        html += '<div class="detail-item"><strong>Telefon:</strong><br>' + escHtml(data.customer_phone) + '</div>';
        html += '<div class="detail-item"><strong>Lokale(r):</strong><br>' + escHtml(data.object_names) + '</div>';
        html += '<div class="detail-item"><strong>Slot:</strong><br>' + escHtml(data.slot_name) + '</div>';
        html += '<div class="detail-item"><strong>Tid:</strong><br>' + escHtml(data.start_time ? data.start_time.substring(0, 5) : '') + ' - ' + escHtml(data.end_time ? data.end_time.substring(0, 5) : '') + '</div>';
        
        if (data.description) {
            html += '<div class="detail-item full-width"><strong>Beskrivelse:</strong><br>' + escHtml(data.description) + '</div>';
        }
        
        html += '</div>';

        $('#booking-info-content').html(html);
        $('#booking-info-modal').fadeIn(200);
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

    /**
     * Admin/General: Reset fields to current logged-in user
     */
    function resetToCurrentUser() {
        var $userId = $('#selected-user-id');
        var $name = $('#name');
        var $email = $('#email');
        var $phone = $('#phone');
        var $search = $('#user-search');
        var $submitBtn = $('.booking-submit');

        var defId = $container.data('user-id');
        var defName = $container.data('user-name');
        var defEmail = $container.data('user-email');
        var defPhone = $container.data('user-phone');

        $userId.val(defId);
        $name.val(defName);
        $email.val(defEmail);
        $phone.val(defPhone);
        if ($search.length) {
            $search.val(defName);
        }

        clearErrorMessages();
        $('#user-search-results').hide();
        
        validatePhone(defPhone);
    }

    /**
     * Clear all form error messages
     */
    function clearErrorMessages() {
        $('.field-error-msg').remove();
        $('#booking-response').hide();
    }

    /**
     * Validate phone number and update UI
     */
    function validatePhone(phone) {
        var $submitBtn = $('.booking-submit');
        var $phoneGroup = $('#phone').closest('.form-group');
        
        clearErrorMessages();
        
        if (!phone) {
            $submitBtn.prop('disabled', true);
            $phoneGroup.append('<p class="field-error-msg" style="color: #d63638; font-size: 0.85em; margin-top: 5px;">' + snippenBookingAjax.strings.missingPhoneLong + '</p>');
        } else {
            $submitBtn.prop('disabled', false);
        }
    }

    // --- Snippen Booking List Frontend Actions ---
    // Handle cancellation from the frontend card list
    $('.snippen-booking-list-container').on('click', '.snippen-btn-cancel-booking.cancel', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        var $card = $btn.closest('.booking-list-card');
        var $badge = $card.find('.snippen-badge');

        var confirmMsg = snippenBookingAjax.strings.confirmCancel;
        if (window.confirm && !window.confirm(confirmMsg)) {
            return;
        }

        $btn.prop('disabled', true).css('opacity', '0.5');

        $.post(snippenBookingAjax.ajaxurl, {
            action: 'snippen_update_booking_status',
            nonce: snippenBookingAjax.admin_nonce,
            id: id,
            status: 'cancelled'
        }, function (response) {
            if (response.success) {
                // Update UI badge
                $badge.text(response.data.status_label)
                      .removeClass('snippen-status-pending snippen-status-confirmed snippen-status-cancelled')
                      .addClass('snippen-status-' + response.data.new_status);
                
                // Fade out the cancel button since it's now cancelled
                $btn.fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert(response.data.message || snippenBookingAjax.strings.errorTryAgain);
                $btn.prop('disabled', false).css('opacity', '1');
            }
        }).fail(function () {
            alert(snippenBookingAjax.strings.errorTryAgain);
            $btn.prop('disabled', false).css('opacity', '1');
        });
    });

    // Handle Terms Modal
    $(document).on('click', '.terms-link', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var sep = url.indexOf('?') > -1 ? '&' : '?';
        var bareUrl = url + sep + 'snippen_bare=1';

        var modalHtml = '<div class="snippen-modal terms-modal" style="display:none;">' +
            '<div class="modal-overlay"></div>' +
            '<div class="modal-content" style="max-width: 800px; height: 80vh; display: flex; flex-direction: column;">' +
                '<div class="modal-header">' +
                    '<h4>' + (snippenBookingAjax.strings.termsTitle || 'Vilkår for leie') + '</h4>' +
                    '<button type="button" class="close-modal">&times;</button>' +
                '</div>' +
                '<div class="modal-body" style="flex-grow: 1; padding: 0;">' +
                    '<iframe src="' + bareUrl + '" style="width: 100%; height: 100%; border: none;"></iframe>' +
                '</div>' +
            '</div>' +
        '</div>';

        $('body').append(modalHtml);
        $('.terms-modal').fadeIn(200);
    });

    $(document).on('click', '.terms-modal .close-modal, .terms-modal .modal-overlay', function() {
        $('.terms-modal').fadeOut(200, function() {
            $(this).remove();
        });
    });

    // Handle form submit custom validity
    var $acceptTerms = $('#accept_terms');
    if ($acceptTerms.length) {
        $acceptTerms[0].oninvalid = function(e) {
            e.target.setCustomValidity(snippenBookingAjax.strings.termsRequired || 'Vennligst kryss av i boksen for å gå videre.');
        };
        $acceptTerms[0].oninput = function(e) {
            e.target.setCustomValidity('');
        };
    }

    // Initialize
    init();
});