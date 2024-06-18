<div class="wrap">
    <h1>Rapid URL Indexer Logs</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <label for="rui-log-search">Search:</label>
            <input type="search" id="rui-log-search" name="s" value="">
            <input type="button" id="rui-log-search-submit" class="button" value="Search">
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_logs; ?> items</span>
            <span class="pagination-links">
                <?php echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => ceil($total_logs / $logs_per_page),
                    'current' => $paged
                )); ?>
            </span>
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
                    <td><?php echo esc_html($log->created_at); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
