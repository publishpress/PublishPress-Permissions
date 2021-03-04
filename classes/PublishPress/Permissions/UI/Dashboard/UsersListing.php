<?php

namespace PublishPress\Permissions\UI\Dashboard;

//use \PressShack\LibArray as Arr;
//use \PublishPress\Permissions\API as API;

class UsersListing
{
    public function __construct() {
        add_filter('manage_users_columns', [$this, 'fltUsersColumns']);
        add_action('manage_users_custom_column', [$this, 'fltUsersCustomColumn'], 99, 3); // filter late in case other plugin filters do not retain passed value
        add_filter('manage_users_sortable_columns', [$this, 'fltUsersColumnsSortable']);
        
        add_filter('pre_user_query', [$this, 'fltUserQueryExceptions']);
        add_filter('query', [$this, 'fltUserQuery']);
        
        add_action('restrict_manage_users', [$this, 'actBulkGroupsUI']);
        
        $this->groupsBulk();
    }

    public function actBulkGroupsUI()
    {
        static $done;
        $sfx = (!empty($done)) ? '2' : '';
        $done = true;

        $pp = presspermit();

        if (!$pp->getOption('users_bulk_groups'))
            return;

        if (!$agent_type = apply_filters('presspermit_query_group_type', ''))
            $agent_type = 'pp_group';

        $groups = $pp->groups()->getGroups($agent_type, ['where' => " AND metagroup_type = ''"]);

        if (!count($groups) || !current_user_can('list_users'))
            return;

        if (!current_user_can('pp_manage_members')) {
            if (!$editable_groups = apply_filters('presspermit_admin_groups', [])) {
                return;
            }

            $groups = Arr::subset($groups, $editable_groups);
        }
        ?>

        <label class="screen-reader-text" for="pp-add-group"><?php esc_html_e('Permissions&hellip;', 'press-permit-core') ?></label>
        <select name="pp-bulk-group<?php echo $sfx; ?>" id="pp-bulk-group<?php echo $sfx; ?>" class="pp-bulk-groups"
                style="display:inline-block; float:none;" autocomplete="off">
            <option value=''><?php esc_html_e('Permissions&hellip;', 'press-permit-core') ?></option>
            <?php
            foreach ($groups as $group_id => $group) : ?>
                <option value="<?php echo $group_id; ?>"><?php echo $group->name; ?></option>
            <?php endforeach; ?>
        </select>

        <?php
        submit_button(
            __('Add', 'press-permit-core'),
            'secondary',
            'pp-add-group-members' . $sfx,
            false,
            ['title' => __('Add selected users to Permission Group', 'press-permit-core')]
        );
        ?>

        <?php
        submit_button(
            __('Remove', 'press-permit-core'),
            'secondary',
            'pp-remove-group-members' . $sfx,
            false,
            ['title' => __('Remove selected users from Permission Group', 'press-permit-core')]
        );

        wp_nonce_field( 'pp-bulk-assign-groups', 'pp-bulk-groups-nonce' );
    }

    public static function fltUsersColumns($defaults)
    {
        $title = __('Click to show only users who have no group', 'press-permit-core');

        $style = (!empty($_REQUEST['pp_no_group']) && (empty($_REQUEST['orderby']) || 'pp_group' != $_REQUEST['orderby']))
            ? 'style="font-weight:bold; color:black"'
            : '';

        $defaults['pp_no_groups'] = sprintf(
            __('%1$s(x)%2$s', 'press-permit-core'),
            "<a href='?pp_no_group=1' title='$title' $style>",
            '</a>'
        );

        $defaults['pp_groups'] = __('Groups', 'press-permit-core');

        $title = __('Click to show only users who have supplemental roles', 'press-permit-core');
        $style = (!empty($_REQUEST['pp_has_roles'])) ? 'style="font-weight:bold; color:black"' : '';

        $defaults['pp_roles'] = sprintf(
            __('Roles %1$s*%2$s', 'press-permit-core'),
            "<a href='?pp_has_roles=1' title='$title' $style>",
            '</a>'
        );

        unset($defaults['role']);
        unset($defaults['bbp_user_role']);

        $title = __('Click to show only users who have specific permissions', 'press-permit-core');
        $style = (!empty($_REQUEST['pp_has_exceptions'])) ? 'style="font-weight:bold; color:black"' : '';

        $defaults['pp_exceptions'] = sprintf(
            __('Specific Permissions %1$s*%2$s', 'press-permit-core'),
            "<a href='?pp_has_exceptions=1' title='$title' $style>",
            '</a>'
        );

        return $defaults;
    }

    public static function fltUsersColumnsSortable($columns)
    {
        $columns['pp_groups'] = 'pp_group';
        return $columns;
    }

