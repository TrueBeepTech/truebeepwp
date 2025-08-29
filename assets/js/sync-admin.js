/**
 * Truebeep Customer Sync Admin JavaScript
 */

(function($) {
    'use strict';

    var TruebeepSync = {
        /**
         * Initialize sync functionality
         */
        init: function() {
            this.bindEvents();
            this.startPolling();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#start-sync').on('click', this.startSync.bind(this));
            $('#cancel-sync').on('click', this.cancelSync.bind(this));
            $('#resume-sync').on('click', this.resumeSync.bind(this));
            $('#reset-sync').on('click', this.resetSync.bind(this));
        },

        /**
         * Start sync process
         */
        startSync: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            $button.prop('disabled', true)
                   .html('<span class="spinner is-active" style="margin: 0;"></span> ' + truebeep_sync.strings.starting);

            $.ajax({
                url: truebeep_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'truebeep_start_sync',
                    nonce: truebeep_sync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || truebeep_sync.strings.error);
                        $button.prop('disabled', false)
                               .html('<span class="dashicons dashicons-update"></span> Start Sync');
                    }
                },
                error: function() {
                    alert(truebeep_sync.strings.error);
                    $button.prop('disabled', false)
                           .html('<span class="dashicons dashicons-update"></span> Start Sync');
                }
            });
        },

        /**
         * Cancel sync process
         */
        cancelSync: function(e) {
            e.preventDefault();
            
            if (!confirm(truebeep_sync.strings.confirm_cancel)) {
                return;
            }
            
            var $button = $(e.currentTarget);
            $button.prop('disabled', true)
                   .html('<span class="spinner is-active" style="margin: 0;"></span> ' + truebeep_sync.strings.stopping);

            $.ajax({
                url: truebeep_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'truebeep_cancel_sync',
                    nonce: truebeep_sync.nonce
                },
                success: function(response) {
                    location.reload();
                },
                error: function() {
                    alert(truebeep_sync.strings.error);
                    location.reload();
                }
            });
        },

        /**
         * Resume paused sync
         */
        resumeSync: function(e) {
            e.preventDefault();
            this.startSync(e);
        },

        /**
         * Reset sync data
         */
        resetSync: function(e) {
            e.preventDefault();
            
            if (!confirm(truebeep_sync.strings.confirm_reset)) {
                return;
            }

            $.ajax({
                url: truebeep_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'truebeep_reset_sync',
                    nonce: truebeep_sync.nonce
                },
                success: function(response) {
                    location.reload();
                },
                error: function() {
                    alert(truebeep_sync.strings.error);
                }
            });
        },

        /**
         * Start polling for status updates
         */
        startPolling: function() {
            // Only poll if sync is running
            if ($('.status-processing, .status-running, .status-preparing').length === 0) {
                return;
            }

            this.pollStatus();
            setInterval(this.pollStatus.bind(this), 5000); // Poll every 5 seconds
        },

        /**
         * Poll for status updates
         */
        pollStatus: function() {
            $.ajax({
                url: truebeep_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'truebeep_get_sync_status',
                    nonce: truebeep_sync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateUI(response.data);
                    }
                }.bind(this)
            });
        },

        /**
         * Update UI with new status
         */
        updateUI: function(data) {
            // Update progress bar
            if (data.statistics && data.statistics.percentage !== undefined) {
                $('.progress-fill').css('width', data.statistics.percentage + '%');
                
                var processed = data.progress.processed || 0;
                var total = data.progress.total || 0;
                var percentage = data.statistics.percentage || 0;
                
                // Add completed class if sync is complete
                if (data.status === 'completed' && percentage === 100) {
                    $('.progress-fill').addClass('completed');
                } else {
                    $('.progress-fill').removeClass('completed');
                }
                
                $('.progress-text').text(
                    processed + ' of ' + total + ' customers synced (' + percentage.toFixed(1) + '%)'
                );
            }

            // Update statistics
            if (data.progress) {
                $('.stat-value.success').text(this.formatNumber(data.progress.successful || 0));
                $('.stat-value.error').text(this.formatNumber(data.progress.failed || 0));
                $('.stat-value.warning').text(this.formatNumber(data.progress.skipped || 0));
            }

            // Reload page if status changed to completed
            if (data.status === 'completed' || data.status === 'cancelled') {
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        },

        /**
         * Format number with thousands separator
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        TruebeepSync.init();
    });

})(jQuery);