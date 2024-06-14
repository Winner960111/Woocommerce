jQuery(document).ready(function($) {
    $('#rui-log-search-submit').on('click', function() {
        var searchQuery = $('#rui-log-search').val();
        $.ajax({
            url: ajaxurl,
            method: 'GET',
            data: {
                action: 'rui_search_logs',
                s: searchQuery
            },
            success: function(response) {
                if (response.success) {
                    $('#rui-logs-table tbody').html(response.data);
                } else {
                    alert('No logs found.');
                }
            },
            error: function() {
                alert('An error occurred while searching logs.');
            }
        });
    });

    // Trigger search on Enter key press
    $('#rui-log-search').on('keypress', function(e) {
        if (e.which == 13) {
            $('#rui-log-search-submit').click();
        }
    });
});
