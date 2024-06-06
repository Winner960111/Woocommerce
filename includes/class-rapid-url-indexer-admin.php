<?php
class Rapid_URL_Indexer_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
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
            'Manage Credits',
            'Manage Credits',
            'manage_options',
            'manage-credits',
            array(__CLASS__, 'manage_credits_page')
        );
    }

    public static function admin_page() {
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
    
}
?>
