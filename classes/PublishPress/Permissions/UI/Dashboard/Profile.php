<?php

namespace PublishPress\Permissions\UI\Dashboard;

require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');
require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsMeta.php');

class Profile
{
    public static function displayUserAssignedRoles($user)
    {
        $roles = [];

        $pp = presspermit();

        $post_types = $pp->getEnabledPostTypes([], 'object');
        $taxonomies = $pp->getEnabledTaxonomies([], 'object');

        $is_administrator = current_user_can('pp_administer_content') && current_user_can('list_users');

        $edit_url = ($is_administrator)
            ? "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$user->ID&amp;agent_type=user"
            : '';

        $roles = $pp->getRoles($user->ID, 'user', ['post_types' => $post_types, 'taxonomies' => $taxonomies]);

        $has_user_roles = \PublishPress\Permissions\UI\AgentPermissionsUI::currentRolesUI(
            $roles,
            [
                'read_only' => true,
                'caption' => sprintf(__('Supplemental Roles %1$s(for this user)%2$s', 'press-permit-core'), '<small>', '</small>'),
                'class' => 'pp-user-roles',
                'link' => $edit_url
            ]
        );

        $caption = sprintf(__('Specific Permissions %1$s(for user)%2$s', 'press-permit-core'), '<small>', '</small>');
        $new_permissions_link = true;
        $maybe_display_note = !$has_user_roles;
        $display_limit = 12;

        self::abbreviatedExceptionsList(
            'user',
            $user->ID,
            compact('edit_url', 'caption', 'new_permissions_link', 'maybe_display_note', 'display_limit')
        );
    }

    public static function displayUserRoles($user)
    {
        $roles = [];

        $pp = presspermit();

        $post_types = $pp->getEnabledPostTypes([], 'object');
        $taxonomies = $pp->getEnabledTaxonomies([], 'object');

        /*
            $is_administrator = current_user_can( 'pp_administer_content' ) && current_user_can('list_users');
    
            $edit_url = ( $is_administrator ) 
            ? "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$user->ID&amp;agent_type=user" 
            : '';
            */

        $user->retrieveExtraGroups();
        $user->getSiteRoles();

        foreach (array_keys($user->groups) as $agent_type) {
            foreach (array_keys($user->groups[$agent_type]) as $agent_id) {
                $roles = array_merge(
                    $roles,
                    $pp->getRoles(
                        $agent_id,
                        $agent_type,
                        ['post_types' => $post_types, 'taxonomies' => $taxonomies, 'query_agent_ids' => array_keys($user->groups[$agent_type])]
                    )
                );
            }
        }

        // @todo: review
        /*
            $link = ( current_user_can( 'pp_assign_roles' ) ) 
            ? "admin.php?page=presspermit-edit-permissions&amp;action=edit&agent_type=user&amp;agent_id=$user->ID" 
            : '';
            */

        \PublishPress\Permissions\UI\AgentPermissionsUI::currentRolesUI(
            $roles,
            [
                'read_only' => true,
                'link' => '',
                'caption' => sprintf(__('Supplemental Roles %1$s(from primary role or group membership)%2$s', 'press-permit-core'), '<small>', '</small>')
            ]
        );

        self::abbreviatedExceptionsList(
            'user',
            $user->ID,
            [
                'edit_url' => '',
                'class' => 'pp-group-roles',
                'caption' => __('Specific Permissions (from primary role or group membership)', 'press-permit-core'),
                'join_groups' => 'groups_only',
                'display_limit' => 12
            ]
        );
    }

