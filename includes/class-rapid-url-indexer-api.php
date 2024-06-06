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
        return json_decode($body, true);
    }

    public static function create_task($api_key, $urls) {
        $response = wp_remote_post(self::$api_base_url . '/v2/task/google/indexer/create', array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('urls' => $urls))
        ));

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public static function get_task_status($api_key, $task_id) {
        $response = wp_remote_post(self::$api_base_url . '/v2/task/google/indexer/status', array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('task_id' => $task_id))
        ));

        return wp_remote_retrieve_body($response);
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
