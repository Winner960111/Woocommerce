<?php
class Rapid_URL_Indexer_Customer {
    public static function init() {
        add_action('init', array(__CLASS__, 'customer_menu'));
        add_shortcode('rui_credits_display', array(__CLASS__, 'credits_display'));
        add_shortcode('rui_project_submission', array(__CLASS__, 'project_submission'));
        add_shortcode('rui_api_key_display', array(__CLASS__, 'api_key_display'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_rui_submit_project', array(__CLASS__, 'handle_ajax_project_submission'));
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'handle_order_completed'));
        add_action('user_register', array(__CLASS__, 'generate_api_key'));

        // Add custom endpoints
        add_action('init', array(__CLASS__, 'add_my_account_endpoints'));
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_my_account_menu_items'));

        // Add custom endpoints
        add_action('init', array(__CLASS__, 'add_my_account_endpoints'));
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_my_account_menu_items'));
        add_action('woocommerce_account_rui-projects_endpoint', array(__CLASS__, 'projects_endpoint_content'));
    }

    public static function add_my_account_endpoints() {
        add_rewrite_endpoint('rui-buy-credits', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('rui-projects', EP_ROOT | EP_PAGES);
        add_action('woocommerce_account_rui-buy-credits_endpoint', array(__CLASS__, 'add_my_account_endpoint_content'));
    }

    public static function add_my_account_endpoint_content() {
        include RUI_PLUGIN_DIR . 'templates/customer-buy-credits.php';
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

        return '<div class="rui-api-key-display">Your API Key: <code>' . esc_html($api_key) . '</code></div>';
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
    
        $project_name = sanitize_text_field($_POST['project_name']);
        $urls = array_map('esc_url_raw', array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['urls'])))));
        $notify = isset($_POST['notify']) ? 1 : 0;
    
        if (count($urls) > 0 && count($urls) <= 9999) {
            $project_id = self::submit_project($project_name, $urls, $notify);
            if ($project_id) {
                wp_send_json_success(array(
                    'message' => __('Project submitted successfully.', 'rapid-url-indexer'),
                    'project_id' => $project_id
                ));
            } else {
                wp_send_json_error(__('Failed to submit project. Please try again.', 'rapid-url-indexer'));
            }
        } else {
            wp_send_json_error(__('Invalid number of URLs. Must be between 1 and 9999.', 'rapid-url-indexer'));
        }
    }

    private static function log_credit_change($user_id, $amount) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        $wpdb->insert($log_table, array(
            'user_id' => $user_id,
            'project_id' => 0,
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


    public static function credits_display() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $credits = self::get_user_credits($user_id);

        return '<div class="rui-credits-display">Remaining Credits: ' . esc_html($credits) . '</div><a href="' . esc_url(wc_get_endpoint_url('rui-buy-credits', '', wc_get_page_permalink('myaccount'))) . '" class="button">Buy Credits</a>';
    }

    public static function project_submission() {
        ob_start();
        ?>
        <form id="rui-project-submission-form" method="post" action="">
            <label for="project_name">Project Name:</label>
            <input type="text" name="project_name" id="project_name" required>
            <label for="urls">URLs (one per line, max 9999):</label>
            <textarea name="urls" id="urls" rows="10" required></textarea>
            <label for="notify">Email Notifications:</label>
            <input type="checkbox" name="notify" id="notify">
            <input type="hidden" name="security" value="<?php echo wp_create_nonce('rui_project_submission'); ?>">
            <input type="submit" value="Submit Project">
        </form>
        <div id="rui-submission-response"></div>
        <?php

        return ob_get_clean();
    }

    private static function submit_project($project_name, $urls, $notify) {
        global $wpdb;
        $user_id = get_current_user_id();
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
        // Subtract credits
        Rapid_URL_Indexer_Customer::update_user_credits($user_id, -count($urls));

        // Update project status
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $wpdb->update($table_name, array('status' => 'submitted'), array('id' => $project_id));
    }

    public static function update_user_credits($user_id, $credits) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $credits = self::get_user_credits($user_id);
        $new_credits = max(0, $credits + $amount);

        if ($credits !== null) {
            $wpdb->update($table_name, array('credits' => $new_credits), array('user_id' => $user_id));
        } else {
            $wpdb->insert($table_name, array('user_id' => $user_id, 'credits' => $new_credits));
        }
        
        self::log_credit_change($user_id, $amount);
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
        wp_enqueue_script('rui-customer-js', RUI_PLUGIN_URL . 'assets/js/customer.js', array('jquery'), null, true);
    }

    public static function add_my_account_menu_items($items) {
        $items['rui-projects'] = 'My Indexing Projects';
        $items['rui-buy-credits'] = 'Buy Indexing Credits';
        return $items;
    }

    public static function projects_endpoint_content() {
        include RUI_PLUGIN_DIR . 'templates/customer-projects.php';
    }
}
