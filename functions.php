<?php

function presspermit() {
    return \PublishPress\Permissions::instance();
}

/**
 * Sanitizes a string entry
 *
 * Keys are used as internal identifiers. Uppercase or lowercase alphanumeric characters,
 * spaces, periods, commas, plusses, asterisks, colons, pipes, parentheses, dashes and underscores are allowed.
 *
 * @param string $entry String entry
 * @return string Sanitized entry
 */
function pp_permissions_sanitize_entry( $entry ) {
    $entry = preg_replace( '/[^a-zA-Z0-9 \.\,\+\*\:\|\(\)_\-]/', '', $entry );
    return $entry;
}

/*
 * Same as sanitize_key(), but without applying filters
 */
function pp_permissions_sanitize_key( $key ) {
    $raw_key = $key;
    $key     = strtolower( $key );
    $key     = preg_replace( '/[^a-z0-9_\-]/', '', $key );
    
    return $key;
}

function presspermitPluginPage()
{
    static $pp_plugin_page = null;

    if (is_null($pp_plugin_page)) {
        $pp_plugin_page = (is_admin() && isset($_REQUEST['page']) && (0 === strpos($_REQUEST['page'], 'presspermit-')))
            ? sanitize_key($_REQUEST['page'])
            : false;
    }

    return $pp_plugin_page;
}

function presspermit_is_preview() {
    if (!$is_preview = is_preview()) {
        if (defined('ELEMENTOR_VERSION')) {
           $is_preview = !empty($_REQUEST['elementor-preview']);
        } elseif (defined('ET_CORE')) {
            $is_preview = !empty($_REQUEST['et_fb']);
        }
    }

    return apply_filters('presspermit_is_preview', $is_preview);
}