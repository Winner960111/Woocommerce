<?php
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

// Fetch user projects
global $wpdb;
$user_id = get_current_user_id();
$table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
$projects = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));

?>

<div class="rui-projects">
    <h2>My Projects</h2>
    <?php echo do_shortcode('[rui_credits_display]'); ?>
    <?php echo do_shortcode('[rui_project_submission]'); ?>
    <h3>Project List</h3>
    <table>
        <thead>
            <tr>
                <th>Project Name</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?php echo esc_html($project->project_name); ?></td>
                    <td><?php echo esc_html($project->status); ?></td>
                    <td><?php echo esc_html($project->created_at); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(array('download_report' => $project->id))); ?>">Download Report</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