    public static function displayUserGroups($include_role_metagroups = false, $args = [])
    {
        global $pagenow;

        $defaults = [
            'initial_hide' => false,
            'selected_only' => false,
            'hide_checkboxes' => false,
            'force_display' => false,
            'edit_membership_link' => false,
            'user_id' => false
        ];

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();
        $pp_groups = $pp->groups();

        if (!is_numeric($user_id)) {
            global $profileuser;
            $user_id = (!empty($profileuser)) ? $profileuser->ID : 0;
        }

        $group_types = $pp_groups->getGroupTypes(['editable' => true]);

        foreach ($group_types as $agent_type) {
            if (('pp_group' == $agent_type) && in_array('pp_net_group', $group_types, true) && (1 == get_current_blog_id())) {
                continue;
            }

            if (!$all_groups = $pp_groups->getGroups($agent_type)) {
                continue;
            }

            if (!in_array($agent_type, ['pp_group', 'pp_net_group'], true)) {
                continue;
            }

            // @todo: review
            //$reqd_caps = (array) apply_filters( 'presspermit_edit_groups_reqd_caps', ['pp_edit_groups'] );

            $editable_ids = (current_user_can('pp_manage_members'))
                ? array_keys($all_groups)
                : apply_filters('presspermit_admin_groups', []);

            $stored_groups = $pp_groups->getGroupsForUser($user_id, $agent_type, ['cols' => 'id']);

            $locked_ids = array_diff(array_keys($stored_groups), $editable_ids);

            // can't manually edit membership of WP Roles groups or other metagroups lacking _ed_ suffix
            $all_ids = [];
            foreach ($all_groups as $key => $group) {
                if ($selected_only && !isset($stored_groups[$group->ID])) {
                    unset($all_groups[$key]);
                    continue;
                }

                $all_ids[] = $group->ID;

                if (!$include_role_metagroups && !empty($group->metagroup_id) && ('wp_role' == $group->metagroup_type)) {
                    $editable_ids = array_diff($editable_ids, [$group->ID]);
                    unset($stored_groups[$group->ID]);
                    unset($all_groups[$key]);
                } elseif (!in_array($group->ID, $editable_ids) && !in_array($group->ID, $locked_ids)) {
                    unset($all_groups[$key]);
                }
            }

            $locked_ids = array_diff(array_keys($stored_groups), $editable_ids);

            // avoid incorrect eligible count if orphaned group roles are included in editable_ids
            $editable_ids = array_intersect($editable_ids, $all_ids);

            if (!$all_groups && !$force_display) {
                continue;
            }

            $style = ($initial_hide) ? "style='display:none'" : '';

            echo "<div id='userprofile_groupsdiv_pp' class='pp-group-box pp-group_members' $style>";
            echo "<h3>";

            if ('pp_group' == $agent_type) {
                if (defined('GROUPS_CAPTION_RS')) {
                    echo(GROUPS_CAPTION_RS);
                } elseif (defined('PP_GROUPS_CAPTION')) {
                    echo(PP_GROUPS_CAPTION);
                } else {
                    _e('Permission Groups', 'press-permit-core');
                }
            } else {
                $group_type_obj = $pp_groups->getGroupTypeObject($agent_type);
                echo $group_type_obj->labels->name;
            }

            echo "</h3>";

            $single_select = ('user-new.php' == $pagenow) || (!empty($_REQUEST['pp_ajax_user']) && ('new_user_groups_ui' == $_REQUEST['pp_ajax_user']))
            ? defined('PRESSPERMIT_ADD_USER_SINGLE_GROUP_SELECT') 
            : defined('PRESSPERMIT_EDIT_USER_SINGLE_GROUP_SELECT');

            $css_id = $agent_type;
            $args = [
                'eligible_ids' => $editable_ids,
                'locked_ids' => $locked_ids,
                'show_subset_caption' => false,
                'hide_checkboxes' => $hide_checkboxes,
                'single_select' => $single_select
            ];

            $pp->admin()->agents()->agentsUI($agent_type, $all_groups, $css_id, $stored_groups, $args);
            
            if ($edit_membership_link || (!$all_groups && $force_display)) :
                ?>
                <p>
                    <?php if (!$all_groups && $force_display) :
                        _e('This user is not a member of any custom Permission Groups.', 'press-permit-core');
                        ?>&nbsp;&bull;&nbsp;
                    <?php endif; ?>

                    <?php $title = esc_attr(__('Edit this user&apos;s group membership', 'press-permit-core')); ?>
                    <a href='user-edit.php?user_id=<?php echo $user_id; ?>#userprofile_groupsdiv_pp'
                       title='<?php echo($title); ?>'>
                        <?php _e('add / edit membership'); ?>
                    </a>
                    &nbsp;&nbsp;
                    <span class="pp-subtext">
                    <?php
                    $note = (defined('BP_VERSION'))
                        ? __('note: BuddyPress Groups and other externally defined groups are not listed here, even if they modify permissions', 'press-permit-core')
                        : '';

                    $note = apply_filters(
                        'presspermit_user_profile_groups_note',
                        $note,
                        $user_id,
                        $args
                    );

                    echo $note;
                    ?>
                </span>
                </p>
            <?php
            endif;

            echo '</div>';
        }  // end foreach agent_type

        echo "<input type='hidden' name='pp_editing_user_groups' value='1' />";
    }
    
