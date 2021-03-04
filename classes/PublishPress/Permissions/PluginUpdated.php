<?php

namespace PublishPress\Permissions;

class PluginUpdated
{
    public function __construct($prev_version)
    {
        // single-pass do loop to easily skip unnecessary version checks
        do {
            if (!$prev_version) {
                if (false === get_option('presspermit_deactivated_modules')) {
                    self::deactivateModules(['current_deactivations' => []]);
                }

                if (get_option('pp_version') && !get_option('presspermit_group_index_drop_done')) {  // previous installation of PP < 2.0 ?
                    update_option('presspermit_need_group_index_drop', true); // flag groups index drop to be launched from PP Options
                }

                break;  // no need to run through version comparisons if no previous version
            }

            // @todo: confirm this is only needed after Import from Role Scoper / Press Permit Beta
            global $wpdb;
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'buffer_metagroup_id_%'");

            if (version_compare($prev_version, '2.7-beta', '>=')
            && version_compare($prev_version, '2.7-beta3', '<')
            ) {
                // Previous 2.7 betas added wrong capabilities
                // (@todo: possibly migrate all capabilities, but not yet)
                if ($role = @get_role('administrator')) {
                    $role->remove_cap('presspermit_create_groups');
                    $role->remove_cap('presspermit_delete_groups');
                    $role->add_cap('pp_create_groups');
                    $role->add_cap('pp_delete_groups');
                }
        
                if ($role = @get_role('editor')) {
                    $role->remove_cap('presspermit_create_groups');
                    $role->remove_cap('presspermit_delete_groups');
                    $role->add_cap('pp_create_groups');
                    $role->add_cap('pp_delete_groups');
                }

                // Delete any invalid user metagroup relationships from bad buffer_metagroup_id values
                self::syncWordPressRoles();
            }

            if (version_compare($prev_version, '3.3.3', '<')) {
                // Activation of invalid "Custom permissions for Authors" setting on Edit Author screen broke Authors > Authors listing and editing access
                if ($taxs = get_option('presspermit_enabled_taxonomies')) {
                    if (!empty($taxs['author'])) {
                        unset($taxs['author']);
                        update_option('presspermit_enabled_taxonomies', $taxs);
                    }
                }
            } else break;

            if ((presspermit()->isPro() && version_compare($prev_version, '2.7.11', '<')) || (!presspermit()->isPro() && version_compare($prev_version, '2.7.3', '<'))) {
                // 2.7 and 2.7.1 incorrectly defaulted this array to ['pp-import'] instead of ['presspermit-import']
                // PressPermit Core 2.7.2 fixed the bug, but did not apply this database patch.
                // Beginning with 2.7.12, PressPermit Pro applies the database patch on first-time Pro execution
                $deactivated = (array) get_option('presspermit_deactivated_modules');

                if (isset($deactivated['pp-import'])) {
                    $deactivated = array_diff_key($deactivated, ['pp-import' => true]);
                    update_option('presspermit_deactivated_modules', $deactivated);
                }
            } else break;

            if (version_compare($prev_version, '2.7-beta', '<')) {
                require_once(PRESSPERMIT_CLASSPATH . '/DB/Migration.php');
                DB\Migration::migrateOptions();
            } else break;

            if (version_compare($prev_version, '2.3.19-dev', '<')) {
                require_once(PRESSPERMIT_CLASSPATH . '/DB/Migration.php');
                DB\Migration::remove_group_members_pk();
            } else break;

            if (version_compare($prev_version, '2.1.47-dev', '<')) {
                if (!get_option('presspermit_post_blockage_priority')) {
                    // previously, post-assigned reading/editing blockages could be overriden by category-assigned additions
                    update_option('presspermit_legacy_exception_handling', true);
                }
            } else break;

            if (version_compare($prev_version, '2.1.35', '<')) {
                require_once(PRESSPERMIT_CLASSPATH . '/DB/Migration.php');

                // Previously, page exceptions were propagated to all descendents, including attachments (but this is unnecessary and potentially undesirable)
                DB\Migration::delete_propagated_attachment_exceptions();
                DB\Migration::expose_attachment_exception_items();  // "include" exceptions for Read/Edit operations are exposed but not deleted, since they affect access to other media 

                // Previously, propagated exceptions were not removed when parent exception assign_for was changed to item only.  Expose them by setting inherited_from to 0
                //DB\Migration::expose_orphaned_exception_items();

                require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');
                \PublishPress\Permissions\DB\Permissions::expose_orphaned_exception_items();
            } else break;

            if (version_compare($prev_version, '2.1.33', '<')) {
                if ($enabled_taxonomies = get_option('presspermit_enabled_taxonomies')) {
                    // previously, post_tag was disabled by default but implicitly enabled for front-end filtering
                    $enabled_taxonomies['post_tag'] = true;
                    update_option('presspermit_enabled_taxonomies', $enabled_taxonomies);
                }
            } else break;

            if (version_compare($prev_version, '2.1.16-beta', '<')) {
                global $wpdb;
                $wpdb->query("UPDATE $wpdb->ppc_exceptions SET for_item_source = 'post' WHERE for_item_source = 'all'");
            } else break;
        } while (0); // end single-pass version check loop

        /*
        if ( $prev_version && is_admin() ) {
            if ( preg_match( "/dev|alpha|beta|rc/i", PRESSPERMIT_VERSION ) && ! preg_match( "/dev|alpha|beta|rc/i", $prev_version ) ) {
                presspermit()->admin()->notice( __( 'You have installed a development / beta version of PressPermit Pro. If this is a concern, see Permissions > Settings > Install > Beta Updates.', 'press-permit-core' ), 'updated' );
            }
        }
        */
    }

