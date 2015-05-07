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
    if(!isset($_POST['id']) || $_POST['id']==''){
        die(json_encode(array('success'=>0,'message'=>'Please select a file.')));
    }

    $file_id = $_POST['id'];
    if(!get_post_meta($file_id, 'track_file', true)){
        die(json_encode(array('success'=>1,'message'=>'This file is not being tracked. Carry on my wayward son!')));
    }


    $day = date('Ymd');
    $user = $_SERVER['REMOTE_ADDR'];

    $details = get_post_meta($file_id, 'download-activity', true);

    if(!$details){
        $details = array();
        $count = 0;
    }else{
        //$details = unserialize($details);
        $total = $details[$user]['total'];
        $count = $details[$user][$day]['count'];
        if(!$count){
            $count = 0;
        }
        if(!total){
            $total = 0;
        }
    }

    $count++;

    //$details[$day] = array();
    $details[$user][$day]['count'] = $count;
    $details[$user]['total'] = $total+1;

    $update = update_post_meta($file_id, 'download-activity', $details);

    if($update){
        die(json_encode(array('success'=>1,'message'=>'Yeh... I think that went ok!', 'debug-in'=>$details, 'count'=>$count)));
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

    $attachments = get_posts(array(
        'post_type'=>'attachment',
        'meta_key' => 'track_file',
        'meta_value' => true,
        'posts_per_page' => -1
    ));

    $content = array();
    $i = 0;
    foreach($attachments as $item){
        $content[$i]['id'] = $item->ID;
        $content[$i]['name'] = $item->post_title;
        $content[$i]['data'] = get_post_meta($item->ID, 'download-activity');
        $i++;
    }

    die(json_encode(array('success'=>1, 'message'=>'Retrieved Records', 'data'=>$content)));


}
