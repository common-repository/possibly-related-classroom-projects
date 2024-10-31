<?php
function dc_settings_init() {
    add_settings_section(
        'dc_settings_section', 'Possibly Related Classroom Projects settings',
        'dc_settings_section', 'media'
    );

    add_settings_field(
        'dc_action_limit', __('Number of projects to display'),
        'dc_text_setting_action', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_keyword_limit', __('Number of keywords to find'),
        'dc_text_setting_keyword', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_post_title_weight', __('Keyword weight for post titles'),
        'dc_text_setting_title', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_post_tag_weight', __('Keyword weight for tags'),
        'dc_text_setting_tag', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_post_content_weight', __('Keyword weight for post content'),
        'dc_text_setting_content', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_post_hot_weight', __('Weight for "hot" keywords'),
        'dc_text_setting_hot', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_max_cache_age', __('Max cache age (in hours) for related classroom projects'),
        'dc_text_setting_cache', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_include_title', __('Include post title when finding keywords'),
        'dc_checkbox_setting_title', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_include_tags', __('Include post tags when finding keywords'),
        'dc_checkbox_setting_tag', 'media', 'dc_settings_section'
    );
    add_settings_field(
        'dc_include_content', __('Include post content when finding keywords'),
        'dc_checkbox_setting_content', 'media', 'dc_settings_section'
    );

    register_setting('media','dc_action_limit');
    register_setting('media','dc_keyword_limit');
    register_setting('media','dc_post_title_weight');
    register_setting('media','dc_post_tag_weight');
    register_setting('media','dc_post_content_weight');
    register_setting('media','dc_post_hot_weight');
    register_setting('media','dc_max_cache_age');
    register_setting('media','dc_include_title');
    register_setting('media','dc_include_tag');
    register_setting('media','dc_include_content');

}

function dc_settings_section() {
    echo "<p>Please refer to the FAQ before changing these settings.</p>";
}

function dc_checkbox_setting( $id ) {
    $bool = ( get_option($id) ) ? 'checked="true"' : '';

    echo '<input type="checkbox" ' . $bool . ' name="' . $id .
         '" id="' . $id . '_checkbox" value="' . $id . '" />';
}

function dc_text_setting( $id ) {
    $val = get_option($id);

    echo '<input type="text" name="' . $id . '" id="' . $id . '_text" ' .
         'value="' . $val . '" />';
}

function dc_text_setting_action() {
    dc_text_setting('dc_action_limit');
}

function dc_text_setting_keyword() {
    dc_text_setting('dc_keyword_limit');
}

function dc_text_setting_title() {
    dc_text_setting('dc_post_title_weight');
}

function dc_text_setting_tag() {
    dc_text_setting('dc_post_tag_weight');
}

function dc_text_setting_content() {
    dc_text_setting('dc_post_content_weight');
}

function dc_text_setting_cache() {
    dc_text_setting('dc_max_cache_age');
}

function dc_text_setting_hot() {
    dc_text_setting('dc_post_hot_weight');
}

function dc_checkbox_setting_title() {
    dc_checkbox_setting('dc_include_title');
}

function dc_checkbox_setting_tag() {
    dc_checkbox_setting('dc_include_tag');
}

function dc_checkbox_setting_content() {
    dc_checkbox_setting('dc_include_content');
}

add_action('admin_init', 'dc_settings_init');
?>