    public static function deactivateModules($args = []) {
        $deactivated = (isset($args['current_deactivations'])) 
        ? $args['current_deactivations'] 
        : get_option('presspermit_deactivated_modules');
        
        if (!$deactivated) {
            $deactivations = [];
        }

        if (!empty($args['deactivate'])) {
            $new_deactivations = array_intersect($args['deactivate'], presspermit()->getAvailableModules());
        } else {
            // default deactivations
            $new_deactivations = [
                'presspermit-circles', 
                'presspermit-file-access', 
                'presspermit-import', 
                'presspermit-membership', 
                'presspermit-teaser'
            ];
        }

        if (!empty($args['activate'])) {
            $new_deactivations = array_diff($new_deactivations, $args['activate']);
        }

        $deactivated = array_merge(
            $deactivated, 
            array_fill_keys($new_deactivations, (object)[])
        );
        
        update_option('presspermit_deactivated_modules', $deactivated);
    }

    public static function populateRoles($reload_user = false)
    {
        if ($role = @get_role('administrator')) {
            $role->remove_cap('pp_bulk_assign_roles');
            $role->add_cap('pp_manage_settings');
            $role->add_cap('pp_administer_content');
            $role->add_cap('pp_create_groups');
            $role->add_cap('pp_edit_groups');
            $role->add_cap('pp_delete_groups');
            $role->add_cap('pp_manage_members');
            $role->add_cap('pp_assign_roles');
            $role->add_cap('pp_set_read_exceptions');
        }

        if ($role = @get_role('editor')) {
            $role->remove_cap('pp_bulk_assign_roles');
            $role->add_cap('pp_create_groups');
            $role->add_cap('pp_edit_groups');
            $role->add_cap('pp_delete_groups');
            $role->add_cap('pp_manage_members');
            $role->add_cap('pp_assign_roles');
            $role->add_cap('pp_set_read_exceptions');
            $role->add_cap('pp_moderate_any');
        }

        update_option('ppperm_added_role_caps_10beta', true);

        if ($reload_user) {
            global $wp_roles;
            $wp_roles = new \WP_Roles();

            // force full menu display after activation
            global $current_user, $wpdb;
            if (!empty($current_user)) {
                wp_cache_delete($current_user->ID, 'user_meta');
                $current_user = new \WP_User($current_user->ID);

                $pp = presspermit();
                if ($pp->isUserSet()) {
                    $pp->getUser()->allcaps = array_merge($pp->getUser()->allcaps, $current_user->allcaps);
                }
            }
        }
    }

