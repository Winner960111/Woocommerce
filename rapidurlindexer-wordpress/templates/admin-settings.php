<div class="wrap">
    <h1><?php _e('Rapid URL Indexer Settings', 'rapid-url-indexer'); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('rui_options');
        do_settings_sections('rapid-url-indexer');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Remaining Credits', 'rapid-url-indexer'); ?></th>
                <td><?php echo esc_html($this->get_credits_balance()); ?></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <hr>
    <h2><?php _e('Bulk Submit URLs', 'rapid-url-indexer'); ?></h2>
    <form id="rui-bulk-submit-form">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="rui-project-name"><?php _e('Project Name', 'rapid-url-indexer'); ?></label></th>
                <td><input type="text" id="rui-project-name" name="project_name" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="rui-urls"><?php _e('URLs (one per line)', 'rapid-url-indexer'); ?></label></th>
                <td><textarea id="rui-urls" name="urls" rows="10" cols="50" required></textarea></td>
            </tr>
        </table>
        <?php submit_button(__('Submit URLs', 'rapid-url-indexer'), 'primary', 'submit', false); ?>
    </form>
    <div id="rui-bulk-submit-response"></div>
</div>
        <h2>Credits Balance</h2>
        <p>Remaining Credits: <?php echo $this->get_credits_balance(); ?></p>
