<?php
class Rapid_URL_Indexer_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('rui_log_entry_created', array('Rapid_URL_Indexer_Admin', 'limit_log_entries'));
    }

    public static function delete_data_on_uninstall_callback() {
        $delete_data_on_uninstall = get_option('rui_delete_data_on_uninstall', 0);
        echo '<input type="checkbox" name="rui_delete_data_on_uninstall" value="1" ' . checked(1, $delete_data_on_uninstall, false) . ' />';
    }

    private static function get_api_key() {
        return get_option('speedyindex_api_key');
    }

    public static function admin_menu() {
        add_menu_page(
            'Rapid URL Indexer',
            'URL Indexer',
            'manage_options',
            'rapid-url-indexer',
            array(__CLASS__, 'admin_page'),
            'dashicons-admin-site'
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Settings',
            'Settings',
            'manage_options',
            'rapid-url-indexer-settings',
            array(__CLASS__, 'settings_page')
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Manage Credits',
            'Manage Credits',
            'manage_options',
            'manage-credits',
            array(__CLASS__, 'manage_credits_page')
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Logs',
            'Logs',
            'manage_options',
            'rapid-url-indexer-logs',
            array(__CLASS__, 'logs_page')
        );
    }

    public static function admin_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        
        $total_projects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $processing_projects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'submitted'");
        $completed_projects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        
        $credits_table = $wpdb->prefix . 'rapid_url_indexer_credits';
        $total_credits = $wpdb->get_var("SELECT SUM(credits) FROM $credits_table");
        
        $api_key = self::get_api_key();
        $api_credits = Rapid_URL_Indexer_API::get_account_balance($api_key);
        $api_credits = $api_credits ? $api_credits['balance']['indexer'] : 'N/A';
        
        include RUI_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    public static function manage_credits_page() {
        include RUI_PLUGIN_DIR . 'templates/admin-manage-credits.php';
    } 

    public static function enqueue_scripts($hook) {
        if (!in_array($hook, array('toplevel_page_rapid-url-indexer', 'rapid-url-indexer_page_rapid-url-indexer-settings', 'rapid-url-indexer_page_manage-credits', 'rapid-url-indexer_page_rapid-url-indexer-logs'))) {
            return;
        }
        wp_enqueue_style('rui-admin-css', RUI_PLUGIN_URL . 'assets/css/admin.css');
        wp_enqueue_script('rui-admin-js', RUI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), null, true);
    }


    public static function get_user_credits($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $credits = $wpdb->get_var($wpdb->prepare("SELECT credits FROM $table_name WHERE user_id = %d", $user_id));
        return $credits ? $credits : 0;
    }

    public static function settings_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            update_option('speedyindex_api_key', sanitize_text_field($_POST['speedyindex_api_key']));
            update_option('rui_delete_data_on_uninstall', isset($_POST['rui_delete_data_on_uninstall']) ? 1 : 0);
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $log_entry_limit = get_option('rui_log_entry_limit', 1000);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            update_option('rui_log_entry_limit', intval($_POST['rui_log_entry_limit']));
        }

        include RUI_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    public static function logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';

        $logs_per_page = 50;
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $offset = ($paged - 1) * $logs_per_page;

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where = $search ? $wpdb->prepare(" WHERE action LIKE %s OR details LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%') : '';

        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
        $logs = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT $offset, $logs_per_page");

        include RUI_PLUGIN_DIR . 'templates/admin-logs.php';
    }

    public static function ajax_search_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where = $search ? $wpdb->prepare(" WHERE action LIKE %s OR details LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%') : '';

        $logs = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY created_at DESC");

        ob_start();
        foreach ($logs as $log) {
            ?>
            <tr>
                <td><?php echo esc_html($log->id); ?></td>
                <td><?php echo esc_html($log->user_id); ?></td>
                <td><?php echo esc_html($log->project_id); ?></td>
                <td><?php echo esc_html($log->action); ?></td>
                <td><?php echo esc_html($log->details); ?></td>
                <td><?php echo esc_html($log->created_at); ?></td>
            </tr>
            <?php
        }
        $output = ob_get_clean();

        wp_send_json_success($output);
    }

    public static function limit_log_entries() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
        $log_entry_limit = get_option('rui_log_entry_limit', 1000);
        
        $log_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($log_count > $log_entry_limit) {
            $logs_to_delete = $log_count - $log_entry_limit;
            $wpdb->query("DELETE FROM $table_name ORDER BY created_at ASC LIMIT $logs_to_delete");
        }
    }
}
