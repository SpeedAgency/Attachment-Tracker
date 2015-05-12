<?php
header("Access-Control-Allow-Origin:*");
/*
Plugin Name: Attachment Tracker Collector
Plugin URI: http://speed.agency
Description: Collect data on who is downloading your attachments. Add data-id="{fileID}" and data-monitor="true" to any attachment you wish to track in your template.
Author: Speed Agency
Version: 1.0
Author URI: http://speed.agency
License: GPLv3
Text Domain: speed
*/

require('speed-options.php');

global $sp_at_db_version;
$sp_at_db_version = '1.0';

function sp_at_install(){
    global $wpdb;
	global $sp_at_db_version;

	$table_name = $wpdb->prefix . 'track_ip';

	$charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        sp_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        sp_ip varchar(55) NOT NULL,
        sp_data text NOT NULL,
        sp_post_id mediumint(9) NOT NULL,
        UNIQUE KEY id (id)
    );";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'sp_at_db_version', $sp_at_db_version );
}

register_activation_hook(__FILE__, 'sp_at_install');






function sp_at_scripts(){

    wp_register_script('sp-at-track', plugin_dir_url(__FILE__).'track.js', array('jquery'));
    wp_localize_script('sp-at-track', 'ajaxurl', admin_url('admin-ajax.php'));

    wp_enqueue_script('sp-at-track');

}
add_action('wp_enqueue_scripts', 'sp_at_scripts');


add_action('wp_ajax_record_click', 'record_click');
add_action('wp_ajax_nopriv_record_click', 'record_click');

function record_click(){
    //die('raaa');
    global $wpdb;

    if(!isset($_POST['id']) || $_POST['id']==''){
        die(json_encode(array('success'=>0,'message'=>'Please select a file.')));
    }

    $file_id = $_POST['id'];
    if(!get_post_meta($file_id, 'track', true)){
        die(json_encode(array('success'=>1,'message'=>'This file is not being tracked. Carry on my wayward son!')));
    }


    $day = date('Ymd');
    $user = $_SERVER['REMOTE_ADDR'];

    //$details = get_post_meta($file_id, 'download-activity', true);
    $table = $wpdb->prefix.'track_ip';
    $details = $wpdb->get_results(
    "
        SELECT id, sp_data
        FROM $table
        WHERE sp_ip = '$user'
            AND sp_post_id = $file_id
    "
    );

    if(!$details){
        $data = array();
        $count = 0;
        $which = 'insert';
    }else{
        $which = 'update';
        $data = unserialize($details[0]->sp_data);
        $total = $data['total'];
        $count = $data[$day]['count'];
        if(!$count){
            $count = 0;
        }
        if(!total){
            $total = 0;
        }
    }

    //print_r($data);

    $count++;

    $data[$day]['count'] = $count;
    $data['total'] = $total+1;

    $updateval = array(
        'sp_ip' => $user,
        'sp_time' => date('Y-m-d H:i:s'),
        'sp_data' => serialize($data),
        'sp_post_id' => $file_id
    );

    if($which=='update'){
        $update = $wpdb->update(
            $table,
            $updateval,
            array('id'=>$details[0]->id)
        );
    }else{
        $update = $wpdb->insert(
            $table,
            $updateval
        );
    }

    // Check to see how many files this IP has downloaded. If more than the minimum trigger amount, email the mailto.

    $check = $wpdb->get_results(
        "SELECT sp_ip
        FROM $table
        WHERE sp_ip = '$user'"
    );

    if(count($check)>=get_option('sp_at_min', 5)){
        wp_mail(get_option('sp_at_mailto', get_option('admin_email')), 'Download Alert from '.get_bloginfo('name'), 'The IP Address: '.$user.' has downloaded '.count($check).' Documents.');
        $ping = "Ping";
    }else{
        $ping = "pong";
    }


    if($update){
        die(json_encode(array('success'=>1,'message'=>'Yeh... I think that went ok!', 'debug-in'=>$details, 'count'=>$count, 'ping'=>$ping)));
    }else{
        die(json_encode(array('success'=>1,'message'=>'No idea why that failed :/', 'debug-in'=>$details, 'count'=>$count)));
    }

}


