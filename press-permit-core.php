<?php
/**
 * Plugin Name: PublishPress Permissions
 * Plugin URI:  https://publishpress.com/presspermit
 * Description: Advanced yet accessible content permissions. Give users or groups type-specific roles. Enable or block access for specific posts or terms.
 * Author: PublishPress
 * Author URI:  https://publishpress.com/
 * Version:     3.5.2
 * Text Domain: press-permit-core
 * Domain Path: /languages/
 * Min WP Version: 4.9.7
 * Requires PHP: 5.6.20
 *
 * Copyright (c) 2020 PublishPress
 *
 * GNU General Public License, Free Software Foundation <https://www.gnu.org/licenses/gpl-3.0.html>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     PressPermit
 * @category    Core
 * @author      PublishPress
 * @copyright   Copyright (c) 2020 PublishPress. All rights reserved.
 *
 **/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

$pro_active = false;

global $presspermit_loaded_by_pro;

$presspermit_loaded_by_pro = strpos(str_replace('\\', '/', __FILE__), 'vendor/publishpress/');

// Detect separate Pro plugin activation, but not self-activation (this file loaded in vendor library by Pro)
if (false === $presspermit_loaded_by_pro) {
    foreach ((array)get_option('active_plugins') as $plugin_file) {
        if (false !== strpos($plugin_file, 'presspermit-pro.php')) {
            $pro_active = true;
            break;
        }
    }

    if (!$pro_active && is_multisite()) {
        foreach (array_keys((array)get_site_option('active_sitewide_plugins')) as $plugin_file) {
            if (false !== strpos($plugin_file, 'presspermit-pro.php')) {
                $pro_active = true;
                break;
            }
        }
    }
}