    public static function deleteOrphanedExceptions()
    {
        global $wpdb;

        if ($orphan_ids = $wpdb->get_col(
            "SELECT eitem_id FROM $wpdb->ppc_exception_items AS i INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id "
            . "WHERE ( e.via_item_source = 'post' AND i.item_id NOT IN ( SELECT ID FROM $wpdb->posts ) )"
            . " OR ( e.via_item_source = 'term' AND i.item_id NOT IN ( SELECT term_taxonomy_id FROM $wpdb->term_taxonomy ) )"
        )) {
            $wpdb->query("DELETE FROM $wpdb->ppc_exception_items WHERE eitem_id IN ('" . implode("','", $orphan_ids) . "')");
        }
    }

    public static function dropGroupIndexesSql()
    {
        global $wpdb;

        return [
            "DROP INDEX `pp_group_meta_id` ON $wpdb->pp_groups",
            "DROP INDEX `pp_group_metaid` ON $wpdb->pp_groups",
            "DROP INDEX `pp_statuskey` ON $wpdb->pp_group_members",
            "DROP INDEX `pp_status_key` ON $wpdb->pp_group_members",
            "DROP INDEX `pp_datekey` ON $wpdb->pp_group_members",
            "DROP INDEX `pp_date_key` ON $wpdb->pp_group_members",
        ];
    }

    public static function doIndexDrop($arr_sql, $option_key, $prevent_retry = true)
    {
        global $wpdb;

        // in case of failure, don't do this more than once
        if ($prevent_retry) {
            if (get_option($option_key) && !$arr_sql) {
                return;
            }
        }

        update_option($option_key, true);

        $wpdb->suppress_errors = true;
        foreach ($arr_sql as $sql) {
            @$wpdb->query($sql);
        }

        $wpdb->suppress_errors = false;
    }

