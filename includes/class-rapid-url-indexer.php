<?php
class Rapid_URL_Indexer {
    public static function init() {
        self::load_dependencies();
        self::define_hooks();
    }

    private static function load_dependencies() {
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-admin.php';
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-customer.php';
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-api.php';
    }

    private static function define_hooks() {
        add_action('rui_cron_job', array('Rapid_URL_Indexer', 'process_cron_jobs'));
        add_action('rui_process_api_request', array('Rapid_URL_Indexer', 'process_api_request'), 10, 3);
    }

    public static function process_cron_jobs() {
        // Code to process scheduled tasks like checking API status and auto refunds
    }

    public static function process_api_request($project_id, $urls, $notify) {
        // Get API key
        $api_key = get_option('speedyindex_api_key');
    
        // Call API to create task
        $response = Rapid_URL_Indexer_API::create_task($api_key, $urls);
    
        // Handle response and update project status
        if ($response) {
            // Update project status to 'submitted'
            global $wpdb;
            $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
            $wpdb->update($table_name, array('status' => 'submitted'), array('id' => $project_id));
            
            // Log the action
            $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
            $wpdb->insert($table_name, array(
                'user_id' => get_current_user_id(),
                'project_id' => $project_id,
                'action' => 'API Request',
                'details' => json_encode($response),
                'created_at' => current_time('mysql')
            ));
    
            // Notify user if required
            if ($notify) {
                $user_info = get_userdata(get_current_user_id());
                wp_mail(
                    $user_info->user_email,
                    __('Your URL Indexing Project Has Been Submitted', 'rapid-url-indexer'),
                    __('Your project has been submitted and is being processed.', 'rapid-url-indexer')
                );
            }
        }
    }
    
}
?>
