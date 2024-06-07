<div class="rui-post-settings">
    <p>
        <label>
            <input type="checkbox" name="rui_submit_on_publish" value="1" <?php checked($submit_on_publish, 1); ?>>
            Submit on Publish
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="rui_submit_on_update" value="1" <?php checked($submit_on_update, 1); ?>>
            Submit on Update
        </label>
    </p>
    <?php wp_nonce_field('rui_post_settings', 'rui_post_settings_nonce'); ?>
</div>
