/**
 * Snippen Booking Plugin JavaScript - Wizard Block-Based
 */

jQuery(document).ready(function ($) {
    'use strict';

    var currentStartDate = new Date();
    currentStartDate.setHours(0, 0, 0, 0);

    // Adjust to Monday
    var day = currentStartDate.getDay();
    var diff = currentStartDate.getDate() - day + (day === 0 ? -6 : 1);
    currentStartDate.setDate(diff);

    var selectedDate = null;
    var selectedBlockIds = [];
    var selectedObjectIds = [];
    var dailyBlocksData = {}; // Cache blocks data for current week

    var $container = $('.snippen-booking-container');
    var objectIds = $container.data('object-id'); // Array of IDs
    var isAdmin = $container.data('is-admin') === true;
    var isLoggedIn = $container.data('logged-in') === true;
    var originalSubmitText = $('.booking-submit').text() || 'Send bookingforespørsel';

    function init() {
        if (!$('#calendar-container').length) return;

        renderCalendar();

        // Object drawer toggle click (when multiple objects exist)
        $(document).on('click', '.object-drawer-toggle', function () {
            var $btn = $(this);
            var $drawer = $btn.closest('.object-drawer');
            var $content = $drawer.find('.object-drawer-content');
            var $icon = $btn.find('.drawer-icon');
            var isExpanded = $btn.attr('aria-expanded') === 'true';

            if (isExpanded) {
                $content.slideUp(150);
                $btn.attr('aria-expanded', 'false');
                $drawer.removeClass('open');
                $icon.text('▾');
            } else {
                $content.slideDown(150);
                $btn.attr('aria-expanded', 'true');
                $drawer.addClass('open');
                $icon.text('▴');
            }
        });

        // Week navigation
        $(document).on('click', '.week-nav-next', function () {
            currentStartDate.setDate(currentStartDate.getDate() + 7);
            renderCalendar();
            closeWizard();
        });

        $(document).on('click', '.week-nav-prev', function () {
            currentStartDate.setDate(currentStartDate.getDate() - 7);
            renderCalendar();
            closeWizard();
        });

        // Current week range click toggles dropdown
        $(document).on('click', '.current-week-range', function (e) {
            e.stopPropagation();
            $('.week-picker-dropdown').fadeToggle(200);
        });

        $(document).on('click', '.week-picker-dropdown li', function () {
            var startStr = $(this).data('start');
            currentStartDate = new Date(startStr);
            renderCalendar();
            closeWizard();
        });

        $(document).click(function () {
            $('.week-picker-dropdown').fadeOut(200);
        });

        // Day click starts the wizard
        $(document).on('click', '.day-column:not(.past)', function (e) {
            if ($(e.target).closest('.slot-item.booked').length) {
                return; // Let admin view booking details
            }
            if (!isLoggedIn) {
                $('.snippen-login-prompt').fadeOut(150).fadeIn(150);
                return;
            }

            var dateStr = $(this).data('date');
            openWizard(dateStr);
        });

        // Clicking a slot directly in the calendar
        $(document).on('click', '.slot-item.available', function (e) {
            e.stopPropagation();
            if (!isLoggedIn) {
                $('.snippen-login-prompt').fadeOut(150).fadeIn(150);
                return;
            }

            var dateStr = $(this).data('date');
            var blockId = $(this).data('block-id');
            openWizard(dateStr, blockId);
        });

        // Close wizard
        $(document).on('click', '.close-wizard', function () {
            closeWizard();
        });

        // Form submit
        $('#booking-form').on('submit', handleFormSubmit);

        // Admin: Booked slot click
        if (isAdmin) {
            initUserSearch();

            $(document).on('click', '.slot-item.booked', function(e) {
                e.stopPropagation();
                var bookingData = $(this).data('booking-info');
                if (bookingData) {
                    showBookingDetails(bookingData);
                }
            });

            $(document).on('click', '.close-modal, .modal-overlay', function() {
                $('#booking-info-modal').fadeOut(200);
                $('body').removeClass('snippen-modal-open');
            });
        }
    }

    /**
     * Render the weekly calendar
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
                object_id: objectIds,
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
     * Draw weekly grid layout with simplified per-object summary
     */
    function drawWeek(data) {
        var $calendar = $('#calendar-container');
        var days = data.days;

        var weekHtml = '<div class="calendar-header">';
        weekHtml += '<button class="week-nav-btn week-nav-prev" title="Forrige uke">&larr;</button>';
        weekHtml += '<div class="current-week-range">' + formatDateRange(currentStartDate) + '</div>';
        weekHtml += generateWeekPicker();
        weekHtml += '<button class="week-nav-btn week-nav-next" title="Neste uke">&rarr;</button>';
        weekHtml += '</div>';

        weekHtml += '<div class="week-grid">';

        days.forEach(function (dayInfo) {
            var isSelected = selectedDate === dayInfo.date;
            
            // Cache block data for wizard
            dailyBlocksData[dayInfo.date] = dayInfo.blocks;

            weekHtml += '<div class="day-column ' + (dayInfo.is_past ? 'past' : '') + (isSelected ? ' selected-day' : '') + '" data-date="' + dayInfo.date + '">';
            weekHtml += '<div class="day-header">';
            weekHtml += '<span class="day-name">' + dayInfo.day_name + '</span>';
            weekHtml += '<span class="day-date">' + dayInfo.day_date_formatted + '</span>';
            weekHtml += '</div>';

            weekHtml += '<div class="slots-container">';

            if (dayInfo.objects_summary && dayInfo.objects_summary.length > 0) {
                dayInfo.objects_summary.forEach(function (objItem) {
                    weekHtml += '<div class="day-object-summary ' + objItem.status_key + '">';
                    weekHtml += '<span class="object-summary-text">' + escHtml(objItem.name) + ' ' + escHtml(objItem.status_text) + '</span>';
                    weekHtml += '</div>';
                });
            } else if (!dayInfo.blocks || dayInfo.blocks.length === 0) {
                weekHtml += '<div class="no-slots-info">' + (snippenBookingAjax.strings.noSlotsAvailable || 'Ingen tider') + '</div>';
            }

            weekHtml += '</div>'; // slots-container
            weekHtml += '</div>'; // day-column
        });

        weekHtml += '</div>'; // week-grid

        $calendar.html(weekHtml);
    }

    function formatTimeInterval(startTime, endTime) {
        if (!startTime || !endTime) return '';
        var start = startTime.substring(0, 5);
        var end = endTime.substring(0, 5);
        if (start.slice(-3) === ':00') {
            start = start.slice(0, -3);
            if (start.length === 2 && start.charAt(0) === '0') {
                start = start.charAt(1);
            }
        }
        if (end.slice(-3) === ':00') {
            end = end.slice(0, -3);
            if (end.length === 2 && end.charAt(0) === '0') {
                end = end.charAt(1);
            }
        }
        return 'kl ' + start + ' - ' + end;
    }

    /**
     * Week picker dropdown
     */
    function generateWeekPicker() {
        var html = '<div class="week-picker-dropdown"><ul>';
        var tempDate = new Date();
        var day = tempDate.getDay();
        var diff = tempDate.getDate() - day + (day === 0 ? -6 : 1);
        tempDate.setDate(diff);
        tempDate.setHours(0, 0, 0, 0);

        var horizonWeeks = snippenBookingAjax.bookingHorizonWeeks ? parseInt(snippenBookingAjax.bookingHorizonWeeks, 10) : 52;
        for (var i = 0; i < horizonWeeks; i++) {
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

    function getWeekNumber(d) {
        d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
        d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }

    /**
     * Open booking wizard
     */
    function openWizard(dateStr, initialBlockId) {
        selectedDate = dateStr;
        selectedBlockIds = [];
        selectedObjectIds = [];

        $('.day-column').removeClass('selected-day');
        $('.day-column[data-date="' + dateStr + '"]').addClass('selected-day');

        $('#event-date').val(dateStr);

        // Populate blocks
        var blocks = dailyBlocksData[dateStr] || [];
        var blocksHtml = '';

        blocks.forEach(function (block) {
            var statusClass = block.is_available ? 'available' : 'booked';
            var timeStr = formatTimeInterval(block.start_time, block.end_time);
            var bookingInfoStr = (isAdmin && block.booking_info) ? JSON.stringify(block.booking_info).replace(/"/g, '&quot;') : '';
            
            blocksHtml += '<div class="block-select-item ' + statusClass + '" data-id="' + block.id + '" data-start="' + block.start_time + '" data-end="' + block.end_time + '" ' + (isAdmin && bookingInfoStr ? 'data-booking-info="' + bookingInfoStr + '"' : '') + '>';
            blocksHtml += '<strong>' + escHtml(block.name) + '</strong>';
            if (timeStr) {
                blocksHtml += '<div class="block-time">' + timeStr + '</div>';
            }
            
            if (block.is_available && block.available_object_names && block.available_object_names.length > 0) {
                blocksHtml += '<div class="block-objects-available">' + escHtml(block.available_object_names.join(', ')) + '</div>';
            } else if (!block.is_available) {
                if (isAdmin && block.booked_by) {
                    blocksHtml += '<div class="customer-name-label">Booket av: ' + escHtml(block.booked_by) + '</div>';
                } else {
                    blocksHtml += '<div class="booking-info">' + (snippenBookingAjax.strings.bookedLabel || 'Opptatt') + '</div>';
                }
            }
            blocksHtml += '</div>';
        });

        $('#blocks-selection-grid').html(blocksHtml);

        // Hide subsequent steps
        $('#step-rooms').hide();
        $('#step-confirm').hide();

        $('#booking-wizard-container').slideDown(400);

        // Auto click initial block if provided
        if (initialBlockId) {
            $('.block-select-item[data-id="' + initialBlockId + '"]').click();
        }

        $('html, body').animate({
            scrollTop: $("#booking-wizard-container").offset().top - 40
        }, 600);
    }

    /**
     * Close wizard
     */
    function closeWizard() {
        $('#booking-wizard-container').slideUp(300);
        $('.day-column').removeClass('selected-day');
        selectedDate = null;
        selectedBlockIds = [];
        selectedObjectIds = [];
        
        // Hide and clear response messages
        $('#booking-response').hide().html('');
        $('#summary-wash-notice').hide();
        
        // Reset submit button text and enabled status
        var $submitBtn = $('#booking-form').find('.booking-submit');
        var hasPhone = $container.data('user-phone') !== '';
        if (hasPhone || isAdmin) {
            $submitBtn.prop('disabled', false);
        } else {
            $submitBtn.prop('disabled', true);
        }
        $submitBtn.text(originalSubmitText);
    }

    // Handle block click in wizard (adjacent selection reinforcement)
    $(document).on('click', '.block-select-item.available', function () {
        var clickedId = parseInt($(this).data('id'));
        var blocks = dailyBlocksData[selectedDate] || [];

        if (selectedBlockIds.includes(clickedId)) {
            // If already selected, remove it (or reset to single selection if clicking in middle)
            var idx = selectedBlockIds.indexOf(clickedId);
            selectedBlockIds.splice(idx, 1);
        } else {
            selectedBlockIds.push(clickedId);
        }

        if (selectedBlockIds.length > 1) {
            // Find min/max indices in daily blocks list to fill adjacent blocks
            var blockIndices = selectedBlockIds.map(function (id) {
                return blocks.findIndex(function (b) { return b.id === id; });
            });

            var minIdx = Math.min.apply(null, blockIndices);
            var maxIdx = Math.max.apply(null, blockIndices);

            // Check if all blocks in range [minIdx, maxIdx] are available
            var hasBookedInBetween = false;
            for (var i = minIdx; i <= maxIdx; i++) {
                if (!blocks[i].is_available) {
                    hasBookedInBetween = true;
                    break;
                }
            }

            if (hasBookedInBetween) {
                // Cannot select across booked blocks; set selection to only the clicked block
                selectedBlockIds = [clickedId];
            } else {
                // Expand selection to cover contiguous range
                selectedBlockIds = [];
                for (var j = minIdx; j <= maxIdx; j++) {
                    selectedBlockIds.push(blocks[j].id);
                }
            }
        }

        // Update UI states
        $('.block-select-item').removeClass('selected');
        selectedBlockIds.forEach(function (id) {
            $('.block-select-item[data-id="' + id + '"]').addClass('selected');
        });

        if (selectedBlockIds.length > 0) {
            loadRoomAvailability();
        } else {
            $('#step-rooms').hide();
            $('#step-confirm').hide();
        }
    });

    /**
     * Load room availability based on selected date and blocks
     */
    function loadRoomAvailability() {
        $('#rooms-selection-grid').html('<div class="loading">' + (snippenBookingAjax.strings.loadingRooms || 'Sjekker lokaler...') + '</div>');
        $('#step-rooms').show();

        $.ajax({
            url: snippenBookingAjax.ajaxurl,
            type: 'GET',
            data: {
                action: 'snippen_get_objects_availability',
                object_id: objectIds,
                selected_object_ids: selectedObjectIds,
                event_date: selectedDate,
                block_ids: selectedBlockIds,
                nonce: snippenBookingAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderRooms(response.data.objects);
                }
            }
        });
    }

    /**
     * Render room choice selector
     */
    function renderRooms(rooms) {
        var html = '';
        rooms.forEach(function (room) {
            var isSelected = selectedObjectIds.includes(room.id);
            var statusClass = room.is_available ? 'available' : 'booked';
            if (isSelected && room.is_available) statusClass += ' selected';

            html += '<div class="room-select-item ' + statusClass + '" data-id="' + room.id + '">';
            html += '<span class="room-status-dot"></span>';
            html += '<span class="room-name">' + room.name + '</span>';
            html += '</div>';
        });

        $('#rooms-selection-grid').html(html);
        updateSummaryAndConfirm();
    }

    // Handle room click in wizard
    $(document).on('click', '.room-select-item.available', function () {
        var clickedId = parseInt($(this).data('id'));
        if (selectedObjectIds.includes(clickedId)) {
            var idx = selectedObjectIds.indexOf(clickedId);
            selectedObjectIds.splice(idx, 1);
        } else {
            selectedObjectIds.push(clickedId);
        }

        // Toggle selected class
        $(this).toggleClass('selected');
        updateSummaryAndConfirm();
    });

    /**
     * Calculate summary details and display form
     */
    function updateSummaryAndConfirm() {
        if (selectedObjectIds.length === 0) {
            $('#step-confirm').hide();
            return;
        }

        // Get selected blocks info
        var blocks = dailyBlocksData[selectedDate] || [];
        var selectedBlocksObj = blocks.filter(b => selectedBlockIds.includes(b.id));

        // Contiguous start & end
        var startTime = selectedBlocksObj[0].start_time.substring(0, 5);
        var endTime = selectedBlocksObj[selectedBlocksObj.length - 1].end_time.substring(0, 5);

        var dateObj = new Date(selectedDate);
        var formattedDate = dateObj.toLocaleDateString('nb-NO', { weekday: 'long', day: 'numeric', month: 'long' });

        $('#summary-date').text(formattedDate);
        $('#summary-time').text(startTime + ' - ' + endTime);

        // Fetch selected room names
        var roomNames = [];
        $('.room-select-item.selected').each(function () {
            roomNames.push($(this).find('.room-name').text());
        });
        $('#summary-rooms').text(roomNames.join(', '));

        // Show loading price
        $('#summary-price').text('...');
        $('#step-confirm').show();

        // Get combined price
        $.ajax({
            url: snippenBookingAjax.ajaxurl,
            type: 'GET',
            data: {
                action: 'snippen_get_objects_availability',
                object_id: objectIds,
                selected_object_ids: selectedObjectIds,
                event_date: selectedDate,
                block_ids: selectedBlockIds,
                nonce: snippenBookingAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.custom_instructions && response.data.custom_instructions.length > 0) {
                        $('#summary-wash-notice-text').text(response.data.custom_instructions.join(' | '));
                        $('#summary-wash-notice').slideDown(200);
                    } else {
                        $('#summary-wash-notice').slideUp(200);
                    }

                    if (response.data.discount_amount > 0) {
                        var html = '<div style="text-decoration: line-through; color: #64748b; font-size: 0.9em;">kr. ' + Math.round(response.data.base_price) + ',-</div>';
                        html += '<div style="color: #16a34a; font-size: 0.9em; margin-bottom: 5px;">Rabatt: -kr. ' + Math.round(response.data.discount_amount) + ',-</div>';
                        html += '<div style="font-weight: bold; font-size: 1.2em;">kr. ' + Math.round(response.data.price) + ',-</div>';
                        $('#summary-price').html(html);
                    } else {
                        $('#summary-price').text('kr. ' + Math.round(response.data.price) + ',-');
                    }
                }
            }
        });
    }

    /**
     * Handle form submit
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
            booking_object_id: selectedObjectIds,
            event_date: selectedDate,
            block_ids: selectedBlockIds,
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
                        closeWizard();
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
     * Admin: user search
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
                                html += '<strong>' + escHtml(user.name) + '</strong><br><small>' + escHtml(user.email) + '</small>';
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
            $userId.val($item.data('id'));
            $name.val($item.data('name'));
            $email.val($item.data('email'));
            $('#phone').val($item.data('phone') || '');
            $search.val($item.data('name'));
            $results.hide();
        });

        $(document).click(function(e) {
            if (!$(e.target).closest('.user-search-wrapper').length) {
                $results.hide();
            }
        });
    }

    function escHtml(str) {
        if (!str) return '-';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function showBookingDetails(data) {
        var html = '<div class="booking-details-grid">';
        html += '<div class="detail-item"><strong>Kunde:</strong><br>' + escHtml(data.customer_name) + '</div>';
        html += '<div class="detail-item"><strong>E-post:</strong><br>' + escHtml(data.customer_email) + '</div>';
        html += '<div class="detail-item"><strong>Telefon:</strong><br>' + escHtml(data.customer_phone) + '</div>';
        html += '<div class="detail-item"><strong>Lokale(r):</strong><br>' + escHtml(data.object_names) + '</div>';
        html += '<div class="detail-item"><strong>Blokk:</strong><br>' + escHtml(data.slot_name) + '</div>';
        html += '<div class="detail-item"><strong>Tid:</strong><br>' + escHtml(data.start_time.substring(0, 5)) + ' - ' + escHtml(data.end_time.substring(0, 5)) + '</div>';
        
        if (data.description) {
            html += '<div class="detail-item full-width"><strong>Beskrivelse:</strong><br>' + escHtml(data.description) + '</div>';
        }
        html += '</div>';

        $('#booking-info-content').html(html);
        $('body').addClass('snippen-modal-open');
        $('#booking-info-modal').fadeIn(200);
    }

    function formatDateISO(date) {
        var d = new Date(date);
        var month = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        var year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    function formatDateRange(start) {
        var end = new Date(start);
        end.setDate(end.getDate() + 6);

        var startOptions = { day: 'numeric', month: 'short' };
        var endOptions = { day: 'numeric', month: 'short', year: 'numeric' };

        return start.toLocaleDateString('nb-NO', startOptions) + ' - ' + end.toLocaleDateString('nb-NO', endOptions);
    }

    // Handle Terms Modal
    $(document).on('click', '.terms-link', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var sep = url.indexOf('?') > -1 ? '&' : '?';
        var bareUrl = url + sep + 'snippen_bare=1';

        var modalHtml = '<div class="snippen-modal terms-modal" style="display:none;" role="dialog" aria-modal="true">' +
            '<div class="modal-overlay"></div>' +
            '<div class="modal-content" style="max-width: 800px; height: 80vh; height: 80dvh; display: flex; flex-direction: column;">' +
                '<div class="modal-header">' +
                    '<h4>' + (snippenBookingAjax.strings.termsTitle || 'Vilkår for leie') + '</h4>' +
                    '<button type="button" class="close-modal" aria-label="Lukk">&times;</button>' +
                '</div>' +
                '<div class="modal-body" style="flex-grow: 1; padding: 0; overflow: hidden;">' +
                    '<iframe src="' + bareUrl + '" style="width: 100%; height: 100%; border: none;"></iframe>' +
                '</div>' +
                '<div class="modal-footer">' +
                    '<button type="button" class="snippen-modal-footer-close-btn close-modal">' + (snippenBookingAjax.strings.close || 'Lukk') + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        $('body').append(modalHtml);
        $('body').addClass('snippen-modal-open');
        $('.terms-modal').fadeIn(200);
    });

    $(document).on('click', '.terms-modal .close-modal, .terms-modal .modal-overlay', function() {
        $('.terms-modal').fadeOut(200, function() {
            $(this).remove();
            if (!$('#booking-info-modal:visible').length) {
                $('body').removeClass('snippen-modal-open');
            }
        });
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if ($('.terms-modal:visible').length) {
                $('.terms-modal').fadeOut(200, function() {
                    $(this).remove();
                    if (!$('#booking-info-modal:visible').length) {
                        $('body').removeClass('snippen-modal-open');
                    }
                });
            } else if ($('#booking-info-modal:visible').length) {
                $('#booking-info-modal').fadeOut(200);
                $('body').removeClass('snippen-modal-open');
            }
        }
    });

    init();
});