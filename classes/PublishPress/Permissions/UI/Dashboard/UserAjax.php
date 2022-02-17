<?php

namespace PublishPress\Permissions\UI\Dashboard;

class UserAjax
{
    public function __construct() 
    {
        if (!current_user_can('create_users') || !current_user_can('pp_manage_members')) {
            return;
        }

        if (!$pp_ajax_user = presspermit_GET_key('pp_ajax_user')) {
            return;
        }

        switch ($pp_ajax_user) {
            case 'new_user_groups_ui':
                require_once( PRESSPERMIT_CLASSPATH . '/UI/Dashboard/Profile.php' );
                Profile::displayUserGroups(false);
                break;
        }
    }
}