    public static function listAgentExceptions($agent_type, $id, $args = [])
    {
        static $exception_info;

        $defaults = ['query_agent_ids' => [], 'show_link' => true, 'join_groups' => true, 'force_refresh' => false, 'display_limit' => 3];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (empty($args['query_agent_ids'])) {
            $args['query_agent_ids'] = (array)$id;
        }

        if (!isset($exception_info) || $force_refresh) {
            $exception_info = \PublishPress\Permissions\DB\PermissionsMeta::countExceptions($agent_type, $args);
        }

        $exc_str = '';

        if (isset($exception_info[$id])) {
            if (isset($exception_info[$id]['exceptions'])) {
                $exc_titles = [];
                $i = 0;
                foreach ($exception_info[$id]['exceptions'] as $exc_title => $exc_count) {
                    $i++;
                    $exc_titles[] = sprintf(__('%1$s (%2$s)', 'press-permit-core'), $exc_title, $exc_count);
                    if ($i >= $display_limit) {
                        break;
                    }
                }

                $exc_str = '<span class="pp-group-site-roles">' . implode(',&nbsp; ', $exc_titles) . '</span>';

                if (count($exception_info[$id]['exceptions']) > $display_limit) {
                    $exc_str = sprintf(__('%s, more...', 'press-permit-core'), $exc_str);
                }

                if ($show_link && current_user_can('pp_assign_roles') && (is_multisite() || current_user_can('edit_user', $id))) {
                    $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                    $exc_str = "<a href=\"$edit_link\">$exc_str</a><br />";
                }
            }
        }
        return $exc_str;
    }

    private static function abbreviatedExceptionsList($agent_type, $agent_id, $args = [])
    {
        $defaults = [
            'caption' => '',
            'edit_url' => '',
            'join_groups' => false,
            'class' => 'pp-user-roles',
            'new_permissions_link' => false,
            'maybe_display_note' => true
        ];

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        $args['show_link'] = false;
        $args['force_refresh'] = true;

        $new_permissions_link = $maybe_display_note && $pp->isUserAdministrator()
            && $pp->admin()->bulkRolesEnabled() && current_user_can('list_users');

        if (!$exceptions_ui = self::listAgentExceptions($agent_type, $agent_id, $args)) {
            if (('user' == $agent_type) && ($join_groups != 'groups_only') && $new_permissions_link) : ?>
                <div style="clear:both;"></div>
                <div id='pp_current_user_exceptions_ui' class='pp-group-box <?php echo $class; ?>'>
                    <h3>
                        <?php
                        _e('Custom User Permissions', 'press-permit-core');
                        ?>
                    </h3>
                    <p>
                        <?php
                        printf(
                            __('Supplemental roles and specific permissions assigned to a user\'s primary role or other Permission Groups are usually the cleanest way to customize permissions.  You can also %1$scustomize this user directly%2$s.', 'press-permit-core'),
                            "<a href='$edit_url'>",
                            '</a>'
                        );

                        ?>
                    </p>
                </div>
            <?php
            endif;

            return;
        }
        ?>
        <div style="clear:both;"></div>
        <div id='ppcurrentExceptionsUI' class='pp-group-box <?php echo $class; ?>'>
            <h3>
                <?php
                echo ($edit_url) ? "<a href='{$edit_url}'>{$caption}</a>" : $caption;
                ?>
            </h3>
            <?php
            echo $exceptions_ui;
            ?>
        </div>
        <?php
    }
}
