<?php
class Rapid_URL_Indexer_Customer {
    public static function init() {
        add_action('init', array(__CLASS__, 'add_my_account_endpoints'), 10);
        add_action('woocommerce_account_menu_items', array(__CLASS__, 'add_my_account_menu_items'), 10);
        add_shortcode('rui_credits_display', array(__CLASS__, 'credits_display'));
        add_shortcode('rui_project_submission', array(__CLASS__, 'project_submission'));
        add_shortcode('rui_api_key_display', array(__CLASS__, 'api_key_display'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_filter('the_title', array(__CLASS__, 'replace_my_account_title'), 10, 2);
        add_action('wp_ajax_rui_submit_project', array(__CLASS__, 'handle_ajax_project_submission'));
        add_action('wp_ajax_rui_get_project_stats', array(__CLASS__, 'handle_ajax_get_project_stats'));
        add_action('woocommerce_payment_complete', array(__CLASS__, 'handle_order_completed'));
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'handle_order_status_changed'), 10, 3);
        add_action('user_register', array(__CLASS__, 'generate_api_key'));

        // Add custom endpoints
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_my_account_menu_items'), 10);
        add_action('woocommerce_account_rui-projects_endpoint', array(__CLASS__, 'projects_endpoint_content'));
        add_action('woocommerce_account_rui-buy-credits_endpoint', array(__CLASS__, 'buy_credits_endpoint_content'));

        // Flush rewrite rules on plugin activation
        register_activation_hook(RUI_PLUGIN_DIR . 'rapid-url-indexer.php', array(__CLASS__, 'flush_rewrite_rules'));
        add_action('template_redirect', array(__CLASS__, 'handle_download_report'));
    }

