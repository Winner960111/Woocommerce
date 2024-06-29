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
    // Remove existing event listener
    $(document).off('click', '.show-chart');

    // Add new event listener
    $(document).on('click', '.show-chart', function(e) {
        e.preventDefault();
        var projectId = $(this).data('project-id');
        var modal = $('#chartModal');
        
        // Reset the chart container
        modal.find('.modal-content').html('<span class="close">&times;</span><canvas id="indexingChart"></canvas>');
        
        // Show the modal before fetching data
        modal.css('display', 'block');
        
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
                    showChart(response);
                } else {
                    modal.find('.modal-content').html('<p>Failed to load chart data</p>');
                }
            },
            error: function() {
                modal.find('.modal-content').html('<p>An error occurred while fetching chart data</p>');
            }
        });
    });

    // Ensure the modal can be closed
    $(document).on('click', '.close, #chartModal', function(event) {
        if (event.target === this || $(event.target).hasClass('close')) {
            $('#chartModal').hide();
            if (window.indexingChart instanceof Chart) {
                window.indexingChart.destroy();
            }
        }
    });

    function showChart(data) {
        var modal = $('#chartModal');
        var canvas = document.getElementById('indexingChart');
        var ctx = canvas.getContext('2d');

        // Ensure the chart is destroyed if it exists
        if (window.indexingChart instanceof Chart) {
            window.indexingChart.destroy();
        }

        // Reset canvas dimensions and clear it
        canvas.width = modal.width() * 0.9; // 90% of modal width
        canvas.height = 400; // Fixed height or adjust as needed
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        console.log('Data received for chart:', JSON.stringify(data, null, 2));

        if (!data || !Array.isArray(data.indexed) || !Array.isArray(data.unindexed)) {
            console.error('Invalid data format:', data);
            modal.find('.modal-content').html('<p>Error: Unable to display chart due to invalid data.</p>');
            modal.css('display', 'block');
            return;
        }

        var labels = data.indexed.map(item => item.x);
        var indexedData = data.indexed.map(item => item.y);
        var unindexedData = data.unindexed.map(item => item.y);

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

    function closeModal() {
        $('#chartModal').hide();
    }

    // Ensure the close button is properly positioned and visible
    function positionCloseButton() {
        var closeButton = $('.close');
        var modalContent = $('.modal-content');
        closeButton.css({
            'position': 'absolute',
            'top': '10px',
            'right': '10px',
            'z-index': '1001',
            'cursor': 'pointer',
            'font-size': '28px',
            'color': '#ffffff'
        });
        modalContent.css('position', 'relative');
    }

    function showChart(data) {
        var modal = $('#chartModal');
        var canvas = document.getElementById('indexingChart');
        var ctx = canvas.getContext('2d');

        // ... (existing chart creation code) ...

        modal.css({'display': 'block', 'background-color': '#121212'});
        positionCloseButton(); // Position the close button

        // Ensure the close button is clickable
        $('.close').off('click').on('click', function(event) {
            event.stopPropagation();
            closeModal();
        });

        // Add click event listener to close modal when clicking outside
        $(document).on('click', function(event) {
            if (event.target === modal[0]) {
                closeModal();
            }
        });
    }

    // Add this line at the end of the jQuery(function($) { ... }) block
    $(document).on('click', '.close', function(event) {
        event.stopPropagation();
        closeModal();
    });
});
