<div class="wrap">
    <h1>Rapid URL Indexer - Tasks</h1>
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
                <label for="rui-task-search" class="screen-reader-text">Search Tasks:</label>
                <input type="search" id="rui-task-search" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="submit" id="rui-task-search-submit" class="button" value="Search Tasks">
            </form>
        </div>
    </div>
    
    <?php if ($tasks): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Processed</th>
                    <th>Indexed</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?php echo esc_html($task['id']); ?></td>
                        <td><?php echo esc_html($task['title']); ?></td>
                        <td><?php echo esc_html($task['type']); ?></td>
                        <td><?php echo esc_html($task['size']); ?></td>
                        <td><?php echo esc_html($task['processed_count']); ?></td>
                        <td><?php echo esc_html($task['indexed_count']); ?></td>
                        <td><?php echo esc_html($task['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No tasks found.</p>
    <?php endif; ?>
</div>
