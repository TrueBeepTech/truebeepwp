jQuery(document).ready(function($) {
    var tiersData = [];
    
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
    
    initializeTiersData();
    
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
                tiers: currentTiers
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
    });
    
    // Handle ESC key to close modal
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            $('#tier-edit-modal').fadeOut(200);
        }
    });
});