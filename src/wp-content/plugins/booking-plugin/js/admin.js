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
            e.stopPropagation();
            const $btn = $(this);
            const $row = $btn.closest('tr.snippen-booking-row');
            const $detailsRow = $row.next('.snippen-details-row');
            
            const isCurrentlyExpanded = $row.hasClass('is-expanded') || $detailsRow.is(':visible');

            if (isCurrentlyExpanded) {
                $detailsRow.removeClass('active').slideUp(180);
                $row.removeClass('is-expanded');
                $row.find('.toggle-details .dashicons')
                    .removeClass('dashicons-arrow-up-alt2')
                    .addClass('dashicons-arrow-down-alt2');
                $row.find('.toggle-details').attr('aria-expanded', 'false').attr('title', 'Vis detaljer');
            } else {
                $detailsRow.addClass('active').slideDown(180);
                $row.addClass('is-expanded');
                $row.find('.toggle-details .dashicons')
                    .removeClass('dashicons-arrow-down-alt2')
                    .addClass('dashicons-arrow-up-alt2');
                $row.find('.toggle-details').attr('aria-expanded', 'true').attr('title', 'Skjul detaljer');
            }
        });

        // Clicking mobile summary card toggles details
        $('.bookings-table').on('click', '.snippen-booking-mobile-summary', function(e) {
            if ($(e.target).closest('button, a, input, select, textarea').length) {
                return;
            }
            $(this).find('.toggle-details').first().trigger('click');
        });

        // AJAX Status Update
        $('.bookings-table').on('click', '.snippen-btn-action.approve, .snippen-btn-action.cancel', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const id = $btn.data('id');
            const newStatus = $btn.hasClass('approve') ? 'confirmed' : 'cancelled';
            const $bookingRow = $('#booking-' + id);
            const $detailsRow = $('#details-' + id);
            let $badges = $bookingRow.find('.snippen-status-badge').add($detailsRow.find('.snippen-status-badge'));
            if (!$badges.length) {
                $badges = $bookingRow.find('td[data-label="Status"] .snippen-badge');
            }

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
                    $badges.text(response.data.status_label)
                          .removeClass('snippen-status-pending snippen-status-confirmed snippen-status-cancelled')
                          .addClass('snippen-status-' + response.data.new_status);
                    
                    // Remove buttons if necessary
                    if (newStatus === 'confirmed') {
                        $bookingRow.find('.snippen-btn-action.approve').fadeOut();
                        $detailsRow.find('.snippen-btn-action.approve').fadeOut();
                    } else if (newStatus === 'cancelled') {
                        $bookingRow.find('.snippen-btn-action.approve, .snippen-btn-action.cancel').fadeOut();
                        $detailsRow.find('.snippen-btn-action.approve, .snippen-btn-action.cancel').fadeOut();
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
                            const $opt = $('<option></option>')
                                .val(tpl.key)
                                .text(tpl.label);
                            if (isSel) {
                                $opt.prop('selected', true);
                            }
                            $select.append($opt);
                        });
                    }

                    // Populate placeholders
                    const $placeholdersWrap = $modal.find('.snippen-modal-placeholders-wrap');
                    $placeholdersWrap.empty();
                    if (data.placeholders) {
                        $.each(data.placeholders, function(key, desc) {
                            const phCode = '{{' + key + '}}';
                            const $chip = $('<button type="button" class="button button-small snippen-placeholder-btn"></button>')
                                .text(phCode)
                                .attr('title', desc)
                                .attr('data-code', phCode)
                                .data('code', phCode)
                                .css({
                                    'font-size': '11px',
                                    'height': '24px',
                                    'line-height': '22px',
                                    'padding': '0 6px',
                                    'border-radius': '3px',
                                    'font-family': 'monospace'
                                });
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
                    $('body').addClass('snippen-modal-open');
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
            $('body').removeClass('snippen-modal-open');
            activeDispatchData = null;
        });

        // Close modal when clicking on backdrop
        $('#snippen-dispatch-modal').on('click', function(e) {
            if ($(e.target).is('#snippen-dispatch-modal')) {
                $('#snippen-dispatch-modal').fadeOut(200);
                $('body').removeClass('snippen-modal-open');
                activeDispatchData = null;
            }
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && $('#snippen-dispatch-modal:visible').length) {
                $('#snippen-dispatch-modal').fadeOut(200);
                $('body').removeClass('snippen-modal-open');
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
                    if (activeDispatchData.id) {
                        refreshBookingMessages(activeDispatchData.id);
                    }
                    setTimeout(function() {
                        $modal.fadeOut(200);
                        $('body').removeClass('snippen-modal-open');
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

        // Function to refresh booking messages via AJAX
        function refreshBookingMessages(bookingId) {
            const $historyContainer = $('.booking-messages-history[data-booking-id="' + bookingId + '"]');
            if (!$historyContainer.length) {
                return;
            }

            $.post(snippenAdmin.ajaxUrl, {
                action: 'snippen_get_booking_messages',
                nonce: snippenAdmin.nonce,
                id: bookingId
            }, function(response) {
                if (response.success && response.data && response.data.messages) {
                    renderMessagesHistory($historyContainer, response.data.messages);
                }
            });
        }

        // Determine if a message is an admin alert or numeric choice reply
        function isSecondaryCommunication(msg) {
            if (!msg) {
                return false;
            }
            const adminEventTypes = ['admin_booking', 'manual_dispatch_admin', 'payment_receipt_uploaded'];
            const eventType = msg.event_type || '';
            if (adminEventTypes.indexOf(eventType) !== -1 || eventType.indexOf('admin') !== -1) {
                return true;
            }

            let meta = {};
            if (msg.metadata) {
                try {
                    meta = (typeof msg.metadata === 'string') ? JSON.parse(msg.metadata) : msg.metadata;
                } catch (e) {}
            }
            if (meta && meta.matched_rule === 'disambiguation_selection') {
                return true;
            }

            const isInbound = (eventType === 'inbound_sms' || msg.status === 'received');
            if (isInbound && msg.message) {
                const trimmed = String(msg.message).trim();
                if (/^\s*(?:nr\.?|nummer|valg|booking|#)?\s*\d+\.?\s*$/i.test(trimmed)) {
                    return true;
                }
            }

            return false;
        }

        // Render messages into container
        function renderMessagesHistory($container, messages) {
            const $countSpan = $container.find('.msg-count');
            const $body = $container.find('.msg-history-body');

            const totalCount = messages ? messages.length : 0;
            let visibleCount = 0;
            let filteredCount = 0;

            // Preserve current checkbox state if it exists
            const $existingCheckbox = $container.find('.snippen-toggle-all-messages');
            const isShowAll = $existingCheckbox.length ? $existingCheckbox.is(':checked') : false;

            if (messages && messages.length > 0) {
                messages.forEach(function(m) {
                    if (isSecondaryCommunication(m)) {
                        filteredCount++;
                    } else {
                        visibleCount++;
                    }
                });
            }

            $countSpan.attr('data-visible-count', visibleCount);
            $countSpan.attr('data-total-count', totalCount);
            $countSpan.text(isShowAll ? totalCount : visibleCount);

            if (visibleCount > 0) {
                $container.addClass('has-visible-messages');
            } else {
                $container.removeClass('has-visible-messages');
            }

            if (isShowAll) {
                $container.addClass('show-all-messages');
            } else {
                $container.removeClass('show-all-messages');
            }

            $body.empty();

            if (!messages || messages.length === 0) {
                $body.html('<p class="no-messages-text" style="margin:0; font-size:12px; color:#64748b;">' +
                    ((window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.noMessages) || 'Ingen meldinger registrert på denne bookingen ennå.') +
                    '</p>');
                return;
            }

            const hiddenText = filteredCount === 1 ? '1 skjult' : filteredCount + ' skjulte';
            const indicatorText = isShowAll ? '(viser alle)' : '(' + hiddenText + ')';
            const indicatorDisplay = filteredCount > 0 ? '' : 'style="display:none;"';

            const toolbarHtml = '<div class="msg-history-toolbar">' +
                '<label class="msg-history-filter-toggle">' +
                '<input type="checkbox" class="snippen-toggle-all-messages"' + (isShowAll ? ' checked' : '') + ' /> ' +
                '<span>' + ((window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.showAllCommunication) || 'Vis all kommunikasjon') + '</span>' +
                '</label>' +
                '<span class="msg-filtered-indicator" ' + indicatorDisplay + '>' + escapeHtml(indicatorText) + '</span>' +
                '</div>';

            const noVisibleTextHtml = '<p class="no-visible-messages-text" style="margin:0; font-size:12px; color:#64748b;">' +
                ((window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.noFilteredMessages) || 'Ingen meldinger å vise med gjeldende filter.') +
                '</p>';

            $body.append(toolbarHtml);
            $body.append(noVisibleTextHtml);

            const $listContainer = $('<div class="msg-list-container"></div>');
            $body.append($listContainer);

            const knownLabels = {
                'booking_confirmation': 'Booking-bekreftelse',
                'manual_dispatch_customer': 'Manuell leietakermelding',
                'admin_booking': 'Admin bookingvarsel',
                'manual_dispatch_admin': 'Manuell adminmelding',
                'user_activation': 'Kontoaktivering',
                'password_reset': 'Passordtilbakestilling',
                'payment_reminder': 'Betalingspåminnelse',
                'payment_receipt_uploaded': 'Kvittering lastet opp',
                'booking_confirmed': 'Booking godkjent',
                'payment_received': 'Betaling bekreftet',
                'inbound_sms': 'Innkommende SMS',
                'sms_disambiguation_prompt': 'Valgforespørsel (SMS)',
                'sms_disambiguation_confirmation': 'Valgbekreftelse (SMS)'
            };

            messages.forEach(function(msg) {
                const iconClass = (msg.channel === 'sms') ? 'dashicons-smartphone' : 'dashicons-email-alt';
                const channelLabel = (msg.channel || '').toUpperCase();
                let statusBadge = '';
                if (msg.status === 'sent') {
                    statusBadge = '<span class="snippen-badge" style="background:#dcfce7; color:#15803d; font-size:10px; padding:1px 5px;">Sendt</span>';
                } else if (msg.status === 'queued') {
                    statusBadge = '<span class="snippen-badge" style="background:#fef3c7; color:#b45309; font-size:10px; padding:1px 5px;">I kø</span>';
                } else if (msg.status === 'received') {
                    statusBadge = '<span class="snippen-badge" style="background:#e0e7ff; color:#3730a3; font-size:10px; padding:1px 5px;">Mottatt</span>';
                } else {
                    statusBadge = '<span class="snippen-badge" style="background:#fee2e2; color:#b91c1c; font-size:10px; padding:1px 5px;">Feilet</span>';
                }

                const eventType = msg.event_type || '';
                const labelText = knownLabels[eventType] || eventType;
                const isFiltered = isSecondaryCommunication(msg);

                const itemClasses = 'msg-item' + (isFiltered ? ' msg-item-filtered' : '');
                let $item = $('<div class="' + itemClasses + '" data-event-type="' + escapeHtml(eventType) + '"></div>');

                let subjectHtml = '';
                if (msg.subject) {
                    subjectHtml = '<div style="font-weight:600; color:#334155; margin-bottom:2px;">Emne: ' + escapeHtml(msg.subject) + '</div>';
                }

                $item.html(
                    '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">' +
                    '<div><span class="dashicons ' + iconClass + '" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:middle;"></span> <strong>' + escapeHtml(channelLabel) + ' &bull; ' + escapeHtml(msg.recipient) + '</strong> ' + statusBadge + ' <span style="font-size:10px; color:#64748b; margin-left:4px;">(' + escapeHtml(labelText) + ')</span></div>' +
                    '<span style="font-size:11px; color:#64748b;">' + escapeHtml(msg.created_at) + '</span>' +
                    '</div>' +
                    subjectHtml +
                    '<div class="msg-item-body">' + escapeHtml(msg.message) + '</div>'
                );

                $listContainer.append($item);
            });

            // If history body was closed when new message arrived, auto-expand it
            if (!$body.is(':visible')) {
                $body.slideDown(200);
                const $btn = $container.find('.toggle-msg-history');
                $btn.attr('aria-expanded', 'true');
                $btn.find('.toggle-text').text((window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.hideCommunication) || 'Skjul kommunikasjon');
                $btn.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        }

        // Escape HTML utility
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Toggle communication history
        $('.bookings-table').on('click', '.toggle-msg-history, .msg-history-header', function(e) {
            if ($(e.target).closest('input, select, textarea').length) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            const $header = $(this).closest('.booking-messages-history').find('.msg-history-header');
            const $container = $header.closest('.booking-messages-history');
            const $body = $container.find('.msg-history-body');
            const $btn = $header.find('.toggle-msg-history');
            const $text = $btn.find('.toggle-text');
            const $icon = $btn.find('.dashicons');

            $body.slideToggle(200, function() {
                const isOpen = $body.is(':visible');
                $btn.attr('aria-expanded', isOpen ? 'true' : 'false');
                $text.text(isOpen
                    ? ((window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.hideCommunication) || 'Skjul kommunikasjon')
                    : ((window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.showCommunication) || 'Vis kommunikasjon')
                );
                if (isOpen) {
                    $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                } else {
                    $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                }
            });
        });

        // Toggle "Vis all kommunikasjon"
        $('.bookings-table').on('change', '.snippen-toggle-all-messages', function(e) {
            e.stopPropagation();
            const $cb = $(this);
            const $container = $cb.closest('.booking-messages-history');
            const $countSpan = $container.find('.msg-count');
            const $indicator = $container.find('.msg-filtered-indicator');
            const isChecked = $cb.is(':checked');

            const visibleCount = parseInt($countSpan.attr('data-visible-count'), 10) || 0;
            const totalCount = parseInt($countSpan.attr('data-total-count'), 10) || 0;
            const filteredCount = totalCount - visibleCount;

            if (isChecked) {
                $container.addClass('show-all-messages');
                $countSpan.text(totalCount);
                if (filteredCount > 0) {
                    $indicator.text('(viser alle)').show();
                }
            } else {
                $container.removeClass('show-all-messages');
                $countSpan.text(visibleCount);
                if (filteredCount > 0) {
                    const hiddenText = filteredCount === 1 ? '1 skjult' : filteredCount + ' skjulte';
                    $indicator.text('(' + hiddenText + ')').show();
                }
            }
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
            const paymentStatusId = $container.find('input.payment-status-radio:checked').val() || $container.find('.payment-status-select').val();
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
