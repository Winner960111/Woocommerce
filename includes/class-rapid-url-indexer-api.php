<?php
class Rapid_URL_Indexer_API {
    const API_BASE_URL = 'https://api.speedyindex.com';
    const API_RETRY_DELAY = 5; // Delay in seconds before retrying a failed API request 
    const API_MAX_RETRIES = 3; // Maximum number of retries for a failed API request

    public static function get_account_balance($api_key) {
        $response = self::make_api_request('GET', '/v2/account', $api_key);
        
        if (self::is_api_response_success($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            self::check_low_balance($data['balance']['indexer']);
            return $data;
        } else {
            self::log_api_error($response, $project_id);
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
    }

    public static function get_total_tasks($api_key, $search = '') {
        $endpoint = "/v2/task/google/indexer/count";
        if (!empty($search)) {
            $endpoint .= "?search=" . urlencode($search);
        }
        $response = self::make_api_request('GET', $endpoint, $api_key);
        
        if (self::is_api_response_success($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return isset($data['count']) ? $data['count'] : 0;
        } else {
            self::log_api_error($response);
            return 0;
        }
    }

    private static function check_low_balance($balance) {
        $low_balance_threshold = get_option('rui_low_balance_threshold', 100000);
        if ($balance < $low_balance_threshold) {
            $message = sprintf(__('The balance for URL indexing is below the threshold of %d.', 'rapid-url-indexer'), $low_balance_threshold);
            self::notify_admin(__('Low URL Indexing Balance', 'rapid-url-indexer'), $message);
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
        }

        // Check if total assigned credits exceed available balance
        global $wpdb;
        $credits_table = $wpdb->prefix . 'rapid_url_indexer_credits';
        $total_assigned_credits = $wpdb->get_var("SELECT SUM(credits) FROM $credits_table");

        if ($total_assigned_credits > $balance) {
            $message = sprintf(__('Total assigned customer credits (%d) exceed available SpeedyIndex API credits (%d).', 'rapid-url-indexer'), $total_assigned_credits, $balance);
            self::notify_admin(__('Credits Imbalance', 'rapid-url-indexer'), $message);
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
        }
    }

    private static function notify_admin($subject, $message = '') {
        if (empty($message)) {
            $message = $subject; // Use the subject as the message if no message is provided
        }
        wp_mail(get_option('admin_email'), $subject, $message);
    }

    private static function is_api_response_success($response) {
        return wp_remote_retrieve_response_code($response) === 200;
    }

    private static function log_api_error($response, $project_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';

        if (is_wp_error($response)) {
            $error_details = array(
                'error_message' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
                'error_data' => $response->get_error_data()
            );
        } elseif (is_array($response) && isset($response['response'])) {
            $error_details = array(
                'response_code' => $response['response']['code'],
                'response_message' => $response['response']['message'],
                'response_body' => wp_remote_retrieve_body($response)
            );
        } else {
            $error_details = array(
                'response_code' => wp_remote_retrieve_response_code($response),
                'response_message' => wp_remote_retrieve_response_message($response),
                'response_body' => wp_remote_retrieve_body($response)
            );
        }

        $wpdb->insert($table_name, array(
            'user_id' => get_current_user_id(),
            'project_id' => $project_id,
            'action' => 'API Error',
            'details' => json_encode($error_details),
            'created_at' => current_time('mysql')
        ));

        error_log('SpeedyIndex API Error for Project ID ' . $project_id . ': ' . json_encode($error_details));
    }
    public static function create_task($api_key, $urls, $title = null, $user_id = null) {
        $body = array('urls' => $urls);
        if ($title !== null) {
            $body['title'] = $title . ($user_id ? " (CID{$user_id})" : '');
        }
        $response = self::make_api_request('POST', '/v2/task/google/indexer/create', $api_key, $body);
        
        if (is_wp_error($response)) {
            self::log_api_error($response);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            self::log_api_error($response);
            return false;
        }

        if (empty($body) || !isset($body['task_id'])) {
            self::log_api_error($response);
            return false;
        }

        return self::handle_api_response($response);
    }

    public static function get_task_status($api_key, $task_id) {
        $response = self::make_api_request('POST', '/v2/task/google/indexer/status', $api_key, array('task_id' => $task_id));
        
        if (self::is_api_response_success($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['result'])) {
                $result = array(
                    'processed_count' => isset($data['result']['processed_count']) ? intval($data['result']['processed_count']) : 0,
                    'indexed_count' => isset($data['result']['indexed_count']) ? intval($data['result']['indexed_count']) : 0,
                    'submitted_count' => isset($data['result']['size']) ? intval($data['result']['size']) : 0
                );
                error_log('API Response for task ' . $task_id . ': ' . json_encode($result));
                return $result;
            }
        }
        self::log_api_error($response);
        return false;
    }


    private static function handle_api_response($response) {
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            self::log_api_error($error_message);
            self::notify_admin(__('SpeedyIndex API Error', 'rapid-url-indexer'), $error_message);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 200 && $response_code < 300) {
            if (isset($response_body['code'])) {
                $message = '';
                switch ($response_body['code']) {
                    case 1:
                        $message = __('The SpeedyIndex API responded with code 1: Top up balance.', 'rapid-url-indexer');
                        break;
                    case 2:
                        $message = __('The SpeedyIndex API responded with code 2: The server is overloaded. Please retry later.', 'rapid-url-indexer');
                        break;
                }
                if (!empty($message)) {
                    self::notify_admin(__('SpeedyIndex API Issue', 'rapid-url-indexer'), $message);
                    add_action('admin_notices', function() use ($message) {
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
                    });
                }
            }
            // Log the API response
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
                'user_id' => get_current_user_id(),
                'project_id' => isset($response_body['project_id']) ? $response_body['project_id'] : 0,
                'action' => 'API Response',
                'details' => json_encode($response_body),
                'created_at' => current_time('mysql')
            ));

            return $response_body;
        } else {
            $error_message = isset($response_body['message']) ? $response_body['message'] : 'Unknown error';
            self::log_api_error($error_message);
            self::notify_admin(__('SpeedyIndex API Error', 'rapid-url-indexer'), $error_message);
            return false;
        }
    }


    private static function make_api_request($method, $endpoint, $api_key, $body = null) {
        $retries = 0;
        
        while ($retries < self::API_MAX_RETRIES) {
            // Retrieve the API key from the settings
            if (empty($api_key)) {
                $api_key = get_option('rui_speedyindex_api_key');
                if (empty($api_key)) {
                    error_log('SpeedyIndex API Key is empty. Please check the plugin settings.');
                    return new WP_Error('api_key_missing', __('API key is missing. Please check the plugin settings.', 'rapid-url-indexer'));
                }
            }

            // Make the API request
            $args = array(
                'headers' => array(
                    'Authorization' => $api_key
                ),
                'timeout' => 30 // Set a 30-second timeout
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

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $retries++;
                if ($retries < self::API_MAX_RETRIES) {
                    sleep(self::API_RETRY_DELAY);
                } else {
                    return false;
                }
            } else {
                return $response;
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
