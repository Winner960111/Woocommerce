jQuery(function($) {
    console.log('Customer JS loaded');
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded. Please make sure it is properly included in your HTML.');
        return;
    }

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
    $(document).on('click', '.show-chart', function(e) {
        e.preventDefault();
        var projectId = $(this).data('project-id');
        var modal = $('#chartModal');
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
                    var createdAt = $(this).closest('tr').find('td:nth-child(4)').text();
                    showChart(response.data, new Date(createdAt));
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
        if (window.indexingChart instanceof Chart) {
            window.indexingChart.destroy();
        }

        // Clear the canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        var labels = [];
        var indexedData = [];
        var unindexedData = [];

        if (data && Array.isArray(data.indexed) && Array.isArray(data.unindexed)) {
            data.indexed.forEach((item, index) => {
                labels.push(item.x);
                indexedData.push(item.y);
                unindexedData.push(data.unindexed[index].y);
            });
        } else {
            console.error('Invalid data format:', data);
            modal.find('.modal-content').html('<p>Error: Unable to display chart due to invalid data.</p>');
            modal.css('display', 'block');
            return;
        }

        if (indexedData.length === 0 && unindexedData.length === 0) {
            modal.find('.modal-content').html('<p>No data available to display in the chart.</p>');
            modal.css('display', 'block');
            return;
        }

        window.indexingChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Indexed URLs',
                    data: indexedData,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    fill: true
                }, {
                    label: 'Unindexed URLs',
                    data: unindexedData,
                    borderColor: '#f44336',
                    backgroundColor: 'rgba(244, 67, 54, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'category',
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

        modal.css('display', 'block');
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
