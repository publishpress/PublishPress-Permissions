<?php

namespace PublishPress\Permissions\UI\Handlers;

class Admin
{
    public static function handleRequest() {
        do_action('presspermit_admin_handlers');

        if (!empty($_POST)) {
            $pp_plugin_page = presspermitPluginPage();

            if (('presspermit-edit-permissions' == $pp_plugin_page)
                || (!empty($_POST['action']) && (in_array($_POST['action'], ['pp_updateroles', 'pp_updateexceptions', 'pp_updateclone'])))
                || ((isset($_REQUEST['_wp_http_referer']) && strpos($_REQUEST['_wp_http_referer'], 'presspermit-edit-permissions')))
                || ('presspermit-group-new' == $pp_plugin_page)
            ) {
                add_action('presspermit_user_init', function()
                {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/AgentEdit.php');
                    new AgentEdit();
                });
            }
        }

        if (!empty($_REQUEST['action']) || !empty($_REQUEST['action2']) || !empty($_REQUEST['pp_action'])) {
            if (('presspermit-groups' == presspermitPluginPage()) || (!empty($_REQUEST['wp_http_referer'])
                    && (strpos($_REQUEST['wp_http_referer'], 'page=presspermit-groups')))
            ) {
                add_action('presspermit_user_init', function()
                {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/Groups.php');
                    Groups::handleRequest();
                });
            }
        }
    }
}
