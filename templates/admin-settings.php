<div class="wrap">
    <h1>Rapid URL Indexer Settings</h1>  
    <form method="post" action="options.php">
        <?php
        settings_fields('rui_options');
        do_settings_sections('rapid-url-indexer');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Delete Data on Uninstall', 'rapid-url-indexer'); ?></th>
                <td><input type="checkbox" name="rui_delete_data_on_uninstall" value="1" <?php checked(get_option('rui_delete_data_on_uninstall', 0), 1); ?> /></td>
            </tr>
            <tr>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
