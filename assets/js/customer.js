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
        var createdAt = $(this).closest('tr').find('td:nth-child(4)').text();
        
        // Reset the chart container
        modal.find('.modal-content').html('<span class="close">&times;</span><canvas id="indexingChart"></canvas>');
        
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
                    showChart(response.data);
                } else {
                    alert('Failed to load chart data');
                }
            },
            error: function() {
                alert('An error occurred while fetching chart data');
            }
        });
    });

    function showChart(data) {
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
                var date = new Date(item.x);
                var formattedDate = date.getFullYear() + '-' + 
                                    ('0' + (date.getMonth() + 1)).slice(-2) + '-' + 
                                    ('0' + date.getDate()).slice(-2);
                labels.push(formattedDate);
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

        // Create a new Chart instance
        window.indexingChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
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
                            color: '#ffffff' // White text for legend labels
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#1e1e1e', // Dark background for tooltip
                        titleColor: '#ffffff', // White text for tooltip title
                        bodyColor: '#ffffff', // White text for tooltip body
                        borderColor: '#424242', // Dark grey border for tooltip
                        borderWidth: 1
                    }
                }
            }
        });

        modal.css({'display': 'block', 'background-color': '#121212'});
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
