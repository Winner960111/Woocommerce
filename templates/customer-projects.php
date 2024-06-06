<?php
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

// Display project submission form and project list
?>

<div class="rui-projects">
    <h2>My Projects</h2>
    <?php echo do_shortcode('[rui_project_submission]'); ?>
    <h3>Project List</h3>
    <!-- Code to display user's projects -->
</div>