    public static function fltUsersCustomColumn($content = '', $column_name, $id)
    {
        $pp_groups = presspermit()->groups();

        switch ($column_name) {
            case 'pp_groups':
                global $wp_list_table;

                static $all_groups;
                static $all_group_types;

                if (!isset($all_groups)) {
                    $all_groups = [];
                    $all_group_types = $pp_groups->getGroupTypes(['editable' => true]);
                }

                $all_group_names = [];

                foreach ($all_group_types as $agent_type) {
                    if (!isset($all_groups[$agent_type]))
                        $all_groups[$agent_type] = $pp_groups->getGroups($agent_type);

                    if (empty($all_groups[$agent_type]))
                        continue;

                    if (('pp_group' == $agent_type) && in_array('pp_net_group', $all_group_types, true)
                        && (1 == get_current_blog_id())
                    ) {
                        continue;
                    }

                    $group_names = [];

                    if ($group_ids = $pp_groups->getGroupsForUser(
                        $id,
                        $agent_type,
                        ['cols' => 'id', 'query_user_ids' => array_keys($wp_list_table->items)]
                    )) {
                        if ('pp_group' == $agent_type) {
                            if (!current_user_can('pp_manage_members')) {
                                $group_ids = Arr::subset($group_ids, apply_filters('presspermit_admin_groups', []));
                            }
                        }

                        foreach (array_keys($group_ids) as $group_id) {
                            if (isset($all_groups[$agent_type][$group_id])) {
                                if (
                                    empty($all_groups[$agent_type][$group_id]->metagroup_type)
                                    || ('wp_role' != $all_groups[$agent_type][$group_id]->metagroup_type)
                                ) {
                                    $group_names[$all_groups[$agent_type][$group_id]->name] = $group_id;
                                }
                            }
                        }

                        if ($group_names) {
                            uksort($group_names, "strnatcasecmp");

                            foreach ($group_names as $name => $_id) {
                                if (defined('PP_USERS_UI_GROUP_FILTER_LINK')) {
                                    $url = add_query_arg('pp_group', $_id, $_SERVER['REQUEST_URI']);
                                    $all_group_names[] = "<a href='$url'>$name</a>";
                                } else {
                                    $all_group_names[] = "<a href='"
                                        . "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_type=$agent_type&amp;agent_id=$_id'>"
                                        . "$name</a>";
                                }
                            }
                        }
                    }
                }

                return implode(", ", $all_group_names);
                break;

            case 'pp_no_groups':
                break;

            case 'pp_roles':
                global $wp_list_table, $wp_roles;
                static $role_info;

                $role_str = '';

                if (!isset($role_info)) {
                    $role_info = \PublishPress\Permissions\API::countRoles(
                        'user', 
                        ['query_agent_ids' => array_keys($wp_list_table->items)]
                    );
                }

                $user_object = new \WP_User((int)$id);

                static $hide_roles;
                if (!isset($hide_roles)) {
                    $hide_roles = (!defined('bbp_get_version'))
                        ? ['bbp_participant', 'bbp_moderator', 'bbp_keymaster', 'bbp_blocked', 'bbp_spectator']
                        : [];

                    $hide_roles = apply_filters('presspermit_hide_roles', $hide_roles);
                }

                // === clean up after any inappropriate role metagroup auto-deletion ===
                $user_groups = $pp_groups->getGroupsForUser($id, 'pp_group');  // these are already being buffered, so no extra DB overhead
                $has_wp_role_metagroup = false;
                foreach ($user_groups as $group) {
                    if (('wp_role' == $group->metagroup_type) && !in_array($group->metagroup_id, ['wp_auth', 'wp_all'], true)
                        && !in_array($group->metagroup_id, $hide_roles, true)
                    ) {
                        $has_wp_role_metagroup = true;
                        break;
                    }
                }

                // if this user does not have at least on role metagroup stored, see if one should be added
                if (!$has_wp_role_metagroup) {
                    foreach ($user_object->roles as $role_name) {
                        if ($role_group = $pp_groups->getMetagroup('wp_role', $role_name)) {
                            $pp_groups->addGroupUser($role_group->ID, $id);

                            // force reload of supplemental roles and exceptions
                            $role_info = \PublishPress\Permissions\API::countRoles(
                                'user',
                                ['query_agent_ids' => array_keys($wp_list_table->items), 'force_refresh' => true]
                            );

                            DashboardFilters::listAgentExceptions(
                                'user',
                                $id,
                                ['query_agent_ids' => array_keys($wp_list_table->items), 'force_refresh' => true]
                            );

                            break;
                        }
                    }
                }
                // === end role metagroup cleanup ===

                $user_object->roles = array_diff($user_object->roles, $hide_roles);

                $role_titles = [];
                foreach ($user_object->roles as $role_name) {
                    if (isset($wp_roles->role_names[$role_name]))
                        $role_titles[] = $wp_roles->role_names[$role_name];
                }

                if (isset($role_info[$id]) && isset($role_info[$id]['roles']))
                    $role_titles = array_merge($role_titles, array_keys($role_info[$id]['roles']));

                $display_limit = 3;
                if (count($role_titles) > $display_limit) {
                    $excess = count($role_titles) - $display_limit;
                    $role_titles = array_slice($role_titles, 0, $display_limit);
                    $role_titles[] = sprintf(__('%s&nbsp;more', 'press-permit-core'), $excess);
                }

                $role_str = '<span class="pp-group-site-roles">' . implode(', ', $role_titles) . '</span>';

                if (current_user_can('pp_assign_roles') && (is_multisite() || current_user_can('edit_user', $id))) {
                    $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                    $role_str = "<a href=\"$edit_link\">$role_str</a><br />";
                }

                return $role_str;
                break;

            case 'pp_exceptions':
                global $wp_list_table;
                return DashboardFilters::listAgentExceptions('user', $id, ['query_agent_ids' => array_keys($wp_list_table->items)]);
                break;

            default:
                return $content;
        }
    }

