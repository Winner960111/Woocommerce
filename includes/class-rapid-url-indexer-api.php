<?php
class Rapid_URL_Indexer_API {
    private static $api_base_url = 'https://api.speedyindex.com';

    public static function get_account_balance($api_key) {
        $response = wp_remote_get(self::$api_base_url . '/v2/account', array(
            'headers' => array(
                'Authorization' => $api_key
            )
        ));

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['balance']['indexer']) && $data['balance']['indexer'] < 100000) {
            // Notify admin
            wp_mail(
                get_option('admin_email'),
                __('Low API Balance', 'rapid-url-indexer'),
                __('The API balance for URL indexing is below 100000.', 'rapid-url-indexer')
            );
        }

        return $data;
    }

    public static function create_task($api_key, $urls) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/create', $api_key, array('urls' => $urls));
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private static function make_api_request($method, $endpoint, $api_key, $body = null) {
        $args = array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'method' => $method,
        );

        if ($body) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request(self::$api_base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            self::log_api_error($endpoint, $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            self::log_api_error($endpoint, wp_remote_retrieve_body($response));
            return null;
        }

        return $response;
    }

    private static function log_api_error($endpoint, $error_message) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        $wpdb->insert($log_table, array(
            'user_id' => get_current_user_id(),
            'project_id' => 0,
            'action' => 'API Error',
            'details' => json_encode(array('endpoint' => $endpoint, 'error' => $error_message)),
            'created_at' => current_time('mysql')
        ));
    }

    private static function make_api_request($method, $endpoint, $api_key, $body = null) {
        $args = array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'method' => $method,
        );

        if ($body) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request(self::$api_base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            self::log_api_error($endpoint, $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            self::log_api_error($endpoint, wp_remote_retrieve_body($response));
            return null;
        }

        return $response;
    }

    private static function make_api_request($method, $endpoint, $api_key, $body = null) {
        $args = array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'method' => $method,
        );

        if ($body) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request(self::$api_base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            self::log_api_error($endpoint, $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            self::log_api_error($endpoint, wp_remote_retrieve_body($response));
            return null;
        }

        return $response;
    }

    private static function log_api_error($endpoint, $error_message) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'rapid_url_indexer_logs';
        $wpdb->insert($log_table, array(
            'user_id' => get_current_user_id(),
            'project_id' => 0,
            'action' => 'API Error',
            'details' => json_encode(array('endpoint' => $endpoint, 'error' => $error_message)),
            'created_at' => current_time('mysql')
        ));
    }

    }

    public static function get_task_status($api_key, $task_id) {
        $response = wp_remote_post(self::$api_base_url . '/v2/task/google/indexer/status', array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('task_id' => $task_id))
        ));

    }

    public static function download_task_report($api_key, $task_id) {
        $response = wp_remote_post(self::$api_base_url . '/v2/task/google/indexer/report', array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('task_id' => $task_id))
        ));

        return wp_remote_retrieve_body($response);
    }
}
?>
