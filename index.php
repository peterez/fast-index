<?php
/*
Plugin Name: Fast Index
Plugin URI:
Description: <strong>Fast Index</strong> on google
Version: 1.0
Author: Samet AKIN
Author URI: https://www.linkedin.com/in/samet-akin/
Contact me at https://www.linkedin.com/in/samet-akin/

Note : Hi dear users, this plugin build with wordpress structure and i don't like it because i make this plugin in one day. i will upgrade plugin when used much people.

*/


if (!defined('ABSPATH') or !defined('WPINC')) {
    die;
}


include_once ABSPATH . 'wp-admin/includes/file.php';
include_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
include_once plugin_dir_path(__FILE__) . '/helpers/indexingApi.php';
require_once plugin_dir_path(__FILE__) . '/freemius-sdk/start.php';


class FastIndex
{
    private $customPostType = "fi_log";
    private $canI;

    function __construct()
    {

        add_action('init', array($this, 'fiPostType'));
        add_action('post_updated', array($this, 'sendRequest'));
        add_filter('cron_schedules', array($this, 'cronSchedule'));
        add_action('admin_head', array($this,'pluginAssets'));

        figi_fs();
        do_action( 'figi_fs_loaded' );

        $this->canI = figi_fs()->can_use_premium_code();

    }

    /* ASSETS */

    function getOption() {
        $options = get_option('fast_index_options');
        if(is_array($options) ==false) { $options = array(); }
        $options['post_type'] = is_array($options['post_type']) ? $options['post_type'] : array("post" => "1");
        $options['post_status'] = is_array($options['post_status']) ? $options['post_status'] : array("publish" => "1", "edit" => "1");
        return $options;
    }

    function pluginAssets() {
        wp_enqueue_style('fi_css', plugin_dir_url(__FILE__).'assets/fi-css.css', array());
        wp_enqueue_script('fi_deletion_message', plugin_dir_url(__FILE__).'assets/message-deactivate.js', array());
    }

    private function getLogs()
    {

        $pn = intval(sanitize_text_field($_REQUEST['pn']));

        if ($pn <= 0) {
            $pn = 0;
            $offset = 0;
        } else {
            $offset = $pn * 20;
        }

        $args = array(
            "offset" => $offset,
            'numberposts' => 20,
            "post_type" => $this->customPostType
        );

        $posts = get_posts($args);

        $return = array();
        foreach ($posts as $item) {
            $return[] = (array)$item;
        }

        return $return;
    }

    private function interLog($id, $url = "")
    {

        $title = get_the_title($id);
        $md5 = md5($title);

        $myPost = array(
            'post_title' => $title,
            'post_name' => $md5,
            'post_content' => $url,
            'post_status' => 'publish',
            'post_author' => 1,
            'post_category' => 0,
            'post_type' => $this->customPostType,
            "post_parent" => $id
        );

        (wp_insert_post($myPost, true));
    }

    function getServiceAccounts()
    {
        $options = $this->getOption();
        $jsonFiles = $options['json_file'];
        return !is_array($jsonFiles) ? array() : $jsonFiles;
    }

    function setServiceAccountStatus($account, $status)
    {
        /* 60 seconds * 60 * 6 => 6 hours */
        set_site_transient("fi_" . $account, $status, 21600);


        /* Change Status */
        $getServiceAccounts = $this->getServiceAccounts();
        if (count($getServiceAccounts) > 0) {

            $currentData = $getServiceAccounts[$account];
            $currentData['status'] = $status;
            $getServiceAccounts[$account] = $currentData;

        }

        /* Get all Options */
        $options = $this->getOption();
        $options['json_file'] = $getServiceAccounts;

        update_option('fast_index_options', $options);

    }

    function getServiceAccountStatus($account)
    {
        return get_site_transient("fi_" . $account);
    }

    function getWaitingPosts()
    {
        global $wpdb;

        $options = $this->getOption();
        $options['post_type'] = is_array($options['post_type']) ? $options['post_type'] : array("post" => "1");
        $options['old_post_number'] = intval($options['old_post_number']);
        $limit = $options['old_post_number'] <= 0 ? 0 : $options['old_post_number'];

        if ($limit <= 0 or $options['status'] == 2) {
            return false;
        }

        $count = intval($this->countDailySent());

        if ($count >= $limit) {
            return false;
        }

        $limit = rand(0,ceil($limit/3));

        /* prapare the additional sql */
        $addSql = "";
        foreach ($options['post_type'] as $key => $value) {
            if ($value == "1") {
                $addSql .= " or p.post_type='{$key}' ";
            }
        }

        if ($addSql != "") {
            $addSql = "and (" . trim(trim($addSql), "or") . ")";
        }

        $wpPostsTable = $wpdb->prefix . "posts";

        $sql = "
        SELECT p.*,
        (select count(ID) from {$wpPostsTable} where {$wpPostsTable}.post_parent = p.ID and {$wpPostsTable}.post_type= %s ) AS content_id
        FROM  {$wpPostsTable} as p
        WHERE  p.post_status='publish'
        {$addSql}
        HAVING content_id<=0  order by p.ID desc limit %d
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare(
                $sql, array(
                    $this->customPostType,
                    $limit
                )
            )
        );

