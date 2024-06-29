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

        $.post(ajax_object.ajaxurl, data, function(response) {
            if (response.success) {
                $('#rui-submission-response').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
            } else {
                $('#rui-submission-response').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
            }
        }).fail(function() {
            $('#rui-submission-response').html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
        });
    });

    // Chart functionality
    $('.show-chart').on('click', function(e) {
        e.preventDefault();
        var projectId = $(this).data('project-id');
        $.ajax({
            url: ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'rui_get_project_stats',
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    showChart(response.data);
                } else {
                    alert('Failed to load chart data');
                }
            }
        });
    });

    function showChart(data) {
        var ctx = document.getElementById('indexingChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: 'Indexed URLs',
                    data: data.indexed,
                    borderColor: 'green',
                    fill: false
                }, {
                    label: 'Unindexed URLs',
                    data: data.unindexed,
                    borderColor: 'red',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        $('#chartModal').show();
    }

    $('.close').on('click', function() {
        $('#chartModal').hide();
    });
});
