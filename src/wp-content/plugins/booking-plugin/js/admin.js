/**
 * Snippen Booking Admin JS
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Snippen Booking Admin initialized');
        
        // Handle delete confirmations
        $('.snippen-delete-confirm').on('click', function(e) {
            if (!confirm('Er du sikker på at du vil slette dette elementet?')) {
                e.preventDefault();
            }
        });
        
        // AJAX toggle for simple status switches if needed
    });

})(jQuery);
