<?php
class Rapid_URL_Indexer {
    public static function init() {
        self::load_dependencies();
        self::define_hooks();
    }

    private static function log_action($user_id, $project_id, $action, $details) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        $wpdb->insert($log_table, array(
            'user_id' => $user_id,
            'project_id' => $project_id,
            'action' => $action,
            'details' => $details,
            'created_at' => current_time('mysql')
        ));
    }

    public static function auto_refund() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $projects = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'submitted' AND DATE_ADD(created_at, INTERVAL 14 DAY) <= NOW() AND refunded = 0");

        foreach ($projects as $project) {
            $api_key = get_option('speedyindex_api_key');
            $response = Rapid_URL_Indexer_API::get_task_status($api_key, $project->id);

            if ($response && isset($response['indexed_count'])) {
                $indexed_count = $response['indexed_count'];
                $total_urls = count(json_decode($project->urls));
                $unindexed_count = $total_urls - $indexed_count;
                $refund_credits = ceil($unindexed_count * 0.8);

                // Refund credits
                Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, $refund_credits);

                // Update project status
                $wpdb->update($table_name, array('status' => 'refunded'), array('id' => $project->id));

                // Mark project as refunded
                $wpdb->update($table_name, array('refunded' => 1), array('id' => $project->id));

                // Log the action
                $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
                $wpdb->insert($log_table, array(
                    'user_id' => $project->user_id,
                    'project_id' => $project->id,
                    'action' => 'Auto Refund',
                    'details' => json_encode($response),
                    'created_at' => current_time('mysql')
                ));
            }
        }
    }

    private static function log_action($user_id, $project_id, $action, $details) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        $wpdb->insert($log_table, array(
            'user_id' => $user_id,
            'project_id' => $project_id,
            'action' => $action,
            'details' => $details,
            'created_at' => current_time('mysql')
        ));
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
        if ($response && isset($response['task_id'])) {
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
?>
