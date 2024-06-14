<?php
class Rapid_URL_Indexer {
    public static function init() {
        self::load_dependencies();
        self::define_hooks();
        // Schedule abuse check
        if (!wp_next_scheduled('rui_check_abuse')) {
            wp_schedule_event(time(), 'daily', 'rui_check_abuse');
        }
        add_action('rui_check_abuse', array('Rapid_URL_Indexer', 'check_abuse'));
    }

    public static function check_abuse() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';

        $min_projects = get_option('rui_min_projects_for_abuse', 10);
        $avg_refund_rate = get_option('rui_avg_refund_rate_for_abuse', 0.7);

        // Get users with more than the minimum number of projects where the average refund rate is above the threshold
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, COUNT(*) as project_count, AVG(refunded_credits / (indexed_links + refunded_credits)) as avg_refund_rate
            FROM $table_name
            WHERE status = 'refunded'
            GROUP BY user_id
            HAVING project_count > %d AND avg_refund_rate >= %f
        ", $min_projects, $avg_refund_rate));

        if ($results) {
            $admin_email = get_option('admin_email');
            $subject = __('Potential Abuse Detected', 'rapid-url-indexer');
            $message = __('The following users have created more than 10 projects with an average of 70% or more URLs not indexed and refunded:', 'rapid-url-indexer') . "\n\n";

            foreach ($results as $result) {
                $message .= sprintf(__('User ID: %d, Project Count: %d, Average Refund Rate: %.2f%%', 'rapid-url-indexer'), $result->user_id, $result->project_count, $result->avg_refund_rate * 100) . "\n";

                // Log the detected abuser
                $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                    'user_id' => $result->user_id,
                    'project_id' => 0,
                    'action' => 'Abuse Detected',
                    'details' => json_encode(array(
                        'project_count' => $result->project_count,
                        'avg_refund_rate' => $result->avg_refund_rate * 100
                    )),
                    'created_at' => current_time('mysql')
                ));
            }

            wp_mail($admin_email, $subject, $message);
        }
    }

    public static function update_project_status() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $projects = $wpdb->get_results("SELECT * FROM $table_name WHERE status IN ('submitted', 'pending')");

        foreach ($projects as $project) {
            $api_key = get_option('speedyindex_api_key');
            $response = Rapid_URL_Indexer_API::get_task_status($api_key, $project->task_id);

            if ($response && isset($response['result']['status'])) {
                $status = $response['result']['status'];
                $indexed_links = isset($response['result']['indexed_count']) ? $response['result']['indexed_count'] : 0;

                if ($status === 'completed') {
                    $wpdb->update($table_name, array('status' => 'completed', 'indexed_links' => $indexed_links), array('id' => $project->id));
                } elseif ($status === 'failed' && !$project->auto_refund_processed) {
                    // Refund credits
                    $total_urls = count(json_decode($project->urls, true));
                    Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, $total_urls);

                    // Mark auto refund as processed and store refunded credits
                    $wpdb->update($table_name, array(
                        'status' => 'failed',
                        'auto_refund_processed' => 1,
                        'refunded_credits' => $total_urls
                    ), array('id' => $project->id));

                    // Log the action
                    $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                        'user_id' => $project->user_id,
                        'project_id' => $project->id,
                        'action' => 'Auto Refund',
                        'details' => json_encode(array('refunded_credits' => $total_urls)),
                        'created_at' => current_time('mysql')
                    ));
                }
            }

            // Check for pending projects older than 24 hours
            if ($project->status === 'pending' && strtotime($project->created_at) < strtotime('-24 hours')) {
                // Refund credits
                $total_urls = count(json_decode($project->urls, true));
                Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, $total_urls);

                // Update project status to failed
                $wpdb->update($table_name, array('status' => 'failed'), array('id' => $project->id));
            }
        }
    }


    public static function auto_refund() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $projects = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'submitted' AND DATE_ADD(created_at, INTERVAL 14 DAY) <= NOW() AND auto_refund_processed = 0");

        foreach ($projects as $project) {
            if (!$project->auto_refund_processed) {
                $api_key = get_option('speedyindex_api_key');
                $response = Rapid_URL_Indexer_API::get_task_status($api_key, $project->id);

                if ($response && isset($response['result']['indexed_count'])) {
                    $indexed_count = $response['result']['indexed_count'];
                    $total_urls = count(json_decode($project->urls, true));
                    $refund_credits = $total_urls - $indexed_count;

                    // Refund credits
                    Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, $refund_credits);

                    // Mark auto refund as processed and store refunded credits
                    $wpdb->update($table_name, array(
                        'auto_refund_processed' => 1, 
                        'refunded_credits' => $refund_credits
                    ), array('id' => $project->id));

                    // Log the action
                    $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                        'user_id' => $project->user_id,
                        'project_id' => $project->id,
                        'action' => 'Auto Refund',
                        'details' => json_encode($response),
                        'created_at' => current_time('mysql')
                    ));
                }
            }
        }
    }


    private static function load_dependencies() {
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-admin.php';
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-customer.php';
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-api.php';
    }

    private static function define_hooks() {
        add_action('rui_cron_job', array('Rapid_URL_Indexer', 'process_cron_jobs')); // Hourly cron job to update project status
        add_action('rui_process_api_request', array('Rapid_URL_Indexer', 'process_api_request'), 10, 3);
        add_action('rest_api_init', array('Rapid_URL_Indexer', 'register_rest_routes'));
        add_action('wp_ajax_rui_search_logs', array('Rapid_URL_Indexer_Admin', 'ajax_search_logs'));
        add_action('wp_ajax_nopriv_rui_search_logs', array('Rapid_URL_Indexer_Admin', 'ajax_search_logs'));
        
        // Add credits amount field to simple product
        add_action('woocommerce_product_options_general_product_data', array('Rapid_URL_Indexer', 'add_credits_field'));
        add_action('woocommerce_process_product_meta', array('Rapid_URL_Indexer', 'save_credits_field'));

        // Admin notice for SpeedyIndex API issues
        add_action('admin_notices', array('Rapid_URL_Indexer_API', 'display_admin_notices'));
    }
    
    public static function add_credits_field() {
        global $post;
        
        echo '<div class="options_group">';
        woocommerce_wp_text_input(array(
            'id' => '_credits_amount',
            'label' => __('Credits Amount', 'rapid-url-indexer'),
            'placeholder' => '',
            'desc_tip' => 'true',
            'description' => __('Enter the number of credits this product will add to the customer\'s account upon purchase.', 'rapid-url-indexer'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min' => '0'
            )
        ));
        echo '</div>';
    }
    
    public static function save_credits_field($post_id) {
        $credits_amount = isset($_POST['_credits_amount']) ? intval($_POST['_credits_amount']) : 0;
        update_post_meta($post_id, '_credits_amount', $credits_amount);
    }

    public static function register_rest_routes() {
        register_rest_route('rui/v1', '/projects', array(
            'methods' => 'POST',
            'callback' => array('Rapid_URL_Indexer', 'handle_project_submission'),
            'permission_callback' => array('Rapid_URL_Indexer', 'authenticate_api_request')
        ));

        register_rest_route('rui/v1', '/projects/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array('Rapid_URL_Indexer', 'get_project_status'),
            'permission_callback' => array('Rapid_URL_Indexer', 'authenticate_api_request')
        ));

        register_rest_route('rui/v1', '/projects/(?P<id>\d+)/report', array(
            'methods' => 'GET',
            'callback' => array('Rapid_URL_Indexer', 'download_project_report'),
            'permission_callback' => array('Rapid_URL_Indexer', 'authenticate_api_request')
        ));

        register_rest_route('rui/v1', '/credits/balance', array(
            'methods' => 'GET',
            'callback' => array('Rapid_URL_Indexer', 'get_credits_balance'),
            'permission_callback' => array('Rapid_URL_Indexer', 'authenticate_api_request')
        ));
    }

    public static function authenticate_api_request($request) {
        $api_key = $request->get_header('X-API-Key');
        if (!$api_key) {
            return new WP_Error('rest_forbidden', 'API key is missing', array('status' => 403));
        }

        $user = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $api_key, 'number' => 1));
        if (empty($user)) {
            return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 403));
        }

        return true;
    }

    public static function handle_project_submission($request) {
        $params = $request->get_params();
        $project_name = sanitize_text_field($params['project_name']);
        $urls = array_map('esc_url_raw', $params['urls']);
        $notify = isset($params['notify_on_status_change']) ? boolval($params['notify_on_status_change']) : false;

        // Validate and process the project submission
        $user_id = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $request->get_header('X-API-Key'), 'number' => 1, 'fields' => 'ID'))[0];
        $project_id = Rapid_URL_Indexer_Customer::submit_project($project_name, $urls, $notify, $user_id);

        return new WP_REST_Response(array('message' => 'Project created', 'project_id' => $project_id), 200);
    }

    public static function get_project_status($request) {
        $project_id = $request['id'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $project_id));

        if ($project) {
            return new WP_REST_Response(array(
                'project_id' => $project_id,
                'status' => $project->status,
                'submitted_links' => count(json_decode($project->urls)),
                'indexed_links' => $project->indexed_links
            ), 200);
        } else {
            return new WP_Error('no_project', 'Project not found', array('status' => 404));
        }
    }

    public static function download_project_report($request) {
        $project_id = $request['id'];

        // Generate and return the project report CSV
        $user_id = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $request->get_header('X-API-Key'), 'number' => 1, 'fields' => 'ID'))[0];
        $report_csv = Rapid_URL_Indexer_API::download_task_report($user_id, $project_id);

        return new WP_REST_Response($report_csv, 200, array('Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="project-report.csv"'));
    }

    public static function get_credits_balance($request) {
        $user_id = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $request->get_header('X-API-Key'), 'number' => 1, 'fields' => 'ID'))[0];
        $credits = Rapid_URL_Indexer_Customer::get_user_credits($user_id);

        return new WP_REST_Response(array('credits' => $credits), 200);
    }

    public static function process_cron_jobs() {
        // Update project status hourly
        self::update_project_status();
        
        // Process auto refunds
        self::auto_refund();
    }

    public static function process_api_request($project_id, $urls, $notify) {
        global $wpdb;

        // Get project details
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $project_id));
        
        if (!$project) {
            return array(
                'success' => false,
                'error' => __('Invalid project ID.', 'rapid-url-indexer')
            );
        }
        
        $user_id = $project->user_id;

        // Get API key
        $api_key = get_option('speedyindex_api_key');

        // Check if user has enough credits
        $credits = Rapid_URL_Indexer_Customer::get_user_credits($user_id);
        if ($credits < count($urls)) {
            return array(
                'success' => false,
                'error' => sprintf(__('Insufficient credits to submit project. <a href="%s">Buy more credits</a> to continue.', 'rapid-url-indexer'), esc_url(wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))))
            );
        }
    
        // Check if the project already has a task ID to prevent double submission
        if (empty($project->task_id)) {
            // Call API to create task
            $response = Rapid_URL_Indexer_API::create_task($api_key, $urls, $project->project_name . ' (CID' . $user_id . ')');
        
            // Handle response and update project status
            if ($response && isset($response['task_id'])) {
                $task_id = $response['task_id'];

                // Update project with task ID
                $wpdb->update($table_name, array('task_id' => $task_id), array('id' => $project_id));

                // Deduct credits and update project status
                Rapid_URL_Indexer_Customer::handle_api_success($project_id, $user_id, $urls);
                
                // Log the action
                $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                    'user_id' => $user_id,
                    'project_id' => $project_id,
                    'action' => 'API Request',
                    'details' => json_encode($response),
                    'created_at' => current_time('mysql')
                ));

                do_action('rui_log_entry_created');
        
                // Notify user if required and not rate limited
                if ($notify) {
                    $last_notification_time = get_post_meta($project_id, '_rui_last_notification_time', true);
                    $current_time = time();

                    if (!$last_notification_time || ($current_time - $last_notification_time) >= 86400) {
                        $user_info = get_userdata($user_id);
                        wp_mail(
                            $user_info->user_email,
                            __('Your URL Indexing Project Has Been Submitted', 'rapid-url-indexer'),
                            __('Your project has been submitted and is being processed.', 'rapid-url-indexer')
                        );
                        update_post_meta($project_id, '_rui_last_notification_time', $current_time);
                    }
                }

                return array(
                    'success' => true,
                    'error' => null
                );
            } else {
                // Log the error
                $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                    'user_id' => get_current_user_id(),
                    'project_id' => $project_id,
                    'action' => 'API Error',
                    'details' => json_encode($response),
                    'created_at' => current_time('mysql')
                ));

                return array(
                    'success' => false,
                    'error' => __('An error occurred while submitting the project.', 'rapid-url-indexer')
                );
            }
        } else {
            return array(
                'success' => false,
                'error' => __('This project has already been submitted.', 'rapid-url-indexer')
            );
        }
    }
}
