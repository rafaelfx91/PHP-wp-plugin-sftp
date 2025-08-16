jQuery(document).ready(function($) {
    $('#devtiftp-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text(devtiftp_vars.testing).prop('disabled', true);
        
        $('#devtiftp-results').hide().removeClass('success error');
        
        $.ajax({
            url: devtiftp_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'devtiftp_test_connection',
                nonce: devtiftp_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#devtiftp-results').html('<p>' + response.data + '</p>')
                        .addClass('success')
                        .show();
                } else {
                    $('#devtiftp-results').html('<p>' + response.data + '</p>')
                        .addClass('error')
                        .show();
                }
            },
            error: function(xhr) {
                $('#devtiftp-results').html('<p>' + xhr.responseJSON.data + '</p>')
                    .addClass('error')
                    .show();
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    $('#devtiftp-migrate-files').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text(devtiftp_vars.migrating).prop('disabled', true);
        
        $('#devtiftp-results').hide().removeClass('success error');
        
        $.ajax({
            url: devtiftp_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'devtiftp_migrate_files',
                nonce: devtiftp_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    var html = '<h3>' + response.data.total_files + ' arquivos encontrados, ' + 
                               response.data.success_count + ' migrados com sucesso.</h3>';
                    
                    $.each(response.data.results, function(index, result) {
                        html += '<div class="devtiftp-file-result ' + (result.success ? 'success' : 'error') + '">' +
                                '<strong>' + result.file + '</strong>: ' + result.message + '</div>';
                    });
                    
                    $('#devtiftp-results').html(html)
                        .addClass('success')
                        .show();
                } else {
                    $('#devtiftp-results').html('<p>' + response.data + '</p>')
                        .addClass('error')
                        .show();
                }
            },
            error: function(xhr) {
                $('#devtiftp-results').html('<p>' + xhr.responseJSON.data + '</p>')
                    .addClass('error')
                    .show();
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});