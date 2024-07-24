<div class="wrap">
    <h1>Rapid URL Indexer - Projects</h1>
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
                <label for="rui-project-search" class="screen-reader-text">Search Projects:</label>
                <input type="search" id="rui-project-search" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="submit" id="rui-project-search-submit" class="button" value="Search Projects">
            </form>
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
                    <th>Submitted URLs</th>
                    <th>Processed URLs</th>
                    <th>Indexed URLs</th>
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
                        <td><?php echo esc_html($project->submitted_links); ?></td>
                        <td><?php echo esc_html($project->processed_links); ?></td>
                        <td><?php echo esc_html($project->indexed_links); ?></td>
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
