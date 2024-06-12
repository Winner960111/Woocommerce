jQuery(document).ready(function($) {
    $('#rui-log-search-submit').on('click', function() {
        var search = $('#rui-log-search').val();
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'rui_search_logs',
                s: search
            },
            success: function(response) {
                $('#rui-logs-table tbody').html(response.data);
            }
        });
    });
});
