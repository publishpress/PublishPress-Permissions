<?php

namespace PublishPress\Permissions\DB;

/**
 * Groups class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2019, PublishPress
 *
 */

class Groups
{
    // returns all groups
    public static function getGroups($args = [])
    {
        global $wpdb;

        $defaults = [
            'agent_type' => 'pp_group',
            'cols' => "DISTINCT ID, group_name AS name, group_description, metagroup_type, metagroup_id",
            'omit_ids' => [],
            'ids' => [],
            'where' => '',
            'join' => '',
            'require_meta_types' => [],
            'skip_meta_types' => [],
            'search' => '',
            'order_by' => 'group_name'
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        if ($ids)
            $where .= "AND ID IN ('" . implode("','", array_map('intval', (array)$ids)) . "')";

        if ($omit_ids)
            $where .= "AND ID NOT IN ('" . implode("','", array_map('intval', (array)$omit_ids)) . "')";

        if ($skip_meta_types)
            $where .= " AND metagroup_type NOT IN ('" . implode("','", (array)$skip_meta_types) . "')";

        if ($require_meta_types)
            $where .= " AND metagroup_type IN ('" . implode("','", (array)$require_meta_types) . "')";

        if ($search) {
            $searches = [];
            foreach (['group_name', 'group_description'] as $col)
                $searches[] = $col . $wpdb->prepare(" LIKE %s", "%$search%");
            $where .= 'AND ( ' . implode(' OR ', $searches) . ' )';
        }

        $query = "SELECT $cols FROM $groups_table $join WHERE 1=1 $where ORDER BY $order_by";
        $results = $wpdb->get_results($query, OBJECT_K);

        foreach (array_keys($results) as $key) {
            $results[$key]->name = stripslashes($results[$key]->name);

            if (isset($results[$key]->group_description))
                $results[$key]->group_description = stripslashes($results[$key]->group_description);

            // strip out Revisionary metagroups if we're not using them (@todo: API)
            if ($results[$key]->metagroup_type) {
                if (!defined('REVISIONARY_VERSION') && ('rvy_notice' == $results[$key]->metagroup_type)) {
                    unset($results[$key]);
                }
            }
        }

        return $results;
    }

    public static function getGroupMembers($group_id, $cols = 'all', $args = [])
    {
        global $wpdb;

        $defaults = ['agent_type' => 'pp_group', 'member_type' => 'member', 'status' => 'active', 'maybe_metagroup' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        // If $group_id is an array of group objects, extract IDs into a separate array (@todo: review calling code)
        if (is_array($group_id)) {
            $first = current($group_id);

            if (is_object($first)) {
                $actual_ids = [];

                foreach ($group_id as $group)
                    $actual_ids[] = $group->ID;

                $group_id = $actual_ids;
            }
        }

        if ('any' == $status)
            $status = '';

        $group_in = "'" . implode("', '", array_map('intval', (array)$group_id)) . "'";

        $members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        $status_clause = ($status) ? $wpdb->prepare("AND status = %s", $status) : '';
        $mtype_clause = $wpdb->prepare("AND member_type = %s", $member_type);

        if ('id' == $cols) {
            $query = "SELECT u2g.user_id 
                  FROM $members_table AS u2g
                  WHERE u2g.group_id IN ($group_in) AND u2g.group_id > 0 $mtype_clause $status_clause";

            if (!$results = $wpdb->get_col($query))
                $results = [];
        } elseif ('count' == $cols) {
            $query = "SELECT COUNT(u2g.user_id)
                  FROM $members_table AS u2g
                  WHERE u2g.group_id IN ($group_in) AND u2g.group_id > 0 $mtype_clause $status_clause";

            $results = $wpdb->get_var($query);
        } else {
            switch ($cols) {
                case 'id_displayname':
                    $qcols = "u.ID, u.display_name AS display_name";
                    $orderby = "ORDER BY u.display_name";
                    break;
                case 'id_name':
                    $qcols = "u.ID, u.user_login AS name"; // calling code assumes display_name property for user or group object
                    $orderby = "ORDER BY u.user_login";
                    break;
                default:
                    $orderby = apply_filters('presspermit_group_members_orderby', "ORDER BY u.user_login");
                    $qcols = "u.*, u2g.*";
            }

            $query = "SELECT $qcols FROM $wpdb->users AS u"
                . " INNER JOIN $members_table AS u2g ON u2g.user_id = u.ID $mtype_clause $status_clause"
                . " AND u2g.group_id IN ($group_in) $orderby";

            $results = $wpdb->get_results($query, OBJECT_K);
        }

        return $results;
    }

    public static function getGroupsForUser($user_id, $args = [])
    {
        $defaults = [
            'agent_type' => 'pp_group',
            'member_type' => 'member',
            'status' => 'active',
            'cols' => 'all',
            'metagroup_type' => null,
            'force_refresh' => false,
            'query_user_ids' => false,
            'wp_roles' => []
        ];
        $args = array_merge($defaults, $args);

        if (is_null($args['metagroup_type']))
            unset($args['metagroup_type']);

        foreach (array_keys($defaults) as $var) {
            if (isset($args[$var])) {
                $$var = $args[$var];
            }
        }

        global $wpdb;

        $pp = presspermit();
        $pp_groups = $pp->groups();

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);
        $members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        if (empty($members_table)) {
            return [];
        }

        if (($cols == 'all') || !empty($metagroup_type))
            $join = apply_filters(
                'presspermit_get_groups_for_user_join',
                "INNER JOIN $groups_table AS g ON $members_table.group_id = g.ID",
                $user_id,
                $args
            );
        else
            $join = '';

        if ('any' == $status) {
            $status = '';
        }
        $status_clause = ($status) ? $wpdb->prepare("AND status = %s", $status) : '';
        $metagroup_clause = (!empty($metagroup_type)) ? $wpdb->prepare("AND g.metagroup_type = %s", $metagroup_type) : '';
        $user_id = (int)$user_id;

        $user_groups = [];

        if ('pp_group' == $agent_type) {
            static $all_group;
            static $auth_group;

            if (!$pp->isContentAdministrator() || !$pp->getOption('suppress_administrator_metagroups')) {
                if (!isset($all_group))
                    $all_group = $pp_groups->getMetagroup('wp_role', 'wp_all');

                if (!isset($auth_group))
                    $auth_group = $pp_groups->getMetagroup('wp_role', 'wp_auth');
            }
        }

        if ('all' == $cols) {
            $query = "SELECT * FROM $members_table $join WHERE user_id = '$user_id'"
                . " AND member_type = '$member_type' $status_clause $metagroup_clause ORDER BY $members_table.group_id";

            $results = $wpdb->get_results($query);

            foreach ($results as $row)
                $user_groups[$row->group_id] = (object)(array)$row;  // force storage by value

            if ('pp_group' == $agent_type) {
                if ($all_group)
                    $user_groups[$all_group->ID] = $all_group;

                if ($auth_group)
                    $user_groups[$auth_group->ID] = $auth_group;
            }

            // ensure current WP roles are recognized even if pp_group_members entries out of sync
            if (!empty($args['wp_roles'])) {
                foreach ($args['wp_roles'] as $role_name) {
                    if (is_object($role_name))
                        $role_name = $role_name->name;

                    $matched = false;
                    foreach ($user_groups as $ug) {
                        if (!empty($ug->metagroup_id) && ($ug->metagroup_id == $role_name) && ('wp_role' == $ug->metagroup_type)) {
                            $matched = true;
                            break;
                        }
                    }

                    if (!$matched) {
                        if ($role_group = $pp_groups->getMetagroup('wp_role', $role_name)) {
                            $user_groups[$role_group->ID] = $role_group;
                            $pp_groups->addGroupUser($role_group->ID, $user_id);
                        }
                    }
                }
            }

            $user_groups = apply_filters('presspermit_get_pp_groups_for_user', $user_groups, $results, $user_id, $args);
        } else {
            if ($query_user_ids) {
                static $user_groups;
                if (!isset($user_groups)) {
                    $user_groups = [];
                }

                if (!isset($user_groups[$agent_type])) {
                    $user_groups[$agent_type] = [];
                }
            } else {
                $user_groups = [$agent_type => []];
                $query_user_ids = $user_id;
            }

            if (!isset($user_groups[$agent_type][$user_id]) || $force_refresh) {
                $query = "SELECT user_id, group_id, add_date_gmt FROM $members_table $join"
                    . " WHERE user_id IN ('" . implode("','", (array)$query_user_ids) . "') AND member_type = '$member_type'"
                    . " $status_clause $metagroup_clause ORDER BY group_id";

                $results = $wpdb->get_results($query);

                foreach ($results as $row) {
                    $user_groups[$agent_type][$row->user_id][$row->group_id] = $row->add_date_gmt;
                }
            }

            if ('pp_group' == $agent_type) {
                foreach ((array)$query_user_ids as $_user_id) {
                    if ($all_group) {
                        $user_groups[$agent_type][$_user_id][$all_group->ID] = constant('PRESSPERMIT_MIN_DATE_STRING');
                    }

                    if ($auth_group) {
                        $user_groups[$agent_type][$_user_id][$auth_group->ID] = constant('PRESSPERMIT_MIN_DATE_STRING');
                    }
                }
            }

            return (isset($user_groups[$agent_type][$user_id])) ? $user_groups[$agent_type][$user_id] : [];
        }

        return $user_groups;
    }

    public static function getMetagroupName($metagroup_type, $meta_id, $default_name = '')
    {
        global $wp_roles;

        if ('wp_auth' == $meta_id) {
            return __('Logged In', 'press-permit-core');
        } elseif ('wp_anon' == $meta_id) {
            return __('Not Logged In', 'press-permit-core');
        } elseif ('wp_all' == $meta_id) {
            return __('Everyone', 'press-permit-core');
        } elseif ('wp_role' == $metagroup_type) {
            switch ($meta_id) {
                case 'rvy_pending_rev_notice':
                    return __('Pending Revision Monitors', 'press-permit-core');
                    break;

                case 'rvy_scheduled_rev_notice':
                    return __('Scheduled Revision Monitors', 'press-permit-core');
                    break;

                default:
            		$role_display_name = isset($wp_roles->role_names[$meta_id]) ? __($wp_roles->role_names[$meta_id]) : $meta_id;
            }

            //return sprintf(__('[WP %s]', 'press-permit-core'), $role_display_name);
            return $role_display_name;
        } else {
            switch ($meta_id) {
                case 'rvy_pending_rev_notice':
                    return __('Pending Revision Monitors', 'press-permit-core');
                    break;

                case 'rvy_scheduled_rev_notice':
                    return __('Scheduled Revision Monitors', 'press-permit-core');
                    break;

                default:
            }

            return $default_name;
        }
    }

    public static function getMetagroupDescript($metagroup_type, $meta_id, $default_descript = '')
    {
        if ('wp_auth' == $meta_id) {
            return __('Authenticated site users (logged in)', 'press-permit-core');
        } elseif ('wp_anon' == $meta_id) {
            return __('Anonymous users (not logged in)', 'press-permit-core');
        } elseif ('wp_all' == $meta_id) {
            return __('All users (including anonymous)', 'press-permit-core');
        } elseif ('wp_role' == $metagroup_type) {
            $role_display_name = self::getMetagroupName($metagroup_type, $meta_id);
            $role_display_name = str_replace('[WP ', '', $role_display_name);
            $role_display_name = str_replace(']', '', $role_display_name);
            return sprintf(__('All users with the WordPress role of %s', 'press-permit-core'), $role_display_name);
        } else {
            return $default_descript;
        }
    }

    public static function isDeletedRole($role_name)
    {
        global $wp_roles;

        return !in_array($role_name, array_keys($wp_roles->role_names), true) && !in_array($role_name, ['wp_anon', 'wp_all', 'wp_auth'], true);
    }
}
