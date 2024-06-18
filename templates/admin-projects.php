<div class="wrap">
    <h1>Rapid URL Indexer - Projects</h1>
    <div class="tablenav top">
        <div class="alignleft actions">
            <label for="rui-project-search">Search:</label>
            <input type="search" id="rui-project-search" name="s" value="<?php echo esc_attr($search); ?>">
            <input type="button" id="rui-project-search-submit" class="button" value="Search">
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_projects; ?> items</span>
            <span class="pagination-links">
                <?php echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => ceil($total_projects / $projects_per_page),
                    'current' => $paged
                )); ?>
            </span>
        </div>
    </div>
    
    <?php if ($projects): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Project ID</th>
                    <th>Project Name</th>
                    <th>Task ID</th>
                    <th>User ID</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Processed</th>
                    <th>Indexed</th>
                    <th>Created At</th>
                    <th>Last Updated</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo esc_html($project->id); ?></td>
                        <td><?php echo esc_html($project->project_name); ?></td>
                        <td><?php echo esc_html($project->task_id); ?></td>
                        <td><?php echo esc_html($project->user_id); ?></td>
                        <td><?php echo esc_html($project->user_email); ?></td>
                        <td><?php echo esc_html($project->status); ?></td>
                        <td><?php echo esc_html(count(json_decode($project->urls, true))); ?></td>
                        <td><?php echo esc_html(isset($project->processed_links) ? $project->processed_links : 'N/A'); ?></td>
                        <td><?php echo esc_html(isset($project->indexed_links) ? $project->indexed_links : 'N/A'); ?></td>
                        <td><?php echo esc_html($project->created_at); ?></td>
                        <td><?php echo esc_html(isset($project->updated_at) ? $project->updated_at : 'N/A'); ?></td>
                        <td><a href="<?php echo esc_url(add_query_arg(array('download_project_report' => $project->id))); ?>" class="button">Download CSV</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No projects found.</p>
    <?php endif; ?>
</div>
