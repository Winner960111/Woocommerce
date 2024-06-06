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

    private static function check_low_balance($balance) {
        if ($balance < self::LOW_BALANCE_THRESHOLD) {
            self::notify_admin(__('Low API Balance', 'rapid-url-indexer'), 
                               __('The API balance for URL indexing is below ' . self::LOW_BALANCE_THRESHOLD . '.', 'rapid-url-indexer'));
        }
    }

    private static function notify_admin($subject, $message) {
        wp_mail(get_option('admin_email'), $subject, $message);
    }

    private static function is_api_response_success($response) {
        return $response['response']['code'] === 200;
    }

    private static function log_api_error($response) {
        error_log('SpeedyIndex API Error: ' . $response['response']['message']);
    }
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    public static function create_task($api_key, $urls) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/create', $api_key, array('urls' => $urls));
        return self::handle_api_response($response);
    }

    public static function get_task_status($api_key, $task_id) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/status', $api_key, array('task_id' => $task_id));
        return self::handle_api_response($response);
    }

    private static function handle_api_response($response) {
        if (self::is_api_response_success($response)) {
            return json_decode(wp_remote_retrieve_body($response), true);
        } else {
            self::log_api_error($response);
            return false;
        }
    }

    private static function make_api_request($method, $endpoint, $api_key, $body = array()) {
        $retries = 0;
        
        while ($retries < self::$api_max_retries) {
            // Implement rate limiting
            static $last_request_time = 0;
            $current_time = microtime(true);
            $elapsed_time = $current_time - $last_request_time;
            
            if ($elapsed_time < 1 / self::$api_rate_limit) {
                usleep((1 / self::$api_rate_limit - $elapsed_time) * 1000000);
            }
            
            $last_request_time = microtime(true);

            // Make the API request
            $args = array(
                'headers' => array(
                    'Authorization' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($body)
            );

            switch ($method) {
                case 'GET':
                    $response = wp_remote_get(self::$api_base_url . $endpoint, $args);
                    break;
                case 'POST':
                    $response = wp_remote_post(self::$api_base_url . $endpoint, $args);
                    break;
                default:
                    return false;
            }

            if ($response['response']['code'] === 200) {
                return $response;
            } else {
                $retries++;
                if ($retries < self::$api_max_retries) {
                    // Delay before retrying
                    sleep(self::$api_retry_delay);
                }
            }
        }

        return $response;
    }


    public static function create_task($api_key, $urls) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/create', $api_key, array('urls' => $urls));
        
        if ($response['response']['code'] === 200) {
            return json_decode(wp_remote_retrieve_body($response), true);
        } else {
            // Log the error
            error_log('SpeedyIndex API Error: ' . $response['response']['message']);
            return false;
        }
    }
    }

    public static function download_task_report($api_key, $task_id) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/report', $api_key, array('task_id' => $task_id));
        
        if (self::is_api_response_success($response)) {
            return wp_remote_retrieve_body($response);
        } else {
            self::log_api_error($response);
            return false;
        }
    }
}
