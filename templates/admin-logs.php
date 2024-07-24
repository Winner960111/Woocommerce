<div class="wrap">
    <h1>Rapid URL Indexer Logs</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
                <label for="rui-log-search" class="screen-reader-text">Search Logs:</label>
                <input type="search" id="rui-log-search" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="submit" id="rui-log-search-submit" class="button" value="Search Logs">
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped" id="rui-logs-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Email</th>
                <th>Project ID</th>
                <th>Action</th>
                <th>Details</th>
                <th>Triggered By</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->id); ?></td>
                    <td><?php echo esc_html($log->user_id); ?></td>
                    <td><?php echo esc_html($log->user_email); ?></td>
                    <td><?php echo esc_html($log->project_id); ?></td>
                    <td><?php echo esc_html($log->action); ?></td>
                    <td><?php echo esc_html($log->details); ?></td>
                    <td><?php echo esc_html($log->triggered_by); ?></td>
                    <td><?php echo esc_html($log->created_at); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
