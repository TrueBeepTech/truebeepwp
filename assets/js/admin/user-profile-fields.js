(function($) {
    'use strict';

    $(document).ready(function() {
        $('#truebeep-smwl-sync-user').on('click', function() {
            var button = $(this);
            var userId = button.data('user-id');

            button.prop('disabled', true).text(truebeep_smwl_user_profile.strings.syncing);

            $.post(ajaxurl, {
                action: 'truebeep_smwl_sync_user',
                user_id: userId,
                nonce: truebeep_smwl_user_profile.nonceSync
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || truebeep_smwl_user_profile.strings.syncFailed);
                    button.prop('disabled', false).text(truebeep_smwl_user_profile.strings.syncWithTruebeep);
                }
            });
        });

        $('#truebeep-smwl-remove-sync').on('click', function() {
            if (!confirm(truebeep_smwl_user_profile.strings.confirmRemove)) {
                return;
            }

            var button = $(this);
            var userId = button.data('user-id');

            button.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'truebeep_smwl_remove_sync',
                user_id: userId,
                nonce: truebeep_smwl_user_profile.nonceRemove
            }, function(response) {
                location.reload();
            });
        });
    });
})(jQuery);

