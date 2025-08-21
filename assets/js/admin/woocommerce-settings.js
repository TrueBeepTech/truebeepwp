jQuery(document).ready(function($) {
    var tiersData = [];
    var couponsData = [];
    
    // Connection button functionality
    $('#truebeep-connection-btn').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $message = $('#truebeep-connection-message');
        var $status = $('#truebeep-status');
        var currentStatus = $button.data('status');
        var action = (currentStatus === 'connected') ? 'disconnect' : 'connect';
        var originalText = $button.text();
        
        // Show loading state
        $button.text('Processing...').prop('disabled', true);
        $message.html('<span style="color: blue;">Processing...</span>');
        
        // Make AJAX request
        $.ajax({
            url: truebeep_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'truebeep_update_connection',
                nonce: truebeep_admin.connection_nonce,
                connection_action: action
            },
            success: function(response) {
                if (response.success) {
                    var newStatus = response.data.status;
                    var newButtonText = (newStatus === 'connected') ? 'Disconnect' : 'Connect';
                    var statusText = (newStatus === 'connected') ? 'Connected' : 'Disconnected';
                    var statusColor = (newStatus === 'connected') ? 'green' : 'red';
                    
                    // Update button
                    $button.text(newButtonText).data('status', newStatus);
                    
                    // Update status display
                    $status.text(statusText).css('color', statusColor);
                    
                    // Show success message
                    $message.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    
                    // Clear message after 3 seconds
                    setTimeout(function() {
                        $message.html('');
                    }, 3000);
                } else {
                    $message.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $message.html('<span style="color: red;">✗ Connection failed. Please try again.</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Initialize tiers data from table
    function initializeTiersData() {
        tiersData = [];
        $('#truebeep-tiers-list .tier-row').each(function() {
            var $row = $(this);
            var tierData = $row.find('.edit-tier').data('tier');
            if (tierData) {
                tiersData.push(tierData);
            }
        });
    }

    // Initialize coupons data from table
    function initializeCouponsData() {
        couponsData = [];
        if ($('#truebeep-coupons-list').length > 0) {
            $('#truebeep-coupons-list .coupon-row').each(function() {
                var $row = $(this);
                var couponData = $row.find('.edit-coupon').data('coupon');
                if (couponData) {
                    couponsData.push(couponData);
                }
            });
        }
    }
    
    initializeTiersData();
    initializeCouponsData();

    // Handle Ways to Redeem radio change
    $('input[name="truebeep_redeem_method"]').on('change', function() {
        var selectedMethod = $(this).val();
        if (selectedMethod === 'coupon') {
            $('#coupon-settings-section').show();
        } else {
            $('#coupon-settings-section').hide();
        }
    });

    // Initialize coupon section visibility
    var initialMethod = $('input[name="truebeep_redeem_method"]:checked').val();
    if (initialMethod === 'coupon') {
        $('#coupon-settings-section').show();
    }
    
    // Add new tier
    $('#add-tier-button').on('click', function() {
        var newTier = {
            name: 'New Tier',
            order_to_points: 1,
            points_to_amount: 1,
            threshold: 0
        };
        
        var newIndex = tiersData.length;
        tiersData.push(newTier);
        
        var newRow = '<tr class="tier-row" data-index="' + newIndex + '">' +
            '<td>' + newTier.name + '</td>' +
            '<td>' + newTier.order_to_points + '</td>' +
            '<td>' + newTier.points_to_amount + '</td>' +
            '<td>' + newTier.threshold + '</td>' +
            '<td>' +
                '<button type="button" class="button edit-tier" data-tier=\'' + JSON.stringify(newTier) + '\' data-index="' + newIndex + '">Edit</button> ' +
                '<button type="button" class="button remove-tier" data-index="' + newIndex + '">Remove</button>' +
            '</td>' +
        '</tr>';
        
        $('#truebeep-tiers-list').append(newRow);
        
        // Trigger edit for the new tier
        $('#truebeep-tiers-list .tier-row:last .edit-tier').click();
    });
    
    // Edit tier - open modal
    $(document).on('click', '.edit-tier', function() {
        var index = $(this).data('index');
        var tier = tiersData[index] || $(this).data('tier');
        
        $('#edit-tier-index').val(index);
        $('#edit-tier-name').val(tier.name);
        $('#edit-tier-order-points').val(tier.order_to_points || 1);
        $('#edit-tier-points-amount').val(tier.points_to_amount || 1);
        $('#edit-tier-threshold').val(tier.threshold || 0);
        
        $('#tier-edit-modal').fadeIn(200);
    });
    
    // Save tier from modal
    $('#save-tier-button').on('click', function() {
        var index = $('#edit-tier-index').val();
        
        var updatedTier = {
            name: $('#edit-tier-name').val(),
            order_to_points: parseFloat($('#edit-tier-order-points').val()),
            points_to_amount: parseFloat($('#edit-tier-points-amount').val()),
            threshold: parseInt($('#edit-tier-threshold').val())
        };
        
        tiersData[index] = updatedTier;
        
        // Update the table row
        var $row = $('#truebeep-tiers-list .tier-row[data-index="' + index + '"]');
        $row.find('td:eq(0)').text(updatedTier.name);
        $row.find('td:eq(1)').text(updatedTier.order_to_points);
        $row.find('td:eq(2)').text(updatedTier.points_to_amount);
        $row.find('td:eq(3)').text(updatedTier.threshold);
        $row.find('.edit-tier').attr('data-tier', JSON.stringify(updatedTier));
        
        $('#tier-edit-modal').fadeOut(200);
    });
    
    // Cancel tier edit
    $('#cancel-tier-button, .tier-modal-close').on('click', function() {
        $('#tier-edit-modal').fadeOut(200);
    });
    
    // Remove tier
    $(document).on('click', '.remove-tier', function() {
        var index = $(this).data('index');
        
        if (confirm('Are you sure you want to remove this tier?')) {
            $(this).closest('tr').remove();
            
            // Remove from data array
            tiersData.splice(index, 1);
            
            // Re-index remaining rows
            $('#truebeep-tiers-list .tier-row').each(function(newIndex) {
                $(this).attr('data-index', newIndex);
                $(this).find('.edit-tier, .remove-tier').attr('data-index', newIndex);
            });
            
            // Re-initialize tiers data to maintain consistency
            initializeTiersData();
        }
    });

    // COUPON MANAGEMENT
    // Add new coupon
    $('#add-coupon-button').on('click', function() {
        var newCoupon = {
            name: 'New Coupon',
            value: 1
        };
        
        var newIndex = couponsData.length;
        couponsData.push(newCoupon);
        
        var newRow = '<tr class="coupon-row" data-index="' + newIndex + '">' +
            '<td>' + newCoupon.name + '</td>' +
            '<td>$' + newCoupon.value + '</td>' +
            '<td>' +
                '<button type="button" class="button edit-coupon" data-coupon=\'' + JSON.stringify(newCoupon) + '\' data-index="' + newIndex + '">Edit</button> ' +
                '<button type="button" class="button remove-coupon" data-index="' + newIndex + '">Remove</button>' +
            '</td>' +
        '</tr>';
        
        $('#truebeep-coupons-list').append(newRow);
        
        // Trigger edit for the new coupon
        $('#truebeep-coupons-list .coupon-row:last .edit-coupon').click();
    });
    
    // Edit coupon - open modal
    $(document).on('click', '.edit-coupon', function() {
        var index = $(this).data('index');
        var coupon = couponsData[index] || $(this).data('coupon');
        
        $('#edit-coupon-index').val(index);
        $('#edit-coupon-name').val(coupon.name);
        $('#edit-coupon-value').val(coupon.value);
        
        $('#coupon-edit-modal').fadeIn(200);
    });
    
    // Save coupon from modal
    $('#save-coupon-button').on('click', function() {
        var index = $('#edit-coupon-index').val();
        
        var updatedCoupon = {
            name: $('#edit-coupon-name').val(),
            value: parseFloat($('#edit-coupon-value').val())
        };
        
        couponsData[index] = updatedCoupon;
        
        // Update the table row
        var $row = $('#truebeep-coupons-list .coupon-row[data-index="' + index + '"]');
        $row.find('td:eq(0)').text(updatedCoupon.name);
        $row.find('td:eq(1)').text('$' + updatedCoupon.value);
        $row.find('.edit-coupon').attr('data-coupon', JSON.stringify(updatedCoupon));
        
        $('#coupon-edit-modal').fadeOut(200);
    });
    
    // Cancel coupon edit
    $('#cancel-coupon-button, .coupon-modal-close').on('click', function() {
        $('#coupon-edit-modal').fadeOut(200);
    });
    
    // Remove coupon
    $(document).on('click', '.remove-coupon', function() {
        var index = $(this).data('index');
        
        if (confirm('Are you sure you want to remove this coupon?')) {
            $(this).closest('tr').remove();
            
            // Remove from data array
            couponsData.splice(index, 1);
            
            // Re-index remaining rows
            $('#truebeep-coupons-list .coupon-row').each(function(newIndex) {
                $(this).attr('data-index', newIndex);
                $(this).find('.edit-coupon, .remove-coupon').attr('data-index', newIndex);
            });
            
            // Re-initialize coupons data to maintain consistency
            initializeCouponsData();
        }
    });

    // Save coupons only
    $('#save-coupons-button').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        button.text('Saving...');
        button.prop('disabled', true);

        // Collect current coupons data
        var currentCoupons = [];
        if ($('#truebeep-coupons-list').length > 0) {
            $('#truebeep-coupons-list .coupon-row').each(function() {
                var $row = $(this);
                var index = $row.data('index');
                if (couponsData[index]) {
                    currentCoupons.push(couponsData[index]);
                }
            });
        }

        // Debug logging
        console.log('Saving coupons only:', currentCoupons);

        $.ajax({
            url: truebeep_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'truebeep_save_coupons',
                nonce: truebeep_admin.coupons_nonce,
                coupons: currentCoupons
            },
            success: function(response) {
                if (response.success) {
                    var notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    $('.wrap h1').after(notice);
                    
                    // Make notice dismissible
                    notice.on('click', '.notice-dismiss', function() {
                        notice.fadeOut();
                    });
                    
                    setTimeout(function() {
                        notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            },
            error: function() {
                var notice = $('<div class="notice notice-error is-dismissible"><p>Error saving coupons.</p></div>');
                $('.wrap h1').after(notice);
            },
            complete: function() {
                button.text(truebeep_admin.strings.save_coupons);
                button.prop('disabled', false);
            }
        });
    });
    
    // Save all changes
    $('#save-all-button').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        button.text('Saving...');
        button.prop('disabled', true);
        
        // Collect loyalty fields values
        var redeemMethod = $('input[name="truebeep_redeem_method"]:checked').val();
        var earningValue = $('#truebeep_earning_value').val();
        var redeemingValue = $('#truebeep_redeeming_value').val();
        var earnOnRedeemed = $('#truebeep_earn_on_redeemed').is(':checked');
        
        // Collect current tiers data
        var currentTiers = [];
        $('#truebeep-tiers-list .tier-row').each(function() {
            var $row = $(this);
            var index = $row.data('index');
            if (tiersData[index]) {
                currentTiers.push(tiersData[index]);
            }
        });

        // Collect current coupons data
        var currentCoupons = [];
        if ($('#truebeep-coupons-list').length > 0) {
            $('#truebeep-coupons-list .coupon-row').each(function() {
                var $row = $(this);
                var index = $row.data('index');
                if (couponsData[index]) {
                    currentCoupons.push(couponsData[index]);
                }
            });
        }
        
        // Debug logging
        console.log('Saving loyalty settings:', {
            redeem_method: redeemMethod,
            earning_value: earningValue,
            redeeming_value: redeemingValue,
            earn_on_redeemed: earnOnRedeemed,
            tiers: currentTiers,
            coupons: currentCoupons
        });

        $.ajax({
            url: truebeep_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'truebeep_save_loyalty',
                nonce: truebeep_admin.nonce,
                redeem_method: redeemMethod,
                earning_value: earningValue,
                redeeming_value: redeemingValue,
                earn_on_redeemed: earnOnRedeemed,
                tiers: currentTiers,
                coupons: currentCoupons
            },
            success: function(response) {
                if (response.success) {
                    var notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    $('.wrap h1').after(notice);
                    
                    // Make notice dismissible
                    notice.on('click', '.notice-dismiss', function() {
                        notice.fadeOut();
                    });
                    
                    setTimeout(function() {
                        notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            },
            error: function() {
                var notice = $('<div class="notice notice-error is-dismissible"><p>Error saving settings.</p></div>');
                $('.wrap h1').after(notice);
            },
            complete: function() {
                button.text(originalText);
                button.prop('disabled', false);
            }
        });
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).is('#tier-edit-modal')) {
            $('#tier-edit-modal').fadeOut(200);
        }
        if ($(event.target).is('#coupon-edit-modal')) {
            $('#coupon-edit-modal').fadeOut(200);
        }
    });
    
    // Handle ESC key to close modal
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            $('#tier-edit-modal').fadeOut(200);
            $('#coupon-edit-modal').fadeOut(200);
        }
    });
});