    public static function fltUserQuery($query)
    {
        // invalid user count due to redundant joined usermeta rows; no WP_User_Query filter effective when fields arg is 'all_with_meta'

        global $wpdb;
        if (0 === strpos($query, "SELECT SQL_CALC_FOUND_ROWS $wpdb->users.ID FROM"))
            $query = str_replace("$wpdb->users.ID FROM", "DISTINCT $wpdb->users.ID FROM", $query);

        return $query;
    }

    public static function fltUserQueryExceptions($query_obj)
    {
        if (isset($_REQUEST['orderby']) && 'pp_group' == $_REQUEST['orderby']) {
            global $wpdb;

            $query_obj->query_where = " INNER JOIN $wpdb->pp_group_members AS gm ON gm.user_id = $wpdb->users.ID"
                . " INNER JOIN $wpdb->pp_groups as g ON gm.group_id = g.ID AND g.metagroup_id='' "
                . $query_obj->query_where;

            $order = (isset($_REQUEST['order']) && ('desc' == $_REQUEST['order'])) ? 'DESC' : 'ASC';
            $query_obj->query_orderby = "ORDER BY g.group_name $order, $wpdb->users.display_name";
        } elseif (isset($_REQUEST['pp_no_group'])) {
            global $wpdb;
            $query_obj->query_where .= " AND $wpdb->users.ID NOT IN ( SELECT gm.user_id FROM $wpdb->pp_group_members AS gm"
                . " INNER JOIN $wpdb->pp_groups as g ON gm.group_id = g.ID AND g.metagroup_id='' )";
        }

        if (!empty($_REQUEST['pp_user_exceptions'])) {
            global $wpdb;
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i"
                . " ON e.exception_id = i.exception_id WHERE e.agent_type = 'user' )";
        }

        if (!empty($_REQUEST['pp_user_roles'])) {
            global $wpdb;
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )";
        }

        if (!empty($_REQUEST['pp_user_perms'])) {
            global $wpdb;
            $query_obj->query_where .= " AND ( ID IN ( SELECT agent_id FROM $wpdb->ppc_roles"
                . " WHERE agent_type = 'user' ) OR ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' ) )";
        }

        if (!empty($_REQUEST['pp_has_exceptions'])) {
            global $wpdb;
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' )";
        }

        if (!empty($_REQUEST['pp_has_roles'])) {
            global $wpdb;
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' )";
        }

        if (!empty($_REQUEST['pp_has_perms'])) {
            global $wpdb;
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' )"
                . " OR ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' )";
        }

        if (!empty($_REQUEST['pp_group'])) {
            global $wpdb;
            $query_obj->query_where .= " AND ID IN ( SELECT user_id FROM $wpdb->pp_group_members WHERE group_id = '" . (int)$_REQUEST['pp_group'] . "' )";
        }

        return $query_obj;
    }

    private static function groupsBulk()
    {
        $sfx = '';

        if (!empty($_REQUEST['pp-bulk-group2']))
            $sfx = '2';
        elseif (empty($_REQUEST['pp-bulk-group']))
            return;

        if (
            empty($_REQUEST['users']) || (empty($_REQUEST['pp-add-group-members' . $sfx])
                && empty($_REQUEST['pp-remove-group-members' . $sfx]))
        ) {
            return;
        }

        check_admin_referer( 'pp-bulk-assign-groups', 'pp-bulk-groups-nonce' );

        if (!current_user_can('list_users'))
            return;

        $group_id = $_REQUEST['pp-bulk-group' . $sfx];

        if (!current_user_can('pp_manage_members')) {
            if (!in_array($group_id, apply_filters('presspermit_admin_groups', []))) {
                return;
            }

            global $current_user;
            $_REQUEST['users'] = array_diff($_REQUEST['users'], [$current_user->ID]);
        }

        if (!empty($_REQUEST['pp-add-group-members' . $sfx])) {
            presspermit()->groups()->addGroupUser($group_id, $_REQUEST['users']);

        } elseif (!empty($_REQUEST['pp-remove-group-members' . $sfx])) {
            presspermit()->groups()->removeGroupUser($group_id, $_REQUEST['users']);
        }
    }

}
