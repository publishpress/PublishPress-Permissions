<?php
/**
 * Plugin Name: PressPermit Import
 * Plugin URI:  https://publishpress.com/press-permit
 * Description: Import Role Scoper groups, roles, restrictions and settings
 * Author:      PublishPress
 * Author URI:  https://publishpress.com
 * Version:     2.7
 * Min WP Version: 4.7
 */

/*
Copyright © 2019 PublishPress.

This file is part of PressPermit Import.

PressPermit Import is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

PressPermit Import is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin.  If not, see <http://www.gnu.org/licenses/>.
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!defined('PRESSPERMIT_IMPORT_FILE')) {
    define('PRESSPERMIT_IMPORT_FILE', __FILE__);
    define('PRESSPERMIT_IMPORT_ABSPATH', __DIR__);
    define('PRESSPERMIT_IMPORT_CLASSPATH', __DIR__ . '/classes/Permissions/Import');

    add_action(
        'plugins_loaded', 
        function()
        {
            if (!defined('PRESSPERMIT_VERSION') || ! is_admin()) { // no front end execution
                return;
            }

            $ext_version = PRESSPERMIT_VERSION;

            $title = __('Import', 'press-permit-core');

            if (presspermit()->registerModule(
                'import', $title, dirname(plugin_basename(__FILE__)), $ext_version, ['min_pp_version' => '2.7-beta']
            )) {
                define('PRESSPERMIT_IMPORT_VERSION', $ext_version);

                define('PRESSPERMIT_IMPORT_DB_VERSION', '1.2');
                
                class_alias( '\PressShack\LibArray', '\PublishPress\Permissions\Import\Arr' );
                class_alias( '\PressShack\LibWP', '\PublishPress\Permissions\Import\PWP' );

                require_once(__DIR__ . '/classes/Permissions/ImportHooksAdmin.php');
                new \PublishPress\Permissions\ImportHooksAdmin();
            }
        }
    );
} else {
    add_action(
        'init',
        function()
        {
            do_action('presspermit_duplicate_module', 'import', dirname(plugin_basename(__FILE__)));
        }
    );
    return;
}
