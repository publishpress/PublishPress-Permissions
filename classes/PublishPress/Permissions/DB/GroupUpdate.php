<?php

namespace PublishPress\Permissions\DB;

//use \PressShack\LibArray as Arr;

class GroupUpdate
{
    /**
     * Adds a user to a group
     * @param int $groupID - Group Identifier
     * @param int $userID - Identifier of the User to add
     **/
    public static function addGroupUser($group_id, $user_ids, $args = [])
    {
        $defaults = [
            'agent_type' => 'pp_group',
            'member_type' => 'member',
            'status' => 'active',
            'date_limited' => false,
            'start_date_gmt' => constant('PRESSPERMIT_MIN_DATE_STRING'),
            'end_date_gmt' => constant('PRESSPERMIT_MAX_DATE_STRING'),
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        $user_ids = (array)$user_ids;

        if (!presspermit()->groups()->getGroup($group_id, $agent_type)) {
            return;
        }

        $data = Arr::subset($args, ['member_type', 'status', 'start_date_gmt', 'end_date_gmt']);
        $data['date_limited'] = intval($date_limited);
        $data['group_id'] = $group_id;

        foreach ($user_ids as $user_id) {
            if (!$user_id) {
                continue;
            }

            $data['user_id'] = $user_id;

            if ($already_member = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM $members_table WHERE group_id = %d AND user_id = %d",
                $group_id,
                $user_id
            ))) {
                self::updateGroupUser($group_id, $user_ids, $args);
                return;
            } else {
                $data['add_date_gmt'] = current_time('mysql', 1);
                $wpdb->insert($members_table, $data);
            }

            do_action('presspermit_add_group_user', $group_id, $user_id, $args);
            if (PRESSPERMIT_LEGACY_HOOKS) {
                do_action('presspermit_add_group_user', $group_id, $user_id, $args);
            }
        }
    }

    public static function removeGroupUser($group_id, $user_ids, $args = [])
    {
        $defaults = ['agent_type' => 'pp_group', 'member_type' => 'member'];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        if (!presspermit()->groups()->getGroup($group_id)) {
            return;
        }

        $members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        $id_in = "'" . implode("', '", (array)$user_ids) . "'";
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $members_table WHERE member_type = %s AND group_id = %d AND user_id IN ($id_in)",
                $member_type,
                $group_id
            )
        );

        foreach ((array)$user_ids as $user_id) {
            do_action('presspermit_delete_group_user', $group_id, $user_id, $agent_type);
            if (PRESSPERMIT_LEGACY_HOOKS) {
                do_action('presspermit_delete_group_user', $group_id, $user_id, $agent_type);
            }
        }
    }

    private static function validateDurationValue($val)
    {
        if ($val < 0) {
            $val = 0;
        }

        if ($val > PRESSPERMIT_MAX_DATE_STRING) {
            $val = PRESSPERMIT_MAX_DATE_STRING;
        }

        return $val;
    }

    public static function updateGroupUser($group_id, $user_ids, $args = [])
    {
        $defaults = ['agent_type' => 'pp_group'];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        // NOTE: no arg defaults because we only update columns that are explicitly passed
        if (!$cols = Arr::subset($args, ['status', 'date_limited', 'start_date_gmt', 'end_date_gmt'])) {
            return;
        }

        global $wpdb;

        $members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        $user_clause = "AND user_id IN ('" . implode("', '", array_map('intval', (array)$user_ids)) . "')";

        if (isset($cols['date_limited'])) {
            $cols['date_limited'] = (int)$cols['date_limited'];
        }

        if (isset($cols['start_date_gmt'])) {
            $cols['start_date_gmt'] = self::validateDurationValue($cols['start_date_gmt']);
        }

        if (isset($cols['end_date_gmt'])) {
            $cols['end_date_gmt'] = self::validateDurationValue($cols['end_date_gmt']);
        }

        $status = (isset($cols['status'])) ? $cols['status'] : '';

        $prev = [];
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $members_table WHERE group_id = %d $user_clause", $group_id));
        foreach ($results as $row) {
            $prev[$row->user_id] = $row;
        }

        foreach ((array)$user_ids as $user_id) {
            $wpdb->update($members_table, $cols, ['group_id' => $group_id, 'user_id' => $user_id]);

            $_prev = (isset($prev[$user_id])) ? $prev[$user_id] : '';

            do_action('presspermit_update_group_user', $group_id, $user_id, $status, $cols, $_prev);
            if (PRESSPERMIT_LEGACY_HOOKS) {
                do_action('presspermit_update_group_user', $group_id, $user_id, $status, $cols, $_prev);
            }
        }
    }

    public static function deleteUserFromGroups($user_id)
    {
        global $wpdb;

        // possible @todo: pre-query user groups so we can do_action('presspermit_delete_group_user')

        $wpdb->delete($wpdb->pp_group_members, compact('user_id'));
    }

    public static function createGroup($groupdata, $agent_type = 'pp_group')
    {
        global $wpdb;

        $defaults = ['group_name' => '', 'group_description' => '', 'metagroup_type' => ''];
        $groupdata = array_merge($defaults, (array)$groupdata);
        $groupdata = array_intersect_key($groupdata, $defaults);

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        if (!self::groupNameAvailable($groupdata['group_name'], $agent_type)) {
            return false;
        }

        $wpdb->insert($groups_table, $groupdata);

        do_action('presspermit_created_group', (int)$wpdb->insert_id, $agent_type);
        if (PRESSPERMIT_LEGACY_HOOKS) {
            do_action('presspermit_created_group', (int)$wpdb->insert_id, $agent_type);
        }

        return (int)$wpdb->insert_id;
    }

    public static function deleteGroup($group_id, $agent_type = 'pp_group')
    {
        global $wpdb;

        if (!$group_id || !presspermit()->groups()->getGroup($group_id, $agent_type)) {
            return false;
        }

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);
        $members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        do_action('presspermit_delete_group', $group_id, $agent_type);

        $wpdb->delete($wpdb->ppc_roles, ['agent_type' => $agent_type, 'agent_id' => $group_id]);

        $wpdb->delete($groups_table, ['ID' => $group_id]);

        $wpdb->delete($members_table, compact('group_id'));

        do_action('presspermit_deleted_group', $group_id, $agent_type);
        if (PRESSPERMIT_LEGACY_HOOKS) {
            do_action('presspermit_deleted_group', $group_id, $agent_type);
        }

        return true;
    }

    /**
     * Updates an existing Group
     *
     * @param int $group_id
     * @param array $groupdata
     * @return boolean true on successful update
     **/
    public static function updateGroup($group_id, $groupdata, $agent_type = 'pp_group')
    {
        global $wpdb;

        $defaults = ['group_name' => '', 'group_description' => ''];
        $groupdata = array_merge($defaults, (array)$groupdata);
        $groupdata = array_intersect_key($groupdata, $defaults);

        $groupdata['group_description'] = strip_tags($groupdata['group_description']);

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        if ($prev = $wpdb->get_row($wpdb->prepare("SELECT * FROM $groups_table WHERE ID = %d", $group_id))) {
            if (($prev->group_name != $groupdata['group_name']) && !self::groupNameAvailable($groupdata['group_name'], $agent_type)) {
                return false;
            }

            // don't allow updating of metagroup name / descript
            if (!empty($prev->metagroup_id)) {
                return false;
            }
        }

        $wpdb->update($groups_table, $groupdata, ['ID' => $group_id]);

        return true;
    }

    public static function groupNameAvailable($string, $agent_type = 'pp_group')
    {
        global $wpdb;

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        if ($string && !$wpdb->get_var($wpdb->prepare("SELECT ID FROM $groups_table WHERE group_name = %s LIMIT 1", $string))) {
            return true;
        }
    }
}