        return $results;

    }

    private function countDailySent()
    {
        global $wpdb;

        $wpPostsTable = $wpdb->prefix . "posts";

        $theDate = date("Y-m-d H:i:s", time() - 86400);

        $sql = "select count(ID) from {$wpPostsTable} where  {$wpPostsTable}.post_type=%s and post_date>='{$theDate}'";

        $results = $wpdb->get_var(
            $wpdb->prepare(
                $sql, array(
                    sanitize_text_field(strip_tags($this->customPostType))
                )
            )
        );

        return $results;

    }

    private function countWaitingPosts()
    {
        global $wpdb;

        $options = $this->getOption();


        /* prapare the additional sql */
        $addSql = "";
        foreach ($options['post_type'] as $key => $value) {
            if ($value == "1") {
                $addSql .= " or p.post_type='".sanitize_text_field(strip_tags($key))."}' ";
            }
        }

        if ($addSql != "") {
            $addSql = "and (" . trim(trim($addSql), "or") . ")";
        }

        $wpPostsTable = $wpdb->prefix . "posts";

        $sql = "
        SELECT count(p.ID),
        (select count(ID) from {$wpPostsTable} where {$wpPostsTable}.post_parent = p.ID and {$wpPostsTable}.post_type= %s ) AS content_id
        FROM  {$wpPostsTable} as p
        WHERE  p.post_status='publish'
        {$addSql}
        HAVING content_id<=0
        ";

        $results = $wpdb->get_var(
            $wpdb->prepare(
                $sql, array(
                    sanitize_text_field(strip_tags($this->customPostType))
                )
            )
        );

        return $results;

    }

    private function countSentPosts()
    {
        global $wpdb;

        $wpPostsTable = $wpdb->prefix . "posts";

        $sql = "select count(ID) from {$wpPostsTable} where post_type= %s";
        $results = $wpdb->get_var(
            $wpdb->prepare(
                $sql, array(
                        sanitize_text_field(strip_tags($this->customPostType))
                )
            )
        );

        return $results;

    }



    /* API - 3.RD PARTY */

    function sendRequest($id, $post_after = "", $post_before = "")
    {

        $options = $this->getOption();
        $post = get_post($id);

        $ref = strip_tags(sanitize_text_field($_SERVER['HTTP_REFERER']));
        $ref = $ref ==""?"none":$ref;

        $postStatus = $post->post_status;
        $postType = $post->post_type;

        if (strstr($ref, 'action=edit') and $postStatus == "publish") {
            $postStatus = "edit";
        }


        if ($options['status'] == 2 or $options['post_status'][$postStatus] != "1" or $options['post_type'][$postType] != "1") {
            return false;
        }

        $permalink = get_permalink($id);
        $indexingApi = new FastIndex_IndexingApi();

        $status = $indexingApi->sendRequest($permalink);

        if ($status == 200) {
            $this->interLog($id, $permalink);
        }

        return $status;

    }


    /* PAGES */

    function historyPage()
    {
        if (!is_admin()) {
            die;
        }

        $totalSent = esc_attr($this->countSentPosts());
        $totalWaitingSubmit = esc_attr($this->countWaitingPosts());
        $totalSubmitToday = esc_attr($this->countDailySent());

        $logs = $this->getLogs();
        include_once(plugin_dir_path(__FILE__) . '/view/history.php');
    }

    function settingsPage()
    {
        if (!is_admin()) {
            die;
        }

        $_POST = $this->fastIndexOptionsValidate($_POST);
        $_FILES = $this->fastIndexOptionsValidate($_FILES);

        $options = $this->getOption();
        $jsonFiles = $options['json_file'];

        if (count($_POST) > 1 and isset($_POST['submit'])) {

            $uploadedFiles = $this->jsonUploader();

            if($this->canI ==false) {
                if(is_array($uploadedFiles) and count($uploadedFiles)>0) {
                    $newFiles = $uploadedFiles;
                } else {
                    $newFiles = !is_array($jsonFiles) ? array() : $jsonFiles;
                }
            } else {
                $newFiles = !is_array($jsonFiles) ? $uploadedFiles : array_merge($jsonFiles, $uploadedFiles);
            }

            if($this->canI ==false) {
                $_POST['fast_index_options']['old_post_number'] = 0;
            }

            /* if deleting a json */
            if ($_POST['fast_index_options']['delete_json'] != "") {
                unset($newFiles[$_POST['fast_index_options']['delete_json']]);
            }

            $_POST['fast_index_options']['json_file'] = $newFiles;
            update_option('fast_index_options', $_POST['fast_index_options']);

            /* for not reload the page */
            $options = $this->getOption();
            $jsonFiles = $options['json_file'];
        }

        include_once(plugin_dir_path(__FILE__) . '/view/settings.php');

    }


    /* FIXED METHODS */

    function fiPostType()
    {

        register_post_type($this->customPostType,
            array(
                'labels' => array(
                    'name' => __('Fast Index Logs'),
                    'singular_name' => __('Fast Index Logs')
                ),
                'public' => false,
                'has_archive' => true,
            )
        );

    }

    function jsonUploader()
    {
        $files = $_FILES['jsons'];

        $newFiles = array();

        if (count($files) > 0) {

            $this->uploadFilter();

            $upload_overrides = array('test_form' => false);

            foreach ($files['name'] as $key => $value) {
                if ($files['name'][$key]) {

                    if ($files['type'][$key] != "application/json") {
                        continue;
                    }

                    $file = array(
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error' => $files['error'][$key],
                        'size' => $files['size'][$key]
                    );

                    $movefile = wp_handle_upload($file, $upload_overrides);

                    if ($movefile['file'] != "" and strlen($movefile['file']) > 10) {
                        $getFile = (array)json_decode(file_get_contents($movefile['file']));

                        /* if is valid mail */
                        if ($getFile['client_email'] != "" and filter_var($getFile['client_email'], FILTER_VALIDATE_EMAIL)) {
                            $newFiles[md5($getFile['client_email'])] = array("file" => $movefile['file'], "status" => 200, "mail" => $getFile['client_email']);

                            if($this->canI==false) {
                                break;
                            }

                        }

                    }

                }
            }

        }


        return $newFiles;

    }

    function uploadFilter()
    {
        add_filter(
            'upload_mimes',
            function ($types) {
                return array_merge($types, array('json' => 'application/json'));
            }
        );
    }

    function registerSettings()
    {
        /* sanitize the data */
        if (current_user_can('manage_options')) {
            register_setting('fast_index', 'fast_index_options', array(&$this, 'fastIndexOptionsValidate'));

        }
    }

    function postTypes($query = "")
    {

        if ($query == "") {
            $query = array('public' => true);
        }

        $postTypes = (array)get_post_types($query, 'objects');

        foreach ($postTypes as $item) {
            $item = (array)$item;
            if ($item['name'] == "attachment") {
                continue;
            }
            $types[] = $item;
        }

        return $types;

    }

    function fastIndexOptionsValidate($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $newData[$key] = $this->fastIndexOptionsValidate($value);
            }
            return $newData;
        } else {
            return sanitize_text_field(strip_tags($data));
        }

    }

    function fastIndexOptionsEscape($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $newData[$key] = $this->fastIndexOptionsEscape($value);
            }
            return $newData;
        }  else {
            return esc_attr(strip_tags($data));
        }

    }


    function adminInit()
    {
        if (current_user_can('manage_options')) {
            add_menu_page('Fast Index', 'Fast Index', 'manage_options', 'fast-index', array(&$this, 'settingsPage'));
            add_submenu_page('fast-index', 'History', 'History', 'manage_options', 'history', array(&$this, 'historyPage'));
        }

        if (!wp_next_scheduled('fiDailyCronHook')) {
            wp_schedule_event(time(), 'daily_fi', 'fiDailyCronHook');
        }

    }


    /* CRON */

    function cronSchedule($schedules)
    {

        $schedules['daily_fi'] = array(
            'interval' => 600,
            'display' => __('Every 1 minutes'),
        );

        return $schedules;
    }

    function fiDailyCron()
    {

        $posts = $this->getWaitingPosts();

        if ($posts != false) {
            foreach ($posts as $item) {
                $this->sendRequest($item->ID);
            }

        }

    }

    function fiDeleteAlert()
    {

        global $wpdb;

        if($_GET['fi_delete'] =="true") {

            $wpPostsTable = $wpdb->prefix . "posts";
            $wpdb->get_var($wpdb->prepare("delete from {$wpPostsTable} where post_type= %s",array($this->customPostType)));
            update_option('fast_index_options', array());
            unregister_post_type($this->customPostType);
            unregister_setting('fast_index', 'fast_index_options');

        }

    }

}

