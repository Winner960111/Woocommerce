<?php
class Rapid_URL_Indexer_API {
    const API_BASE_URL = 'https://api.speedyindex.com';
    const API_RATE_LIMIT = 5; // Maximum number of API requests per second
    const API_RETRY_DELAY = 15; // Delay in seconds before retrying a failed API request 
    const API_MAX_RETRIES = 3; // Maximum number of retries for a failed API request
    const LOW_BALANCE_THRESHOLD = 100000; // Threshold for low balance notification

    public static function get_account_balance($api_key) {
        $response = self::make_api_request('GET', '/v2/account', $api_key);
        
        if (self::is_api_response_success($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            self::check_low_balance($data['balance']['indexer']);
            return $data;
        } else {
            self::log_api_error($response);
            return false;
        }
    }

    public static function get_tasks($api_key, $page = 0, $search = '') {
        $endpoint = "/v2/task/google/indexer/list/$page";
        if (!empty($search)) {
            $endpoint .= "?search=" . urlencode($search);
        }
        $response = self::make_api_request('GET', $endpoint, $api_key);
        
        if (self::is_api_response_success($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $tasks = $data['result'];
            
            if (!empty($search)) {
                $search = strtolower($search);
                $tasks = array_filter($tasks, function($task) use ($search) {
                    return stripos($task['id'], $search) !== false
                        || stripos($task['title'], $search) !== false
                        || stripos($task['type'], $search) !== false
                        || stripos($task['size'], $search) !== false
                        || stripos($task['processed_count'], $search) !== false
                        || stripos($task['indexed_count'], $search) !== false
                        || stripos($task['created_at'], $search) !== false;
                });
            }
            
            return $tasks;
        } else {
            self::log_api_error($response);
            return false;
        }
        
        if (self::is_api_response_success($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return $data['result'];
        } else {
            self::log_api_error($response);
            return false;
        }
    }

    private static function check_low_balance($balance) {
        if ($balance < self::LOW_BALANCE_THRESHOLD) {
            self::notify_admin(__('Low URL Indexing Balance', 'rapid-url-indexer'), 
                               __('The balance for URL indexing is below the threshold.', 'rapid-url-indexer'));
        }
    }

    private static function notify_admin($subject, $message) {
        wp_mail(get_option('admin_email'), $subject, $message);
    }

    private static function is_api_response_success($response) {
        return wp_remote_retrieve_response_code($response) === 200;
    }

    private static function log_api_error($response) {
        if (is_wp_error($response)) {
            error_log('SpeedyIndex API Error: ' . $response->get_error_message());
        } else {
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($response_body['message']) ? $response_body['message'] : 'Unknown error';
            error_log('SpeedyIndex API Error: ' . $error_message);
        }
    }
    public static function create_task($api_key, $urls, $title = null) {
        $body = array('urls' => $urls);
        if ($title !== null) {
            $body['title'] = $title;
        }
        $response = self::make_api_request('POST', '/v2/task/google/indexer/create', $api_key, $body);
        return self::handle_api_response($response);
    }

    public static function get_task_status($api_key, $task_id) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/status', $api_key, array('task_id' => $task_id));
        
        if (self::is_api_response_success($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return $data['result'];
        } else {
            self::log_api_error($response);
            return false;
        }
    }


    private static function handle_api_response($response) {
        if (is_wp_error($response)) {
            self::log_api_error($response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 200 && $response_code < 300) {
            if (isset($response_body['code'])) {
                switch ($response_body['code']) {
                    case 1:
                        $message = __('The SpeedyIndex API responded with code 1: Top up balance.', 'rapid-url-indexer');
                        break;
                    case 2:
                        $message = __('The SpeedyIndex API responded with code 2: The server is overloaded. Please retry later.', 'rapid-url-indexer');
                        break;
                }
                self::notify_admin(__('SpeedyIndex API Issue', 'rapid-url-indexer'), $message);
                self::add_admin_notice($message);
            }
            // Log the API response
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                'user_id' => get_current_user_id(),
                'project_id' => 0,
                'action' => 'API Response',
                'details' => json_encode($response_body),
                'created_at' => current_time('mysql')
            ));

            return $response_body;
        } else {
            $error_message = isset($response_body['message']) ? $response_body['message'] : 'Unknown error';
            self::log_api_error($error_message);
            return false;
        }
    }

    private static function add_admin_notice($message) {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }

    private static function make_api_request($method, $endpoint, $api_key, $body = null) {
        $retries = 0;
        
        while ($retries < self::API_MAX_RETRIES) {
            // Implement rate limiting
            static $last_request_time = 0;
            $current_time = microtime(true);
            $elapsed_time = $current_time - $last_request_time;
            
            if ($elapsed_time < 1 / self::API_RATE_LIMIT) {
                $sleep_time = (1 / self::API_RATE_LIMIT - $elapsed_time) * 1000000;
                usleep($sleep_time);
            }
            
            $last_request_time = $current_time;

        // Retrieve the API key from the settings
        $api_key = get_option('rui_speedyindex_api_key');
        if (empty($api_key)) {
            error_log('SpeedyIndex API Key is empty. Please check the plugin settings.');
            return new WP_Error('api_key_missing', __('API key is missing. Please check the plugin settings.', 'rapid-url-indexer'));
        }

        // Make the API request
        $args = array(
            'headers' => array(
                'Authorization' => $api_key
            )
        );

        if ($body !== null) {
            $args['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $args['headers']['Content-Type'] = 'application/json';
        }


        switch ($method) {
            case 'GET':
                $response = wp_remote_get(self::API_BASE_URL . $endpoint, $args);
                break;
            case 'POST':
                $response = wp_remote_post(self::API_BASE_URL . $endpoint, $args);
                break;
            default:
                return false;
        }


        if (is_wp_error($response)) {
            $retries++;
            if ($retries < self::API_MAX_RETRIES) {
                sleep(self::API_RETRY_DELAY);
            } else {
                return false;
            }
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 429) {
                // Rate limit exceeded
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                if ($retry_after) {
                    sleep($retry_after);
                } else {
                    sleep(1);
                }
                $retries++;
            } else {
                return $response;
            }
        }
    }

    return false;
    }



    public static function download_task_report($api_key, $task_id) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/report', $api_key, array('task_id' => $task_id));
        
        if (self::is_api_response_success($response)) {
            $report_data = json_decode(wp_remote_retrieve_body($response), true);
            $csv_data = array(
                array('URL', 'Status')
            );
            foreach ($report_data['result']['indexed_links'] as $url) {
                $csv_data[] = array($url, 'Indexed');
            }
            foreach ($report_data['result']['unindexed_links'] as $url) {
                $csv_data[] = array($url, 'Not Indexed');
            }
            
            return self::generate_csv($csv_data);
        } else {
            self::log_api_error($response);
            return false;
        }
    }

    public static function generate_csv($data) {
        $output = fopen('php://temp', 'w'); 
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        return stream_get_contents($output);
    }
}
