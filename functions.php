<?php

function presspermit() {
    return \PublishPress\Permissions::instance();
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
