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

<h2>Automatic Submission Settings</h2>
<table class="form-table">
    <tr>
        <th scope="row">Submit on Publish</th>
        <td>
            <fieldset>
                <legend class="screen-reader-text"><span>Submit on Publish</span></legend>
                <?php 
                $post_types = get_post_types(array('public' => true), 'objects');
                foreach ($post_types as $post_type) {
                    $submit_on_publish = get_option("rui_submit_on_publish_{$post_type->name}", 0);
                    ?>
                    <label>
                        <input type="checkbox" name="rui_submit_on_publish_<?php echo esc_attr($post_type->name); ?>" value="1" <?php checked($submit_on_publish, 1); ?>>
                        <?php echo esc_html($post_type->labels->singular_name); ?>
                    </label>
                    <br>
                    <?php
                }
                ?>
            </fieldset>
        </td>
    </tr>
    <tr>
        <th scope="row">Submit on Update</th>
        <td>
            <fieldset>
                <legend class="screen-reader-text"><span>Submit on Update</span></legend>
                <?php 
                foreach ($post_types as $post_type) {
                    $submit_on_update = get_option("rui_submit_on_update_{$post_type->name}", 0);
                    ?>
                    <label>
                        <input type="checkbox" name="rui_submit_on_update_<?php echo esc_attr($post_type->name); ?>" value="1" <?php checked($submit_on_update, 1); ?>>
                        <?php echo esc_html($post_type->labels->singular_name); ?>
                    </label>
                    <br>
                    <?php
                }
                ?>
            </fieldset>
        </td>
    </tr>
</table>
