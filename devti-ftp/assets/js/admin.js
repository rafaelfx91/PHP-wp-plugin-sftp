jQuery(document).ready(function($) {
    $('#devtiftp-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $('#devtiftp-spinner');
        var $results = $('#devtiftp-results');
        
        $button.prop('disabled', true);
        $spinner.show();
        $results.hide().removeClass('success error');
        
        $.ajax({
            url: devtiftp_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'devtiftp_test_connection',
                nonce: devtiftp_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    $results.html(response.data).addClass('success').show();
                } else {
                    $results.html(devtiftp_vars.error + ' ' + response.data).addClass('error').show();
                }
            },
            error: function(xhr, status, error) {
                $results.html(devtiftp_vars.error + ' ' + error).addClass('error').show();
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.hide();
            }
        });
    });
    
    $('#devtiftp-start-migration').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(devtiftp_vars.confirm_migration || 'Are you sure you want to start the migration? This will transfer and then delete files from your server.')) {
            return;
        }
        
        var $button = $(this);
        var $spinner = $('#devtiftp-spinner');
        var $results = $('#devtiftp-results');
        
        $button.prop('disabled', true);
        $spinner.show();
        $results.hide().removeClass('success error');
        
        $.ajax({
            url: devtiftp_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'devtiftp_start_migration',
                nonce: devtiftp_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    $results.html(response.data).addClass('success').show();
                } else {
                    $results.html(devtiftp_vars.error + ' ' + response.data).addClass('error').show();
                }
            },
            error: function(xhr, status, error) {
                $results.html(devtiftp_vars.error + ' ' + error).addClass('error').show();
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.hide();
            }
        });
    });
});