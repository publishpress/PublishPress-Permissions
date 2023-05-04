<?php

function presspermit() {
    require_once(__DIR__ . '/classes/PublishPress/Permissions.php');
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

function presspermit_empty_REQUEST($var = false) {
    if (false === $var) {
        return empty($_REQUEST);
    } else {
        return empty($_REQUEST[$var]);
    }
}

function presspermit_is_REQUEST($var, $match = false) {
    if (false === $match) {
        return isset($_REQUEST[$var]);
        
    } elseif (is_array($match)) {
        return (isset($_REQUEST[$var]) && in_array($_REQUEST[$var], $match));
    } else {
        return (isset($_REQUEST[$var]) && ($_REQUEST[$var] == $match));
    }
}

function presspermit_REQUEST_key($var) {
    if (empty($_REQUEST[$var])) {
        return '';
    }

    return (is_array($_REQUEST[$var])) ? array_map('sanitize_key', $_REQUEST[$var]) : sanitize_key($_REQUEST[$var]);
}

function presspermit_REQUEST_key_match($var, $match, $args = []) {
    $args = (array) $args;
    
    $match_type = (!empty($args['match_type'])) ? $args['match_type'] : 'starts';

	$matched = false;
    
    $request_key = presspermit_REQUEST_key($var);

    if (is_array($request_key)) {
        $matched = false;
    } else {
    	switch ($match_type) {
    		case 'contains':
    			$matched = (false !== strpos($request_key, $match));
    			break;
    			
    		default: // 'starts'
        		$matched = (0 === strpos($request_key, $match));
    	}
    }

	return $matched;
}

function presspermit_REQUEST_int($var) {
    return (!empty($_REQUEST[$var])) ? intval($_REQUEST[$var]) : 0;
}

function presspermit_REQUEST_var($var) {
    return (!empty($_REQUEST) && !empty($_REQUEST[$var])) ? $_REQUEST[$var] : '';
}

function presspermit_empty_POST($var = false) {
    if (false === $var) {
        return empty($_POST);
    } else {
        return empty($_POST[$var]);
    }
}

function presspermit_is_POST($var, $match = false) {
    if (empty($_POST)) {
        return false;
    }
    
    if (false == $match) {
        return (isset($_POST[$var]));
    
    } elseif (is_array($match)) {
        return (isset($_POST[$var]) && in_array($_POST[$var], $match));
    } else {
        return (isset($_POST[$var]) && ($_POST[$var] == $match));
    }
}

function presspermit_POST_key($var) {
    if (empty($_POST) || empty($_POST[$var])) {
        return '';
    }

    return (is_array($_POST[$var])) ? array_map('sanitize_key', $_POST[$var]) : sanitize_key($_POST[$var]);
}

function presspermit_POST_int($var) {
    return (!empty($_POST) && !empty($_POST[$var])) ? intval($_POST[$var]) : 0;
}

function presspermit_POST_var($var) {
    return (!empty($_POST) && !empty($_POST[$var])) ? $_POST[$var] : '';
}

function presspermit_empty_GET($var = false) {
    if (false === $var) {
        return empty($_GET);
    } else {
        return empty($_GET[$var]);
    }
}

function presspermit_is_GET($var, $match = false) {
    if (false === $match) {
        return isset($_GET[$var]);

    } elseif (is_array($match)) {
        return (isset($_GET[$var]) && in_array($_GET[$var], $match));
    } else {
        return (!empty($_GET[$var]) && ($_GET[$var] == $match));
    }
}

function presspermit_GET_key($var) {
    if (empty($_GET[$var])) {
        return '';
    }

    return (is_array($_GET[$var])) ? array_map('sanitize_key', $_GET[$var]) : sanitize_key($_GET[$var]);
}

function presspermit_GET_int($var) {
    return (!empty($_GET[$var])) ? intval($_GET[$var]) : 0;
}

function presspermit_GET_var($var) {
    return (!empty($_GET[$var])) ? $_GET[$var] : '';
}

function presspermit_SERVER_var($var) {
    return (!empty($_SERVER[$var])) ? $_SERVER[$var] : '';
}

function presspermitPluginPage()
{
    static $pp_plugin_page = null;

    if (is_null($pp_plugin_page)) {
        $pp_plugin_page = (is_admin() && isset($_REQUEST['page']) && (0 === strpos(sanitize_key($_REQUEST['page']), 'presspermit-')))
            ? sanitize_key($_REQUEST['page'])
            : false;
    }

    return $pp_plugin_page;
}

function presspermit_is_preview() {
    if (!$is_preview = is_preview()) {
        if (defined('ELEMENTOR_VERSION')) {
           $is_preview = !presspermit_empty_REQUEST('elementor-preview');
        } elseif (defined('ET_CORE')) {
            $is_preview = !presspermit_empty_REQUEST('et_fb');
        }
    }

    return apply_filters('presspermit_is_preview', $is_preview);
}
