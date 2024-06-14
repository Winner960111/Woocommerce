<div class="wrap">
    <h1>Rapid URL Indexer Settings</h1>  
    <form method="post" action="options.php">
        <?php
        settings_fields('rui_settings');
        do_settings_sections('rui_settings');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('SpeedyIndex API Key', 'rapid-url-indexer'); ?></th>
                <td><input type="text" name="rui_speedyindex_api_key" value="<?php echo esc_attr(get_option('rui_speedyindex_api_key')); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Delete Data on Uninstall', 'rapid-url-indexer'); ?></th>
                <td><input type="checkbox" name="rui_delete_data_on_uninstall" value="1" <?php checked(get_option('rui_delete_data_on_uninstall', 0), 1); ?> /></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Log Entry Limit', 'rapid-url-indexer'); ?></th>
                <td><input type="number" name="rui_log_entry_limit" value="<?php echo esc_attr(get_option('rui_log_entry_limit', 1000)); ?>" /></td>
            </tr>
            </table>
            <h2><?php _e('Abuse Detection Settings', 'rapid-url-indexer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Minimum Number of Projects for Abuse Detection', 'rapid-url-indexer'); ?></th>
                    <td><input type="number" name="rui_min_projects_for_abuse" value="<?php echo esc_attr(get_option('rui_min_projects_for_abuse', 10)); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Average Refund Rate for Abuse Detection', 'rapid-url-indexer'); ?></th>
                    <td><input type="number" step="0.01" name="rui_avg_refund_rate_for_abuse" value="<?php echo esc_attr(get_option('rui_avg_refund_rate_for_abuse', 0.7)); ?>" /></td>
                </tr>
            </table>
        <?php submit_button(); ?>
    </form>
</div>
