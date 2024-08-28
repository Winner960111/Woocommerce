<?php
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer.php';

/**
 * Class responsible for handling admin-related functionalities.
 * 
 * This class manages the admin menu, enqueues scripts, handles AJAX requests, and provides
 * various admin pages for managing projects, tasks, settings, and logs.
 */
class Rapid_URL_Indexer_Admin {
    /**
     * Initializes the admin functionalities by setting up hooks and actions.
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('rui_log_entry_created', array(__CLASS__, 'limit_log_entries'));
        add_action('wp_ajax_check_abuse', array(__CLASS__, 'ajax_check_abuse'));
        add_action('wp_ajax_nopriv_check_abuse', array(__CLASS__, 'ajax_check_abuse'));
        add_action('admin_init', array(__CLASS__, 'handle_download_project_report'));
        register_setting('rui_settings', 'rui_project_age_limit', array(
            'type' => 'integer',
            'default' => 90,
            'sanitize_callback' => 'intval'
        ));
    }

    /**
     * Handles the download of project reports.
     * 
     * This function checks for the 'download_project_report' GET parameter and generates a CSV report
     * for the specified project if the user has permission.
     */
    public static function handle_download_project_report() {
        if (isset($_GET['download_project_report'])) {
            $project_id = intval($_GET['download_project_report']);

            global $wpdb;
            $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
            $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $project_id));

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

    /**
     * Handles AJAX requests to check for potential abuse.
     * 
     * This function identifies users with a high refund rate and a large number of submitted URLs,
     * indicating potential abuse of the system.
     */
    public static function ajax_check_abuse() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';

        $min_urls = get_option('rui_min_urls_for_abuse', 1000);
        $avg_refund_rate = get_option('rui_avg_refund_rate_for_abuse', 0.7);

        try {
            // Get users with more than the minimum number of URLs where the average refund rate is above the threshold
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    user_id, 
                    SUM(submitted_links) as total_urls,
                    SUM(indexed_links) as total_indexed,
                    SUM(submitted_links - indexed_links) as total_unindexed,
                    SUM(submitted_links - indexed_links) / SUM(submitted_links) as refund_rate
                FROM $table_name
                WHERE status IN ('completed', 'refunded')
                GROUP BY user_id
                HAVING total_urls >= %d AND refund_rate >= %f
            ", $min_urls, $avg_refund_rate));

            if ($results) {
                ob_start();
                echo '<ul>';
                foreach ($results as $result) {
                    echo '<li>' . sprintf(__('User ID: %d, Total URLs: %d, Indexed: %d, Unindexed: %d, Refund Rate: %.2f%%', 'rapid-url-indexer'), 
                        $result->user_id, 
                        $result->total_urls, 
                        $result->total_indexed, 
                        $result->total_unindexed, 
                        $result->refund_rate * 100
                    ) . '</li>';
                }
                echo '</ul>';
                $output = ob_get_clean();
                wp_send_json_success($output);
            } else {
                wp_send_json_error(__('No potential abusers found based on the configured criteria.', 'rapid-url-indexer'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('An error occurred while checking for abusers: ', 'rapid-url-indexer') . $e->getMessage());
        }
    }

    /**
     * Displays the admin page for viewing projects.
     * 
     * This function retrieves and displays a paginated list of projects, allowing admins to search
     * and filter projects based on various criteria.
     */
    public static function view_projects_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';

        $projects_per_page = 50;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $projects_per_page;

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where = $search ? $wpdb->prepare(" WHERE project_name LIKE %s OR task_id LIKE %s OR users.user_email LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%') : '';

        $total_projects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name projects LEFT JOIN {$wpdb->users} users ON projects.user_id = users.ID $where");
        $total_pages = ceil($total_projects / $projects_per_page);

        $projects = $wpdb->get_results("
            SELECT projects.*, users.user_email 
            FROM $table_name projects 
            LEFT JOIN {$wpdb->users} users ON projects.user_id = users.ID 
            $where 
            ORDER BY projects.created_at DESC 
            LIMIT $offset, $projects_per_page
        ");

        $pagination = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $paged
        ));

        include RUI_PLUGIN_DIR . 'templates/admin-projects.php';
    }

