<div class="wrap">
    <h1>Rapid URL Indexer - Dashboard</h1>
    
    <h2>Overview</h2>
    <ul>
        <li>Total Projects: <?php echo esc_html($total_projects); ?></li>
        <li>Projects Processing: <?php echo esc_html($processing_projects); ?></li>
        <li>Projects Completed: <?php echo esc_html($completed_projects); ?></li>
        <li>URL Indexing Service Credits: <?php echo esc_html($api_credits); ?></li>
        <li>Total Assigned Customer Credits: <?php echo esc_html($total_assigned_credits); ?></li>
    </ul>
    <h2>Potential Abusers</h2>
    <form id="check-abuse-form" method="post" action="">
        <input type="hidden" name="action" value="check_abuse">
        <button type="button" id="check-abuse-button" class="button">Find Abusers</button>
    </form>
    <div id="abuse-results"></div>
    <h2>Projects</h2>
    <a href="<?php echo admin_url('admin.php?page=rapid-url-indexer-projects'); ?>" class="button">View Projects</a>

    <h2>Logs</h2>
    <a href="<?php echo admin_url('admin.php?page=rapid-url-indexer-logs'); ?>" class="button">View Logs</a>
    <h2>Tasks</h2>
    <a href="<?php echo admin_url('admin.php?page=rapid-url-indexer-tasks'); ?>" class="button">View Tasks</a>
</div>
