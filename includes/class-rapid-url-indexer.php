<?php
class Rapid_URL_Indexer {
    const API_MAX_RETRIES = 3; // Maximum number of retries for a failed API request

    public static function init() {
        add_action('init', array(__CLASS__, 'initialize_plugin'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
        
        // Add WooCommerce hooks for credits field
        add_action('woocommerce_product_options_general_product_data', array(__CLASS__, 'add_credits_field'));
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_credits_field'));

        // Add action for database update
        add_action('plugins_loaded', array(__CLASS__, 'update_database'));

        // Register activation hook
        register_activation_hook(RUI_PLUGIN_FILE, array(__CLASS__, 'activate'));
    }

    public static function update_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $column_name = 'project_name_hash';

        if($wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE '$column_name'") != $column_name) {
            $wpdb->query("ALTER TABLE $table_name ADD $column_name VARCHAR(32)");
            error_log("Added project_name_hash column to $table_name table");
        }
    }

    public static function activate() {
        self::update_database();
    }

    public static function initialize_plugin() {
        self::load_dependencies();
        self::define_hooks();
        self::initialize_cron_jobs();
    }

    private static function initialize_cron_jobs() {
        $cron_jobs = array(
            'rui_cron_job' => 'twicedaily',
            'rui_check_abuse' => 'daily',
            'rui_purge_logs' => 'daily',
            'rui_purge_projects' => 'daily',
            'rui_daily_stats_update' => 'daily'
        );

        foreach ($cron_jobs as $job => $recurrence) {
            if (!wp_next_scheduled($job)) {
                wp_schedule_event(time(), $recurrence, $job);
            }
        }

        // Add actions for cron jobs
        add_action('rui_cron_job', array(__CLASS__, 'process_cron_jobs'));
        add_action('rui_check_abuse', array(__CLASS__, 'check_abuse'));
        add_action('rui_purge_logs', array(__CLASS__, 'purge_logs'));
        add_action('rui_purge_projects', array(__CLASS__, 'purge_projects'));
        add_action('rui_daily_stats_update', array(__CLASS__, 'update_daily_stats'));
    }

    public static function purge_logs() {
        self::log_cron_execution('Purge Logs Started');

        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
        $log_age_limit = get_option('rui_log_age_limit', 90); // Default to 90 days

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < NOW() - INTERVAL %d DAY",
            $log_age_limit
        ));

