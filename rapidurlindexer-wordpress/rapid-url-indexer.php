<?php
/**
 * Plugin Name: Rapid URL Indexer for WordPress
 * Description: Submit URLs to the Rapid URL Indexer API for indexing.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: rapid-url-indexer
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rapid_URL_Indexer_WordPress {
    private $api_base_url = 'https://api.speedyindex.com';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_rui_bulk_submit', array($this, 'handle_bulk_submit'));
        
        // Add actions for post status transitions
        add_action('transition_post_status', array($this, 'on_post_status_change'), 10, 3);
    }
    
    public function on_post_status_change($new_status, $old_status, $post) {
        if ($new_status === 'publish') {
            $submit_on_publish = get_post_meta($post->ID, '_rui_submit_on_publish', true);
            $submit_on_update = get_post_meta($post->ID, '_rui_submit_on_update', true);

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
    }

    public function section_info() {
        // Display section info if needed
    }

    public function api_key_callback() {
        $settings = get_option('rui_settings');
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        echo "<input type='text' name='rui_settings[api_key]' value='" . esc_attr($api_key) . "' />";
    }

    public function add_meta_boxes() {
        $post_types = get_post_types();
        foreach ($post_types as $post_type) {
            add_meta_box(
                'rui_post_settings',
                'Rapid URL Indexer',
                array($this, 'render_post_settings'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_post_settings($post) {
        $submit_on_publish = get_post_meta($post->ID, '_rui_submit_on_publish', true);
        $submit_on_update = get_post_meta($post->ID, '_rui_submit_on_update', true);
        include 'templates/post-settings.php';
    }

    public function save_post($post_id, $post) {
        if (!isset($_POST['rui_post_settings_nonce']) || !wp_verify_nonce($_POST['rui_post_settings_nonce'], 'rui_post_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $submit_on_publish = isset($_POST['rui_submit_on_publish']) ? 1 : 0;
        $submit_on_update = isset($_POST['rui_submit_on_update']) ? 1 : 0;

        update_post_meta($post_id, '_rui_submit_on_publish', $submit_on_publish);
        update_post_meta($post_id, '_rui_submit_on_update', $submit_on_update);

        if (($post->post_status === 'publish' && $submit_on_publish) || ($post->post_status === 'publish' && $submit_on_update && $post->post_modified !== $post->post_modified_gmt)) {
            $this->submit_url($post->guid, $post->post_name);
        }
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
        $settings = get_option('rui_settings');
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';

        if (empty($api_key)) {
            return array('code' => 1, 'message' => __('API key not set', 'rapid-url-indexer'));
        }

        $response = wp_remote_post($this->api_base_url . '/v2/task/google/indexer/create', array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'title' => $project_name,
                'urls' => array($url),
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("SpeedyIndex API Error: $error_message");
            return array('code' => 1, 'message' => __('Error communicating with API', 'rapid-url-indexer'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code === 200) {
            return $response_body;
        } else {
            $error_message = isset($response_body['message']) ? $response_body['message'] : __('Unknown API error', 'rapid-url-indexer');
            error_log("SpeedyIndex API Error: $error_message");
            return array('code' => $response_code, 'message' => $error_message);
        }
    }

    private function submit_urls($urls, $project_name) {
        $settings = get_option('rui_settings');
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';

        if (empty($api_key)) {
            return array('code' => 1, 'message' => 'API key not set');
        }

        $response = wp_remote_post($this->api_base_url . '/v2/task/google/indexer/create', array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'title' => $project_name,
                'urls' => $urls,
            )),
        ));

        if (is_wp_error($response)) {
            return array('code' => 1, 'message' => $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        return $response_body;
    }

    public function get_credits_balance() {
        $settings = get_option('rui_settings');
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';

        if (empty($api_key)) {
            return 'N/A';
        }

        $response = wp_remote_get($this->api_base_url . '/v2/account', array(
            'headers' => array(
                'Authorization' => $api_key,
            ),
        ));

        if (is_wp_error($response)) {
            return 'N/A';
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['code'] === 0) {
            return $response_body['balance']['indexer'];
        }

        return 'N/A';
    }
}

if (is_admin()) {
    $rapid_url_indexer_wordpress = new Rapid_URL_Indexer_WordPress();
}
?>
