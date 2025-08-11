(function($) {
    'use strict';

    console.log('loyalty-panel.js loaded', truebeep_panel);

    var TruebeepLoyaltyPanel = {
        
        init: function() {
            this.panel = $('#truebeep-loyalty-panel');
            if (!this.panel.length) {
                return;
            }
            
            this.customerId = this.panel.data('customer-id');
            this.isOpen = false;
            this.dataLoaded = false;
            
            this.bindEvents();
            this.loadLoyaltyData();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Toggle panel
            this.panel.find('.panel-toggle').on('click', function(e) {
                e.preventDefault();
                self.togglePanel();
            });
            
            // Close panel
            this.panel.find('.panel-close').on('click', function(e) {
                e.preventDefault();
                self.closePanel();
            });
            
            // Close on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.closePanel();
                }
            });
            
            // Close on click outside
            $(document).on('click', function(e) {
                if (self.isOpen && !$(e.target).closest('#truebeep-loyalty-panel').length) {
                    self.closePanel();
                }
            });
            
            // Update wallet URLs when panel opens
            this.panel.on('panelOpened', function() {
                self.updateWalletUrls();
            });
        },
        
        togglePanel: function() {
            if (this.isOpen) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        },
        
        openPanel: function() {
            console.log('openPanel');
            this.panel.addClass('active');
            this.isOpen = true;
            
            // Load fresh data if not loaded or stale (older than 5 minutes)
            if (!this.dataLoaded || this.isDataStale()) {
                this.loadLoyaltyData();
            }
            
            this.panel.trigger('panelOpened');
        },
        
        closePanel: function() {
            this.panel.removeClass('active');
            this.isOpen = false;
        },
        
        loadLoyaltyData: function() {
            var self = this;
            
            // Show loading state
            this.panel.addClass('loading');

            console.log('loadLoyaltyData');
            console.log(truebeep_panel);
            console.log(truebeep_panel.ajax_url);
           
            
            $.ajax({
                url: truebeep_panel.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_loyalty_data',
                    nonce: truebeep_panel.nonce
                },
                success: function(response) {
                    console.log('success');
                    console.log(response);
                    if (response.success) {
                        self.updatePanelData(response.data);
                        self.dataLoaded = true;
                        self.lastLoadTime = Date.now();
                    } else {
                        self.showError(response.data.message || truebeep_panel.strings.error);
                    }
                },
                error: function() {
                    self.showError(truebeep_panel.strings.error);
                },
                complete: function() {
                    self.panel.removeClass('loading');
                }
            });
        },
        
        updatePanelData: function(data) {
            // Update points
            this.panel.find('.points-number').text(this.formatNumber(data.points));
            this.panel.find('.points-badge').text(this.formatCompactNumber(data.points));
            
            // Update tier
            var tierName = data.tier || truebeep_panel.strings.no_tier;
            this.panel.find('.tier-value').text(tierName);
            
            // Store data for wallet URLs
            this.loyaltyData = data;
            
            // Update wallet URLs
            this.updateWalletUrls();
        },
        
        updateWalletUrls: function() {
            if (!this.customerId) {
                return;
            }
            
            // Build Apple Wallet URL
            if (truebeep_panel.apple_template_id) {
                var appleUrl = truebeep_panel.apple_wallet_url + 
                    '?templateId=' + encodeURIComponent(truebeep_panel.apple_template_id) +
                    '&customerId=' + encodeURIComponent(this.customerId);
                this.panel.find('.apple-wallet').attr('href', appleUrl);
            } else {
                this.panel.find('.apple-wallet').hide();
            }
            
            // Build Google Wallet URL
            if (truebeep_panel.google_template_id) {
                var googleUrl = truebeep_panel.google_wallet_url + 
                    '?templateId=' + encodeURIComponent(truebeep_panel.google_template_id) +
                    '&customerId=' + encodeURIComponent(this.customerId);
                this.panel.find('.google-wallet').attr('href', googleUrl);
            } else {
                this.panel.find('.google-wallet').hide();
            }
        },
        
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        formatCompactNumber: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        },
        
        isDataStale: function() {
            if (!this.lastLoadTime) {
                return true;
            }
            // Consider data stale after 5 minutes
            return (Date.now() - this.lastLoadTime) > 300000;
        },
        
        showError: function(message) {
            console.error('Truebeep Loyalty Panel Error:', message);
            // Optionally show error in UI
            this.panel.find('.points-number').text('--');
            this.panel.find('.tier-value').text('--');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TruebeepLoyaltyPanel.init();
    });
    
    // Refresh data on WooCommerce checkout success
    $(document.body).on('checkout_complete', function() {
        setTimeout(function() {
            TruebeepLoyaltyPanel.loadLoyaltyData();
        }, 2000);
    });

})(jQuery);