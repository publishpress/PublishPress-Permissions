<?php

namespace PublishPress\Permissions\UI;

// Plugin admin UI function not inherently tied to WordPress Dashboard framework
class PluginPage
{
    private static $instance = null;
    var $table;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new PluginPage();
        }
        
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_head', [$this, 'actAdminHead']);
    }

    public static function handleScreenOptions()
    {
        if (isset($_REQUEST['wp_screen_options'])) {
            if (isset($_REQUEST['wp_screen_options']['option']) && ('groups_per_page' == $_REQUEST['wp_screen_options']['option'])) {
                global $current_user;
                update_user_option($current_user->ID, $_REQUEST['wp_screen_options']['option'], $_REQUEST['wp_screen_options']['value']);
            }
        }
    }

    public static function icon()
    {
        echo '<div class="pp-icon"><img src="' . PRESSPERMIT_URLPATH . '/common/img/publishpress-logo-icon.png" alt="" /></div>';
    }

    public function actAdminHead()
    {
        global $pagenow;

        if (('upload.php' == $pagenow) && !defined('PRESSPERMIT_FILE_ACCESS_VERSION')
            && current_user_can('pp_manage_settings') && presspermit()->getOption('display_extension_hints')
        ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/HintsMedia.php');
            HintsMedia::fileFilteringPromo();
        }

        if ('presspermit-groups' == presspermitPluginPage()) {
            // @todo: eliminate redundancy with Groups::__construct()
            if (!empty($_REQUEST['action2']) && !is_numeric($_REQUEST['action2']))
                $action = $_REQUEST['action2'];
            elseif (!empty($_REQUEST['action']) && !is_numeric($_REQUEST['action']))
                $action = $_REQUEST['action'];
            elseif (!empty($_REQUEST['pp_action']))
                $action = $_REQUEST['pp_action'];
            else
                $action = '';

            if ( ! in_array($action, ['delete', 'bulkdelete'])) {
                $agent_type = (!empty($_REQUEST['agent_type'])) ? $_REQUEST['agent_type'] : 'pp_group';
            } else {
                $agent_type = '';
            }

            $agent_type = self::getAgentType($agent_type);

            $group_variant = self::getGroupVariant();

            if ( ! $this->table = apply_filters('presspermit_groups_list_table', false, $agent_type) ) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsListTable.php' );
                $this->table = new GroupsListTable(compact('agent_type', 'group_variant'));
            }

            add_screen_option(
                'per_page',
                
                ['label' => _x('Groups', 'groups per page (screen options)', 'press-permit-core'), 
                'default' => 20, 
                'option' => 'groups_per_page'
                ]
            );
        }

        add_action('in_admin_header', function() {do_action('presspermit_plugin_page_admin_header');}, 100);
    }

    public static function getAgentType($default_type = '') {
        $_agent_type = (isset($_REQUEST['agent_type'])) ? sanitize_key($_REQUEST['agent_type']) : $default_type;

        if (!$agent_type = apply_filters('presspermit_query_group_type', $_agent_type)) {
            $agent_type = 'pp_group';
        }

        return $agent_type;
    }

    public static function getGroupVariant() {
        if (empty($_REQUEST['group_variant']) && !empty($_REQUEST['s']) && !empty($_REQUEST['_wp_http_referer'])) {
            $matches = [];
            if (preg_match("/group_variant=([0-9a-zA-Z_\-]+)/", urldecode($_REQUEST['_wp_http_referer']), $matches)) {
                if ($matches[1]) {
                    $group_variant = $matches[1];
                }
            }
        } elseif (empty($_REQUEST['group_variant']) && !current_user_can('edit_users')) {
            $group_variant = 'pp_group';
        }

        if (empty($group_variant)) {
            $group_variant = (isset($_REQUEST['group_variant'])) ? sanitize_key($_REQUEST['group_variant']) : '';
        }

        return apply_filters('presspermit_query_group_variant', $group_variant);
    }
}