    private static function send_out_of_credits_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $to = $user->user_email;
        $subject = __('Warning: You Are Out of Credits', 'rapid-url-indexer');
        $message = sprintf(
            __('Hey %s,

You are out of credits for URL indexing. To continue submitting URLs, please purchase more credits.

Click here to buy credits: %s

Thank you for using Rapid URL Indexer!', 'rapid-url-indexer'),
            $user->display_name,
            wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))
        );
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($to, $subject, $message, $headers);
    }

    public static function handle_download_report() {
        if (isset($_GET['download_report'])) {
            $project_id = intval($_GET['download_report']);
            $user_id = get_current_user_id();

            if (!$user_id) {
                wp_redirect(wp_login_url());
                exit;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
            $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $project_id, $user_id));

            if ($project) {
                $api_key = get_option('speedyindex_api_key');
                $report_csv = Rapid_URL_Indexer_API::download_task_report($api_key, $project->task_id);

                if ($report_csv) {
                    header('Content-Type: text/csv');
                    $filename = sanitize_file_name($project->project_name) . '-report.csv';
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    echo $report_csv;
                    exit;
                } else {
                    wp_die(__('Failed to generate report. Please try again later.', 'rapid-url-indexer'));
                }
            } else {
                wp_die(__('You do not have permission to download this report.', 'rapid-url-indexer'));
            }
        }
    }

    public static function replace_my_account_title($title, $id) {
        if (is_account_page()) {
            global $wp;
            if (isset($wp->query_vars['rui-projects'])) {
                return __('My Projects', 'rapid-url-indexer');
            } elseif (isset($wp->query_vars['rui-buy-credits'])) {
                return __('Buy Credits', 'rapid-url-indexer');
            }
        }
        return $title;
    }


    public static function add_my_account_endpoints() {
        add_rewrite_endpoint('rui-buy-credits', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('rui-projects', EP_ROOT | EP_PAGES);
    }


    public static function generate_api_key($user_id) {
        $api_key = wp_generate_password(32, false);
        update_user_meta($user_id, 'rui_api_key', $api_key);
    }

    public static function api_key_display() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $api_key = get_user_meta($user_id, 'rui_api_key', true);

        if (!$api_key) {
            $api_key = wp_generate_password(32, false);
            update_user_meta($user_id, 'rui_api_key', $api_key);
        }

        return '<div class="rui-api-key-display"><strong>Your API Key:</strong> <code>' . esc_html($api_key) . '</code></div>';
    }

    public static function handle_order_completed($order_id) {
        $order = wc_get_order($order_id);
        self::process_order_credits($order);
    }

    public static function handle_order_status_changed($order_id, $old_status, $new_status) {
        if ($new_status === 'completed') {
            $order = wc_get_order($order_id);
            self::process_order_credits($order);
        }
    }

    private static function process_order_credits($order) {
        $order_id = $order->get_id();

        // Check if the order has already been processed
        if ($order->get_meta('_rui_credits_processed')) {
            error_log('Order ' . $order_id . ' has already been processed for credits. Skipping.');
            return;
        }

        $credits_to_add = 0;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $credits = get_post_meta($product_id, '_credits_amount', true);
            if ($credits) {
                $quantity = $item->get_quantity();
                $credits_to_add += $credits * $quantity;
            }
        }

        if ($credits_to_add > 0) {
            $user_id = $order->get_user_id();
            try {
                error_log('Attempting to add ' . $credits_to_add . ' credits for user ' . $user_id . ' on order ' . $order_id);
                self::update_user_credits($user_id, $credits_to_add, 'purchase', $order_id);
                
                // Mark the order as processed for credits
                $order->update_meta_data('_rui_credits_processed', true);
                $order->save();
                error_log('Order ' . $order_id . ' marked as processed for credits');

                // Always complete the order as all products are virtual
                if ($order->get_status() === 'processing') {
                    $order->update_status('completed', 'Order completed automatically by Rapid URL Indexer.');
                }
            } catch (Exception $e) {
                error_log('Failed to add credits for order ' . $order_id . ': ' . $e->getMessage());
                wp_mail(
                    get_option('admin_email'),
                    'Credit Addition Failed',
                    'Failed to add ' . $credits_to_add . ' credits for user ' . $user_id . ' on order ' . $order_id . '. Error: ' . $e->getMessage()
                );
            }
        } else {
            error_log('No credits to add for order ' . $order_id);
        }
    }
    
    private static function validate_urls($urls_input) {
        $urls = array_filter(array_map('trim', explode("\n", $urls_input)));
        $valid_urls = [];
        $invalid_urls = [];

        foreach ($urls as $index => $url) {
            if (self::is_valid_url_lenient($url)) {
                $valid_urls[] = $url;
            } else {
                $invalid_urls[] = ['line' => $index + 1, 'url' => $url];
            }
        }

        return ['valid' => $valid_urls, 'invalid' => $invalid_urls];
    }

    private static function is_valid_url_lenient($url) {
        // Normalize the URL
        $url = trim($url);
        
        // If the URL doesn't start with a scheme, add http://
        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'http://' . $url;
        }
        
        // Parse the URL
        $parsed_url = parse_url($url);
        
        // Check if we have at least a host
        if (empty($parsed_url['host'])) {
            return false;
        }
        
        // Validate the host
        $host = $parsed_url['host'];
        
        // Allow IP addresses (both IPv4 and IPv6)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }
        
        // Validate domain name (including IDN and punycode)
        $domain_regex = '/^(xn--)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z0-9-]{2,}$/i';
        if (preg_match($domain_regex, idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46))) {
            return true;
        }
        
        // If we've reached here, the URL is not valid
        return false;
    }

    private static function sanitize_project_name($name) {
        // Remove any non-alphanumeric characters except spaces, hyphens, and underscores
        $name = preg_replace('/[^a-zA-Z0-9 \-_]/', '', $name);
        
        // Trim whitespace from start and end
        $name = trim($name);
        
        // Limit to 120 characters
        $name = substr($name, 0, 120);
        
        return $name;
    }

    private static function validate_project_name($name) {
        $errors = [];
        
        if (empty($name)) {
            $errors[] = __('Project name cannot be empty.', 'rapid-url-indexer');
        } elseif (strlen($name) > 120) {
            $errors[] = __('Project name must be 120 characters or less.', 'rapid-url-indexer');
        }
        
        return $errors;
    }

    private static function generate_fallback_project_name() {
        return 'Project-' . uniqid();
    }

    public static function handle_ajax_project_submission() {
        try {
            check_ajax_referer('rui_project_submission', 'security');
        
            $user_id = get_current_user_id();
            $credits = self::get_user_credits($user_id);

            $project_name = sanitize_text_field($_POST['project_name']);
            $original_project_name = $project_name;
            $sanitized_project_name = self::sanitize_project_name($project_name);

            $project_name_errors = self::validate_project_name($sanitized_project_name);
            $urls_input = sanitize_textarea_field($_POST['urls']);
            $url_validation_result = self::validate_urls($urls_input);
            $notify = isset($_POST['notify']) ? intval($_POST['notify']) : 0;

            $warnings = [];

            if ($sanitized_project_name !== $original_project_name) {
                $warnings['project_name'] = [__('Project name has been adjusted to meet requirements.', 'rapid-url-indexer')];
            }

            if (!empty($project_name_errors)) {
                $warnings['project_name'] = array_merge($warnings['project_name'] ?? [], $project_name_errors);
            }

            if (empty($url_validation_result['valid'])) {
                wp_send_json_error(['message' => __('No valid URLs provided. Please check your input and try again.', 'rapid-url-indexer')]);
                return;
            }

            if (!empty($url_validation_result['invalid'])) {
                $warnings['invalid_urls'] = [
                    'message' => __('Some URLs were invalid and were not submitted:', 'rapid-url-indexer'),
                    'urls' => $url_validation_result['invalid']
                ];
            }

            $urls = $url_validation_result['valid'];

            if ($credits <= 0 || $credits < count($urls)) {
                self::send_out_of_credits_email($user_id);
                self::log_submission_attempt($user_id, $project_name, count($urls), 'Insufficient credits');
                if ($credits <= 0) {
                    wp_send_json_error(array('message' => sprintf(__('You have no credits. <a href="%s">Buy more credits</a> to continue.', 'rapid-url-indexer'), esc_url(wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))))));
                } else {
                    wp_send_json_error(array('message' => sprintf(__('You do not have enough credits to submit %d URLs. <a href="%s">Buy more credits</a> to continue.', 'rapid-url-indexer'), count($urls), esc_url(wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))))));
                }
            } else {
                if (count($urls) > 0 && count($urls) <= 9999) {
                    $api_key = get_option('rui_speedyindex_api_key');
                    try {
                        $project_id = self::submit_project($project_name, $urls, $notify, $user_id);
                        if ($project_id) {
                            $api_response = Rapid_URL_Indexer::process_api_request($project_id, $urls, $notify, $user_id);
                            if ($api_response['success']) {
                                $user_email = wp_get_current_user()->user_email;
                                self::log_submission_attempt($user_id, $project_name, count($urls), 'Success');
                                $response = array(
                                    'message' => __('Project submitted successfully.', 'rapid-url-indexer'),
                                    'project_id' => $project_id,
                                    'user_email' => $user_email,
                                    'project_name' => $sanitized_project_name
                                );
                                if (!empty($warnings)) {
                                    $response['warnings'] = $warnings;
                                }
                                wp_send_json_success($response);
                            } else {
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
                                // Update project status to 'pending'
                                $wpdb->update($table_name, array('status' => 'pending'), array('id' => $project_id));
                                // Log the detailed error for admin
                                self::log_submission_attempt($user_id, $project_name, count($urls), 'API Error: ' . $api_response['error']);
                                // Send generic message to customer
                                wp_send_json_success(array(
                                    'message' => __('Project created but processing pending. It will be retried automatically.', 'rapid-url-indexer'),
                                    'project_id' => $project_id,
                                    'user_email' => wp_get_current_user()->user_email
                                ));
                            }
                        } else {
                            throw new Exception('Failed to create project');
                        }
                    } catch (Exception $e) {
                        self::log_submission_attempt($user_id, $project_name, count($urls), 'Exception: ' . $e->getMessage());
                        wp_send_json_error(array('message' => __('Failed to submit project. Please try again.', 'rapid-url-indexer')));
                    }
                } else {
                    self::log_submission_attempt($user_id, $project_name, count($urls), 'Invalid URL count');
                    wp_send_json_error(array('message' => __('Invalid number of URLs. Must be between 1 and 9999.', 'rapid-url-indexer')));
                }
            }
        } catch (Exception $e) {
            error_log('Rapid URL Indexer - Unhandled exception in project submission: ' . $e->getMessage());
            self::log_submission_attempt($user_id, $project_name, count($urls), 'Unhandled Exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An unexpected error occurred. Please try again.', 'rapid-url-indexer')));
        }
    }


    public static function customer_menu() {
        add_rewrite_rule('^my-account/projects/?', 'index.php?is_projects_page=1', 'top');
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'template_redirect'));
    }


    public static function query_vars($vars) {
        $vars[] = 'is_projects_page';
        return $vars;
    }

    public static function template_redirect() {
        if (get_query_var('is_projects_page')) {
            include plugin_dir_path(__FILE__) . '../templates/customer-projects.php';
            exit;
        }
    }


    public static function credits_display($show_button = true) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $credits = self::get_user_credits($user_id);

        $output = '<div class="rui-credits-display"><strong>Remaining Credits:</strong> ' . esc_html($credits) . '</div>';
        if ($show_button) {
            $output .= ' <a href="' . esc_url(wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))) . '" class="button wp-element-button">Buy Credits</a>';
        }
        return $output;
    }

    public static function project_submission() {
        ob_start();
        ?>
        <form id="rui-project-submission-form" method="post" action="">
            <label for="project_name">Project Name:</label>
            <input type="text" name="project_name" id="project_name" required>
            <label for="urls">URLs (one per line, max 9999):</label>
            <textarea name="urls" id="urls" rows="10" required></textarea>
            <div class="rui-checkbox-wrapper">
                <input type="checkbox" name="notify" id="notify" value="1">
                <label for="notify">Email Notifications</label>
            </div>
            <input type="hidden" name="security" value="<?php echo wp_create_nonce('rui_project_submission'); ?>">
            <button type="submit" class="button wp-element-button">Submit New Project ›</button>
        </form>
        <div id="rui-submission-response"></div>
        <?php

        return ob_get_clean();
    }

    public static function submit_project($project_name, $urls, $notify, $user_id) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $credits_needed = count($urls);
            $available_credits = self::get_user_credits($user_id);

            if ($available_credits < $credits_needed) {
                throw new Exception(__('Insufficient credits', 'rapid-url-indexer'));
            }

            // Sanitize project name
            $sanitized_project_name = self::sanitize_project_name($project_name);

            $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
            $result = $wpdb->insert($table_name, array(
                'user_id' => $user_id,
                'project_name' => $sanitized_project_name,
                'urls' => json_encode($urls),
                'status' => 'pending',
                'submitted_links' => count($urls),
                'notify' => $notify ? 1 : 0,
                'created_at' => current_time('mysql')
            ));

            if ($result === false) {
                throw new Exception(__('Failed to create project: ' . $wpdb->last_error, 'rapid-url-indexer'));
            }

            $project_id = $wpdb->insert_id;

            // Deduct credits here
            self::update_user_credits($user_id, -$credits_needed, 'system', 0, $project_id);

            $wpdb->query('COMMIT');
            return $project_id;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Project submission failed: ' . $e->getMessage());
            self::log_submission_attempt($user_id, $project_name, count($urls), 'Exception: ' . $e->getMessage());
            return false;
        }
    }

    public static function handle_api_success($project_id, $user_id, $urls) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';

        // Update project status
        $wpdb->update($table_name, array('status' => 'submitted'), array('id' => $project_id));

        // Credits have already been reserved, so we don't need to log credit usage here
        // The credit change was already logged when the project was created
    }

    public static function update_user_credits($user_id, $amount, $triggered_by = 'system', $order_id = 0, $project_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $credits = self::get_user_credits($user_id);
        $new_credits = $credits + $amount;

        if ($new_credits < 0) {
            throw new Exception(__('Insufficient credits', 'rapid-url-indexer'));
        }

        if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d", $user_id)) > 0) {
            $wpdb->update($table_name, array('credits' => $new_credits), array('user_id' => $user_id));
        } else {
            $wpdb->insert($table_name, array('user_id' => $user_id, 'credits' => $new_credits));
        }
        
        self::log_credit_change($user_id, $amount, $triggered_by, $order_id, $project_id);
    }

    private static function log_credit_change($user_id, $amount, $triggered_by, $order_id = 0, $project_id = 0) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        
        if ($triggered_by === 'purchase') {
            $triggered_by = 'Purchase (Order ID: ' . $order_id . ')';
        } elseif ($triggered_by === 'system') {
            $triggered_by = 'System';
        } elseif (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $triggered_by = $current_user->roles[0] === 'administrator' ? 'Admin' : 'User ID: ' . get_current_user_id();
        }

        $wpdb->insert($log_table, array(
            'triggered_by' => $triggered_by,
            'user_id' => $user_id,
            'project_id' => $project_id,
            'action' => 'Credit Change',
            'details' => json_encode(array('amount' => $amount, 'order_id' => $order_id)),
            'created_at' => current_time('mysql')
        ));
    }

    private static function schedule_api_request($project_id, $urls, $notify) {
        wp_schedule_single_event(time() + 60, 'rui_process_api_request', array($project_id, $urls, $notify));
    }

    public static function get_user_credits($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $credits = $wpdb->get_var($wpdb->prepare("SELECT credits FROM $table_name WHERE user_id = %d", $user_id));
        return $credits ? $credits : 0;
    }

    public static function enqueue_scripts() {
        wp_enqueue_style('rui-customer-css', RUI_PLUGIN_URL . 'assets/css/customer.css');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true);
        $customer_js_version = filemtime(RUI_PLUGIN_DIR . 'assets/js/customer.js');
        wp_enqueue_script('rui-customer-js', RUI_PLUGIN_URL . 'assets/js/customer.js', array('jquery', 'chart-js'), $customer_js_version, true);
        wp_localize_script('rui-customer-js', 'ajax_object', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('rui_ajax_nonce')
        ));
    }

    public static function add_my_account_menu_items($items) {
        $new_items = array();
        $new_items['rui-projects'] = __('My Projects', 'rapid-url-indexer');
        $new_items['rui-buy-credits'] = __('Buy Credits', 'rapid-url-indexer');
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
        }
        return $new_items;
    }

    public static function projects_endpoint_content() {
        include RUI_PLUGIN_DIR . 'templates/customer-projects.php';
    }

    public static function buy_credits_endpoint_content() {
        include RUI_PLUGIN_DIR . 'templates/customer-buy-credits.php';
    }

    public static function handle_ajax_get_project_stats() {
        check_ajax_referer('rui_ajax_nonce', 'security');
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();

        global $wpdb;
        $projects_table = $wpdb->prefix . 'rapid_url_indexer_projects';
        $stats_table = $wpdb->prefix . 'rapid_url_indexer_daily_stats';

        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id = %d AND user_id = %d", $project_id, $user_id));

        if ($project) {
            $created_at = new DateTime($project->created_at);
            $end_date = clone $created_at;
            $end_date->modify('+13 days');

            $stats = $wpdb->get_results($wpdb->prepare(
                "SELECT date, indexed_count, unindexed_count 
                FROM $stats_table 
                WHERE project_id = %d 
                AND date BETWEEN %s AND %s 
                ORDER BY date ASC",
                $project_id,
                $created_at->format('Y-m-d'),
                $end_date->format('Y-m-d')
            ));

            if (!empty($stats)) {
                wp_send_json_success(array('data' => $stats));
            } else {
                // Fallback to project data if no stats are available
                $total_urls = count(json_decode($project->urls));
                $indexed_urls = count(json_decode($project->indexed_urls));
                $unindexed_urls = $total_urls - $indexed_urls;

                $fallback_data = array(
                    array(
                        'date' => $created_at->format('Y-m-d'),
                        'indexed_count' => $indexed_urls,
                        'unindexed_count' => $unindexed_urls
                    )
                );
                wp_send_json_success(array('data' => $fallback_data, 'is_fallback' => true));
            }
        } else {
            wp_send_json_error('Project not found or access denied');
        }
    }

    private static function log_submission_attempt($user_id, $project_name, $url_count, $result) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        
        $log_data = array(
            'user_id' => $user_id,
            'project_id' => 0, // We don't have a project ID at this point
            'triggered_by' => 'User',
            'action' => 'Project Submission Attempt',
            'details' => json_encode(array(
                'project_name' => $project_name,
                'url_count' => $url_count,
                'result' => $result
            )),
            'created_at' => current_time('mysql')
        );
        
        $insert_result = $wpdb->insert($log_table, $log_data);
        
        if ($insert_result === false) {
            error_log('Failed to insert log entry: ' . $wpdb->last_error);
        }
        
        // Keep the existing debug logging as a fallback
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                "Project submission attempt - User ID: %d, Project Name: %s, URL Count: %d, Result: %s",
                $user_id,
                $project_name,
                $url_count,
                $result
            ));
        }
    }
}
