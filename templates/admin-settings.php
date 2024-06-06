<div class="wrap">
    <h1>Rapid URL Indexer Settings</h1>
    <form method="post">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="speedyindex_api_key">SpeedyIndex API Key</label></th>
                <td><input name="speedyindex_api_key" type="text" id="speedyindex_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
