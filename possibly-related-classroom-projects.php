<?php

/*
Plugin Name: Possibly Related Classroom Projects
Plugin URI: http://www.socialactions.com/labs/wordpress-donorschoose-plugin
Description: Possibly Related Classroom Projects recommends related classroom fundraising projects from DonorsChoose.org at the bottom of each blog post you publish. Related projects can be deactivated for a particular post by using the tag %NOCP% somewhere within its text. Possibly Related Classroom Projects is powered by Social Actions.
Version: 0.5.1
Author: Social Actions
Author URI: http://www.socialactions.com
*/

define('DONORSCHOOSE_VERSION', '0.5.1');
define('DONORSCHOOSE_API_URL', 'http://api.donorschoose.org/common/json_feed.html');
define('DONORSCHOOSE_API_KEY', 'vsexve8e3i');
define('DONORSCHOOSE_DB_TABLE', 'dc_cache' );
define('DONORSCHOOSE_IGNORE_LIST', 'http://www.socialactions.com/~wp/lists/ignore.txt');
define('DONORSCHOOSE_IGNORE_LIST_ID', -1);
define('DONORSCHOOSE_HOT_LIST', 'http://www.socialactions.com/~wp/lists/hot.txt');
define('DONORSCHOOSE_HOT_LIST_ID', -2);
define('DONORSCHOOSE_DEBUG', false);
define('DONORSCHOOSE_USE_EXTERNAL', false);

if ( !class_exists('RelatedActionsRequest') ) include_once('dc_request.php');
if ( !class_exists('RelatedActionsKeywords') ) include_once('dc_keywords.php');
if ( !class_exists('RelatedActionsCache') ) include_once('dc_cache.php');
if ( !class_exists('SocialActionsRedirect') ) include_once('dc_redirect.php');

global $wp_version;
if ( version_compare($wp_version, '2.7.0', '>') ) include_once('dc_settings.php');

register_activation_hook( __FILE__, 'dc_activate' );
register_deactivation_hook( __FILE__, 'dc_deactivate' );

add_filter( 'the_content', 'dc_display', 999 );
//add_action( 'save_post', 'dc_cache_related', 999 ); TODO: This return 0 keywords
add_action( 'deleted_post', 'dc_remove_related', 999);
add_action( 'wp_head',  'dc_get_style' );

if ( DONORSCHOOSE_DEBUG === true ) {
    include_once( 'dc_debug.php');
}

/*
 * Function called by WP to display related actions at bottom of post.
 * Only displays if post is single.
 *
 * @params string $content Post body to add related actions output to bottom of
 * @returns string $content returns modified content
 */
function dc_display($content) {
	global $post;

    if ( !is_single() ) {
        do_action('donorschoose_not_single');
        remove_filter('the_content', 'dc_display', 999);
        return $content;
    }

 	if ( !dc_ignore( $content ) ) {
        do_action('donorschoose_get_related', $post);
 		$content .= dc_get_related( $post );
 	} else {
        do_action('donorschoose_content_ignored', $post);
 		$content = dc_ignore( $content );
 	}

 	return $content;
}

/*
 * Function called by WP to add link to style sheet in head of document
 *
 * @returns bool
 */
function dc_get_style() {
    echo '<link rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/possibly-related-classroom-projects/dc_style.css" type="text/css" media="screen" />';
}

/*
 * Activates WP plugin by creating/updating table via
 * dbDelta() and initalizing plugin options with default
 * values
 *
 * @returns bool
 */
function dc_activate() {
    $sql = 	'CREATE TABLE ' . DONORSCHOOSE_DB_TABLE . ' (
            cache_id INT NOT NULL AUTO_INCREMENT ,
            post_id INT NOT NULL ,
            cached_result LONGTEXT NOT NULL ,
            last_update TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
            PRIMARY KEY  post_id (post_id),
            UNIQUE KEY cache_id (cache_id))';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    if ( dbDelta($sql) ) {
        dc_activate_options(dc_options());
        //Get black and white lists for keyword generation
        $ignore = dc_get_word_list( DONORSCHOOSE_IGNORE_LIST, DONORSCHOOSE_IGNORE_LIST_ID );
        $hot =  dc_get_word_list ( DONORSCHOOSE_HOT_LIST, DONORSCHOOSE_HOT_LIST_ID );
        return true;
    }

    return false;
}

function dc_deactivate() {
    global $wpdb;

    $sql = 'DROP TABLE ' . DONORSCHOOSE_DB_TABLE;
    $results = $wpdb->query($wpdb->prepare($sql));

    if ( $results ) {
        $options = dc_options();
        foreach ( $options as $option => $val ) {
            if ( !delete_option($option) ) {
                $fail = true;
            }
        }
        return ($fail) ? false : true;
    }

    return false;
}

