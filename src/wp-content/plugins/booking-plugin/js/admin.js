/**
 * Snippen Booking Admin JS
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Snippen Booking Admin initialized');
        
        // Handle delete confirmations
        $('.snippen-delete-confirm').on('click', function(e) {
            if (!confirm(snippenAdmin.strings.confirmDelete)) {
                e.preventDefault();
            }
        });

        // Toggle Details (Table version)
        $('.bookings-table').on('click', '.toggle-details', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const $detailsRow = $row.next('.snippen-details-row');
            
            $detailsRow.fadeToggle(200);
            $btn.find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });

        // AJAX Status Update
        $('.bookings-table').on('click', '.snippen-btn-action.approve, .snippen-btn-action.cancel', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const id = $btn.data('id');
            const newStatus = $btn.hasClass('approve') ? 'confirmed' : 'cancelled';
            const $row = $btn.closest('tr');
            const $badge = $row.find('.snippen-badge');

            if (newStatus === 'cancelled' && !confirm(snippenAdmin.strings.confirmCancel)) {
                return;
            }

            $btn.prop('disabled', true).css('opacity', '0.5');

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_update_booking_status',
                nonce: snippenAdmin.nonce,
                id: id,
                status: newStatus
            }, function(response) {
                if (response.success) {
                    // Update UI
                    $badge.text(response.data.status_label)
                          .removeClass('snippen-status-pending snippen-status-confirmed snippen-status-cancelled')
                          .addClass('snippen-status-' + response.data.new_status);
                    
                    // Remove buttons if necessary
                    if (newStatus === 'confirmed') {
                        $row.find('.snippen-btn-action.approve').fadeOut();
                    } else if (newStatus === 'cancelled') {
                        $row.find('.snippen-btn-action.approve, .snippen-btn-action.cancel').fadeOut();
                    }
                } else {
                    alert(response.data.message || snippenAdmin.strings.error);
                }
            }).fail(function() {
                alert(snippenAdmin.strings.error);
            }).always(function() {
                $btn.prop('disabled', false).css('opacity', '1');
            });
        });

        // AJAX Notification Manual Dispatch
        $('.bookings-table').on('click', '.snippen-btn-dispatch', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $container = $btn.closest('.booking-assistant-actions');
            const id = $container.data('id');
            const channel = $btn.data('channel');
            const $feedback = $container.find('.assistant-feedback');

            $feedback.text('').css('color', 'inherit');
            $container.find('.snippen-btn-dispatch').prop('disabled', true).css('opacity', '0.5');
            $feedback.html('<span class="spinner is-active" style="float:none; margin:0 4px 0 0; vertical-align:middle; display:inline-block; visibility:visible;"></span> ' + (typeof snippenAdmin !== 'undefined' && snippenAdmin.strings.sending ? snippenAdmin.strings.sending : 'Sender...'));

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_dispatch_notification_manually',
                nonce: snippenAdmin.nonce,
                id: id,
                channel: channel
            }, function(response) {
                if (response.success) {
                    $feedback.text(response.data.message).css('color', '#15803d');
                } else {
                    $feedback.text(response.data.message || 'Sending feilet.').css('color', '#b91c1c');
                }
            }).fail(function() {
                $feedback.text('En ukjent feil oppstod.').css('color', '#b91c1c');
            }).always(function() {
                $container.find('.snippen-btn-dispatch').prop('disabled', false).css('opacity', '1');
            });
        });
    });

})(jQuery);