        self::log_cron_execution('Purge Logs Completed');
    }

    public static function purge_projects() {
        self::log_cron_execution('Purge Projects Started');

        global $wpdb;
        $projects_table = $wpdb->prefix . 'rapid_url_indexer_projects';
        $stats_table = $wpdb->prefix . 'rapid_url_indexer_daily_stats';
        $project_age_limit = get_option('rui_project_age_limit', 30); // Default to 30 days

        $old_projects = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $projects_table WHERE created_at < NOW() - INTERVAL %d DAY",
            $project_age_limit
        ));

        if (!empty($old_projects)) {
            $wpdb->query("DELETE FROM $stats_table WHERE project_id IN (" . implode(',', $old_projects) . ")");
            $wpdb->query("DELETE FROM $projects_table WHERE id IN (" . implode(',', $old_projects) . ")");
        }

        self::log_cron_execution('Purge Projects Completed');
    }

    public static function update_daily_stats($project_id) {
        self::log_cron_execution('Update Daily Stats Started for Project ID: ' . $project_id);
        global $wpdb;
        $projects_table = $wpdb->prefix . 'rapid_url_indexer_projects';
        $stats_table = $wpdb->prefix . 'rapid_url_indexer_daily_stats';
        $date = current_time('Y-m-d');

        // Get the project data
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT task_id, submitted_links, status, updated_at FROM $projects_table WHERE id = %d",
            $project_id
        ));

        if ($project && $project->task_id && 
            (($project->status !== 'completed' && $project->status !== 'failed' && $project->status !== 'refunded') ||
            (strtotime($project->updated_at) > strtotime('-14 days')))) {
            // Fetch the latest data from SpeedyIndex API
            $api_key = get_option('rui_speedyindex_api_key');
            $response = Rapid_URL_Indexer_API::get_task_status($api_key, $project->task_id);

            if ($response && isset($response['processed_count']) && isset($response['indexed_count'])) {
                $processed_count = $response['processed_count'];
                $indexed_count = $response['indexed_count'];
                $unindexed_count = $processed_count - $indexed_count;

                self::log_cron_execution('API data retrieved for Project ID: ' . $project_id . '. Processed: ' . $processed_count . ', Indexed: ' . $indexed_count);

                // Update the project table
                $wpdb->update(
                    $projects_table,
                    array(
                        'processed_links' => $processed_count,
                        'indexed_links' => $indexed_count,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $project_id)
                );

                // Update the daily stats table
                $result = $wpdb->replace(
                    $stats_table,
                    array(
                        'project_id' => $project_id,
                        'date' => $date,
                        'indexed_count' => $indexed_count,
                        'unindexed_count' => $unindexed_count
                    ),
                    array('%d', '%s', '%d', '%d')
                );

                if ($result === false) {
                    self::log_cron_execution('Error updating daily stats for Project ID: ' . $project_id . '. MySQL Error: ' . $wpdb->last_error);
                } else {
                    self::log_cron_execution('Daily stats updated successfully for Project ID: ' . $project_id . '. Indexed: ' . $indexed_count . ', Unindexed: ' . $unindexed_count);
                }
            } else {
                self::log_cron_execution('Failed to retrieve API data for Project ID: ' . $project_id);
            }
        } else {
            self::log_cron_execution('Project not found or no task ID for Project ID: ' . $project_id);
        }
    }

    public static function get_project_stats($project_id) {
        self::log_cron_execution('Get Project Stats Started');

        global $wpdb;
        $stats_table = $wpdb->prefix . 'rapid_url_indexer_daily_stats';
        $projects_table = $wpdb->prefix . 'rapid_url_indexer_projects';
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(date) as date, indexed_count, unindexed_count 
            FROM $stats_table 
            WHERE project_id = %d 
            ORDER BY date ASC 
            LIMIT 14",
            $project_id
        ), ARRAY_A);

        if (empty($stats)) {
            // If no stats are available, get the current project data
            $project = $wpdb->get_row($wpdb->prepare(
                "SELECT indexed_links, submitted_links, DATE(created_at) as created_at 
                FROM $projects_table 
                WHERE id = %d",
                $project_id
            ));

            if ($project) {
                $stats[] = array(
                    'date' => $project->created_at,
                    'indexed_count' => $project->indexed_links,
                    'unindexed_count' => $project->submitted_links - $project->indexed_links
                );
            }
        }

        self::log_cron_execution('Get Project Stats Completed');
        return $stats;
    }


    public static function check_abuse() {
        self::log_cron_execution('Check Abuse Started');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';

        $min_urls = intval(get_option('rui_min_urls_for_abuse', 1000));
        $avg_refund_rate = floatval(get_option('rui_avg_refund_rate_for_abuse', 0.7));

        // Get users with more than the minimum number of URLs where the average refund rate is above the threshold
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                user_id, 
                SUM(submitted_links) as total_urls,
                SUM(indexed_links) as total_indexed,
                SUM(submitted_links - indexed_links) as total_unindexed,
                SUM(submitted_links - indexed_links) / SUM(submitted_links) as refund_rate
            FROM $table_name
            WHERE status IN ('completed', 'refunded')
            GROUP BY user_id
            HAVING total_urls >= %d AND refund_rate >= %f
        ", $min_urls, $avg_refund_rate));

        // Log the values being used for debugging
        error_log("Abuse check parameters - Min URLs: $min_urls, Avg Refund Rate: $avg_refund_rate");

        if ($results) {
            $admin_email = get_option('admin_email');
            $subject = __('Potential Abuse Detected', 'rapid-url-indexer');
            $message = __('The following users have submitted more than 1000 URLs with a high percentage of unindexed URLs:', 'rapid-url-indexer') . "\n\n";

            foreach ($results as $result) {
                $message .= sprintf(__('User ID: %d, Total URLs: %d, Indexed: %d, Unindexed: %d, Refund Rate: %.2f%%', 'rapid-url-indexer'), 
                    $result->user_id, 
                    $result->total_urls, 
                    $result->total_indexed, 
                    $result->total_unindexed, 
                    $result->refund_rate * 100
                ) . "\n";

                // Log the detected abuser
                $triggered_by = 'system';
                $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                    'triggered_by' => $triggered_by,
                    'user_id' => $result->user_id,
                    'project_id' => 0,
                    'action' => 'Abuse Detected',
                    'details' => json_encode(array(
                        'total_urls' => $result->total_urls,
                        'total_indexed' => $result->total_indexed,
                        'total_unindexed' => $result->total_unindexed,
                        'refund_rate' => $result->refund_rate * 100
                    )),
                    'created_at' => current_time('mysql')
                ));
            }

            wp_mail($admin_email, $subject, $message);

            // Log the email notification
            $triggered_by = 'system';
            if (is_user_logged_in()) {
                $triggered_by .= ' (User ID: ' . get_current_user_id() . ')';
            }
            $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                'triggered_by' => $triggered_by,
                'user_id' => 0,
                'project_id' => 0,
                'action' => 'Admin Notification',
                'details' => json_encode(array(
                    'subject' => $subject,
                    'message' => $message
                )),
                'created_at' => current_time('mysql')
            ));
        }

        self::log_cron_execution('Check Abuse Completed');
    }

    public static function update_project_status() {
        self::log_cron_execution('Update Project Status Started');

        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $projects = $wpdb->get_results("SELECT *, notify = 1 as notify FROM $table_name WHERE status IN ('submitted', 'pending', 'completed') AND task_id IS NOT NULL");

        foreach ($projects as $project) {
            $api_key = get_option('rui_speedyindex_api_key');
            $response = Rapid_URL_Indexer_API::get_task_status($api_key, $project->task_id);
            
            if (!$response) {
                self::log_cron_execution("Failed to get API response for project {$project->id}");
                continue;
            }

            self::log_cron_execution("API response for project {$project->id}: " . json_encode($response));

            if (isset($response['processed_count'])) {
                $indexed_links = isset($response['indexed_count']) ? $response['indexed_count'] : 0;
                $processed_links = $response['processed_count'];
                $submitted_links = isset($response['submitted_count']) ? $response['submitted_count'] : $project->submitted_links;
                $last_updated = current_time('mysql');

                $created_at = strtotime($project->created_at);
                $current_time = time();
                $days_since_creation = ($current_time - $created_at) / (60 * 60 * 24);

                $old_status = $project->status;
                $new_status = $old_status;

                if ($old_status === 'submitted' && $days_since_creation >= 13) {
                    $new_status = 'completed';
                    self::log_cron_execution("Project {$project->id} marked as completed after 13 days");
                    self::send_status_change_email($project, $new_status, $processed_links, $indexed_links);
                }

                $update_data = array(
                    'status' => $new_status,
                    'submitted_links' => $submitted_links,
                    'processed_links' => $processed_links,
                    'indexed_links' => $indexed_links,
                    'updated_at' => $last_updated
                );

                $update_result = $wpdb->update($table_name, $update_data, array('id' => $project->id));

                if ($update_result === false) {
                    self::log_cron_execution("Failed to update project {$project->id}. MySQL Error: " . $wpdb->last_error);
                } else {
                    self::log_cron_execution("Updated project {$project->id}. Status: $new_status, Processed: $processed_links, Indexed: $indexed_links");
                }

                self::update_daily_stats($project->id);

                // Log status changes
                if ($new_status !== $old_status) {
                    $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                        'user_id' => $project->user_id,
                        'project_id' => $project->id,
                        'action' => 'Project Status Update',
                        'details' => json_encode(array(
                            'old_status' => $old_status,
                            'new_status' => $new_status,
                            'processed_links' => $processed_links,
                            'indexed_links' => $indexed_links
                        )),
                        'created_at' => current_time('mysql')
                    ));
                }

                // Send initial report email after 96 hours, regardless of status changes
                $hours_since_creation = ($current_time - $created_at) / (60 * 60);
                if ($hours_since_creation >= 96 && $hours_since_creation < 97 && !$project->initial_report_sent) {
                    self::send_status_change_email($project, 'initial_report', $processed_links, $indexed_links);
                    $wpdb->update($table_name, array('initial_report_sent' => 1), array('id' => $project->id));
                    self::log_cron_execution("Sent initial report email for project {$project->id}");
                }
            } else {
                self::log_cron_execution("Invalid API response for project {$project->id}: " . json_encode($response));
            }
        }

        // Check for pending projects older than 24 hours
        $old_pending_projects = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        foreach ($old_pending_projects as $project) {
            $total_urls = count(json_decode($project->urls, true));
            Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, $total_urls);
            $wpdb->update($table_name, array('status' => 'failed'), array('id' => $project->id));
            self::log_cron_execution("Marked project {$project->id} as failed due to being pending for over 24 hours");
            self::send_status_change_email($project, 'failed', 0, 0);
        }

        self::log_cron_execution('Update Project Status Completed');
    }


    public static function auto_refund() {
        self::log_cron_execution('Auto Refund Started');

        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $projects = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'completed' AND DATE_ADD(created_at, INTERVAL 14 DAY) <= NOW() AND auto_refund_processed = 0");

        foreach ($projects as $project) {
            $api_key = get_option('speedyindex_api_key');
            $response = Rapid_URL_Indexer_API::get_task_status($api_key, $project->task_id);

            if ($response && isset($response['indexed_count']) && isset($response['submitted_count'])) {
                $indexed_count = $response['indexed_count'];
                $submitted_count = $response['submitted_count'];
                $processed_count = $response['processed_count'] ?? 0;

                // Use submitted_count, fallback to processed_count if submitted_count is 0
                $total_count = $submitted_count > 0 ? $submitted_count : $processed_count;
                $refund_credits = $total_count - $indexed_count;

                if ($refund_credits > 0) {
                    // Refund credits
                    Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, $refund_credits);

                    // Update project status and details
                    $wpdb->update($table_name, array(
                        'status' => 'refunded',
                        'auto_refund_processed' => 1,
                        'refunded_credits' => $refund_credits,
                        'submitted_links' => $submitted_count,
                        'processed_links' => $processed_count,
                        'indexed_links' => $indexed_count,
                        'updated_at' => current_time('mysql')
                    ), array('id' => $project->id));

                    self::log_cron_execution("Project {$project->id} auto-refunded. Refunded credits: $refund_credits");

                    // Log the action
                    $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                        'user_id' => $project->user_id,
                        'project_id' => $project->id,
                        'action' => 'Auto Refund',
                        'details' => json_encode(array(
                            'submitted_count' => $submitted_count,
                            'processed_count' => $processed_count,
                            'indexed_count' => $indexed_count,
                            'refunded_credits' => $refund_credits
                        )),
                        'created_at' => current_time('mysql')
                    ));

                    // Send refund notification
                    self::send_status_change_email($project, 'refunded', $processed_count, $indexed_count);
                } else {
                    // If all submitted URLs were indexed, just mark as processed
                    $wpdb->update($table_name, array(
                        'auto_refund_processed' => 1,
                        'submitted_links' => $submitted_count,
                        'processed_links' => $processed_count,
                        'indexed_links' => $indexed_count,
                        'updated_at' => current_time('mysql')
                    ), array('id' => $project->id));

                    self::log_cron_execution("Project {$project->id} processed without refund. All URLs indexed.");
                }
            } else {
                self::log_cron_execution("Failed to get API response for project {$project->id} during auto-refund process");
            }
        }

        self::log_cron_execution('Auto Refund Completed');
    }

    public static function generate_fallback_project_name($urls) {
        $urls_string = implode('', $urls);
        $hash = md5($urls_string);
        return "noname_" . $hash;
    }

    private static function load_dependencies() {
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-admin.php';
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-customer.php';
        require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-api.php';
    }

    private static function define_hooks() {
        add_action('rui_daily_stats_update', array('Rapid_URL_Indexer_Cron', 'update_daily_stats'));

        add_action('rui_process_api_request', array(__CLASS__, 'process_api_request'), 10, 3);
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
        add_action('wp_ajax_rui_search_logs', array('Rapid_URL_Indexer_Admin', 'ajax_search_logs'));
        add_action('wp_ajax_nopriv_rui_search_logs', array('Rapid_URL_Indexer_Admin', 'ajax_search_logs'));

        // Admin notice for SpeedyIndex API issues
    }
    
    public static function add_credits_field() {
        global $product_object;

        if ('simple' === $product_object->get_type()) {
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
        }
    }
    
    public static function save_credits_field($post_id) {
        $credits_amount = isset($_POST['_credits_amount']) ? intval($_POST['_credits_amount']) : 0;
        update_post_meta($post_id, '_credits_amount', $credits_amount);
    }

    public static function register_rest_routes() {
        register_rest_route('api/v1', '/projects', array(
            'methods' => 'POST',
            'callback' => array('Rapid_URL_Indexer', 'handle_project_submission'),
            'permission_callback' => array('Rapid_URL_Indexer', 'authenticate_api_request')
        ));

        register_rest_route('api/v1', '/projects/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array('Rapid_URL_Indexer', 'get_project_status'),
            'permission_callback' => array('Rapid_URL_Indexer', 'authenticate_api_request')
        ));

        register_rest_route('api/v1', '/projects/(?P<id>\d+)/report', array(
            'methods' => 'GET',
            'callback' => array('Rapid_URL_Indexer', 'download_project_report'),
            'permission_callback' => array('Rapid_URL_Indexer', 'authenticate_api_request')
        ));

        register_rest_route('api/v1', '/credits/balance', array(
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
        try {
            $params = $request->get_params();
            $project_name = isset($params['project_name']) ? sanitize_text_field($params['project_name']) : '';
            $urls_input = isset($params['urls']) ? $params['urls'] : '';
            $notify = isset($params['notify_on_status_change']) ? filter_var($params['notify_on_status_change'], FILTER_VALIDATE_BOOLEAN) : false;

            // Validate and process the project submission
            $api_key = $request->get_header('X-API-Key');
            $user = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $api_key, 'number' => 1));
            if (empty($user)) {
                return new WP_REST_Response(array('message' => 'Invalid API key'), 403);
            }
            $user_id = $user[0]->ID;

            // Process URLs
            $urls = is_array($urls_input) ? $urls_input : explode("\n", $urls_input);
            $valid_urls = array();
            $invalid_urls = array();
            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    if (Rapid_URL_Indexer_Customer::is_valid_url_lenient($url)) {
                        $valid_urls[] = $url;
                    } else {
                        $invalid_urls[] = $url;
                    }
                }
            }

            // Check if user has enough credits
            $credits = Rapid_URL_Indexer_Customer::get_user_credits($user_id);
            if ($credits < count($valid_urls)) {
                self::send_out_of_credits_email($user[0]);
                return new WP_REST_Response(array('message' => 'Insufficient credits'), 400);
            }

            if (empty($valid_urls)) {
                $error_message = 'No valid URLs provided. ';
                if (!empty($invalid_urls)) {
                    $error_message .= 'Invalid URLs: ' . implode(', ', array_slice($invalid_urls, 0, 5));
                    if (count($invalid_urls) > 5) {
                        $error_message .= ' and ' . (count($invalid_urls) - 5) . ' more.';
                    }
                }
                return new WP_REST_Response(array('message' => $error_message), 400);
            }

            if (count($valid_urls) > 9999) {
                return new WP_REST_Response(array('message' => 'Invalid number of URLs. Must be between 1 and 9999.'), 400);
            }

            $urls = $valid_urls;

            // Use fallback project name if not provided
            if (empty($project_name)) {
                $project_name = self::generate_fallback_project_name($urls);
            }

            // Create project
            $project_id = Rapid_URL_Indexer_Customer::submit_project($project_name, $urls, $notify, $user_id);

            if ($project_id) {
                // Submit to SpeedyIndex API
                $api_key = get_option('speedyindex_api_key');
                $response = Rapid_URL_Indexer_API::create_task($api_key, $urls, $project_name . ' (CID' . $user_id . ')');

                global $wpdb;
                $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
                
                if ($response && isset($response['task_id'])) {
                    $wpdb->update($table_name, array(
                        'task_id' => $response['task_id'],
                        'status' => 'submitted',
                        'submitted_links' => count($urls)
                    ), array('id' => $project_id));

                    // Deduct credits
                    Rapid_URL_Indexer_Customer::update_user_credits($user_id, -count($urls), 'system', $project_id);

                    return new WP_REST_Response(array('message' => 'Project created and submitted', 'project_id' => $project_id), 200);
                } else {
                    // Log the API submission failure
                    error_log("Failed to submit project $project_id to SpeedyIndex API");
                    
                    // Update project status to 'pending' instead of 'submitted'
                    $wpdb->update($table_name, array(
                        'status' => 'pending',
                        'submitted_links' => count($urls)
                    ), array('id' => $project_id));

                    // Deduct credits (we still create the project, so we deduct credits)
                    Rapid_URL_Indexer_Customer::update_user_credits($user_id, -count($urls), 'system', $project_id);

                    // Return a 200 status because the project was created in our database
                    return new WP_REST_Response(array('message' => 'Project created but processing pending. It will be retried automatically.', 'project_id' => $project_id), 200);
                }
            } else {
                return new WP_REST_Response(array('message' => 'Project creation failed'), 500);
            }
        } catch (Exception $e) {
            return self::handle_error($e, $user_id);
        }
    }
    
    public static function get_project_status($request) {
        $project_id = intval($request['id']);
        $api_key = $request->get_header('X-API-Key');

        if (!$api_key) {
            return new WP_Error('rest_forbidden', 'API key is missing', array('status' => 403));
        }

        $user = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $api_key, 'number' => 1));
        if (empty($user)) {
            return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 403));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $project_id, $user[0]->ID));

        if ($project) {
            return new WP_REST_Response(array(
                'project_id' => $project_id,
                'project_name' => $project->project_name,
                'status' => $project->status,
                'submitted_links' => count(json_decode($project->urls)),
                'processed_links' => $project->processed_links,
                'indexed_links' => $project->indexed_links,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at
            ), 200);
        } else {
            return new WP_Error('no_project', 'Project not found or access denied', array('status' => 404));
        }
    }

    public static function download_project_report($request) {
        $project_id = intval($request['id']);
        $api_key = $request->get_header('X-API-Key');

        if (!$api_key) {
            return new WP_Error('rest_forbidden', 'API key is missing', array('status' => 403));
        }

        $user = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $api_key, 'number' => 1));
        if (empty($user)) {
            return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 403));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $project_id, $user[0]->ID));

        if (!$project) {
            return new WP_Error('no_project', 'Project not found or access denied', array('status' => 404));
        }

        if ($project->status !== 'completed' && $project->status !== 'refunded') {
            return new WP_Error('report_not_available', 'Project report is not available yet', array('status' => 400));
        }

        $report_csv = Rapid_URL_Indexer_API::download_task_report(get_option('rui_speedyindex_api_key'), $project->task_id);

        if (!$report_csv) {
            return new WP_Error('report_generation_failed', 'Failed to generate report', array('status' => 500));
        }

        return new WP_REST_Response($report_csv, 200, array('Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="project-report.csv"'));
    }

    public static function get_credits_balance($request) {
        $api_key = $request->get_header('X-API-Key');
        if (!$api_key) {
            return new WP_Error('rest_forbidden', 'API key is missing', array('status' => 403));
        }

        $user = get_users(array('meta_key' => 'rui_api_key', 'meta_value' => $api_key, 'number' => 1));
        if (empty($user)) {
            return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 403));
        }

        $user_id = $user[0]->ID;
        $credits = Rapid_URL_Indexer_Customer::get_user_credits($user_id);

        return new WP_REST_Response(array('credits' => $credits), 200);
    }

    public static function process_cron_jobs() {
        self::log_cron_execution('Twice Daily Cron Job Started');

        try {
            // Update project status
            self::log_cron_execution('Starting update_project_status');
            self::update_project_status();
            self::log_cron_execution('Finished update_project_status');
            
            // Process auto refunds
            self::log_cron_execution('Starting auto_refund');
            self::auto_refund();
            self::log_cron_execution('Finished auto_refund');

            // Retry failed submissions
            self::log_cron_execution('Starting retry_failed_submissions');
            self::retry_failed_submissions();
            self::log_cron_execution('Finished retry_failed_submissions');

            self::log_cron_execution('Twice Daily Cron Job Completed Successfully');
        } catch (Exception $e) {
            self::log_cron_execution('Twice Daily Cron Job Failed: ' . $e->getMessage());
            error_log('Rapid URL Indexer Twice Daily Cron Job Failed: ' . $e->getMessage());
        }
    }

    private static function log_cron_execution($action) {
        global $wpdb;
        $result = $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
            'user_id' => 0,
            'project_id' => 0,
            'triggered_by' => 'Cron',
            'action' => $action,
            'details' => '',
            'created_at' => current_time('mysql')
        ));

        if ($result === false) {
            error_log('Error logging cron execution: ' . $wpdb->last_error);
        } else {
            error_log('Cron execution logged: ' . $action);
        }
    }

    private static function log_action($project_id, $action, $details) {
        global $wpdb;
        $result = $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
            'user_id' => 0,
            'project_id' => $project_id,
            'triggered_by' => 'Cron',
            'action' => $action,
            'details' => $details,
            'created_at' => current_time('mysql')
        ));

        if ($result === false) {
            error_log('Error logging action: ' . $wpdb->last_error);
        }
    }


    private static function retry_failed_submissions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $projects = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending' AND task_id IS NULL AND created_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        foreach ($projects as $project) {
            $api_key = get_option('rui_speedyindex_api_key');
            $urls = json_decode($project->urls, true);
            
            // Check if user has enough credits
            $user_credits = Rapid_URL_Indexer_Customer::get_user_credits($project->user_id);
            if ($user_credits < count($urls)) {
                $wpdb->update($table_name, array(
                    'status' => 'failed',
                    'updated_at' => current_time('mysql')
                ), array('id' => $project->id));
                self::log_action($project->id, 'Submission Failed', 'Insufficient credits');
                continue;
            }

            $response = Rapid_URL_Indexer_API::create_task($api_key, $urls, $project->project_name, $project->user_id);

            if ($response && isset($response['task_id'])) {
                $wpdb->update($table_name, array(
                    'task_id' => $response['task_id'],
                    'status' => 'submitted',
                    'updated_at' => current_time('mysql')
                ), array('id' => $project->id));

                // Deduct credits
                Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, -count($urls), 'system', $project->id);

                // Log the successful submission
                self::log_action($project->id, 'Retry Submission Successful', json_encode($response));
            } else {
                // Log the retry attempt
                self::log_action($project->id, 'Retry Submission Failed', json_encode($response));

                // If still failing after 24 hours, mark as failed
                if (strtotime($project->created_at) <= strtotime('-24 hours')) {
                    $wpdb->update($table_name, array(
                        'status' => 'failed',
                        'updated_at' => current_time('mysql')
                    ), array('id' => $project->id));

                    // Log the final failure
                    self::log_action($project->id, 'Submission Failed', 'Failed after 24 hours of retries');

                    // Refund credits to the user
                    Rapid_URL_Indexer_Customer::update_user_credits($project->user_id, count($urls), 'system', $project->id);
                    self::log_action($project->id, 'Credits Refunded', 'Refunded ' . count($urls) . ' credits due to submission failure');
                }
            }
        }
    }


    public static function process_api_request($project_id, $urls, $notify, $user_id) {
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

        // Get API key
        $api_key = get_option('rui_speedyindex_api_key');

        if (empty($api_key)) {
            error_log('Rapid URL Indexer: SpeedyIndex API key is not set.');
            return array(
                'success' => false,
                'error' => __('API key is not set. Please contact the administrator.', 'rapid-url-indexer')
            );
        }

        // Check if the project already has a task ID to prevent double submission
        if (empty($project->task_id)) {
            // Check if user has enough credits
            $credits_needed = count($urls);
            $available_credits = Rapid_URL_Indexer_Customer::get_user_credits($user_id);

            if ($available_credits < $credits_needed) {
                return array(
                    'success' => false,
                    'error' => __('Insufficient credits to submit the project.', 'rapid-url-indexer')
                );
            }

            // Call API to create task
            $response = Rapid_URL_Indexer_API::create_task($api_key, $urls, $project->project_name . ' (CID' . $user_id . ')');
        
            // Handle response and update project status
            if ($response && isset($response['task_id'])) {
                $task_id = $response['task_id'];

                // Update project with task ID and status
                $wpdb->update($table_name, array(
                    'task_id' => $task_id,
                    'status' => 'submitted',
                    'updated_at' => current_time('mysql')
                ), array('id' => $project_id));

                // Log the action
                $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                    'user_id' => $user_id,
                    'project_id' => $project_id,
                    'action' => 'API Request',
                    'details' => json_encode($response),
                    'created_at' => current_time('mysql')
                ));

                do_action('rui_log_entry_created');
        
                // Notify user if required
                if ($notify) {
                    $user_info = get_userdata($user_id);
                    $subject = sprintf(__('Project "%s" Has Been Submitted', 'rapid-url-indexer'), $project->project_name);
                    $message = sprintf(__('Your Rapid URL Indexer project "%s" has been submitted and is being processed.', 'rapid-url-indexer'), $project->project_name);
                    $message .= "\n\n";
                    $message .= sprintf(__('Number of submitted URLs: %d', 'rapid-url-indexer'), count($urls));
                    wp_mail($user_info->user_email, $subject, $message);

                    // Log the email notification
                    $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                        'user_id' => $user_id,
                        'project_id' => $project_id,
                        'action' => 'User Notification',
                        'details' => json_encode(array(
                            'subject' => $subject,
                            'message' => $message
                        )),
                        'created_at' => current_time('mysql')
                    ));
                }

                return array(
                    'success' => true,
                    'error' => null
                );
            } else {
                // Log the error
                $error_details = is_wp_error($response) ? $response->get_error_message() : json_encode($response);
                error_log('Rapid URL Indexer API Error: ' . $error_details);
                
                $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                    'user_id' => $user_id,
                    'project_id' => $project_id,
                    'action' => 'API Error',
                    'details' => $error_details,
                    'created_at' => current_time('mysql')
                ));

                return array(
                    'success' => false,
                    'error' => __('An error occurred while submitting the project. It will be retried automatically.', 'rapid-url-indexer')
                );
            }
        } else {
            return array(
                'success' => true,
                'error' => null
            );
        }
    }

    private static function send_status_change_email($project, $status, $processed_links, $indexed_links) {
        // Log the notification attempt
        self::log_action($project->id, 'Email Notification Attempt', json_encode(array(
            'status' => $status,
            'notify_flag' => $project->notify
        )));

        // Only send email if notifications are enabled for this project
        if ($project->notify) {
            $user_info = get_userdata($project->user_id);
            $subject = sprintf(__('Project "%s" Status Update', 'rapid-url-indexer'), $project->project_name);
            $first_name = trim(explode(' ', $user_info->display_name)[0]);
            $message = sprintf(__('Hey %s,', 'rapid-url-indexer'), $first_name) . "\n\n";
            $message .= sprintf(__('Your Rapid URL Indexer project "%s" has been updated:', 'rapid-url-indexer'), $project->project_name) . "\n\n";

            $submission_time = strtotime($project->created_at);
            $current_time = time();
            $hours_since_submission = ($current_time - $submission_time) / 3600;

            $message .= sprintf(__('Status: %s', 'rapid-url-indexer'), ucfirst($status)) . "\n";
            $message .= sprintf(__('Total Submitted URLs: %d', 'rapid-url-indexer'), count(json_decode($project->urls, true))) . "\n";

            if ($hours_since_submission >= 96 || $status === 'completed' || $status === 'initial_report') {
                $message .= sprintf(__('Processed URLs: %d', 'rapid-url-indexer'), $processed_links) . "\n";
                $message .= sprintf(__('Indexed URLs: %d', 'rapid-url-indexer'), $indexed_links) . "\n";
                $message .= sprintf(__('Indexing Rate: %.2f%%', 'rapid-url-indexer'), ($processed_links > 0 ? ($indexed_links / $processed_links) * 100 : 0)) . "\n";
            }

            $report_link = add_query_arg(array('download_report' => $project->id), home_url());

            if ($status === 'completed') {
                $message .= "\n" . sprintf(__('Your final project report is now available. Download it here: %s', 'rapid-url-indexer'), $report_link) . "\n";
            } elseif ($status === 'initial_report') {
                $message .= "\n" . sprintf(__('Your initial project report is now available. Please note that this is not the final report and more URLs will be indexed in the coming days. Download the current report here: %s', 'rapid-url-indexer'), $report_link) . "\n";
            } elseif ($status === 'failed') {
                $message .= "\n" . __('Unfortunately, your project has failed. All credits used for this project have been refunded to your account.', 'rapid-url-indexer') . "\n";
            } elseif ($status === 'refunded') {
                $refunded_credits = intval($project->refunded_credits);
                $message .= "\n" . sprintf(__('%d credit(s) have been automatically refunded to your account for URLs that were not indexed within 14 days.', 'rapid-url-indexer'), $refunded_credits) . "\n";
            }

            $message .= "\n" . __('Thank you for using Rapid URL Indexer!', 'rapid-url-indexer') . "\n";

            $sent = wp_mail($user_info->user_email, $subject, $message);
            if (!$sent) {
                error_log('Failed to send email notification for project ' . $project->id . ' with status ' . $status);
            }
            
            // Log the email sending attempt
            self::log_action($project->id, 'Email Notification', json_encode(array(
                'status' => $status,
                'recipient' => $user_info->user_email,
                'sent' => $sent
            )));
        } else {
            // Log that email was not sent due to notifications being disabled
            self::log_action($project->id, 'Email Notification Skipped', json_encode(array(
                'status' => $status,
                'reason' => 'Notifications disabled for this project'
            )));
        }
    }

    private static function handle_error($error, $user_id) {
        $error_message = 'Rapid URL Indexer Error: ' . $error->getMessage();
        error_log($error_message);
        
        $user = get_user_by('id', $user_id);
        if ($user && user_can($user, 'manage_options')) {
            error_log('Error details for admin (User ID: ' . $user_id . '): ' . $error->getTraceAsString());
            return new WP_REST_Response(array(
                'message' => 'An error occurred. Please check the error logs for more details.',
                'error' => $error->getMessage(),
                'trace' => $error->getTraceAsString()
            ), 500);
        } else {
            error_log('Error occurred for non-admin user (User ID: ' . $user_id . ')');
            return new WP_REST_Response(array(
                'message' => 'An error occurred. Our team has been notified and will investigate the issue.'
            ), 500);
        }
    }
}
