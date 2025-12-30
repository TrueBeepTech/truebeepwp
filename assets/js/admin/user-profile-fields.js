(function($) {
    'use strict';

    $(document).ready(function() {
        $('#truebeep-sync-user').on('click', function() {
            var button = $(this);
            var userId = button.data('user-id');

            button.prop('disabled', true).text(truebeepUserProfile.strings.syncing);

            $.post(ajaxurl, {
                action: 'truebeep_sync_user',
                user_id: userId,
                nonce: truebeepUserProfile.nonceSync
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || truebeepUserProfile.strings.syncFailed);
                    button.prop('disabled', false).text(truebeepUserProfile.strings.syncWithTruebeep);
                }
            });
        });

        $('#truebeep-remove-sync').on('click', function() {
            if (!confirm(truebeepUserProfile.strings.confirmRemove)) {
                return;
            }

            var button = $(this);
            var userId = button.data('user-id');

            button.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'truebeep_remove_sync',
                user_id: userId,
                nonce: truebeepUserProfile.nonceRemove
            }, function(response) {
                location.reload();
            });
        });
    });
})(jQuery);