if ( ! function_exists( 'figi_fs' ) ) {
    // Create a helper function for easy SDK access.
    function figi_fs() {
        global $figi_fs;

        if ( ! isset( $figi_fs ) ) {
            // Activate multisite network integration.
            if ( ! defined( 'WP_FS__PRODUCT_11893_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_11893_MULTISITE', true );
            }

            // Include Freemius SDK.


            $figi_fs = fs_dynamic_init( array(
                'id'                  => '11893',
                'slug'                => 'fast-index-google-indexing',
                'premium_slug'        => 'fast-index',
                'type'                => 'plugin',
                'public_key'          => 'pk_4352cecbab080b84df64da3246477',
                'is_premium'          => true,
                'is_premium_only'     => false,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'menu'                => array(
                    'slug'           => 'fast-index',
                    'first-path'     => 'admin.php?page=fast-index',
                    'contact'        => false,
                    'support'        => false,
                    'network'        => true,
                ),
            ) );
        }

        return $figi_fs;
    }


}


$fastIndex = new FastIndex();

add_action('admin_menu', array(&$fastIndex, 'adminInit'), 99999999);
add_action('admin_init', array(&$fastIndex, 'registerSettings'), 99999999);
add_action('fiDailyCronHook', array(&$fastIndex, 'fiDailyCron'));
register_deactivation_hook(__FILE__, array(&$fastIndex, 'fiDeleteAlert'));

?>