    /**
     * Displays the admin page for viewing tasks.
     * 
     * This function retrieves and displays a paginated list of tasks from the API, allowing admins
     * to search and filter tasks based on various criteria.
     */
    public static function view_tasks_page() {
        $api_key = get_option('rui_speedyindex_api_key');
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $tasks_per_page = 50;

        $tasks = Rapid_URL_Indexer_API::get_tasks($api_key, ($paged - 1) * $tasks_per_page, $search, $tasks_per_page);

        $total_tasks = Rapid_URL_Indexer_API::get_total_tasks($api_key, $search);
        $total_pages = ceil($total_tasks / $tasks_per_page);

        $pagination = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $paged
        ));

        include RUI_PLUGIN_DIR . 'templates/admin-tasks.php';
    }

    /**
     * Renders the checkbox for deleting data on plugin uninstall.
     */
    public static function delete_data_on_uninstall_callback() {
        $delete_data_on_uninstall = get_option('rui_delete_data_on_uninstall', 0);
        echo '<input type="checkbox" name="rui_delete_data_on_uninstall" value="1" ' . checked(1, $delete_data_on_uninstall, false) . ' />';
    }

    /**
     * Retrieves the API key from the WordPress options.
     * 
     * @return string The API key.
     */
    private static function get_api_key() {
        return get_option('speedyindex_api_key');
    }

    /**
     * Adds the admin menu and submenu pages for the plugin.
     */
    public static function admin_menu() {
        add_menu_page(
            'Rapid URL Indexer',
            'URL Indexer',
            'manage_options',
            'rapid-url-indexer',
            array(__CLASS__, 'admin_page'),
            'dashicons-admin-site'
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Projects',
            'Projects',
            'manage_options',
            'rapid-url-indexer-projects',
            array(__CLASS__, 'view_projects_page')
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Tasks',
            'Tasks',
            'manage_options',
            'rapid-url-indexer-tasks',
            array(__CLASS__, 'view_tasks_page')
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Settings',
            'Settings',
            'manage_options',
            'rapid-url-indexer-settings',
            array(__CLASS__, 'settings_page')
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Manage Credits',
            'Manage Credits',
            'manage_options',
            'manage-credits',
            array(__CLASS__, 'manage_credits_page')
        );

        add_submenu_page(
            'rapid-url-indexer',
            'Logs',
            'Logs',
            'manage_options',
            'rapid-url-indexer-logs',
            array(__CLASS__, 'logs_page')
        );
    }

    /**
     * Displays the main admin dashboard page.
     * 
     * This function provides an overview of the plugin's status, including project counts and
     * available credits.
     */
    public static function admin_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        
        $total_projects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $processing_projects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'submitted'");
        $completed_projects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        
        $credits_table = $wpdb->prefix . 'rapid_url_indexer_credits';
        $total_credits = $wpdb->get_var("SELECT SUM(credits) FROM $credits_table");
        
        $api_key = get_option('rui_speedyindex_api_key');
        $api_credits = Rapid_URL_Indexer_API::get_account_balance($api_key);
        $api_credits = $api_credits ? $api_credits['balance']['indexer'] : 'N/A';
        
        $total_assigned_credits = $wpdb->get_var("SELECT SUM(credits) FROM $credits_table");
        
        include RUI_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    /**
     * Displays the admin page for managing credits.
     */
    public static function manage_credits_page() {
        include RUI_PLUGIN_DIR . 'templates/admin-manage-credits.php';
    } 

    /**
     * Enqueues admin scripts and styles for the plugin.
     * 
     * @param string $hook The current admin page hook.
     */
    public static function enqueue_scripts($hook) {
        $valid_pages = array(
            'toplevel_page_rapid-url-indexer',
            'rapid-url-indexer_page_rapid-url-indexer-settings',
            'rapid-url-indexer_page_rapid-url-indexer-manage-credits',
            'rapid-url-indexer_page_rapid-url-indexer-logs',
            'rapid-url-indexer_page_rapid-url-indexer-tasks'
        );
        
        if (strpos($hook, 'rapid-url-indexer') !== false || in_array($hook, $valid_pages)) {
            wp_enqueue_style('rui-admin-css', RUI_PLUGIN_URL . 'assets/css/admin.css', array(), '1.0.0', 'all');
            $admin_js_version = filemtime(RUI_PLUGIN_DIR . 'assets/js/admin.js');
            wp_enqueue_script('rui-admin-js', RUI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $admin_js_version, true);
            wp_localize_script('rui-admin-js', 'rui_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rui_ajax_nonce')
            ));
        }
    }


    /**
     * Retrieves the number of credits a user has.
     * 
     * @param int $user_id The ID of the user.
     * @return int The number of credits the user has.
     */
    public static function get_user_credits($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $credits = $wpdb->get_var($wpdb->prepare("SELECT credits FROM $table_name WHERE user_id = %d", $user_id));
        return $credits ? $credits : 0;
    }

    /**
     * Displays the admin settings page and handles form submissions.
     * 
     * This function allows admins to update various plugin settings and triggers an abuse check
     * after saving the settings.
     */
    public static function settings_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('rui_settings');
            update_option('rui_speedyindex_api_key', sanitize_text_field($_POST['rui_speedyindex_api_key']));
            update_option('rui_delete_data_on_uninstall', isset($_POST['rui_delete_data_on_uninstall']) ? 1 : 0);
            update_option('rui_log_age_limit', intval($_POST['rui_log_age_limit']));
            update_option('rui_project_age_limit', intval($_POST['rui_project_age_limit']));
            $min_urls = intval($_POST['rui_min_urls_for_abuse']);
            $avg_refund_rate = floatval($_POST['rui_avg_refund_rate_for_abuse']);
            
            update_option('rui_min_urls_for_abuse', $min_urls);
            update_option('rui_avg_refund_rate_for_abuse', $avg_refund_rate);
            
            // Trigger abuse check immediately after updating settings
            Rapid_URL_Indexer::check_abuse();
            
            echo '<div class="notice notice-success"><p>Settings saved and abuse check triggered. Min URLs: ' . $min_urls . ', Avg Refund Rate: ' . $avg_refund_rate . '</p></div>';
        }

        include RUI_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * Registers the plugin settings with WordPress.
     */
    public static function register_settings() {
        register_setting('rui_settings', 'rui_speedyindex_api_key');
        register_setting('rui_settings', 'rui_delete_data_on_uninstall');
        register_setting('rui_settings', 'rui_log_age_limit', array(
            'type' => 'integer',
            'default' => 1000,
            'sanitize_callback' => 'intval'
        ));
        register_setting('rui_settings', 'rui_min_urls_for_abuse', array(
            'type' => 'integer',
            'default' => 1000,
            'sanitize_callback' => 'intval'
        ));
        register_setting('rui_settings', 'rui_avg_refund_rate_for_abuse', array(
            'type' => 'float',
            'default' => 0.7,
            'sanitize_callback' => 'floatval'
        ));
        register_setting('rui_settings', 'rui_low_balance_threshold', array(
            'type' => 'integer',
            'default' => 100000,
            'sanitize_callback' => 'intval'
        ));
    }

    /**
     * Displays the admin page for viewing logs.
     * 
     * This function retrieves and displays a paginated list of log entries, allowing admins to
     * search and filter logs based on various criteria.
     */
    public static function logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';

        $logs_per_page = 50;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $logs_per_page;

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where = $search ? $wpdb->prepare(" WHERE action LIKE %s OR details LIKE %s OR users.user_email LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%') : '';

        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name logs LEFT JOIN {$wpdb->users} users ON logs.user_id = users.ID $where");
        $total_pages = ceil($total_logs / $logs_per_page);

        $logs = $wpdb->get_results("
            SELECT logs.*, users.user_email, projects.id as project_id, projects.user_id as project_user_id
            FROM $table_name logs 
            LEFT JOIN {$wpdb->users} users ON logs.user_id = users.ID 
            LEFT JOIN {$wpdb->prefix}rapid_url_indexer_projects projects ON logs.project_id = projects.id
            $where 
            ORDER BY logs.created_at DESC 
            LIMIT $offset, $logs_per_page
        ");

        // Ensure that the logs have the correct user and project information
        foreach ($logs as $log) {
            if (empty($log->user_id) && !empty($log->project_id)) {
                $project = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}rapid_url_indexer_projects WHERE id = %d", $log->project_id));
                if ($project) {
                    $log->user_id = $project->user_id;
                    $user = get_userdata($project->user_id);
                    $log->user_email = $user ? $user->user_email : '';
                }
            }
        }

        $pagination = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $paged
        ));

        include RUI_PLUGIN_DIR . 'templates/admin-logs.php';
    }

    /**
     * Handles AJAX requests to search logs.
     * 
     * This function retrieves log entries based on the search criteria and returns them as a JSON
     * response.
     */
    public static function ajax_search_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where = $search ? $wpdb->prepare(" WHERE action LIKE %s OR details LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%') : '';

        $logs = $wpdb->get_results("
            SELECT logs.*, users.user_email 
            FROM $table_name logs 
            LEFT JOIN {$wpdb->users} users ON logs.user_id = users.ID 
            $where 
            ORDER BY created_at DESC
        ");

        ob_start();
        foreach ($logs as $log) {
            ?>
            <tr>
                <td><?php echo esc_html($log->id); ?></td>
                <td><?php echo esc_html($log->user_id); ?></td>
                <td><?php echo esc_html($log->project_id); ?></td>
                <td><?php echo esc_html($log->action); ?></td>
                <td><?php echo esc_html($log->details); ?></td>
                <td><?php echo esc_html($log->created_at); ?></td>
            </tr>
            <?php
        }
        $output = ob_get_clean();

        wp_send_json_success($output);
    }

    /**
     * Limits the number of log entries by deleting old entries.
     * 
     * This function deletes log entries older than the configured age limit.
     */
    public static function limit_log_entries() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
        $log_age_limit = get_option('rui_log_age_limit', 30); // Default to 30 days

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < NOW() - INTERVAL %d DAY",
            $log_age_limit
        ));
    }
}
