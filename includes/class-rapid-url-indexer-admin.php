<?php
class Rapid_URL_Indexer_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
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
        if (strpos($hook, 'rapid-url-indexer') === false) {
            return;
        }
        wp_enqueue_style('rui-admin-css', RUI_PLUGIN_URL . 'assets/css/admin.css');
        wp_enqueue_script('rui-admin-js', RUI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), null, true);
    }

    public static function update_user_credits($user_id, $credits) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $current_credits = $wpdb->get_var($wpdb->prepare("SELECT credits FROM $table_name WHERE user_id = %d", $user_id));
        if ($current_credits !== null) {
            $wpdb->update($table_name, array('credits' => $credits), array('user_id' => $user_id));
        } else {
            $wpdb->insert($table_name, array('user_id' => $user_id, 'credits' => $credits));
        }
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
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $api_key = get_option('speedyindex_api_key');
        include RUI_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    public static function logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        include RUI_PLUGIN_DIR . 'templates/admin-logs.php';
    }
}