function dc_options() {
    return $dc_options = array (
        'action_limit' => 3,
        'keyword_limit' => 3,
        'post_title_weight' => 1.2,
        'post_tag_weight' => 1.4,
        'post_content_weight' => 1,
        'post_hot_weight' => 1.3,
        'max_cache_age' => 12,
        'include_title' => true,
        'include_tag' => true,
        'include_content' => true,
        'version' => DONORSCHOOSE_VERSION);
}

/*
 * Sets default values for all plugin options during plugin
 * activation
 *
 * @returns bool
 */
function dc_activate_options($dc_options) {
	foreach ($dc_options as $option => $val) {
		if ( !get_option( 'dc_'.$option ) ) {
			add_option( 'dc_'.$option, $val );
		}
	}

    update_option('dc_version', DONORSCHOOSE_VERSION);
    return true;
}

function dc_cache_related( $wp_post ) {
    $wp_post = dc_get_post_ID($wp_post);

    if ( !$wp_post )
        return false;
    do_action('donorschoose_cache_related');
    return dc_get_related($wp_post, false);
}

function dc_remove_related($wp_post) {
    $wp_post = dc_get_post_ID($wp_post);
    $dc_cache = new RelatedActionsCache( DONORSCHOOSE_DB_TABLE, $wp_post );

    if ( $dc_cache->exists() ) {
        return ($dc_cache->remove($wp_post)) ? true : false;
    }

    do_action('donorschoose_remove_related');
    return NULL;
}

/*
 * Workhorse of plugin. Calls on various external frameworks to recall results
 * from cache, or define keywords and request results from DC API
 *
 * @params object $wp_post WP post to get related content for
 * @returns string $results raw html of related content for post
 */
function dc_get_related( $wp_post, $random_failsafe = true ) {
    $wp_post = dc_get_post_ID($wp_post);

    if ( !$wp_post )
        return "";

    $dc_cache = new RelatedActionsCache( DONORSCHOOSE_DB_TABLE, $wp_post );
    if ( $dc_cache->exists() && $dc_cache->lastUpdate() <= get_option('dc_max_cache_age') ) {
        do_action('donorschoose_from_cache', $wp_post);
        return $dc_cache->get();
    }

    //Get black and white lists for keyword generation
    $ignore = dc_get_word_list( DONORSCHOOSE_IGNORE_LIST, DONORSCHOOSE_IGNORE_LIST_ID );
    $hot =  dc_get_word_list ( DONORSCHOOSE_HOT_LIST, DONORSCHOOSE_HOT_LIST_ID );

    //Begin generating keywords
    $keywords = new RelatedActionsKeywords($ignore, $hot, get_option('dc_post_hot_weight'));
    $areas = dc_get_included_areas();

    if (!$areas) {
        do_action('donorschoose_no_content', $wp_post);
        return "";
    }
    
    foreach ( $areas as $area => $weight ) {
        $keywords->addKeywords( dc_get_area_text( $area ), $weight );
    }

    $query = array( 'APIKey' => DONORSCHOOSE_API_KEY,
                    'max' => intval(get_option('dc_action_limit')),
                    'keywords' => $keywords->makeList(' OR ', get_option('dc_keyword_limit')));
    do_action('donorschoose_using_keywords', $query['keywords']);
    $results = dc_fetch(DONORSCHOOSE_API_URL, $query, 'json', 'http');
    
    if ( count( $results->proposals ) < 1 ) {
        do_action('donorschoose_no_related', $post, $random_failsafe);
        return ($random_failsafe) ? $dc_cache->random() : "";
    }
    
    $results = dc_list_actions( $results->proposals );

    if ( !$results ) {
        do_action('donorschoose_no_related', $post, $random_failsafe);
        return ($random_failsafe) ? $dc_cache->random() : "";
    }

    $dc_cache->set( $results );
    do_action('donorschoose_new_related', $wp_post);
    return $results;
}

/*
 * Gets content area and weightings for keyword generation. Without admin
 *
 * interface, mostly worthless function.
 * @returns array $areas assoc array of content area and its weighting
 */
function dc_get_included_areas() {

	$areas = array();

	if ( get_option( 'dc_include_title' ) )
		$areas['title'] = get_option( 'dc_post_title_weight' );

	if ( get_option( 'dc_include_tag' ) )
		$areas['tag'] = get_option( 'dc_post_tag_weight' );

	if ( get_option( 'dc_include_content' ) )
		$areas['content'] = get_option( 'dc_post_content_weight' );

 	return $areas;
}

/*
 * Finds and returns text of a given area, like tags, title, or post body
 *
 * @params string $area a given area's name
 * @returns string text of a given area
 */