    public static function syncWordPressRoles($user_ids = '', $role_name_arg = '', $blog_id_arg = '')
    {
        global $wpdb, $wp_roles;

        if (empty($wp_roles->role_objects)) {
            return;
        }

        if (!$user_ids) {
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'presspermit_buffer_metagroup%'");
        }

        if ($user_ids) {
            $user_ids = (array)$user_ids;
        }

        $metagroups = $stored_metagroups = $all_role_metagroups = $stored_role_metagroups = $insert_sql_rows = $delete_clauses = [];

        if ($blog_id_arg) {
            $members_table = ($blog_id_arg > 1)
                ? $wpdb->base_prefix . $blog_id_arg . '_' . 'pp_group_members'
                : $wpdb->base_prefix . 'pp_group_members';
        } else {
            $members_table = $wpdb->pp_group_members;
        }

        $length_limit = apply_filters('presspermit_rolename_max_length', 40);
        if ($length_limit < 40) {
            $length_limit = 40;  // pp_groups:metagroup_id db column width is 64, but default to limit of 40 to avoid forcing change to any existing stored truncations
        }

        // sync WP Role metagroups
        foreach (array_keys($wp_roles->role_objects) as $role_name) {
            $metagroup_id = trim(substr($role_name, 0, $length_limit));

            // if the name is too long and its truncated ID already taken, just exclude it from eligible metagroups
            if (!isset($metagroups[$metagroup_id])) {
                $metagroups[$metagroup_id] = (object)[
                    'type' => 'wp_role',
                    'name' => sprintf('[WP %s]', $role_name),
                    'descript' => sprintf('All users with a WordPress %s role', $role_name)
                ];
            }
        }

        // add a metagroup for anonymous users
        $metagroups['wp_anon'] = (object)[
            'type' => 'wp_role',
            'name' => 'Not Logged In',
            'descript' => 'Anonymous users (not logged in)'
        ];

        // add a metagroup for authenticated users
        $metagroups['wp_auth'] = (object)[
            'type' => 'wp_role',
            'name' => 'Logged In',
            'descript' => 'All users who are logged in and have a role on the site'
        ];

        // add a metagroup for all users
        $metagroups['wp_all'] = (object)[
            'type' => 'wp_role',
            'name' => 'Everyone',
            'descript' => 'All users (including anonymous)'
        ];

        // add metagroups for Revisionary notification recipients
        $metagroups['rvy_pending_rev_notice'] = (object)[
            'type' => 'rvy_notice',
            'name' => 'Pending Revision Monitors',
            'descript' => 'Administrators / Publishers to notify (by default) of pending revisions'
        ];

        $metagroups['rvy_scheduled_rev_notice'] = (object)[
            'type' => 'rvy_notice',
            'name' => 'Scheduled Revision Monitors',
            'descript' => 'Administrators / Publishers to notify when any scheduled revision is published'
        ];

        $do_group_deletions = defined('PP_AUTODELETE_ROLE_METAGROUPS') && PP_AUTODELETE_ROLE_METAGROUPS;

        if ($results = $wpdb->get_results("SELECT * FROM $wpdb->pp_groups WHERE metagroup_id != ''")) {
            $delete_metagroup_ids = [];

            foreach ($results as $row) {
                if (!isset($metagroups[$row->metagroup_id]) && $do_group_deletions) {
                    $delete_metagroup_ids[] = $row->ID;
                } else {
                    $stored_metagroups[$row->metagroup_id] = true;

                    if ('wp_role' == $row->metagroup_type) {
                        $all_role_metagroups[$row->metagroup_id] = $row->ID;
                    }
                }
            }

            if ($delete_metagroup_ids) {
                $id_in = implode("','", $delete_metagroup_ids);
                $wpdb->query("DELETE FROM $wpdb->pp_groups WHERE ID IN ('$id_in')");
                $wpdb->query("DELETE FROM $members_table WHERE group_id IN ('$id_in')");
            }
        }

        if ($insert_metagroups = array_diff_key($metagroups, $stored_metagroups)) {
            foreach ($insert_metagroups as $metagroup_id => $metagroup) {
                $wpdb->insert(
                    $wpdb->pp_groups,
                    [
                        'metagroup_id' => $metagroup_id,
                        'metagroup_type' => $metagroup->type,
                        'group_name' => $metagroup->name,
                        'group_description' => $metagroup->descript
                    ]
                );

                if (('wp_role' == $metagroup->type) && $wpdb->insert_id) {
                    $all_role_metagroups[$metagroup_id] = $wpdb->insert_id;
                }
            }
        }

        if ($user_ids || !defined('PP_SKIP_USER_SYNC') || !PP_SKIP_USER_SYNC) {
            $user_clause = ($user_ids) ? 'AND user_id IN (' . implode(', ', array_map('intval', $user_ids)) . ')' : '';

            // which user roles are already represented by PP metagroup membership?
            $results = $wpdb->get_results(
                "SELECT group_id, user_id FROM $members_table WHERE group_id IN ('" . implode("','", $all_role_metagroups) . "') $user_clause"
            );

            foreach ($results as $key => $row) {
                $stored_role_metagroups[$row->user_id][] = $row->group_id;
            }

            // Now step through every WP usermeta capabilities record, synchronizing PP metagroup membership with WP role and custom caps
            $usermeta = $wpdb->get_results(
                "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = '{$wpdb->prefix}capabilities' $user_clause"
            );

            foreach (array_keys($usermeta) as $key) {
                $user_caps = maybe_unserialize($usermeta[$key]->meta_value);
                if (empty($user_caps) || !is_array($user_caps))
                    continue;

                // Filter out caps that are not role names
                $user_role_metagroups = array_intersect_key($all_role_metagroups, array_diff($user_caps, ['', 0, false]));

                $user_id = $usermeta[$key]->user_id;

                if (isset($stored_role_metagroups[$user_id])) {
                    if ($delete_role_metagroups = array_diff($stored_role_metagroups[$user_id], $user_role_metagroups))
                        $delete_clauses[] = "user_id = '$user_id' AND group_id IN ('" . implode("','", $delete_role_metagroups) . "')";
                }

                if (isset($stored_role_metagroups[$user_id]))
                    $user_role_metagroups = array_diff($user_role_metagroups, $stored_role_metagroups[$user_id]);

                foreach ($user_role_metagroups as $group_id)
                    $insert_sql_rows[] = "('$user_id', '$group_id', 'member', 'active')";
            }

            if ($delete_clauses) {
                $wpdb->query("DELETE FROM $members_table WHERE ( " . implode(' ) OR ( ', $delete_clauses) . " )");
            }

            if ($insert_sql_rows) {
                $wpdb->query("INSERT INTO $members_table (user_id, group_id, member_type, status ) VALUES " . implode(',', $insert_sql_rows));
            }
        }

        update_option('presspermit_wp_role_sync', true);
    } // end syncWordPressRoles function
}