add_action('wp_ajax_collect_data', 'collect_data');
add_action('wp_ajax_nopriv_collect_data', 'collect_data');

function collect_data(){

    // Check to see if the site is sending an API Key. It should match the API Key stored in the site options.
    // If it doesnt, polietly kick them out.

    if(!isset($_POST['key']) || $_POST['key']!=get_option('sp_at_api_key')){
        die(json_encode(array('success'=>0, 'message'=>'API key incorrect. Please change and try again')));
    }
    if(isset($_POST['sort']) && $_POST['sort']!=''){
        $orderby = $_POST['sort'];
    }else{
        $orderby = 'sp_post_id';
    }

    //print_r($_POST['sort']);

    global $wpdb;

    $return_sub = '';
    $table = $wpdb->prefix.'track_ip';
    $output = array();

    if($orderby == 'sp_post_id'){

        $return_sub = 'IP Address';

        $attachments = get_posts(array(
            'post_type'=>'attachment',
            'meta_key' => 'track',
            'meta_value' => true,
            'posts_per_page' => -1
        ));


        foreach($attachments as $item){

            $sql = "SELECT * FROM $table WHERE sp_post_id = $item->ID ORDER BY sp_post_id DESC";

            $results = $wpdb->get_results($sql);


            $data = array();
            $dump = array();
            $totalCount = 0;
            foreach($results as $r){

                $sp_data = unserialize($r->sp_data);

                $content = array();
                $content['ip'] = $r->sp_ip;
                $content['data'] = $sp_data;
                $totalCount += $sp_data['total'];
                array_push($data, $content);

                $dump['recent'] = $r->sp_time;
                $dump['post_id'] = $r->sp_post_id;
                $dump['post_title'] = get_the_title($r->sp_post_id);

            }
            if(count($data)!=0){
                $dump['content'] = $data;
                $dump['total'] = $totalCount;
            }

            /*$dump = array(
                'recent' => $r->sp_time,
                'ip' => $r->sp_ip,
                'data' => unserialize($r->sp_data),
                'post_id' => $r->sp_post_id,
                'post_title' => get_the_title($r->sp_post_id)
            );*/
            if(count($dump)!=0){
                array_push($output, $dump);
            }
        }
    }else{

        $return_sub = 'File Name';

        $iplist = $wpdb->get_results("SELECT sp_ip FROM $table GROUP BY sp_ip");
        //print_r($iplist);
        if($iplist){
            foreach($iplist as $ip){
                $sql = "SELECT * FROM $table WHERE sp_ip = '$ip->sp_ip' ORDER BY sp_ip";
                $results = $wpdb->get_results($sql);

                $data = array();
                $dump = array();
                $totalCount = 0;

                foreach($results as $r){
                    $sp_data = unserialize($r->sp_data);
                    $content = array();
                    $content['ip'] = get_the_title($r->sp_post_id);
                    $content['data'] = $sp_data;
                    $totalCount += $sp_data['total'];
                    array_push($data, $content);

                    $dump['recent'] = $r->sp_up;
                    $dump['post_id'] = $r->sp_ip;
                    $dump['post_title'] = $r->sp_ip;
                }
                if(count($data)!=0){
                    $dump['content'] = $data;
                    $dump['total'] = $totalCount;
                }

                if(count($dump)!=0){
                    array_push($output, $dump);
                }

            }
        }


    }

    if(count($output)!=0){
        die(json_encode(array('success'=>1, 'message'=>'Retrieved Records', 'data'=>$output, 'return_sub'=>$return_sub)));
    }else{
        die(json_encode(array('success'=>0, 'message'=>'Something has gone terribly wrong')));
    }

}
