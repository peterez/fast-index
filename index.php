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

error_reporting(0);
if (!defined('ABSPATH') or !defined('WPINC')) {
    die;
}


include_once ABSPATH . 'wp-admin/includes/file.php';
include_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
include_once(plugin_dir_path(__FILE__) . '/helpers/indexingApi.php');



class FastIndex
{
    private $customPostType = "fi_log";

    function __construct()
    {
        add_action('init', array($this, 'fiPostType'));
        add_action('post_updated', array($this, 'sendRequest'));
        add_filter('cron_schedules', array($this,'cronSchedule') );

        if (!wp_next_scheduled('dailyFiCron')) {
            wp_schedule_event( time(), 'daily', array($this,'dailyCron') );
        }

        add_action( 'dailyFiCron', array($this,'dailyCron') );


    }

    /* ASSETS */

    private function getLogs()
    {

        $_REQUEST['pn'] = intval($_REQUEST['pn']);
        if ($_REQUEST['pn'] <= 0) {
            $_REQUEST['pn'] = 0;
            $offset = 0;
        } else {
            $offset = $_REQUEST['pn'] * 20;
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
        $options = get_option('fast_index_options');
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
        $options = get_option('fast_index_options');
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

        $options = get_option('fast_index_options');
        $options['post_type'] = is_array($options['post_type']) ? $options['post_type'] : array("post" => "1");
        $options['old_post_number'] = intval($options['old_post_number']);
        $limit = $options['old_post_number']<=0?0:$options['old_post_number'];

        if($limit<=0 or $options['status'] ==2) {
            return false;
        }

        /* prapare the additional sql */
        $addSql = "";
        foreach($options['post_type'] as $key => $value) {
            if($value =="1") {
                $addSql .= " or p.post_type='{$key}' ";
            }
        }

        if($addSql !="") { $addSql = "and (".trim(trim($addSql),"or").")"; }

        $wpPostsTable = $wpdb->prefix . "posts";
        $limit = 5;

        $sql = "
        SELECT p.*,
        (select count(ID) from {$wpPostsTable} where {$wpPostsTable}.post_parent = p.ID ) AS content_id
        FROM  {$wpPostsTable} as p
        WHERE  p.post_status='publish'
        {$addSql}
        HAVING content_id<=0  order by p.ID desc limit $limit
        ";

        $results = $wpdb->get_results($wpdb->prepare($sql));

        return $results;

    }

    function dailyCron() {


        $posts = $this->getWaitingPosts();


        print_R(count($posts));

        if($posts !=false) {
            foreach($posts as $item) {
               $sonuc = $this->sendRequest($item->ID);



            }

        }

    }

    /* API - 3.RD PARTY */

    function sendRequest($id,$post_after="", $post_before="")
    {

        $options = get_option('fast_index_options');
        $options['post_status'] = is_array($options['post_status']) ? $options['post_status'] : array("publish" => "1", "edit" => "1");
        $options['post_type'] = is_array($options['post_type']) ? $options['post_type'] : array("post" => "1");
        $post = get_post($id);

        $postStatus = $post->post_status;
        $postType = $post->post_type;

        if(strstr($_SERVER['HTTP_REFERER'],'action=edit') and $postStatus == "publish") {
            $postStatus = "edit";
        }

        if($options['status'] ==2 or $options['post_status'][$postStatus] !="1" or $options['post_type'][$postType] !="1") {
            return false;
        }

        $permalink = get_permalink($id);
        $indexingApi = new IndexingApi();

        $status = $indexingApi->sendRequest($permalink);

        if ($status == 200) {
            $this->interLog($id, $permalink);
        }

        return $status;

    }


    /* PAGES */

    function historyPage()
    {
        $logs = $this->getLogs();
        include_once(plugin_dir_path(__FILE__) . '/view/history.php');
    }

    function settingsPage()
    {

        $options = get_option('fast_index_options');
        $jsonFiles = $options['json_file'];

        if (count($_POST) > 1 and isset($_POST['submit'])) {

            $uploadedFiles = $this->jsonUploader();

            $newFiles = !is_array($jsonFiles) ? $uploadedFiles : array_merge($jsonFiles, $uploadedFiles);

            /* if deleting a json */
            if ($_POST['fast_index_options']['delete_json'] != "") {
                unset($newFiles[$_POST['fast_index_options']['delete_json']]);
            }

            $_POST['fast_index_options']['json_file'] = $newFiles;
            update_option('fast_index_options', $_POST['fast_index_options']);

            /* for not reload the page */
            $jsonFiles = $newFiles;
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
        $siteUrl = site_url();

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
        register_setting('fast_index', 'fast_index_options', array(&$this, 'fastIndexOptionsValidate'));

    }

    function postTypes()
    {

       $query = array('public' => true);

        $postTypes = (array)get_post_types($query, 'objects');

        foreach ($postTypes as $item) {
            $item = (array)$item;
            if ($item['name'] == "attachment" or $item['name'] == $this->customPostType) {
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
            return sanitize_text_field($data);
        }

    }

    function adminInit()
    {
        add_menu_page('Fast Index', 'Fast Index', 'manage_options', 'fast-index', array(&$this, 'settingsPage'));
        add_submenu_page('fast-index', 'History', 'History', 'manage_options', 'history', array(&$this, 'historyPage'));
    }

    function cronSchedule( $schedules ) {

            $schedules['daily'] = array(
                'interval' => 86400,
                'display' => __('Once every 1 minutes'));


        return $schedules;
    }



}


$fastIndex = new FastIndex();

add_action('admin_menu', array(&$fastIndex, 'adminInit'), 99999999);
add_action('admin_init', array(&$fastIndex, 'registerSettings'), 99999999);


?>