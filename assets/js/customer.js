jQuery(function($) {
    if (typeof Chart === 'undefined') {
        return;
    }

    $('#rui-project-submission-form').on('submit', function(e) {
        e.preventDefault();

        var $submitButton = $(this).find('button[type="submit"]');
        $submitButton.prop('disabled', true).text('Submitting...');

        var data = {
            action: 'rui_submit_project',
            security: $('input[name="security"]').val(),
            project_name: $('#project_name').val(),
            urls: $('#urls').val().trim(),
            notify: $('#notify').is(':checked') ? 1 : 0
        };

        if (data.urls === '') {
            showError('Please enter at least one URL.');
            $submitButton.prop('disabled', false).text('Submit');
            return;
        }

        $.ajax({
            url: ajax_object.ajaxurl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let message = response.data.message;
                    if (response.data.project_name) {
                        message += '\n\nProject Name: ' + response.data.project_name;
                    }
                    if (response.data.warnings) {
                        message += '\n\nWarnings:';
                        if (response.data.warnings.project_name) {
                            message += '\n- ' + response.data.warnings.project_name.join('\n- ');
                        }
                        if (response.data.warnings.invalid_urls) {
                            message += '\n- ' + response.data.warnings.invalid_urls.message;
                            response.data.warnings.invalid_urls.urls.forEach(function(invalidUrl) {
                                message += '\n  Line ' + invalidUrl.line + ': ' + invalidUrl.url;
                            });
                        }
                    }
                    sessionStorage.setItem('rui_submission_message', message);
                    sessionStorage.setItem('rui_submission_status', 'success');
                    
                    if (typeof sendinblue !== 'undefined' && typeof sendinblue.track === 'function') {
                        sendinblue.track(
                            'project_submitted',
                            { email: response.data.user_email },
                            { id: 'project:' + response.data.project_id }
                        );
                    }
                    
                    window.location.reload();
                } else {
                    handleErrors({'general': [response.data.message || 'An error occurred while submitting the project.']});
                    $submitButton.prop('disabled', false).text('Submit');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
                handleErrors({'general': ['An error occurred while submitting the project. Please try again.']});
                $submitButton.prop('disabled', false).text('Submit');
            }
        });
    });

    function handleErrors(errors) {
        var errorHtml = '<div class="notice notice-error">';
        
        if (errors.project_name) {
            errorHtml += '<p><strong>Project Name Errors:</strong></p><ul>';
            errors.project_name.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            errorHtml += '</ul>';
        }
        
        if (errors.urls) {
            errorHtml += '<p><strong>URL Errors:</strong></p><p>' + errors.urls + '</p>';
        }
        
        if (errors.invalid_urls) {
            errorHtml += '<p><strong>Invalid URLs:</strong></p><ul>';
            errors.invalid_urls.forEach(function(invalidUrl) {
                errorHtml += '<li>Line ' + invalidUrl.line + ': ' + invalidUrl.url + '</li>';
            });
            errorHtml += '</ul>';
        }
        
        if (errors.general) {
            errorHtml += '<p><strong>General Errors:</strong></p><ul>';
            errors.general.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            errorHtml += '</ul>';
        }
        
        errorHtml += '</div>';
        $('#rui-submission-response').html(errorHtml);
    }

    // Check for stored messages on page load
    $(document).ready(function() {
        var message = sessionStorage.getItem('rui_submission_message');
        var status = sessionStorage.getItem('rui_submission_status');
        
        if (message) {
            var noticeClass = status === 'success' ? 'notice-success' : 'notice-error';
            $('#rui-submission-response').html('<div class="notice ' + noticeClass + '"><p>' + message + '</p></div>');
            
            // Clear the stored message and status
            sessionStorage.removeItem('rui_submission_message');
            sessionStorage.removeItem('rui_submission_status');
        }
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
                if (response.success && response.data) {
                    showChart(response.data);
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

    function showChart(responseData) {
        var modal = $('#chartModal');
        var canvas = document.getElementById('indexingChart');
        var ctx = canvas.getContext('2d');

        // Ensure the chart is destroyed if it exists
        if (window.indexingChart instanceof Chart) {
            window.indexingChart.destroy();
        }

        // Set modal styles
        modal.css({
            'display': 'block',
            'background-color': '#121212',
            'color': '#ffffff'
        });

        // Set modal content styles
        modal.find('.modal-content').css({
            'background-color': '#1e1e1e',
            'color': '#ffffff',
            'padding': '20px',
            'border-radius': '5px'
        });

        // Position close button
        positionCloseButton();

        // Reset canvas dimensions and clear it
        canvas.width = modal.width() * 0.9; // 90% of modal width
        canvas.height = 400; // Fixed height or adjust as needed
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (!responseData || !responseData.data || !Array.isArray(responseData.data)) {
            modal.find('.modal-content').html('<p style="color: #ffffff;">Error: Invalid data structure received for the chart.</p>');
            return;
        }

        var data = responseData.data;

        if (data.length === 0) {
            modal.find('.modal-content').html('<p style="color: #ffffff;">No data available to display in the chart.</p>');
            return;
        }

        var labels = [];
        var indexedData = [];
        var unindexedData = [];

        for (var i = 0; i < data.length; i++) {
            var item = data[i];
            labels.push(item.date || '');
            indexedData.push(parseInt(item.indexed_count) || 0);
            unindexedData.push(parseInt(item.unindexed_count) || 0);
        }

        if (indexedData.length === 0 && unindexedData.length === 0) {
            modal.find('.modal-content').html('<p style="color: #ffffff;">No valid data available to display in the chart.</p>');
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
                maintainAspectRatio: false,
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
                        backgroundColor: '#1e1e1e',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#424242',
                        borderWidth: 1
                    }
                }
            }
        });
    }

    function closeModal() {
        $('#chartModal').hide();
        if (window.indexingChart instanceof Chart) {
            window.indexingChart.destroy();
        }
    }

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

    // Ensure the close button is clickable
    $(document).on('click', '.close', function(event) {
        event.stopPropagation();
        closeModal();
    });

    // Add click event listener to close modal when clicking outside
    $(document).on('click', '#chartModal', function(event) {
        if (event.target === this) {
            closeModal();
        }
    });
});
