<div class="wrap">
    <h1>Rapid URL Indexer Settings</h1>  
    <form method="post" action="options.php">
        <?php
        settings_fields('rui_options');
        do_settings_sections('rapid-url-indexer');
        ?>
        <table class="form-table">
            <tr>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
