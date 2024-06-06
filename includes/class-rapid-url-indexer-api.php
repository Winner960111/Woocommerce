<?php
class Rapid_URL_Indexer_API {
    private static $api_base_url = 'https://api.speedyindex.com';

    private static $api_rate_limit = 5; // Maximum number of API requests per second
    private static $api_retry_delay = 15; // Delay in seconds before retrying a failed API request
    private static $api_max_retries = 3; // Maximum number of retries for a failed API request

    public static function get_account_balance($api_key) {
        $response = self::make_api_request('GET', '/v2/account', $api_key);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response['response']['code'] === 200) {
            if (isset($data['balance']['indexer']) && $data['balance']['indexer'] < 100000) {
                // Notify admin
                wp_mail(
                    get_option('admin_email'),
                    __('Low API Balance', 'rapid-url-indexer'),
                    __('The API balance for URL indexing is below 100000.', 'rapid-url-indexer')
                );
            }
            return $data;
        } else {
            // Log the error
            error_log('SpeedyIndex API Error: ' . $response['response']['message']);
            return false;
        }
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
        return json_decode(wp_remote_retrieve_body($response), true);
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

    public static function get_task_status($api_key, $task_id) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/status', $api_key, array('task_id' => $task_id));
        
        if ($response['response']['code'] === 200) {
            return json_decode(wp_remote_retrieve_body($response), true);
        } else {
            // Log the error  
            error_log('SpeedyIndex API Error: ' . $response['response']['message']);
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
