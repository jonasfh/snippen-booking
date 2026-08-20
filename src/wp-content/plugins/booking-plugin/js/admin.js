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

        // AJAX Notification Manual Dispatch (Opens Modal Dialog)
        let activeDispatchData = null;
        let isMessageEdited = false;

        $('.bookings-table').on('click', '.snippen-btn-dispatch', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $container = $btn.closest('.booking-assistant-actions');
            const id = $container.data('id');
            const channel = $btn.data('channel');
            const $feedback = $container.find('.assistant-feedback');

            $feedback.text('').css('color', 'inherit');
            $container.find('.snippen-btn-dispatch').prop('disabled', true).css('opacity', '0.5');
            $feedback.html('<span class="spinner is-active" style="float:none; margin:0 4px 0 0; vertical-align:middle; display:inline-block; visibility:visible;"></span> Henter melding...');

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_get_notification_preview',
                nonce: snippenAdmin.nonce,
                id: id,
                channel: channel
            }, function(response) {
                if (response.success) {
                    $feedback.text('');
                    activeDispatchData = {
                        id: id,
                        channel: channel,
                        $feedback: $feedback,
                        templates: response.data.templates || []
                    };

                    const data = response.data;
                    const $modal = $('#snippen-dispatch-modal');
                    isMessageEdited = false;

                    $modal.find('.snippen-modal-feedback').text('').css('color', 'inherit');
                    $modal.find('.snippen-modal-recipient').val(data.recipient || '');
                    $modal.find('.snippen-modal-message').val(data.message || '');

                    // Populate template dropdown
                    const $select = $modal.find('.snippen-modal-template-select');
                    $select.empty();
                    if (data.templates && data.templates.length > 0) {
                        data.templates.forEach(function(tpl) {
                            const isSel = (tpl.key === data.default_template_key);
                            $select.append($('<option>', {
                                value: tpl.key,
                                text: tpl.label,
                                selected: isSel
                            }));
                        });
                    }

                    // Populate placeholders
                    const $placeholdersWrap = $modal.find('.snippen-modal-placeholders-wrap');
                    $placeholdersWrap.empty();
                    if (data.placeholders) {
                        $.each(data.placeholders, function(key, desc) {
                            const phCode = '{{' + key + '}}';
                            const $chip = $('<button>', {
                                type: 'button',
                                class: 'button button-small snippen-placeholder-btn',
                                text: phCode,
                                title: desc,
                                style: 'font-size:11px; height:24px; line-height:22px; padding:0 6px; border-radius:3px; font-family:monospace;'
                            }).data('code', phCode);
                            $placeholdersWrap.append($chip);
                        });
                    }

                    if (channel === 'email_customer' || channel === 'email_admin') {
                        $modal.find('.snippen-modal-subject-wrap').show();
                        $modal.find('.snippen-modal-subject').val(data.subject || '');
                        if (channel === 'email_customer') {
                            $modal.find('.snippen-modal-title').text('Send e-post til kunde');
                            $modal.find('.snippen-modal-submit').text('Send e-post');
                        } else {
                            $modal.find('.snippen-modal-title').text('Send varsel til admin');
                            $modal.find('.snippen-modal-submit').text('Send e-post');
                        }
                    } else if (channel === 'sms_customer') {
                        $modal.find('.snippen-modal-subject-wrap').hide();
                        $modal.find('.snippen-modal-subject').val('');
                        $modal.find('.snippen-modal-title').text('Send SMS til kunde');
                        $modal.find('.snippen-modal-submit').text('Send SMS');
                    }

                    $modal.fadeIn(200);
                } else {
                    $feedback.text(response.data.message || 'Kunne ikke hente forhåndsvisning.').css('color', '#b91c1c');
                }
            }).fail(function() {
                $feedback.text('En ukjent feil oppstod.').css('color', '#b91c1c');
            }).always(function() {
                $container.find('.snippen-btn-dispatch').prop('disabled', false).css('opacity', '1');
            });
        });

        // Track editing of textarea or subject
        $('#snippen-dispatch-modal').on('input', '.snippen-modal-message, .snippen-modal-subject', function() {
            isMessageEdited = true;
        });

        // Handle template select change with warning if edited
        $('#snippen-dispatch-modal').on('change', '.snippen-modal-template-select', function() {
            const $select = $(this);
            const selectedKey = $select.val();

            if (!activeDispatchData || !activeDispatchData.templates) {
                return;
            }

            if (isMessageEdited) {
                const confirmChange = confirm('Advarsel: Endring av mal vil overskrive teksten du har skrevet. Vil du fortsette?');
                if (!confirmChange) {
                    // Revert select option
                    return;
                }
            }

            const tplObj = activeDispatchData.templates.find(t => t.key === selectedKey);
            if (tplObj) {
                const $modal = $('#snippen-dispatch-modal');
                $modal.find('.snippen-modal-message').val(tplObj.raw_body || '');
                if (activeDispatchData.channel !== 'sms_customer') {
                    $modal.find('.snippen-modal-subject').val(tplObj.raw_subject || '');
                }
                isMessageEdited = false;
            }
        });


        // Handle Placeholder Chip Click (Copies to Clipboard + Inserts at cursor)
        $('#snippen-dispatch-modal').on('click', '.snippen-placeholder-btn', function(e) {
            e.preventDefault();
            const phCode = $(this).data('code');
            const $textarea = $('#snippen-dispatch-modal').find('.snippen-modal-message');
            const textarea = $textarea[0];

            if (phCode && textarea) {
                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(phCode).catch(function() {});
                }

                // Insert into textarea at cursor position
                const startPos = textarea.selectionStart || 0;
                const endPos = textarea.selectionEnd || 0;
                const origVal = textarea.value;

                textarea.value = origVal.substring(0, startPos) + phCode + origVal.substring(endPos, origVal.length);
                textarea.selectionStart = startPos + phCode.length;
                textarea.selectionEnd = startPos + phCode.length;
                textarea.focus();

                isMessageEdited = true;

                // Show feedback hint briefly
                const $hint = $('#snippen-dispatch-modal').find('.snippen-placeholder-copied-hint');
                $hint.stop(true, true).fadeIn(150).delay(2000).fadeOut(200);
            }
        });

        // Modal Close/Cancel handlers
        $('#snippen-dispatch-modal').on('click', '.snippen-modal-close, .snippen-modal-cancel', function(e) {
            e.preventDefault();
            $('#snippen-dispatch-modal').fadeOut(200);
            activeDispatchData = null;
        });

        // Close modal when clicking on backdrop
        $('#snippen-dispatch-modal').on('click', function(e) {
            if ($(e.target).is('#snippen-dispatch-modal')) {
                $('#snippen-dispatch-modal').fadeOut(200);
                activeDispatchData = null;
            }
        });

        // Submit Dispatch from Modal
        $('#snippen-dispatch-modal').on('click', '.snippen-modal-submit', function(e) {
            e.preventDefault();
            if (!activeDispatchData) {
                return;
            }

            const $modal = $('#snippen-dispatch-modal');
            const $submitBtn = $modal.find('.snippen-modal-submit');
            const $modalFeedback = $modal.find('.snippen-modal-feedback');
            const editedSubject = $modal.find('.snippen-modal-subject').val();
            const editedMessage = $modal.find('.snippen-modal-message').val();

            $submitBtn.prop('disabled', true).css('opacity', '0.5');
            $modalFeedback.text('Sender...').css('color', '#6b7280');

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_dispatch_notification_manually',
                nonce: snippenAdmin.nonce,
                id: activeDispatchData.id,
                channel: activeDispatchData.channel,
                subject: editedSubject,
                message: editedMessage
            }, function(response) {
                if (response.success) {
                    $modalFeedback.text(response.data.message).css('color', '#15803d');
                    if (activeDispatchData.$feedback) {
                        activeDispatchData.$feedback.text(response.data.message).css('color', '#15803d');
                    }
                    setTimeout(function() {
                        $modal.fadeOut(200);
                        activeDispatchData = null;
                    }, 1000);
                } else {
                    $modalFeedback.text(response.data.message || 'Sending feilet.').css('color', '#b91c1c');
                }
            }).fail(function() {
                $modalFeedback.text('En ukjent feil oppstod.').css('color', '#b91c1c');
            }).always(function() {
                $submitBtn.prop('disabled', false).css('opacity', '1');
            });
        });

        // AJAX Save Door Code
        $('.bookings-table').on('click', '.snippen-btn-save-door-code', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $container = $btn.closest('.door-code-edit-container');
            const id = $container.data('id');
            const doorCode = $container.find('.door-code-input').val();
            const $feedback = $container.find('.door-code-feedback');

            $btn.prop('disabled', true);
            $feedback.text('Lagrer...').css('color', '#6b7280');

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_update_door_code',
                nonce: snippenAdmin.nonce,
                id: id,
                door_code: doorCode
            }, function(response) {
                if (response.success) {
                    $feedback.text('Lagret').css('color', '#15803d');
                    setTimeout(function() { $feedback.fadeOut(function() { $(this).text('').show(); }); }, 2000);
                } else {
                    $feedback.text(response.data.message || 'Feilet').css('color', '#b91c1c');
                }
            }).fail(function() {
                $feedback.text('Feilet').css('color', '#b91c1c');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });

        // AJAX Save Payment Status
        $('.bookings-table').on('click', '.snippen-btn-save-payment', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $container = $btn.closest('.payment-admin-container');
            const id = $container.data('id');
            const paymentStatusId = $container.find('.payment-status-select').val();
            const paymentNotes = $container.find('.payment-notes-input').val();
            const $feedback = $container.find('.payment-feedback');

            $btn.prop('disabled', true);
            $feedback.text('Lagrer...').css('color', '#6b7280');

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_update_payment_status',
                nonce: snippenAdmin.nonce,
                booking_id: id,
                payment_status_id: paymentStatusId,
                payment_notes: paymentNotes
            }, function(response) {
                if (response.success) {
                    $feedback.text('Lagret').css('color', '#15803d');
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    $feedback.text(response.data.message || 'Feilet').css('color', '#b91c1c');
                }
            }).fail(function() {
                $feedback.text('Feilet').css('color', '#b91c1c');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });

        // AJAX Toggle Status (Time Slots, Pricing Rules, Discount Rules)
        $(document).on('change', '.snippen-toggle-status', function(e) {
            const $checkbox = $(this);
            const id = $checkbox.data('id');
            const entityType = $checkbox.data('entity-type');
            const isChecked = $checkbox.is(':checked') ? 1 : 0;
            const $container = $checkbox.closest('.snippen-switch');

            $checkbox.prop('disabled', true);

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_toggle_entity_status',
                nonce: snippenAdmin.nonce,
                id: id,
                entity_type: entityType,
                is_active: isChecked
            }, function(response) {
                if (response.success) {
                    if (entityType === 'time_slot') {
                        // Dynamically update the corresponding row in the weekly preview matrix if present
                        const $row = $checkbox.closest('tr');
                        const $weeklyTable = $('.wp-list-table');
                        if ($weeklyTable.length && $row.length) {
                            const rowIndex = $row.index();
                            const $weeklyRow = $weeklyTable.find('tbody tr').eq(rowIndex);
                            if ($weeklyRow.length) {
                                if (isChecked) {
                                    $weeklyRow.css({'opacity': '1', 'background-color': ''});
                                    $weeklyRow.find('.snippen-badge').remove();
                                    $weeklyRow.find('td').each(function() {
                                        if ($(this).text().trim() === '✓') {
                                            $(this).css('color', '#46b450');
                                        }
                                    });
                                } else {
                                    $weeklyRow.css({'opacity': '0.55', 'background-color': '#f8fafc'});
                                    if (!$weeklyRow.find('.snippen-badge').length) {
                                        $weeklyRow.find('td').first().find('strong').after(' <span class="snippen-badge snippen-status-cancelled" style="font-size:10px; padding:2px 6px; margin-left:4px;">Deaktivert</span>');
                                    }
                                    $weeklyRow.find('td').each(function() {
                                        if ($(this).text().trim() === '✓') {
                                            $(this).css('color', '#94a3b8');
                                        }
                                    });
                                }
                            }
                        }
                    }
                } else {
                    alert(response.data.message || snippenAdmin.strings.error);
                    $checkbox.prop('checked', !isChecked);
                }
            }).fail(function() {
                alert(snippenAdmin.strings.error);
                $checkbox.prop('checked', !isChecked);
            }).always(function() {
                $checkbox.prop('disabled', false);
            });
        });
    });

})(jQuery);
