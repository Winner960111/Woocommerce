<?php
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}
global $wpdb;
$user_id = get_current_user_id();
$table_name = $wpdb->prefix . 'rapid_url_indexer_projects';

// Pagination settings
$projects_per_page = 50;
$paged = max(1, get_query_var('paged'));
$offset = ($paged - 1) * $projects_per_page;

// Fetch user projects with pagination
$total_projects = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d", $user_id));
$projects = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $projects_per_page, $offset));
?>
<div class="rui-projects">
    <div class="rui-project-submission">
        <?php echo do_shortcode('[rui_project_submission]'); ?>
    </div>
    <script>
        // Clear form fields after page load (which happens after form submission)
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('project_name').value = '';
            document.getElementById('project_name').placeholder = 'My Project';
            document.getElementById('urls').value = '';
            document.getElementById('urls').placeholder = 'https://example.com/some-page/\nhttps://example.org/another-page/\n...';
            document.getElementById('notify').checked = false;
        });
    </script>
    <div class="rui-credits-display">
    <?php echo Rapid_URL_Indexer_Customer::credits_display(false); ?>
    </div>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            $total_pages = ceil($total_projects / $projects_per_page);
            $current_url = remove_query_arg('paged', get_pagenum_link(1));
            $paginate_links = paginate_links(array(
                'base' => $current_url . '%_%',
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => $total_pages,
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'type' => 'array'
            ));

            if ($paginate_links) {
                echo '<nav class="pagination">';
                echo '<ul class="page-numbers">';
                foreach ($paginate_links as $link) {
                    echo '<li>' . $link . '</li>';
                }
                echo '</ul>';
                echo '</nav>';
            }
            ?>
        </div>
    </div>
    <h2>Project List</h2>
    <table>
        <thead>
            <tr>
                <th>Project</th>
                <th>Status</th>
                <th>Indexed</th>
                <th>Created at</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?php echo esc_html($project->project_name); ?></td>
                    <td>
                        <?php echo esc_html($project->status); ?>
                        <?php if ($project->status == 'refunded'): ?>
                            (<?php echo esc_html($project->refunded_credits); ?> credits)
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $submission_time = strtotime($project->created_at);
                        $current_time = time();
                        $hours_since_submission = ($current_time - $submission_time) / 3600;

                        if ($project->status == 'pending' || $project->status == 'failed') {
                            echo esc_html("N/A");
                        } else {
                            $total_urls = $project->processed_links;
                            $indexed_links = $project->indexed_links;
                            
                            if ($hours_since_submission >= 96) {
                                $percentage = $total_urls > 0 ? round(($indexed_links / $total_urls) * 100) : 0;
                                echo esc_html("$indexed_links/$total_urls ($percentage%)");
                            } else {
                                $hours_remaining = ceil(96 - $hours_since_submission);
                                echo esc_html("Available in " . $hours_remaining . " " . ($hours_remaining == 1 ? "hour" : "hours") . "...");
                            }
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html($project->created_at); ?></td>
                    <td>
                        <?php if ($project->status == 'pending' || $project->status == 'failed'): ?>
                            <span>N/A</span>
                        <?php else: ?>
                            <?php if ($hours_since_submission >= 96): ?>
                                <a href="<?php echo esc_url(add_query_arg(array('download_report' => $project->id))); ?>" class="button wp-element-button">Download Report</a>
                                <br>
                                <a href="#" class="show-chart" data-project-id="<?php echo esc_attr($project->id); ?>" style="margin-top: 5px; display: inline-block;">Show Chart</a>
                            <?php else: ?>
                                <?php
                                $hours_remaining = ceil(96 - $hours_since_submission);
                                echo esc_html("Available in " . $hours_remaining . " " . ($hours_remaining == 1 ? "hour" : "hours") . "...");
                                ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</tbody>
    </table>

    <!-- Chart Modal -->
    <div id="chartModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <canvas id="indexingChart"></canvas>
        </div>
    </div>

    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            $current_url = remove_query_arg('paged', get_pagenum_link(1));
            echo paginate_links(array(
                'base' => $current_url . '%_%',
                'format' => '?paged=%#%',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $paged
            ));
            ?>
        </div>
    </div>
    <br>
    <ul>
        <li><strong>Pending:</strong> The project has been created but not yet submitted for indexing.</li>
        <li><strong>Submitted:</strong> The project has been submitted and indexing is in progress.</li>
        <li><strong>Completed:</strong> The indexing process has been completed.</li>
        <li><strong>Failed:</strong> The indexing process failed. Credits have been refunded.</li>
        <li><strong>Refunded:</strong> Some URLs were not indexed within 14 days, and credits have been automatically refunded.</li>
    </ul>
    <h2>How it Works</h2>
    <ul>
        <li><strong>1. Submit your project:</strong> First, submit your project.</li>
        <li><strong>2. Wait for 4 days:</strong> After 4 days, the initial indexing report and charts will be available.</li>
        <li><strong>3. Wait for another 10 days:</strong> 14 days after project submission, the final indexing report will be available and you will automatically receive a credit refund for unindexed URLs.</li>
    </ul>
    <br>
    <br>
    <div class="rui-api-key-display">
        <?php echo do_shortcode('[rui_api_key_display]'); ?>
    </div>
    <details>
        <summary>RESTful API Documentation</summary>
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Method</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>/api/v1/projects</td>
                    <td>POST</td>
                    <td>Submit a new project for indexing. Requires authentication.</td>
                </tr>
                <tr>
                    <td>/api/v1/projects/{project_id}</td>
                    <td>GET</td>
                    <td>Get the status of a specific project. Requires authentication.</td>
                </tr>
                <tr>
                    <td>/api/v1/projects/{project_id}/report</td>
                    <td>GET</td>
                    <td>Download the report for a specific project. Requires authentication.</td>
                </tr>
                <tr>
                    <td>/api/v1/credits/balance</td>
                    <td>GET</td>
                    <td>Get the current credit balance for the authenticated user.</td>
                </tr>
            </tbody>
        </table>

        <h3>Authentication</h3>
        <p>All API endpoints require authentication using an API key. The API key should be included in the <code>X-API-Key</code> header of each request.</p>

        <h3>Project Names and URLs</h3>
        <p>Project names will be sanitized to remove any special characters or HTML tags. If no project name is provided, a fallback name will be generated using the format "noname_" followed by an MD5 hash of the submitted URLs. URLs will be validated and must start with either "http://" or "https://". Invalid URLs will be discarded.</p>

        <h3>Error Responses</h3>
        <p>If an error occurs, the API will return an appropriate HTTP status code along with an error message in the response body. Possible error codes include:</p>
        <ul>
            <li><strong>400 Bad Request</strong> - The request was malformed or missing required parameters.</li>
            <li><strong>401 Unauthorized</strong> - The API key is missing or invalid.</li>
            <li><strong>403 Forbidden</strong> - The API key is valid but does not have permission to access the requested resource.</li>
            <li><strong>404 Not Found</strong> - The requested resource (e.g., project) does not exist.</li>
            <li><strong>429 Too Many Requests</strong> - The rate limit for API requests has been exceeded.</li>
            <li><strong>500 Internal Server Error</strong> - An unexpected error occurred on the server.</li>
        </ul>

        <h3>RESTful API Endpoints</h3>

        <h4>Submit a New Project</h4>
        <p><strong>Endpoint:</strong> <code>POST https://rapidurlindexer.com/wp-json/api/v1/projects</code></p>
        <p><strong>Request Body:</strong></p>
        <pre><code>{
    "project_name": "My Project",
    "urls": ["http://example.com", "http://example.org"],
    "notify_on_status_change": true
}</code></pre>
        <p><strong>Arguments:</strong></p>
        <ul>
            <li><strong>project_name</strong> (string, required): The name of your project.</li>
            <li><strong>urls</strong> (array of strings, required): An array of URLs to be indexed. Each URL must start with "http://" or "https://".</li>
            <li><strong>notify_on_status_change</strong> (boolean, optional, default: false): If set to true, you will receive email notifications when the project status changes.</li>
        </ul>
        <p><strong>Successful Response (201 Created):</strong></p>
        <pre><code>{
    "message": "Project created successfully",
    "project_id": 123
}</code></pre>
        <p><strong>Error Responses:</strong></p>
        <pre><code>400 Bad Request
{
    "message": "Invalid project name or URLs"
}

401 Unauthorized
{
    "message": "Invalid API key"
}

403 Forbidden
{
    "message": "Insufficient credits"
}</code></pre>

        <h4>Get Project Status</h4>
        <p><strong>Endpoint:</strong> <code>GET https://rapidurlindexer.com/wp-json/api/v1/projects/{project_id}</code></p>
        <p><strong>Arguments:</strong></p>
        <ul>
            <li><strong>project_id</strong> (integer, required): The ID of the project you want to check.</li>
        </ul>
        <p><strong>Successful Response (200 OK):</strong></p>
        <pre><code>{
    "project_id": 123,
    "project_name": "My Project",
    "status": "submitted",
    "submitted_links": 2,
    "processed_links": 1,
    "indexed_links": 1,
    "created_at": "2023-06-01T12:00:00Z",
    "updated_at": "2023-06-01T12:05:00Z"
}</code></pre>
        <p><strong>Possible Status Values:</strong></p>
        <ul>
            <li><strong>pending:</strong> The project has been created but not yet submitted for indexing.</li>
            <li><strong>submitted:</strong> The project has been submitted and indexing is in progress.</li>
            <li><strong>completed:</strong> The indexing process has been completed.</li>
            <li><strong>failed:</strong> The indexing process failed. Credits have been refunded.</li>
            <li><strong>refunded:</strong> Some URLs were not indexed within 14 days, and credits have been automatically refunded.</li>
        </ul>
        <p><strong>Error Response (404 Not Found):</strong></p>
        <pre><code>{
    "message": "Project not found"
}</code></pre>

        <h4>Download Project Report</h4>
        <p><strong>Endpoint:</strong> <code>GET https://rapidurlindexer.com/wp-json/api/v1/projects/{project_id}/report</code></p>
        <p><strong>Arguments:</strong></p>
        <ul>
            <li><strong>project_id</strong> (integer, required): The ID of the project for which you want to download the report.</li>
        </ul>
        <p><strong>Successful Response (200 OK):</strong></p>
        <p>Returns a CSV file with the following format:</p>
        <pre><code>URL,Status
http://example.com,Indexed
http://example.org,Not Indexed</code></pre>
        <p><strong>Error Response (404 Not Found):</strong></p>
        <pre><code>{
    "message": "Project report not available"
}</code></pre>

        <h4>Get Credit Balance</h4>
        <p><strong>Endpoint:</strong> <code>GET https://rapidurlindexer.com/wp-json/api/v1/credits/balance</code></p>
        <p><strong>Successful Response (200 OK):</strong></p>
        <pre><code>{
    "credits": 100
}</code></pre>
        <p><strong>Error Response (401 Unauthorized):</strong></p>
        <pre><code>{
    "message": "Invalid API key"
}</code></pre>

        <h3>Rate Limiting</h3>
        <p>API requests are limited to 100 requests per minute per API key. If you exceed this limit, you'll receive a 429 Too Many Requests response.</p>
    </details>
</div>
<?php
// Log the project data for debugging
error_log('Customer Projects: ' . print_r($projects, true));
?>
