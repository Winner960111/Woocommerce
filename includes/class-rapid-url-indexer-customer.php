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
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'handle_order_completed'));
        add_action('user_register', array(__CLASS__, 'generate_api_key'));

        // Add custom endpoints
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_my_account_menu_items'), 10);
        add_action('woocommerce_account_rui-projects_endpoint', array(__CLASS__, 'projects_endpoint_content'));
        add_action('woocommerce_account_rui-buy-credits_endpoint', array(__CLASS__, 'buy_credits_endpoint_content'));

        // Flush rewrite rules on plugin activation
        register_activation_hook(RUI_PLUGIN_DIR . 'rapid-url-indexer.php', array(__CLASS__, 'flush_rewrite_rules'));
        add_action('template_redirect', array(__CLASS__, 'handle_download_report'));
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

    public static function flush_rewrite_rules() {
        self::add_my_account_endpoints();
        flush_rewrite_rules();
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
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $credits = get_post_meta($product_id, '_credits_amount', true);
            if ($credits) {
                $user_id = $order->get_user_id();
                $quantity = $item->get_quantity();
                self::update_user_credits($user_id, $credits * $quantity);
            }
        }
    }
    
    public static function handle_ajax_project_submission() {
        check_ajax_referer('rui_project_submission', 'security');
    
        $user_id = get_current_user_id();
        $credits = self::get_user_credits($user_id);

        $project_name = sanitize_text_field($_POST['project_name']);
        $urls = array_filter(array_map(function($url) {
            $url = trim($url);
            return filter_var($url, FILTER_SANITIZE_URL);
        }, explode("\n", sanitize_textarea_field($_POST['urls']))), function($url) {
            return !empty($url) && filter_var($url, FILTER_VALIDATE_URL);
        });
        $notify = isset($_POST['notify']) ? intval($_POST['notify']) : 0;

        if ($credits <= 0) {
            wp_send_json_error(array('message' => sprintf(__('You have no credits. <a href="%s">Buy more credits</a> to continue.', 'rapid-url-indexer'), esc_url(wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))))));
        } elseif ($credits < count($urls)) {
            wp_send_json_error(array('message' => sprintf(__('You do not have enough credits to submit %d URLs. <a href="%s">Buy more credits</a> to continue.', 'rapid-url-indexer'), count($urls), esc_url(wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))))));
        } else {
            if (count($urls) > 0 && count($urls) <= 9999) {
                $api_key = get_option('speedyindex_api_key');
                $project_id = self::submit_project($project_name, $urls, $notify, $user_id);
                if ($project_id) {
                    $api_response = Rapid_URL_Indexer::process_api_request($project_id, $urls, $notify);
                    if ($api_response['success']) {
                        wp_send_json_success(array(
                            'message' => __('Project submitted successfully.', 'rapid-url-indexer'),
                            'project_id' => $project_id
                        ));
                    } else {
                        wp_send_json_error(array('message' => $api_response['error']));
                    }
                } else {
                    wp_send_json_error(array('message' => __('Failed to submit project. Please try again.', 'rapid-url-indexer')));
                }
            } else {
                wp_send_json_error(array('message' => __('Invalid number of URLs. Must be between 1 and 9999.', 'rapid-url-indexer')));
            }
        }
    }

    private static function log_credit_change($user_id, $amount, $triggered_by = 'system', $project_id = 0) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        if (is_user_logged_in()) {
            $triggered_by = 'User ID: ' . get_current_user_id();
        }
        $wpdb->insert($log_table, array(
            'triggered_by' => $triggered_by,
            'user_id' => $user_id,
            'project_id' => $project_id,
            'action' => 'Credit Change',
            'details' => json_encode(array('amount' => $amount)),
            'created_at' => current_time('mysql')
        ));
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
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'project_name' => $project_name,
            'urls' => json_encode($urls),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));

        $project_id = $wpdb->insert_id;

        // Schedule API request
        self::schedule_api_request($project_id, $urls, $notify);
        return $project_id;
    }

    public static function handle_api_success($project_id, $user_id, $urls) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';

        // Check if credits have already been deducted
        $project = $wpdb->get_row($wpdb->prepare("SELECT status FROM $table_name WHERE id = %d", $project_id));
        if ($project && $project->status !== 'submitted') {
            // Subtract credits
            Rapid_URL_Indexer_Customer::update_user_credits($user_id, -count($urls));
        }

        // Update project status
        $wpdb->update($table_name, array('status' => 'submitted'), array('id' => $project_id));
    }

    public static function update_user_credits($user_id, $amount, $triggered_by = 'system', $project_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $credits = self::get_user_credits($user_id);
        $new_credits = max(0, $credits + $amount);

        if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d", $user_id)) > 0) {
            $wpdb->update($table_name, array('credits' => $new_credits), array('user_id' => $user_id));
        } else {
            $wpdb->insert($table_name, array('user_id' => $user_id, 'credits' => $new_credits));
        }
        
        self::log_credit_change($user_id, $amount, 'system', $project_id);
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
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js', array(), '4.4.1', true);
        wp_enqueue_script('rui-customer-js', RUI_PLUGIN_URL . 'assets/js/customer.js', array('jquery', 'chart-js'), null, true);
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
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $project_id, $user_id));

        if ($project) {
            $stats = Rapid_URL_Indexer::get_project_stats($project_id);
            $dates = array();
            $indexed = array();
            $unindexed = array();

            foreach ($stats as $stat) {
                $dates[] = $stat['date'];
                $indexed[] = intval($stat['indexed_count']);
                $unindexed[] = intval($stat['unindexed_count']);
            }

            // If there are no stats yet, use the project's current values
            if (empty($stats)) {
                $dates[] = date('Y-m-d');
                $indexed[] = intval($project->indexed_links);
                $unindexed[] = intval($project->submitted_links) - intval($project->indexed_links);
            }

            wp_send_json_success(array(
                'dates' => $dates,
                'indexed' => $indexed,
                'unindexed' => $unindexed
            ));
        } else {
            wp_send_json_error('Project not found or access denied');
        }
    }
}
