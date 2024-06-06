<div class="wrap">
    <h1>Manage Credits</h1>
    <form method="post" action="">
        <label for="user_id">User ID:</label>
        <input type="number" name="user_id" id="user_id" required>
        <label for="credits">Credits:</label>
        <input type="number" name="credits" id="credits" required>
        <input type="submit" value="Update Credits">
    </form>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $credits = intval($_POST['credits']);

    if ($user_id && $credits) {
        Rapid_URL_Indexer_Admin::update_user_credits($user_id, $credits);
        echo '<p>Credits updated successfully.</p>';
    } else {
        echo '<p>Invalid user ID or credits amount.</p>';
    }
}
?>
