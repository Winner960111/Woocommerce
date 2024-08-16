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
global $wp;
$paged = 1;
if (preg_match('/\/rui-projects\/page\/(\d+)\/?$/', $wp->request, $matches)) {
    $paged = max(1, intval($matches[1]));
} elseif (isset($_GET['paged'])) {
    $paged = max(1, intval($_GET['paged']));
}
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
    <h2>Project List</h2>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            $total_pages = ceil($total_projects / $projects_per_page);
            $paginate_links = paginate_links(array(
                'base' => home_url('/my-account/rui-projects/page/%#%/'),
                'format' => '',
                'current' => $paged,
                'total' => $total_pages,
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'type' => 'array'
            ));
            ?>
        </div>
    </div>
    <div class="rui-table-wrapper">
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
    </div>

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
            echo paginate_links(array(
                'base' => home_url('/my-account/rui-projects/page/%#%/'),
                'format' => '',
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
    <h2>Integrations</h2>
    <ul>
        <li><strong><a href="https://rapidurlindexer.com/wordpress-plugin/">WordPress Plugin</a>:</strong> Automate submission of posts/pages when publishing or updating.</li>
        <li><strong><a href="https://rapidurlindexer.com/google-chrome-extension/">Chrome Extension</a>:</strong> Automatic indexability check, one-click URL submission and Google index check.</li>
        <li><strong><a href="https://rapidurlindexer.com/zapier-integration/">Zapier Integration</a>:</strong> No-code indexing workflow automation.</li>
        <li><strong><a href="https://rapidurlindexer.com/indexing-api/">RESTful API</a>:</strong> Integrate with anything.</li>
    </ul>
    <br>
    <br>
    <div class="rui-api-key-display">
        <?php echo do_shortcode('[rui_api_key_display]'); ?>
    </div>
</div>
<?php
// Log the project data for debugging
error_log('Customer Projects: ' . print_r($projects, true));
?>