if ((!defined('PRESSPERMIT_FILE') && !$pro_active) || $presspermit_loaded_by_pro) {
    define('PRESSPERMIT_FILE', __FILE__);
    define('PRESSPERMIT_ABSPATH', __DIR__);
    define('PRESSPERMIT_CLASSPATH', __DIR__ . '/classes/PublishPress/Permissions');

    if (!defined('PRESSPERMIT_CLASSPATH_COMMON')) {
        define('PRESSPERMIT_CLASSPATH_COMMON', __DIR__ . '/classes/PressShack');
    }

    define('PRESSPERMIT_DB_VERSION', '2.0.1');

    if (!defined('PRESSPERMIT_DEBUG')) {
        define('PRESSPERMIT_DEBUG', false);
    }

    include_once(constant('PRESSPERMIT_DEBUG') ? __DIR__ . '/library/debug.php' : __DIR__ . '/library/debug_shell.php');

    function presspermit_load() {
	    global $wp_version, $presspermit_loaded_by_pro;
	
	    $min_wp_version = '4.9.7';
	    $min_php_version = '5.6.20';
	
	    $php_version = phpversion();
	
	    // Critical errors that prevent initialization
	    if ((version_compare($min_php_version, $php_version, '>') && presspermit_err('old_php', ['min_version' => $min_php_version, 'version' => $php_version]))
	        || (version_compare($wp_version, $min_wp_version, '<') && presspermit_err('old_wp', ['min_version' => $min_wp_version, 'version' => $wp_version]))
	        || (defined('PPC_FOLDER') && defined('PPC_BASENAME') && function_exists('ppc_deactivate') && presspermit_err('pp_core_active'))
	        || (defined('PP_VERSION') && function_exists('pp_get_otype_option') && presspermit_err('pp_legacy_active'))  // Press Permit 1.x (circa 2012) active
	        || (defined('SCOPER_VERSION') && function_exists('rs_get_user') && presspermit_err('rs_active') && ! is_admin())
	        || (constant('PRESSPERMIT_DEBUG') && is_admin() && presspermit_editing_plugin()) // avoid lockout in case of erroneous plugin edit via wp-admin
	    ) {
	        return;
	    }
	
	    define('PRESSPERMIT_VERSION', '3.5.2');
		
	    if (!$presspermit_loaded_by_pro) {
	        require_once(__DIR__ . '/includes/Core.php');
	        new \PublishPress\Permissions\Core();
	
	        if (is_admin()) {
	            require_once(__DIR__ . '/includes/CoreAdmin.php');
	            new \PublishPress\Permissions\CoreAdmin();
	        }
	    }
	
	    if (!defined('PRESSPERMIT_LEGACY_HOOKS')) {
	        define('PRESSPERMIT_LEGACY_HOOKS', false);
	    }
	
	    // Non-critical intialization errors (may prevent integration with module or external plugin, but continue with initialization)
	    if (defined('RVY_VERSION') && !defined('REVISIONARY_VERSION')) {
	        presspermit_err('old_extension', ['module_title' => 'Revisionary', 'min_version' => '1.3.5']);
	    }
	
	    require_once(PRESSPERMIT_CLASSPATH_COMMON . '/LibArray.php');
	    class_alias('\PressShack\LibArray', '\PublishPress\Arr');
	    class_alias('\PressShack\LibArray', '\PublishPress\Permissions\Arr');
	    class_alias('\PressShack\LibArray', '\PublishPress\Permissions\DB\Arr');
	    class_alias('\PressShack\LibArray', '\PublishPress\Permissions\UI\Arr');
	    class_alias('\PressShack\LibArray', '\PublishPress\Permissions\UI\Dashboard\Arr');
	
	    require_once(PRESSPERMIT_CLASSPATH_COMMON . '/LibWP.php');
	    class_alias('\PressShack\LibWP', '\PublishPress\PWP');
	    class_alias('\PressShack\LibWP', '\PublishPress\Permissions\PWP');
	    class_alias('\PressShack\LibWP', '\PublishPress\Permissions\DB\PWP');
	    class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\PWP');
	    class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\Dashboard\PWP');
	    class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\Handlers\PWP');
	
	    require_once(PRESSPERMIT_CLASSPATH . '/API.php');
	    
	    require_once(__DIR__ . '/db-config.php');
	    require_once(__DIR__ . '/classes/PublishPress/Permissions.php');
	    require_once(__DIR__ . '/functions.php');
    
	    presspermit();
	}

	// negative priority to precede any default WP action handlers
    if ($presspermit_loaded_by_pro) {
    	presspermit_load();	// Pro support
	} else {
    	add_action('plugins_loaded', 'presspermit_load', -10);
	}

    register_activation_hook(
        __FILE__, 
        function()
        {
            require_once( __DIR__.'/activation.php' );
        }
    );

    function presspermit_err($err_slug, $args = [])
    {
        if (is_admin()) {
            require_once(PRESSPERMIT_CLASSPATH . '/ErrorNotice.php');
            return new \PublishPress\Permissions\ErrorNotice($err_slug, $args);
        }
    }
} elseif (defined('PRESSPERMIT_FILE') && !defined('PRESSPERMIT_PRO_FILE')) {
    if (is_admin()) {
        global $pagenow;
        if (('plugins.php' == $pagenow) && !strpos(urldecode($_SERVER['REQUEST_URI']), 'deactivate')) {
            add_action('all_admin_notices', function()
            {
                $msg = sprintf(
                    '<strong>Error:</strong> Multiple copies of %1$s activated. Only the copy in folder "%2$s" is functional.',
                    'PressPermit',
                    dirname(plugin_basename(PRESSPERMIT_FILE))
                );

                echo "<div id='message' class='error fade' style='color:black'>" . $msg . '</div>';
            }, 5);
        }
    }
    return;
} else {
    add_filter(
        'plugin_row_meta', 
        function($links, $file)
        {
            if ($file == plugin_basename(__FILE__)) {
                $links[]= __('<strong>This plugin can be deleted.</strong>', 'press-permit-core');
            }

            return $links;
        },
        10, 2
    );
}
