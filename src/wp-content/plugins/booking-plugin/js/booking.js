/**
 * Snippen Booking Plugin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Handle booking form submission
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('.booking-submit');
        var $response = $('#booking-response');

        // Disable submit button
        $submitBtn.prop('disabled', true).text('Submitting...');

        // Hide previous response
        $response.hide();

        // Collect form data
        var formData = {
            action: 'snippen_booking_submit',
            nonce: snippenBookingAjax.nonce,
            facility: $('#facility').val(),
            event_date: $('#event-date').val(),
            start_time: $('#start-time').val(),
            end_time: $('#end-time').val(),
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

                    // Reset form
                    $form[0].reset();
                } else {
                    $response.removeClass('success').addClass('error')
                            .html('An error occurred. Please try again.')
                            .show();
                }
            },
            error: function() {
                $response.removeClass('success').addClass('error')
                        .html('Connection error. Please try again.')
                        .show();
            },
            complete: function() {
                // Re-enable submit button
                $submitBtn.prop('disabled', false).text('Submit Booking Request');
            }
        });
    });

    // Set minimum date to today
    var today = new Date().toISOString().split('T')[0];
    $('#event-date').attr('min', today);

    // Basic form validation
    $('#start-time, #end-time').on('change', function() {
        var startTime = $('#start-time').val();
        var endTime = $('#end-time').val();

        if (startTime && endTime && startTime >= endTime) {
            alert('End time must be after start time.');
            $('#end-time').val('');
        }
    });

    // Auto-format phone number (basic)
    $('#phone').on('input', function() {
        var phone = $(this).val().replace(/\D/g, '');
        if (phone.length >= 8) {
            // Simple Norwegian phone number formatting
            if (phone.length === 8) {
                phone = phone.replace(/(\d{3})(\d{2})(\d{3})/, '$1 $2 $3');
            }
            $(this).val(phone);
        }
    });
});