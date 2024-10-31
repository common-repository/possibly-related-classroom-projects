<?php

define( 'DONORSCHOOSE_LOG_NOTICE', 'notice' );
define( 'DONORSCHOOSE_LOG_WARN', 'warn' );
define( 'DONORSCHOOSE LOG_ERROR', 'error' );

add_action( 'donorschoose_get_related', 'dc_log_get_related' ); //wp_post
add_action( 'donorschoose_content_ignored', 'dc_log_content_ignored' ); //wp_post
add_action( 'donorschoose_cache_related', 'dc_log_cache_related' ); //wp_post
add_action( 'donorschoose_remove_related', 'dc_log_remove_related' ); //wp_post
add_action( 'donorschoose_not_single', 'dc_log_not_single' ); //none
add_action( 'donorschoose_from_cache', 'dc_log_from_cache' ); //wp_post or list
add_action( 'donorschoose_no_content', 'dc_log_no_content' ); //wp_post
add_action( 'donorschoose_no_related', 'dc_log_no_related' ); //wp_post
add_action( 'donorschoose_new_related', 'dc_log_new_related' ); //wp_post or list
add_action( 'donorschoose_no_word_list', 'dc_log_no_word_list' ); //list
add_action( 'donorschoose_fetch_related', 'dc_log_fetch_related' ); //type
add_action( 'donorschoose_using_keywords', 'dc_log_using_keywords' ); //query string

function dc_log( $type, $content, $id = "" ) {
    $id = dc_get_post_ID($id);
    $id = ( $id ) ? $id : "";
    $msg = '[' . $type . '] DONORSCHOOSE WP PLUGIN: ' . $content . ' ' . $id;
    error_log($msg);
}

function dc_log_using_keywords($str) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'using keywords:', $str);
}

function dc_log_get_related($id) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'getting related items during the viewing of post', $id);
}

function dc_log_content_ignored($id) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, '%NOCP% flag found, skipping related items for post', $id);
}

function dc_log_cache_related($id) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'getting related items during the saving of post', $id);
}

function dc_log_remove_related($id) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'removing cached related items for post', $id);
}

function dc_log_not_single($id) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'not a single blog post, skipping all actions', $id);
}

function dc_log_from_cache($id) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'fresh cache hit for post', $id);
}

function dc_log_no_content($id) {
    dc_log(DONORSCHOOSE_LOG_ERROR, 'no content to find keywords for in post', $id);
}

function dc_log_no_related($id, $random = false) {
    dc_log(DONORSCHOOSE_LOG_ERROR, 'no related items found' .
          ($random) ? ", using random existing cache" : "", $id);
}

function dc_log_new_related($id) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'new related items found and cached for post', $id);
}

function dc_log_no_word_list($list) {
    dc_log(DONORSCHOOSE_LOG_WARN, 'update word list could not be found', $list);
}

function dc_log_fetch_related($type) {
    dc_log(DONORSCHOOSE_LOG_NOTICE, 'fetching remote related items with libraries that are', $type);
}


?>
