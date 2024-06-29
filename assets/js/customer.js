import { Chart, registerables } from 'chart.js';
import { DateTime } from 'luxon';
import { DateAdapter } from 'chartjs-adapter-luxon';

Chart.register(...registerables, DateAdapter);

jQuery(document).ready(function($) {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded. Please make sure it is properly included in your HTML.');
        return;
    }
    console.log('Customer JS loaded');

    Chart.defaults.locale = 'en';

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
        console.log('Show chart clicked');
        var projectId = $(this).data('project-id');
        console.log('Project ID:', projectId);
        $.ajax({
            url: ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'rui_get_project_stats',
                project_id: projectId,
                security: ajax_object.security
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    showChart(response.data);
                } else {
                    console.error('Failed to load chart data:', response);
                    alert('Failed to load chart data');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('An error occurred while fetching chart data');
            }
        });
    });

    function showChart(data) {
        console.log('Showing chart with data:', data);
        var modal = $('#chartModal');
        var canvas = document.getElementById('indexingChart');
        var ctx = canvas.getContext('2d');

        // Clear any existing chart
        if (window.indexingChart instanceof Chart) {
            window.indexingChart.destroy();
        }

        // Parse dates and ensure they are valid
        var parsedData = data.dates.map((date, index) => ({
            x: date,
            y: data.indexed[index]
        }));

        var parsedUnindexedData = data.dates.map((date, index) => ({
            x: date,
            y: data.unindexed[index]
        }));

        window.indexingChart = new Chart(ctx, {
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
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of URLs'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                }
            }
        });

        // Generate the table
        var tableHtml = '<table class="chart-data-table"><thead><tr><th>Date</th><th>Indexed URLs</th><th>Unindexed URLs</th></tr></thead><tbody>';
        for (var i = 0; i < data.dates.length; i++) {
            tableHtml += '<tr><td>' + data.dates[i] + '</td><td>' + data.indexed[i] + '</td><td>' + data.unindexed[i] + '</td></tr>';
        }
        tableHtml += '</tbody></table>';

        $('.modal-content').append(tableHtml);
        modal.show();
        console.log('Modal shown');
    }

    $('.close').on('click', function() {
        $('#chartModal').hide();
        console.log('Modal closed');
    });

    $(window).on('click', function(event) {
        var modal = $('#chartModal');
        if (event.target == modal[0]) {
            modal.hide();
            console.log('Modal closed by clicking outside');
        }
    });
});