function dc_get_area_text( $area ) {
	global $post;

	if ( $post && is_object($post) ) {
        switch ($area) {
            case 'content':
                return $post->post_content;
                break;
            case 'title':
                return $post->post_title;
                break;
            case 'tag':
                $tags = wp_get_post_tags( $post->ID );
                if ( count($tags) < 1 )
                    return "";
                foreach ($tags as $tag) {
                    $postTags .= $tag->name . " ";
                }

                return $postTags;
                break;
        }
    }

    return "";
}

/*
 * Formats JSON-decoded response from API into a HTML <ul></ul>
 *
 * @params array $results multi-dimensional array of results from API
 * @returns string $html raw html of related content
 */
function dc_list_actions( $results )  {

	if ( !$results )
		 return false;

	$html = "<div class='raWrapper'>";
	$html .= "<span class='raHeader'>Possibly Related Classroom Projects From
				<a href='http://www.DonorsChoose.org'>DonorsChoose.org</a></span>\n";

	foreach ( $results as $result ) {
		list($url) = explode( "&", $result->proposalURL );
		$urlTitle = htmlentities( $result->shortDescription );
		$onclick = "this.href=\"" . dc_make_redirect( $result->proposalURL ) . "\"";

		if ( strlen( $result->title >= 85 ) ) {
			$linkText = substr( $result->title, 0, 82 ) . "...";
		} else {
			$linkText = $result->title;
		}

		$actions[] = "<li><a href='$url' title='$urlTitle' onclick='$onclick'>$linkText</a></li>\n";
	}

	$html .= "<ul>" . implode("\n", $actions) . "</ul>\n";
	$html .= "<span class='raTagLine'>Powered by <a href='http://www.socialactions.com'>Social Actions</a></span>";
	$html .= "</div>\n";
	return $html;
}

function dc_get_word_list( $list, $cache_ID ) {
	$wlCache = new RelatedActionsCache( DONORSCHOOSE_DB_TABLE, $cache_ID );

	if ( $wlCache->isValid(72) ) {
        do_action('donorschoose_from_cache', $list);
		return $wlCache->get();
	} else {
        $query = "";
        $results = dc_fetch($list, $query, 'txt', 'httpfile');

		if ( !$results ) {
            do_action('donorschoose_no_word_list', $list);
			if ( $wlCache->exists() )
				return $wlCache->get();
			return array();
		}

        do_action('donorschoose_new_related', $list);
        $wlCache->set($results);

		return $results;
	}

}

/*
 * Makes redirect to SA site to track click-throughs for system improvements
 *
 * @params string $url url to make redirect out of
 * @returns string redirect url
 */
function dc_make_redirect( $url )  {
	if ( !$url )
		return false;

	$redirect = new SocialActionsRedirect( "http://www.socialactions.com/~wp/redirect.php" );
	$redirect->setTarget( $url );
	$redirect->addParam( "r", $_SERVER['SERVER_NAME'] );


	return $redirect->getRedirect();
}

/*
 * Filter function used to not display related content on a given page
 *
 * @params string $content text content of a given blog post
 * @returns string $content parsed text to remove tag if present
 */
function dc_ignore( $content ) {

	if ( preg_match( "/%NOCP%/i", $content ) ) {
		$content = preg_replace( "/%NOCP%/i", "", $content );
		return $content;
	}
	return false;
}

function dc_fetch($location, $query, $format='json', $type='http') {
    if ( function_exists('wp_remote_post') 
        && function_exists('wp_remote_retrieve_body')
        && !DONORSCHOOSE_USE_EXTERNAL ) {
        $results = dc_builtin_fetch($location, $query, $format, $type);
    } else {
        $results = dc_external_fetch($location, $query, $format, $type);
    }

    return $results;
}

function dc_external_fetch($location, $query='', $format='json', $type='http') {
    do_action('donorschoose_fetch_related', 'external');
    $request = new RelatedActionsRequest( $type, $format );
    $request->setRequestURI( $location );
    $request->formQuery( $query );
    if ( !$request->doRequest() ) {
        return false;
    }

    return $request->decodeResponse();
}

function dc_builtin_fetch($location, $query='', $type='json') {
    do_action('donorschoose_fetch_related', 'builtin');
    
    if ( $query && is_array($query) ) {
        $query_string = '';
        foreach ( $query as $key => $value ) {
            $query_string .= '&' . urlencode($key) . '=' . urlencode($value);
        }
    } else {
        $query_string = $query;
    }

    $request = wp_remote_post($location . '?' . $query_string);
    $request = wp_remote_retrieve_body($request);

    if ( !$request )
        return "";

    if ( $type == 'json' ) {
        require_once('JSON.php');
        $json = new Services_JSON();
        return $json->decode($request);
    } else {
        return $request;
    }

}

function dc_get_post_ID( $post ) {
    if ( is_object($post) ) {
        return $post->ID;
    } else {
        return $post;
    }
}

?>
