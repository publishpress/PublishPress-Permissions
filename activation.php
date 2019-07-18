<?php
require(__DIR__ . '/db-config.php');

// get last database version
if ( ! $ver = get_option('presspermitpro_version') ) {
    if ( ! $ver = get_option('presspermit_version') ) {
        $ver = get_option('pp_c_version');
    }
}

$db_ver = ( isset( $ver['db_version'] ) ) ? $ver['db_version'] : '';
require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');
new \PublishPress\Permissions\DB\DatabaseSetup($db_ver);

require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
\PublishPress\Permissions\PluginUpdated::syncWordPressRoles();

update_option('presspermit_activation', true);
do_action('presspermit_activate');