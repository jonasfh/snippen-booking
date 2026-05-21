jQuery(document).ready(function($) {
    var $container = $('.snippen-confirmation-container');
    var $step1 = $('#confirmation-step-1');
    var $step2 = $('#confirmation-step-2');
    var $response = $('#confirmation-response');

    function showMessage(msg, type) {
        $response.html(msg).removeClass('error success').addClass(type).show();
    }

    $('#snippen-request-code').on('click', function() {
        var phone = $('#snippen_phone_confirm').val();
        
        if (!phone) {
            showMessage(snippenConfirmation.strings.enterPhone, 'error');
            return;
        }

        $(this).prop('disabled', true).text(snippenConfirmation.strings.sending);

        $.post(snippenConfirmation.ajaxUrl, {
            action: 'snippen_request_confirmation_code',
            phone: phone,
            nonce: snippenConfirmation.nonce
        }, function(res) {
            if (res.success) {
                $('#snippen_confirm_user_id').val(res.data.user_id);
                $step1.hide();
                $step2.show();
                showMessage(res.data.message, 'success');
            } else {
                showMessage(res.data.message || snippenConfirmation.strings.error, 'error');
                $('#snippen-request-code').prop('disabled', false).text(snippenConfirmation.strings.sendCode);
            }
        });
    });

    $('#snippen-verify-code').on('click', function() {
        var userId = $('#snippen_confirm_user_id').val();
        var code = $('#snippen_code').val();
        var password = $('#snippen_new_password').val();
        var confirm = $('#snippen_confirm_password').val();

        if (!code || !password || !confirm) {
            showMessage(snippenConfirmation.strings.fillAllFields, 'error');
            return;
        }

        if (password !== confirm) {
            showMessage(snippenConfirmation.strings.passwordsNotMatch, 'error');
            return;
        }

        if (password.length < 8) {
            showMessage(snippenConfirmation.strings.passwordMinLength, 'error');
            return;
        }

        $(this).prop('disabled', true).text(snippenConfirmation.strings.verifying);

        $.post(snippenConfirmation.ajaxUrl, {
            action: 'snippen_verify_confirmation_code',
            user_id: userId,
            code: code,
            password: password,
            nonce: snippenConfirmation.nonce
        }, function(res) {
            if (res.success) {
                showMessage(res.data.message, 'success');
                $step2.hide();
                setTimeout(function() {
                    window.location.href = '/wp-login.php';
                }, 3000);
            } else {
                showMessage(res.data.message || snippenConfirmation.strings.error, 'error');
                $('#snippen-verify-code').prop('disabled', false).text(snippenConfirmation.strings.confirmAndSavePass);
            }
        });
    });
});
