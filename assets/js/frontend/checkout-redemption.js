(function($) {
    'use strict';

    var TruebeepCheckoutRedemption = {
        init: function() {
            this.bindEvents();
            this.initializeUI();
        },

        bindEvents: function() {
            var self = this;

            // Dynamic coupon events
            if (truebeep_checkout.redemption_method === 'dynamic_coupon') {
                $('#points-to-redeem').on('input', function() {
                    self.updateDiscountPreview($(this).val());
                });

                $('#apply-points').on('click', function(e) {
                    e.preventDefault();
                    self.applyPoints();
                });

                $('#remove-points').on('click', function(e) {
                    e.preventDefault();
                    self.removePoints();
                });

                // Validate points on blur
                $('#points-to-redeem').on('blur', function() {
                    self.validatePoints($(this).val());
                });
            } 
            // Predefined coupon events
            else {
                $('#coupon-select').on('change', function() {
                    self.updateCouponInfo($(this));
                });

                $('#apply-coupon').on('click', function(e) {
                    e.preventDefault();
                    self.applyCoupon();
                });

                $('#remove-coupon').on('click', function(e) {
                    e.preventDefault();
                    self.removeCoupon();
                });
            }

            // Update checkout on cart update
            $(document.body).on('updated_checkout', function() {
                self.checkAppliedDiscount();
            });
        },

        initializeUI: function() {
            // Check if there's already a discount applied
            this.checkAppliedDiscount();
        },

        updateDiscountPreview: function(points) {
            points = parseInt(points) || 0;
            // Rate represents points per dollar (e.g., 100 points = $1)
            // So discount = points / rate
            var discount = truebeep_checkout.redemption_rate > 0 ? 
                (points / truebeep_checkout.redemption_rate).toFixed(2) : 
                '0.00';
            $('#discount-preview').text(discount);
        },

        updateCouponInfo: function($select) {
            var $selected = $select.find('option:selected');
            var points = $selected.data('points');
            var value = $selected.data('value');

            if (points && value) {
                var message = 'This coupon will use ' + points + ' points for $' + value + ' off';
                $('#coupon-message').html('<p class="info">' + message + '</p>');
            } else {
                $('#coupon-message').empty();
            }
        },

        validatePoints: function(points) {
            points = parseInt(points);
            
            if (!points || points <= 0) {
                return false;
            }

            if (points > truebeep_checkout.user_points) {
                this.showMessage('error', truebeep_checkout.strings.insufficient_points, 'points');
                return false;
            }

            var self = this;
            $.ajax({
                url: truebeep_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'validate_points',
                    points: points,
                    nonce: truebeep_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('success', '', 'points');
                    } else {
                        self.showMessage('error', response.data.message, 'points');
                        if (response.data.max_points) {
                            $('#points-to-redeem').val(response.data.max_points);
                            self.updateDiscountPreview(response.data.max_points);
                        }
                    }
                }
            });

            return true;
        },

        applyPoints: function() {
            var points = parseInt($('#points-to-redeem').val());
            
            if (!this.validatePoints(points)) {
                this.showMessage('error', truebeep_checkout.strings.invalid_points, 'points');
                return;
            }

            var self = this;
            var $button = $('#apply-points');
            
            $button.prop('disabled', true).text(truebeep_checkout.strings.applying);

            $.ajax({
                url: truebeep_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'apply_points_discount',
                    points: points,
                    nonce: truebeep_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('success', response.data.message, 'points');
                        
                        // Update UI
                        $('#points-to-redeem').prop('readonly', true);
                        $button.hide();
                        $('#remove-points').show();
                        
                        // Trigger checkout update
                        $(document.body).trigger('update_checkout');
                    } else {
                        self.showMessage('error', response.data.message, 'points');
                    }
                },
                error: function() {
                    self.showMessage('error', truebeep_checkout.strings.error, 'points');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Apply Points');
                }
            });
        },

        removePoints: function() {
            var self = this;
            var $button = $('#remove-points');
            
            $button.prop('disabled', true).text(truebeep_checkout.strings.removing);

            $.ajax({
                url: truebeep_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'remove_points_discount',
                    nonce: truebeep_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('success', response.data.message, 'points');
                        
                        // Reset UI
                        $('#points-to-redeem').prop('readonly', false).val('');
                        $('#discount-preview').text('0.00');
                        $button.hide();
                        $('#apply-points').show();
                        
                        // Trigger checkout update
                        $(document.body).trigger('update_checkout');
                    } else {
                        self.showMessage('error', response.data.message, 'points');
                    }
                },
                error: function() {
                    self.showMessage('error', truebeep_checkout.strings.error, 'points');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Remove Points');
                }
            });
        },

        applyCoupon: function() {
            var couponIndex = $('#coupon-select').val();
            
            if (!couponIndex) {
                this.showMessage('error', truebeep_checkout.strings.select_coupon, 'coupon');
                return;
            }

            var self = this;
            var $button = $('#apply-coupon');
            
            $button.prop('disabled', true).text(truebeep_checkout.strings.applying);

            $.ajax({
                url: truebeep_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'apply_points_discount',
                    coupon_index: couponIndex,
                    nonce: truebeep_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('success', response.data.message, 'coupon');
                        
                        // Update UI
                        $('#coupon-select').prop('disabled', true);
                        $button.hide();
                        $('#remove-coupon').show();
                        
                        // Trigger checkout update
                        $(document.body).trigger('update_checkout');
                    } else {
                        self.showMessage('error', response.data.message, 'coupon');
                    }
                },
                error: function() {
                    self.showMessage('error', truebeep_checkout.strings.error, 'coupon');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Apply Coupon');
                }
            });
        },

        removeCoupon: function() {
            var self = this;
            var $button = $('#remove-coupon');
            
            $button.prop('disabled', true).text(truebeep_checkout.strings.removing);

            $.ajax({
                url: truebeep_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'remove_points_discount',
                    nonce: truebeep_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('success', response.data.message, 'coupon');
                        
                        // Reset UI
                        $('#coupon-select').prop('disabled', false).val('');
                        $('#coupon-message').empty();
                        $button.hide();
                        $('#apply-coupon').show();
                        
                        // Trigger checkout update
                        $(document.body).trigger('update_checkout');
                    } else {
                        self.showMessage('error', response.data.message, 'coupon');
                    }
                },
                error: function() {
                    self.showMessage('error', truebeep_checkout.strings.error, 'coupon');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Remove Coupon');
                }
            });
        },

        checkAppliedDiscount: function() {
            // Check if discount is already applied by looking for the fee in cart
            var $fees = $('.fee');
            var hasDiscount = false;
            
            $fees.each(function() {
                if ($(this).text().indexOf('Loyalty Points Redemption') !== -1) {
                    hasDiscount = true;
                    return false;
                }
            });

            if (hasDiscount) {
                if (truebeep_checkout.redemption_method === 'dynamic_coupon') {
                    $('#points-to-redeem').prop('readonly', true);
                    $('#apply-points').hide();
                    $('#remove-points').show();
                } else {
                    $('#coupon-select').prop('disabled', true);
                    $('#apply-coupon').hide();
                    $('#remove-coupon').show();
                }
            }
        },

        showMessage: function(type, message, target) {
            var $messageContainer = target === 'points' ? $('#points-message') : $('#coupon-message');
            
            if (!message) {
                $messageContainer.empty();
                return;
            }

            var className = type === 'error' ? 'woocommerce-error' : 'woocommerce-message';
            $messageContainer.html('<div class="' + className + '">' + message + '</div>');
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $messageContainer.fadeOut(function() {
                        $(this).empty().show();
                    });
                }, 5000);
            }
        }
    };

    $(document).ready(function() {
        TruebeepCheckoutRedemption.init();
    });

})(jQuery);