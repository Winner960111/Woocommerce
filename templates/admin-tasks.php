<div class="wrap">
    <h1>Rapid URL Indexer - Tasks</h1>
    
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
