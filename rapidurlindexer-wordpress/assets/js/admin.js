jQuery(document).ready(function($) {
    $('#rui-bulk-submit-form').on('submit', function(e) {
        e.preventDefault();

        var formData = {
            action: 'rui_bulk_submit',
            nonce: rui_ajax.nonce,
            project_name: $('#rui-project-name').val(),
            urls: $('#rui-urls').val(),
        };

        $.post(rui_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                $('#rui-bulk-submit-response').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
            } else {
                $('#rui-bulk-submit-response').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            $('#rui-bulk-submit-response').html('<div class="notice notice-error"><p>Error: ' + errorThrown + '</p></div>');
        });
    });
});
        $('#rui-bulk-submit-response').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
    }).fail(function(response) {
        $('#rui-bulk-submit-response').html('<div class="notice notice-error"><p>' + response.responseJSON + '</p></div>');
