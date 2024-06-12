<?php
/**
 * Plugin Name: Rapid URL Indexer for WordPress
 * Description: Submit URLs to Rapid URL Indexer for fast and reliable Google indexing.
 * Version: 1.0
 * Author: RapidURLIndexer.com
 * Text Domain: rapidurlindexer-wordpress
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rapid_URL_Indexer_WordPress {
    private $api_base_url = 'https://rapidurlindexer.com/';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_rui_bulk_submit', array($this, 'handle_bulk_submit'));
        
        // Add actions for post status transitions
        add_action('transition_post_status', array($this, 'on_post_status_change'), 10, 3);

        // Add credits amount field to simple products
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_credits_amount_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_credits_amount_field'));
    }

    public function add_credits_amount_field() {
        global $post;
        $product = wc_get_product($post->ID);

        if ($product->is_type('simple')) {
            woocommerce_wp_text_input(array(
                'id' => '_credits_amount',
                'label' => __('Credits Amount', 'rapid-url-indexer'),
                'placeholder' => '',
                'desc_tip' => 'true',
                'description' => __('Enter the number of credits this product represents.', 'rapid-url-indexer'),
                'type' => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min' => '0'
                )
            ));
        }
    }

    public function save_credits_amount_field($post_id) {
        $credits_amount = isset($_POST['_credits_amount']) ? intval($_POST['_credits_amount']) : '';
        update_post_meta($post_id, '_credits_amount', $credits_amount);
    }
    
    public function on_post_status_change($new_status, $old_status, $post) {
        if ($new_status === 'publish') {
            $submit_on_publish = get_option("rui_submit_on_publish_{$post->post_type}", 0);
            $submit_on_update = get_option("rui_submit_on_update_{$post->post_type}", 0);

            if (($old_status !== 'publish' && $submit_on_publish) || ($old_status === 'publish' && $submit_on_update)) {
                $this->submit_url($post->guid, $post->post_name);
            }
        }
    }

    public function add_plugin_page() {
        add_options_page(
            'Rapid URL Indexer Settings',
            'Rapid URL Indexer',
            'manage_options',
            'rapid-url-indexer',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        include_once 'templates/admin-settings.php';
    }

    public function page_init() {
        register_setting('rui_options', 'rui_settings');
        add_settings_section(
            'rui_settings_section',
            'API Settings',
            array($this, 'section_info'),
            'rapid-url-indexer'
        );
        add_settings_field(
            'rui_api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'rapid-url-indexer',
            'rui_settings_section'
        );

        $post_types = get_post_types(array('public' => true), 'objects');
        foreach ($post_types as $post_type) {
            register_setting('rui_options', "rui_submit_on_publish_{$post_type->name}");
            register_setting('rui_options', "rui_submit_on_update_{$post_type->name}");
        }

        add_filter('allowed_options', array($this, 'allowed_options'));
    }

    public function allowed_options($allowed_options) {
        $allowed_options['rui_options'] = array(
            'rui_settings',
            'rui_delete_data_on_uninstall',
            'rui_log_entry_limit',
        );

        $post_types = get_post_types(array('public' => true), 'objects');
        foreach ($post_types as $post_type) {
            $allowed_options['rui_options'][] = "rui_submit_on_publish_{$post_type->name}";
            $allowed_options['rui_options'][] = "rui_submit_on_update_{$post_type->name}";
        }

        return $allowed_options;
    }

    public function section_info() {
        // Display section info if needed
    }

    public function api_key_callback() {
        $settings = get_option('rui_settings');
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        echo "<input type='text' name='rui_settings[api_key]' value='" . esc_attr($api_key) . "' />";
    }


    public function enqueue_scripts($hook) {
        if ($hook === 'settings_page_rapid-url-indexer') {
            wp_enqueue_script('rui-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array(), '1.0.0', true);
            wp_localize_script('rui-admin-js', 'rui_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rui_bulk_submit'),
            ));
        }
    }

    public function handle_bulk_submit() {
        check_ajax_referer('rui_bulk_submit', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'rapid-url-indexer'));
        }

        $urls = isset($_POST['urls']) ? explode("\n", sanitize_textarea_field($_POST['urls'])) : array();
        $urls = array_filter(array_map('esc_url_raw', $urls));

        if (empty($urls)) {
            wp_send_json_error(__('No valid URLs provided', 'rapid-url-indexer'));
        }

        $project_name = isset($_POST['project_name']) ? sanitize_text_field($_POST['project_name']) : __('Bulk Submit', 'rapid-url-indexer');

        $response = $this->submit_urls($urls, $project_name);

        if ($response['code'] === 0) {
            wp_send_json_success(__('URLs submitted successfully', 'rapid-url-indexer'));
        } else {
            wp_send_json_error(sprintf(__('Error submitting URLs: %s', 'rapid-url-indexer'), $response['message']));
        }
    }

    private function submit_url($url, $project_name) {
        $response = wp_remote_post(rest_url('rui/v1/projects'), array(
            'headers' => array(
                'X-API-Key' => $this->get_api_key(),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'project_name' => $project_name,
                'urls' => array($url),
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("API Error: $error_message");
            return array('code' => 1, 'message' => __('Error communicating with API', 'rapid-url-indexer'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code === 200) {
            return $response_body;
        } else {
            $error_message = isset($response_body['message']) ? $response_body['message'] : __('Unknown API error', 'rapid-url-indexer');
            error_log("API Error: $error_message");
            return array('code' => $response_code, 'message' => $error_message);
        }
    }

    private function submit_urls($urls, $project_name) {
        $response = wp_remote_post(rest_url('rui/v1/projects'), array(
            'headers' => array(
                'X-API-Key' => $this->get_api_key(),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'project_name' => $project_name,
                'urls' => $urls,
            )),
        ));

        if (is_wp_error($response)) {
            return array('code' => 1, 'message' => $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        return $response_body;
    }

    private function get_api_key() {
        $user_id = get_current_user_id();
        return get_user_meta($user_id, 'rui_api_key', true);
    }

    public function get_credits_balance() {
        $response = wp_remote_get(rest_url('rui/v1/credits/balance'), array(
            'headers' => array(
                'X-API-Key' => $this->get_api_key(),
            ),
        ));

        if (is_wp_error($response)) {
            return 'N/A';
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['credits'])) {
            return $response_body['credits'];
        }

        return 'N/A';
    }
}

if (is_admin()) {
    $rapid_url_indexer_wordpress = new Rapid_URL_Indexer_WordPress();
}
?>
