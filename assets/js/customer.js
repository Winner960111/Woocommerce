jQuery(document).ready(function($) {
    $('#rui-project-submission-form').on('submit', function(e) {
        e.preventDefault();

        var data = {
            action: 'rui_submit_project',
            security: $('input[name="security"]').val(),
            project_name: $('#project_name').val(),
            urls: $('#urls').val(),
            notify: $('#notify').is(':checked') ? 1 : 0
        };

        $.post(ajaxurl, data, function(response) {
            $('#rui-submission-response').html(response.data);
        });
    });
});
