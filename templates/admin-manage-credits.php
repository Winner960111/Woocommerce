<div class="wrap">
    <h1>Manage Credits</h1>
    <form method="post" action="">
        <label for="user_id">User ID:</label>
        <input type="number" name="user_id" id="user_id" required>
        <input type="submit" name="check_balance" value="Check Balance">
    </form>

    <?php
    if (isset($_POST['check_balance'])) {
        $user_id = intval($_POST['user_id']);
        $current_credits = Rapid_URL_Indexer_Admin::get_user_credits($user_id);
        echo '<p>Current balance for user ' . esc_html($user_id) . ': ' . esc_html($current_credits) . ' credits</p>';
    }
    ?>

    <form method="post" action="">
        <label for="user_id">User ID:</label>
        <input type="number" name="user_id" id="user_id" required>
        <label for="credits">Credits to add/remove:</label>
        <input type="number" name="credits" id="credits" required>
        <input type="submit" name="update_credits" value="Update Credits">
    </form>

    <?php
    if (isset($_POST['update_credits'])) {
        $user_id = absint($_POST['user_id']);
        $credits = intval($_POST['credits']);

        if ($user_id && $credits) {
            $current_credits = Rapid_URL_Indexer_Admin::get_user_credits($user_id);
            $new_credits = $current_credits + $credits;
            Rapid_URL_Indexer_Customer::update_user_credits($user_id, $credits);
            echo '<p>Credits updated successfully. New balance: ' . esc_html($new_credits) . ' credits</p>';
        } else {
            echo '<p>Invalid user ID or credits amount.</p>';
        }
    }
    ?>
</div>
