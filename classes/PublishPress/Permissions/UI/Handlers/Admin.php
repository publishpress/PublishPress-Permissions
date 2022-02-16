<?php

namespace PublishPress\Permissions\UI\Handlers;

class Admin
{
    public static function handleRequest() {
        do_action('presspermit_admin_handlers');

        if (!presspermit_empty_POST()) {
            $pp_plugin_page = presspermitPluginPage();

            if (('presspermit-edit-permissions' == $pp_plugin_page)
                || presspermit_is_POST('action', ['pp_updateroles', 'pp_updateexceptions', 'pp_updateclone'])
                || ((presspermit_is_REQUEST('_wp_http_referer') && strpos(esc_url_raw(presspermit_REQUEST_var('_wp_http_referer')), 'presspermit-edit-permissions'))
                || ('presspermit-group-new' == $pp_plugin_page)
            ) {
                add_action('presspermit_user_init', function()
                {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/AgentEdit.php');
                    new AgentEdit();
                    do_action('presspermit_trigger_cache_flush');
                });
            }
        }

        if (!presspermit_empty_REQUEST('action') || !presspermit_empty_REQUEST('action2') || !presspermit_empty_REQUEST('pp_action')) {
            if (('presspermit-groups' == presspermitPluginPage()) || (!presspermit_empty_REQUEST('wp_http_referer')
                    && (strpos(esc_url_raw(presspermit_REQUEST_var('wp_http_referer')), 'page=presspermit-groups'))
            )) {
                add_action('presspermit_user_init', function()
                {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/Groups.php');
                    Groups::handleRequest();
                });
            }
        }
    }
}
