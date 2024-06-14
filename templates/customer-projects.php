<?php
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}
// Fetch user projects
global $wpdb;
$user_id = get_current_user_id();
$table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
$projects = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC", $user_id));
?>
<div class="rui-projects">
    <div class="rui-project-submission">
        <?php echo do_shortcode('[rui_project_submission]'); ?>
    </div>
    <div class="rui-credits-display">
    <?php echo Rapid_URL_Indexer_Customer::credits_display(true); ?>
    </div>
    <br>
    <h2>Project List</h2>
    <table>
        <thead>
            <tr>
                <th>Project</th>
                <th>Status</th>
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
                            (<?php echo esc_html($project->refunded_credits); ?> credits refunded)
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($project->created_at); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(array('download_report' => $project->id))); ?>" class="button">Download Report</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <br>
    <br>
    <div class="rui-api-key-display">
        <?php echo do_shortcode('[rui_api_key_display]'); ?>
    </div>
    <details>
        <summary>API Documentation</summary>
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

        <h4>Authentication</h4>
        <p>All API endpoints require authentication using an API key. The API key should be included in the <code>X-API-Key</code> header of each request.</p>

        <h4>Project Names and URLs</h4>
        <p>Project names will be sanitized to remove any special characters or HTML tags. URLs will be validated and must start with either "http://" or "https://". Invalid URLs will be discarded.</p>

        <h4>Error Responses</h4>
        <p>If an error occurs, the API will return an appropriate HTTP status code along with an error message in the response body. Possible error codes include:</p>
        <ul>
            <li><strong>400 Bad Request</strong> - The request was malformed or missing required parameters.</li>
            <li><strong>401 Unauthorized</strong> - The API key is missing or invalid.</li>
            <li><strong>404 Not Found</strong> - The requested resource (e.g., project) does not exist.</li>
            <li><strong>500 Internal Server Error</strong> - An unexpected error occurred on the server.</li>
        </ul>
    </details>
</div>
