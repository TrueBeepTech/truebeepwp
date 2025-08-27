/**
 * Truebeep Import Admin JavaScript
 */
(function($) {
    'use strict';

    let statusChecker;
    let isStatusChecking = false;

    const ImportManager = {
        init: function() {
            this.bindEvents();
            this.startStatusChecker();
        },

        bindEvents: function() {
            $('#start-import-btn').on('click', this.startImport.bind(this));
            $('#cancel-import-btn').on('click', this.cancelImport.bind(this));
            $('#reset-import-btn').on('click', this.resetImport.bind(this));
        },

        startImport: function() {
            if (!confirm('Are you sure you want to start the import? This will process all your existing customers.')) {
                return;
            }

            this.showLoading();
            this.setButtonState('start-import-btn', 'loading');

            $.ajax({
                url: truebeepImport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'truebeep_start_import',
                    nonce: truebeepImport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotice('success', 'Import started successfully! Processing in background...');
                        this.updateUI();
                        this.startStatusChecker();
                    } else {
                        this.showNotice('error', response.data || 'Failed to start import');
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showNotice('error', 'Network error: ' + error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                    this.setButtonState('start-import-btn', 'normal');
                }.bind(this)
            });
        },

        cancelImport: function() {
            if (!confirm(truebeepImport.strings.confirmCancel)) {
                return;
            }

            this.showLoading();
            this.setButtonState('cancel-import-btn', 'loading');

            $.ajax({
                url: truebeepImport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'truebeep_cancel_import',
                    nonce: truebeepImport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotice('success', 'Import cancelled successfully');
                        this.stopStatusChecker();
                        this.updateUI();
                    } else {
                        this.showNotice('error', response.data || 'Failed to cancel import');
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showNotice('error', 'Network error: ' + error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                    this.setButtonState('cancel-import-btn', 'normal');
                }.bind(this)
            });
        },

        resetImport: function() {
            if (!confirm(truebeepImport.strings.confirmReset)) {
                return;
            }

            this.showLoading();
            this.setButtonState('reset-import-btn', 'loading');

            $.ajax({
                url: truebeepImport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'truebeep_reset_import',
                    nonce: truebeepImport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotice('success', 'Import data reset successfully');
                        location.reload(); // Reload page to show reset state
                    } else {
                        this.showNotice('error', response.data || 'Failed to reset import');
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showNotice('error', 'Network error: ' + error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                    this.setButtonState('reset-import-btn', 'normal');
                }.bind(this)
            });
        },

        checkStatus: function() {
            console.log('checkStatus');
            if (isStatusChecking) {
                return;
            }

            isStatusChecking = true;

            $.ajax({
                url: truebeepImport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'truebeep_get_import_status',
                    nonce: truebeepImport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateStatus(response.data);
                    }
                }.bind(this),
                complete: function() {
                    isStatusChecking = false;
                }.bind(this)
            });
        },

        updateStatus: function(statusData) {
            // Update progress bar
            const percentage = statusData.statistics.percentage || 0;
            $('.progress-fill').css('width', percentage + '%');
            $('#progress-text').text(percentage + '%');

            // Update statistics
            if (statusData.progress) {
                $('.stat.successful .number').text(this.formatNumber(statusData.progress.successful || 0));
                $('.stat.failed .number').text(this.formatNumber(statusData.progress.failed || 0));
                $('.stat.skipped .number').text(this.formatNumber(statusData.progress.skipped || 0));
            }

            // Update status display
            const statusElement = $('.value.status-' + statusData.status);
            if (statusElement.length) {
                statusElement.removeClass('status-idle status-preparing status-running status-completed status-cancelled status-failed')
                           .addClass('status-' + statusData.status);
            }

            // Update remaining count
            $('.status-item:contains("Remaining:") .value').text(this.formatNumber(statusData.statistics.remaining || 0));
            $('.status-item:contains("Processed:") .value').text(this.formatNumber(statusData.progress.processed || 0));

            // Update controls based on status
            this.updateControls(statusData);

            // Handle completion
            if (statusData.status === 'completed') {
                this.stopStatusChecker();
                this.showNotice('success', 'Import completed successfully!');
            }

            // Handle failure
            if (statusData.status === 'failed') {
                this.stopStatusChecker();
                this.showNotice('error', 'Import failed. Please check the logs.');
            }

            // Handle cancellation
            if (statusData.status === 'cancelled') {
                this.stopStatusChecker();
                this.showNotice('warning', 'Import was cancelled.');
            }
        },

        updateControls: function(statusData) {
            const startBtn = $('#start-import-btn');
            const cancelBtn = $('#cancel-import-btn');
            const resetBtn = $('#reset-import-btn');

            // Hide all buttons first
            startBtn.hide();
            cancelBtn.hide();
            resetBtn.hide();

            if (statusData.status === 'idle' || statusData.status === 'completed' || statusData.status === 'cancelled') {
                startBtn.show();
                resetBtn.show();
            } else if (statusData.is_running || statusData.status === 'preparing' || statusData.status === 'running') {
                cancelBtn.show();
            }
        },

        startStatusChecker: function() {
            console.log('startStatusChecker');
            this.stopStatusChecker(); // Clear any existing checker
            statusChecker = setInterval(this.checkStatus.bind(this), 3000); // Check every 3 seconds
        },

        stopStatusChecker: function() {
            if (statusChecker) {
                clearInterval(statusChecker);
                statusChecker = null;
            }
        },

        updateUI: function() {
            console.log('updateUI');
            // Trigger a status check to refresh the UI
            setTimeout(this.checkStatus.bind(this), 1000);
        },

        setButtonState: function(buttonId, state) {
            const button = $('#' + buttonId);
            
            if (state === 'loading') {
                button.addClass('loading').prop('disabled', true);
            } else {
                button.removeClass('loading').prop('disabled', false);
            }
        },

        showLoading: function() {
            $('#import-loading-overlay').show();
        },

        hideLoading: function() {
            $('#import-loading-overlay').hide();
        },

        showNotice: function(type, message) {
            // Remove existing notices
            $('.notice.notice-import').remove();

            // Create new notice
            const noticeClass = 'notice notice-import notice-' + type + ' is-dismissible';
            const notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
            
            // Add dismiss button functionality
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut();
            });

            // Insert after page title
            $('.wrap h1').after(notice);

            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    notice.fadeOut();
                }, 5000);
            }
        },

        formatNumber: function(number) {
            return new Intl.NumberFormat().format(number);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ImportManager.init();
    });

    // Clean up on page unload
    $(window).on('beforeunload', function() {
        ImportManager.stopStatusChecker();
    });

})(jQuery);