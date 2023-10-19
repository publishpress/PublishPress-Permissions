<?php
function presspermit() {
    require_once(__DIR__ . '/classes/PublishPress/Permissions.php');
    return \PublishPress\Permissions::instance();
}

function presspermitPluginPage()
{
    static $pp_plugin_page = null;

    if (is_null($pp_plugin_page)) {
        $pp_plugin_page = (is_admin() && \PressShack\LibWP::REQUEST_key_match('page', 'presspermit-'))
            ? \PressShack\LibWP::REQUEST_key('page')
            : false;
    }

    return $pp_plugin_page;
}

function presspermit_is_preview() {
    global $wp_query;

	if (isset($wp_query)) {
        $is_preview = is_preview();
    } else {
        $is_preview = !\PressShack\LibWP::empty_REQUEST('preview');
    }

    if (!$is_preview) {
        if (defined('ELEMENTOR_VERSION')) {
           $is_preview = !\PressShack\LibWP::empty_REQUEST('elementor-preview');
        } elseif (defined('ET_CORE')) {
            $is_preview = !\PressShack\LibWP::empty_REQUEST('et_fb');
        }
    }

    return apply_filters('presspermit_is_preview', $is_preview);
}
