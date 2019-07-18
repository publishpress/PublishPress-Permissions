<?php

namespace PublishPress\Permissions;

class UserGroupsUpdate
{
    public static function addUserGroups($user_id, $omit_group_ids = [])
    {
        $pp = presspermit();

        $group_types = $pp->groups()->getGroupTypes(['editable' => true]);

        foreach ($group_types as $agent_type) {
            if (('pp_group' == $agent_type) && in_array('pp_net_group', $group_types, true) && (1 == get_current_blog_id()))
                continue;

            if (empty($_POST[$agent_type]))
                continue;

            $status = (isset($_POST['pp_membership_status'])) ? sanitize_key($_POST['pp_membership_status']) : 'active';

            if ($user_id == $pp->getUser()->ID)
                $stored_groups = (array)$pp->getUser()->groups[$agent_type];
            else {
                $user = $pp->getUser($user_id, '', ['skip_role_merge' => 1]);
                $stored_groups = (isset($user->groups[$agent_type])) ? (array)$user->groups[$agent_type] : [];
            }

            // by retrieving filtered groups here, user will only modify membership for groups they can administer
            $is_administrator = $pp->isUserAdministrator();

            $posted_groups = (isset($_POST[$agent_type])) ? $_POST[$agent_type] : [];

            if ($omit_group_ids)
                $posted_groups = array_diff($posted_groups, $omit_group_ids);

            if (!current_user_can('pp_manage_members'))
                $posted_groups = array_intersect($posted_groups, apply_filters('presspermit_admin_groups', []));

            foreach ($posted_groups as $group_id) {
                if (isset($stored_groups[$group_id]))
                    continue;

                if ($pp->groups()->userCan('pp_manage_members', $group_id, $agent_type)) {
                    $args = compact('agent_type', 'status');
                    $args = apply_filters('presspermit_add_group_args', $args, $group_id);

                    $pp->groups()->addGroupUser((int)$group_id, $user_id, $args);
                }
            }
        }
    }

    public static function removeUserGroups($user_id, $omit_group_ids = [])
    {
        $pp = presspermit();

        $group_types = $pp->groups()->getGroupTypes(['editable' => true]);

        foreach ($group_types as $agent_type) {
            if (('pp_group' == $agent_type) && in_array('pp_net_group', $group_types, true) && (1 == get_current_blog_id()))
                continue;

            $posted_groups = (isset($_POST[$agent_type])) ? array_map('intval', $_POST[$agent_type]) : [];

            $stored_groups = array_keys($pp->groups()->getGroupsForUser($user_id, $agent_type, ['cols' => 'id']));

            if ($omit_group_ids)
                $stored_groups = array_diff($stored_groups, $omit_group_ids);

            if (!current_user_can('pp_manage_members'))
                $posted_groups = array_intersect($posted_groups, apply_filters('presspermit_admin_groups', []));

            $delete_groups = array_diff($stored_groups, $posted_groups);

            foreach ($delete_groups as $group_id) {
                if ($pp->groups()->userCan('pp_manage_members', $group_id, $agent_type)) {
                    $pp->groups()->removeGroupUser($group_id, $user_id, compact('agent_type'));
                }
            }
        }
    }
}
