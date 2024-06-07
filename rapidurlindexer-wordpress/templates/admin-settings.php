<div class="wrap">
    <h1>Rapid URL Indexer Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('rui_options');
        do_settings_sections('rapid-url-indexer');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Remaining Credits</th>
                <td><?php echo $this->get_credits_balance(); ?></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <hr>
    <h2>Bulk Submit URLs</h2>
    <form id="rui-bulk-submit-form">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="rui-project-name">Project Name</label></th>
                <td><input type="text" id="rui-project-name" name="project_name" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="rui-urls">URLs (one per line)</label></th>
                <td><textarea id="rui-urls" name="urls" rows="10" cols="50"></textarea></td>
            </tr>
        </table>
        <?php submit_button('Submit URLs', 'primary', 'submit', false); ?>
    </form>
    <div id="rui-bulk-submit-response"></div>
</div>
        <h2>Credits Balance</h2>
        <p>Remaining Credits: <?php echo $this->get_credits_balance(); ?></p>
