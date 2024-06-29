jQuery(document).ready(function($) {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded. Please make sure it is properly included in your HTML.');
        return;
    }
    console.log('Customer JS loaded');

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
                project_id: projectId,
                security: ajax_object.security
            },
            success: function(response) {
                if (response.success) {
                    showChart(response.data, $(this).closest('tr').find('td:nth-child(4)').text());
                } else {
                    alert('Failed to load chart data');
                }
            },
            error: function() {
                alert('An error occurred while fetching chart data');
            }
        });
    });

    function showChart(data, createdAt) {
        var modal = $('#chartModal');
        var canvas = document.getElementById('indexingChart');
        var ctx = canvas.getContext('2d');

        // Destroy any existing chart
        if (window.indexingChart && window.indexingChart.destroy) {
            window.indexingChart.destroy();
        }

        var startDate = new Date(createdAt);
        var endDate = new Date(startDate.getTime() + 14 * 24 * 60 * 60 * 1000); // 14 days after creation

        window.indexingChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates.map(date => new Date(date).toLocaleDateString()),
                datasets: [{
                    label: 'Indexed URLs',
                    data: data.indexed,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    fill: true
                }, {
                    label: 'Unindexed URLs',
                    data: data.unindexed,
                    borderColor: '#f44336',
                    backgroundColor: 'rgba(244, 67, 54, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        },
                        min: startDate,
                        max: endDate,
                        title: {
                            display: true,
                            text: 'Date',
                            color: '#ffffff'
                        },
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of URLs',
                            color: '#ffffff'
                        },
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#ffffff'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(97, 97, 97, 0.9)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#333333',
                        borderWidth: 1
                    }
                }
            }
        });

        modal.show();
    }

    $('.close').on('click', function() {
        $('#chartModal').hide();
    });

    $(window).on('click', function(event) {
        var modal = $('#chartModal');
        if (event.target == modal[0]) {
            modal.hide();
        }
    });
});
