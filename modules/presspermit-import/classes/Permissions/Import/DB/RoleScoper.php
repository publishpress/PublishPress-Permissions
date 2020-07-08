<?php
namespace PublishPress\Permissions\Import\DB;

require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/Importer.php');

class RoleScoper extends \PublishPress\Permissions\Import\Importer
{
    private $all_post_ids = [];
    private $tt_ids_by_taxonomy = [];
    private $importing_publish_exceptions;

    private static $instance = null;

    public static function instance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new RoleScoper();
        }

        return self::$instance;
    }

    public function __construct() // some PHP versions do not allow subclass constructor to be private
    {
        parent::__construct();
        $this->import_types = ['sites' => __('Sites', 'press-permit-core'), 'groups' => __('Groups', 'press-permit-core'), 'group_members' => __('Group Members', 'press-permit-core'), 'site_roles' => __('General Roles', 'press-permit-core'), 'item_roles' => __('Term / Object Roles', 'press-permit-core'), 'restrictions' => __('Restrictions', 'press-permit-core'), 'options' => __('Options', 'press-permit-core')];
    }

    function doImport($import_type = 'rs')
    {
        global $wpdb;

        $blog_id = get_current_blog_id();

        parent::doImport('rs');

        $this->all_post_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type NOT IN ('revision', 'attachment') AND post_status NOT IN ('auto_draft')");

        $results = $wpdb->get_results("SELECT * FROM $wpdb->term_taxonomy");
        foreach ($results as $row) {
            if (!isset($this->tt_ids_by_taxonomy[$row->taxonomy]))
                $this->tt_ids_by_taxonomy[$row->taxonomy] = [];

            $this->tt_ids_by_taxonomy[$row->taxonomy][$row->term_id] = $row->term_taxonomy_id;
        }

        if (is_multisite() && is_main_site()) {
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs ORDER BY blog_id");
            $orig_blog_id = $blog_id;
            $this->sites_examined = 0;
        } else
            $blog_ids = [$blog_id];

        foreach ($blog_ids as $id) {
            if (count($blog_ids) > 1) {
                switch_to_blog($id);
                $this->sites_examined++;

                SourceConfig::setRoleScoperTables();

                if (!$wpdb->get_results("SHOW TABLES LIKE '$wpdb->user2role2object_rs'")) {
                    continue;    // RS tables were never created for this site, so skip it
                }

                // early alpha versions of PPC didn't have this hooked to action
                require(PRESSPERMIT_ABSPATH . '/db-config.php');

                if (!$wpdb->get_results("SHOW TABLES LIKE '$wpdb->ppc_exceptions'")) {
                    require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');
                    new \PublishPress\Permissions\DB\DatabaseSetup();    // PP tables were not yet created for this site, so create them
                }
            }

            try {
                if (!defined('PPI_NO_OPTION_IMPORT'))
                    $this->import_rs_options();

                $this->import_rs_groups();
                $this->import_rs_site_roles();
                $this->import_rs_restrictions();
                $this->import_rs_item_roles();

            } catch (Exception $e) {
                $this->return_error = $e;
                $this->timed_out = true;
                parent::updateCounts();

                if (count($blog_ids) > 1)
                    restore_current_blog();

                return;
            }
        }

        $this->completed = true;
        parent::updateCounts();
    }

    private function import_rs_groups()
    {
        global $wpdb;

        $blog_id = get_current_blog_id();

        // if groups were set to netwide, sites may not have their own RS groups/members tables
        if (!$wpdb->get_results("SHOW TABLES LIKE '$wpdb->groups_rs'") || !$wpdb->get_results("SHOW TABLES LIKE '$wpdb->user2group_rs'"))
            return false;

        $rs_groups = $wpdb->get_results("SELECT $wpdb->groups_id_col as rs_group_id, $wpdb->groups_name_col AS group_name, $wpdb->groups_descript_col AS group_description, $wpdb->groups_meta_id_col AS group_meta_id FROM $wpdb->groups_rs", OBJECT_K);

        $existing_pp_groups = $wpdb->get_results("SELECT group_name, ID as pp_group_id, group_description, metagroup_id, metagroup_type FROM $wpdb->pp_groups", OBJECT_K);
        $imported_members = $wpdb->get_results($wpdb->prepare("SELECT rel_id AS user_id, source_id AS rs_group_id, import_id AS pp_group_id, import_tbl FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->user2group_rs)), OBJECT_K);

        $inserts = [];
        $errors = [];

        $do_rs_groups = $rs_groups;

        foreach ($do_rs_groups as $rs_group) {
            parent::checkTimeout();

            $data = ['group_name' => $rs_group->group_name, 'group_description' => $rs_group->group_description, 'metagroup_type' => '', 'metagroup_id' => ''];

            // TODO: * import meta groups only if no existing meta_id match
            //         * if existing metagroup, log RS > PP group_ids for role import and rvy metagroup membership

            if (0 === strpos($rs_group->group_meta_id, 'wp_role_')) {
                $data['metagroup_type'] = 'wp_role';
                $data['metagroup_id'] = substr($rs_group->group_meta_id, 8);

            } elseif (0 === strpos($rs_group->group_meta_id, 'rv_')) {
                $data['metagroup_type'] = 'rvy_notice';

                if (strpos($rs_group->group_meta_id, 'pending'))
                    $data['metagroup_id'] = 'rvy_pending_rev_notice';
                elseif (strpos($rs_group->group_meta_id, 'scheduled'))
                    $data['metagroup_id'] = 'rvy_scheduled_rev_notice';

            } elseif ('wp_anon' == $rs_group->group_meta_id) {
                $data['metagroup_type'] = 'wp_role';
                $data['metagroup_id'] = 'wp_anon';
                $data['group_name'] = '{Anonymous}';
            }

            if (isset($existing_pp_groups[$data['group_name']])) {
                $pp_group_id = $existing_pp_groups[$data['group_name']]->pp_group_id;
            } else {
                $wpdb->insert($wpdb->pp_groups, $data);
                $pp_group_id = (int)$wpdb->insert_id;

                $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->groups_rs), 'source_id' => $rs_group->rs_group_id, 'import_tbl' => $this->getTableCode($wpdb->pp_groups), 'import_id' => $pp_group_id, 'site' => $blog_id];
                $wpdb->insert($wpdb->ppi_imported, $log_data);

                $this->total_imported++;
                $this->num_imported['groups']++;
            }

            if (!empty($data['metagroup_type']) && ($data['metagroup_type'] == 'wp_role')) {    // RS stores WP role group membership alongside RS role assignments, but PP activation has already inserted equivalent records in pp_group_members
                $rs_group_members = [];
            } else {
                $rs_group_members = $wpdb->get_results("SELECT $wpdb->user2group_uid_col as user_id, $wpdb->user2group_status_col AS status FROM $wpdb->user2group_rs WHERE $wpdb->user2group_gid_col = '$rs_group->rs_group_id'", OBJECT_K);
            }

            $existing_pp_members = $wpdb->get_results("SELECT user_id, status FROM $wpdb->pp_group_members WHERE group_id = '$pp_group_id'", OBJECT_K);

            $do_group_members = array_diff_key($rs_group_members, $imported_members, $existing_pp_members);

            foreach ($do_group_members as $member) {
                $data = ['user_id' => $member->user_id, 'group_id' => $pp_group_id, 'member_type' => 'member', 'status' => $member->status];

                $wpdb->insert($wpdb->pp_group_members, $data);

                $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->user2group_rs), 'import_tbl' => $this->getTableCode($wpdb->pp_group_members), 'source_id' => $rs_group->rs_group_id, 'import_id' => $pp_group_id, 'rel_id' => $member->user_id, 'site' => $blog_id];
                $wpdb->insert($wpdb->ppi_imported, $log_data);

                $this->total_imported++;
                $this->num_imported['group_members']++;
            }
        }
    }

    private function import_rs_site_roles()
    {
        global $wpdb;

        $blog_id = get_current_blog_id();

        /*--------- group config and mapping setup ---------*/
        $rs_groups_table = (is_multisite() && get_site_option('scoper_mu_sitewide_groups')) ? $wpdb->base_prefix . 'groups_rs' : $wpdb->groups_rs;
        $pp_groups_table = (is_multisite() && get_site_option('presspermit_netwide_groups')) ? $wpdb->base_prefix . 'pp_groups' : $wpdb->pp_groups;
        $group_agent_type = (is_multisite() && get_site_option('presspermit_netwide_groups')) ? 'pp_net_group' : 'pp_group';

        $imported_pp_groups = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d AND import_tbl = %d", $this->getTableCode($rs_groups_table), $this->getTableCode($pp_groups_table)), OBJECT_K);
        $role_metagroups_rs = $wpdb->get_results("SELECT ID, group_meta_id FROM $rs_groups_table WHERE group_meta_id LIKE 'wp_role_%' OR group_meta_id = 'wp_anon'", OBJECT_K);
        $role_metagroups_pp = $wpdb->get_results("SELECT metagroup_id, ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'", OBJECT_K);
        $stored_pp_groups = $wpdb->get_results("SELECT group_name, ID FROM $pp_groups_table WHERE metagroup_type != 'wp_role'", OBJECT_K);
        $rs_group_names = $wpdb->get_results("SELECT ID, group_name FROM $rs_groups_table WHERE group_meta_id NOT LIKE 'wp_role_%'", OBJECT_K);
        /*----------- end group mapping setup -------------*/

        // TODO: assigner_id for exceptions / exception_items ?
        //
        // RS site roles import
        $imported_roles = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->user2role2object_rs)), OBJECT_K);

        $stored_roles = $wpdb->get_results("SELECT * FROM $wpdb->ppc_roles WHERE agent_type IN ( 'pp_group', 'user' )");

        $results = $wpdb->get_results("SELECT assignment_id AS source_id, role_name, user_id, group_id, assigner_id FROM $wpdb->user2role2object_rs WHERE role_type = 'rs' AND scope = 'blog' AND date_limited = '0' AND content_date_limited = '0'", OBJECT_K);
        $results = array_diff_key($results, $imported_roles);

        foreach ($results as $row) {
            parent::checkTimeout();

            $arr = explode('_', $row->role_name);

            if (0 === strpos($row->role_name, 'private_')) {
                $item_type = $arr[1];
                $item_status = 'private';
                unset($arr[0]);
                unset($arr[1]);
                $role_name = implode('_', $arr);
            } else {
                $item_type = $arr[0];
                $item_status = '';
                unset($arr[0]);
                $role_name = implode('_', $arr);
            }

            //pp_dump($item_type);

            if (post_type_exists($item_type)) {
                $item_source = 'post';

                if ($role_name == 'reader')
                    $role_name = 'subscriber';

                $pp_role_name = "{$role_name}:post:{$item_type}";
                if ($item_status)
                    $pp_role_name .= ":post_status:{$item_status}";

            } elseif (taxonomy_exists($item_type)) {
                if ('manager' == $role_name)
                    $pp_role_name = "pp_{$item_type}_manager";
                else
                    continue;
            } else
                continue;

            $agent_type = ($row->user_id) ? 'user' : $group_agent_type;

            if ($row->user_id && !is_null($row->user_id))
                $agent_id = $row->user_id;
            else {
                if (isset($role_metagroups_rs[$row->group_id])) {
                    // this is a WP role metagroup
                    $agent_type = 'pp_group'; // netwide group setting does not apply to role metagroups

                    $role_name = str_replace('wp_role_', '', $role_metagroups_rs[$row->group_id]->group_meta_id);
                    if (isset($role_metagroups_pp[$role_name]))
                        $agent_id = $role_metagroups_pp[$role_name]->ID;
                    else
                        continue;

                } elseif (isset($imported_pp_groups[$row->group_id])) {    // match against group import record if available
                    $agent_id = $imported_pp_groups[$row->group_id]->import_id;

                } elseif (isset($rs_group_names[$row->group_id])) {
                    if (isset($stored_pp_groups[$rs_group_names[$row->group_id]->group_name]))    // otherwise match group name
                        $agent_id = $stored_pp_groups[$rs_group_names[$row->group_id]->group_name]->ID;
                    else
                        continue;
                } else
                    continue;
            }


            $wpdb->insert_id = 0;        // Thanks to Sumon and Warren: https://stackoverflow.com/a/14168822
            $sql = "INSERT INTO $wpdb->ppc_roles (agent_type, agent_id, role_name, assigner_id) SELECT * FROM ( SELECT '$agent_type' AS a, '$agent_id' AS b, '$pp_role_name' AS c, '$row->assigner_id' AS d ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_roles WHERE agent_type = '$agent_type' AND agent_id = '$agent_id' AND role_name = '$pp_role_name') LIMIT 1";

            $wpdb->query($sql);
            if ($wpdb->insert_id) {
                $assignment_id = (int)$wpdb->insert_id;

                $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->user2role2object_rs), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_roles), 'import_id' => $assignment_id, 'site' => $blog_id];
                $wpdb->insert($wpdb->ppi_imported, $log_data);

                $this->total_imported++;
                $this->num_imported['site_roles']++;
            }
        }
    }

    private function import_rs_restrictions()
    {
        global $wpdb, $wp_roles;

        $blog_id = get_current_blog_id();

        $post_types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');
        $log_eitem_ids = [];        // conversion of role_scope_rs.requirement_id to pp_conditions.assignment_id
        $rs_inherited_from = [];    // 

        $imported_restrictions = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->role_scope_rs)), OBJECT_K);
        
        //echo 'imported: ';
        //var_dump($imported_restrictions);

        $pp_agent_id = [];
        $results = $wpdb->get_results("SELECT metagroup_id, ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'");
        foreach ($results as $row) {
            $pp_agent_id[$row->metagroup_id] = $row->ID;
        }

        /*
        post_reader => Subscriber (and any other role with read but not read_private or edit_posts)
        private_post_reader => Subscriber (and any other roles that have read_private but not edit_posts)
        post_contributor => Contributor (and any other roles that have edit_posts but not edit_published_posts)
        page_contributor => (any role that has edit_pages but not edit_published_pages or edit_others_pages)
        post_author => Author (and any other roles that have edit_published but not edit_others)
        *_author => 
        *_revisor => Revisor (any role that has edit_posts and edit_others but not edit_published)
        *_editor => Editor (any role that has edit_posts, edit_others and edit_published)
        */
        $wp_role_restrictions = [];
        foreach ($post_types as $post_type => $type_obj) {
            $cap = $type_obj->cap;

            if (!isset($cap->edit_posts))
                continue;

            if (empty($cap->edit_published_posts))
                $cap->edit_published_posts = str_replace('edit_', 'edit_published', $cap->edit_posts);

            if (empty($cap->edit_others_posts))
                $cap->edit_others_posts = str_replace('edit_', 'edit_others', $cap->edit_posts);

            if (empty($cap->read_private_posts))
                $cap->read_private_posts = str_replace('edit_', 'read_private_', $cap->edit_posts);

            foreach (array_keys($pp_agent_id) as $role_name) {
                parent::checkTimeout();

                if (!isset($wp_roles->role_objects[$role_name]) || !isset($pp_agent_id[$role_name]))
                    continue;

                $role_caps = array_intersect($wp_roles->role_objects[$role_name]->capabilities, [1, "1", true]);

                $exemption_caps = ['activate_plugins', 'administer_content', 'pp_administer_content'];
                if (defined('SCOPER_CONTENT_ADMIN_CAP'))
                    $exemption_caps[] = constant('SCOPER_CONTENT_ADMIN_CAP');

                if (array_intersect_key($role_caps, array_fill_keys($exemption_caps, true)))
                    continue;

                if (!empty($role_caps['read']) && empty($role_caps[$cap->read_private_posts]) && empty($role_caps[$cap->edit_posts])) {
                    $wp_role_restrictions["{$post_type}_reader"][] = $role_name;
                }

                if (!empty($role_caps[$cap->read_private_posts]) && empty($role_caps[$cap->edit_posts])) {
                    $wp_role_restrictions["private_{$post_type}_reader"][] = $role_name;
                }

                if (!empty($role_caps[$cap->edit_posts]) && empty($role_caps[$cap->edit_published_posts]) && empty($role_caps[$cap->edit_others_posts])) {
                    $wp_role_restrictions["{$post_type}_contributor"][] = $role_name;
                }

                if (!empty($role_caps[$cap->edit_published_posts]) && empty($role_caps[$cap->edit_others_posts])) {
                    $wp_role_restrictions["{$post_type}_author"][] = $role_name;
                }

                if (!empty($role_caps[$cap->edit_posts]) && !empty($role_caps[$cap->edit_others_posts]) && empty($role_caps[$cap->edit_published_posts])) {
                    $wp_role_restrictions["{$post_type}_revisor"][] = $role_name;
                }

                if (!empty($role_caps[$cap->edit_posts]) && !empty($role_caps[$cap->edit_others_posts]) && !empty($role_caps[$cap->edit_published_posts])) {
                    $wp_role_restrictions["{$post_type}_editor"][] = $role_name;
                }
            }
        }


        // === Restrictions and Unrestrictions (direct-assigned) ===
        $default_restrictions = $wpdb->get_results("SELECT requirement_id AS source_id, role_name, require_for, inherited_from, topic, src_or_tx_name, max_scope FROM $wpdb->role_scope_rs WHERE role_type = 'rs' AND ( topic = 'term' OR ( topic = 'object' AND src_or_tx_name = 'post' ) ) AND max_scope = topic AND obj_or_term_id = '0'", OBJECT_K);
        $def_restrictions_for = [];
        foreach ($default_restrictions as $row) {
            $key = $row->role_name . '~' . $row->topic . '~' . $row->src_or_tx_name;
            $def_restrictions_for[$key] = true;
        }

        $stored_exceptions = $wpdb->get_results("SELECT * FROM $wpdb->ppc_exceptions WHERE agent_type = 'pp_group' AND for_item_source IN ( 'post', 'term' ) AND via_item_source IN ( 'post', 'term' ) AND mod_type IN ( 'exclude', 'include' ) ");
        if ($log_populated_exceptions = $wpdb->get_col("SELECT DISTINCT exception_id FROM $wpdb->ppc_exception_items"))
            $log_populated_exceptions = array_fill_keys($log_populated_exceptions, true);

        $results = $wpdb->get_results("SELECT requirement_id AS source_id, role_name, obj_or_term_id AS item_id, require_for, inherited_from, topic, src_or_tx_name, max_scope FROM $wpdb->role_scope_rs WHERE role_type = 'rs' AND obj_or_term_id > 0 AND ( topic = 'term' OR ( topic = 'object' AND src_or_tx_name = 'post' ) )", OBJECT_K);
        $results = array_diff_key($results, $imported_restrictions);
        foreach ($results as $row) {
            parent::checkTimeout();

            if (empty($wp_role_restrictions[$row->role_name]))
                continue;

            if (('object' == $row->topic) && !in_array($row->item_id, $this->all_post_ids))
                continue;

            if ('term' == $row->topic) {
                if (!isset($this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id]))
                    continue;

                // convert term_id to term_taxonomy_id
                $row->item_id = $this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id];
            }

            $key = $row->role_name . '~' . $row->topic . '~' . $row->src_or_tx_name;

            if ($row->max_scope != $row->topic) {
                // disregard unrestrictions which do not have a corresponding default restriction active
                if (!isset($def_restrictions_for[$key]))
                    continue;
            } else {
                // disregard restrictions which DO have a corresponding default restriction active (RS config UI treats them as non-existant)
                if (isset($def_restrictions_for[$key]))
                    continue;
            }

            if (!$data = $this->get_exception_fields($row))  // determines mod_type (exclude or include) based on topic and max_scope
                continue;

            $data['agent_type'] = 'pp_group';

            if ($data['for_item_type'] && !post_type_exists($data['for_item_type']) && !taxonomy_exists($data['for_item_type']))
                continue;

            if ($data['via_item_type'] && !post_type_exists($data['via_item_type']) && !taxonomy_exists($data['via_item_type']))
                continue;

            $operations = (array) $data['operation'];

            foreach($operations as $operation) {
                $data['operation'] = $operation;

                if (!empty($data['need_site_role']))
                    unset($data['need_site_role']);    // should never be set for restriction import

                if ('both' == $row->require_for)
                    $arr_assign_for = ['item', 'children'];
                else
                    $arr_assign_for = ('entity' == $row->require_for) ? ['item'] : ['children'];

                foreach ($arr_assign_for as $assign_for) {
                    if (('exclude' == $data['mod_type']) && ('read' == $data['operation']) && in_array('subscriber', $wp_role_restrictions[$row->role_name]) && !in_array('wp_anon', $wp_role_restrictions[$row->role_name])) {
                        // if WP Subscribers will be excluded due to exceptions, also exclude Anonymous
                        $wp_role_restrictions[$row->role_name][] = 'wp_anon';
                    }

                    foreach ($wp_role_restrictions[$row->role_name] as $wp_rolename) {
                        $data['agent_id'] = $pp_agent_id[$wp_rolename];

                        $inherited_from = ($row->inherited_from && isset($log_eitem_ids[$row->inherited_from])) ? $log_eitem_ids[$row->inherited_from] : 0;

                        $exception_id = $this->get_exception_id($stored_exceptions, $data, $row->source_id);

                        $wpdb->insert_id = 0;
                        $sql = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, item_id, inherited_from) SELECT * FROM ( SELECT '$assign_for' AS a, '$exception_id' AS b, '$row->item_id' AS c, '$inherited_from' AS d ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_exception_items WHERE assign_for = '$assign_for' AND exception_id = '$exception_id' AND item_id = '$row->item_id') LIMIT 1";
                        
                        //var_dump($sql);
                        
                        $wpdb->query($sql);
                        if ($wpdb->insert_id) {
                            $eitem_id = (int)$wpdb->insert_id;

                            $log_eitem_ids[$row->source_id] = $eitem_id;

                            if ($row->inherited_from) {
                                $rs_inherited_from[$eitem_id] = $row->inherited_from;
                            }

                            $log_populated_exceptions[$exception_id] = true;

                            $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->role_scope_rs), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exception_items), 'import_id' => $eitem_id, 'site' => $blog_id];
                            $wpdb->insert($wpdb->ppi_imported, $log_data);

                            $this->total_imported++;
                            $this->num_imported['restrictions']++;
                        }
                    }
                }
            }
        }

        // convert inherited_from values from role_scope_rs.requirement_id to ppc_exception_items.eitem_id
        foreach ($rs_inherited_from as $eitem_id => $rs_id) {
            if (isset($log_eitem_ids[$rs_id])) {
                $data = ['inherited_from' => $log_eitem_ids[$rs_id]];
                $where = ['eitem_id' => $eitem_id];
                $wpdb->update($wpdb->ppc_exception_items, $data, $where);
            }
        }

        // Default Restrictions will be imported as Include Exceptions for corresponding WP Role group
        $default_restrictions = array_diff_key($default_restrictions, $imported_restrictions);
        foreach ($default_restrictions as $row) {
            parent::checkTimeout();

            if (empty($wp_role_restrictions[$row->role_name]))
                continue;

            if (!$data = $this->get_exception_fields($row))
                continue;

            if ($data['for_item_type'] && !post_type_exists($data['for_item_type']) && !taxonomy_exists($data['for_item_type']))
                continue;

            if ($data['via_item_type'] && !post_type_exists($data['via_item_type']) && !taxonomy_exists($data['via_item_type']))
                continue;

            if (!empty($data['need_site_role']))
                unset($data['need_site_role']);  // should never be set for restriction import

            $data['mod_type'] = 'include';
            $data['agent_type'] = 'pp_group';

            $operations = (array) $data['operation'];

            foreach($operations as $operation) {
                $data['operation'] = $operation;

                foreach ($wp_role_restrictions[$row->role_name] as $wp_rolename) {
                    $data['agent_id'] = $pp_agent_id[$wp_rolename];

                    $exception_id = $this->get_exception_id($stored_exceptions, $data, $row->source_id);
                    if (!isset($log_populated_exceptions[$exception_id])) {
                        // if any default restrictions did not have a corresponding unrestriction imported, create an exception and "none" exception_item
                        $wpdb->insert_id = 0;
                        $sql = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, item_id) SELECT * FROM ( SELECT 'item' AS a, '$exception_id' AS b, '0' AS c ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_exception_items WHERE assign_for = 'item' AND exception_id = '$exception_id' AND item_id = '0') LIMIT 1";
                        $wpdb->query($sql);
                        if ($wpdb->insert_id) {
                            $eitem_id = (int)$wpdb->insert_id;

                            $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->role_scope_rs), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exception_items), 'import_id' => $eitem_id, 'site' => $blog_id];
                            $wpdb->insert($wpdb->ppi_imported, $log_data);

                            $this->total_imported++;
                            $this->num_imported['restrictions']++;
                        }

                        $log_populated_exceptions[$exception_id] = true;
                    }
                }
            }
        }

        if ($this->importing_publish_exceptions) {
            $this->establish_publish_exceptions();
        }
        // === end RS restrictions import ===
    }

    private function import_rs_item_roles()
    {
        global $wpdb, $wp_roles;

        $blog_id = get_current_blog_id();

        $cap_caster = presspermit()->capCaster();

        // === RS item roles import ("additional" exceptions) ===
        $imported_roles = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->user2role2object_rs)), OBJECT_K);

        $stored_exceptions = $wpdb->get_results("SELECT * FROM $wpdb->ppc_exceptions WHERE agent_type IN ( 'pp_group', 'user' ) AND for_item_source IN ( 'post', 'term' ) AND via_item_source IN ( 'post', 'term' ) AND mod_type = 'additional' ");
        $rs_role_inherited_from = [];
        $log_eitem_ids = [];

        /*--------- group config and mapping setup ---------*/
        $rs_groups_table = (is_multisite() && get_site_option('scoper_mu_sitewide_groups')) ? $wpdb->base_prefix . 'groups_rs' : $wpdb->groups_rs;
        $pp_groups_table = (is_multisite() && get_site_option('presspermit_netwide_groups')) ? $wpdb->base_prefix . 'pp_groups' : $wpdb->pp_groups;
        $group_agent_type = (is_multisite() && get_site_option('presspermit_netwide_groups')) ? 'pp_net_group' : 'pp_group';
        $imported_pp_groups = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d AND import_tbl = %d", $this->getTableCode($rs_groups_table), $this->getTableCode($pp_groups_table)), OBJECT_K);
        $role_metagroups_rs = $wpdb->get_results("SELECT ID, group_meta_id FROM $rs_groups_table WHERE group_meta_id LIKE 'wp_role_%' OR group_meta_id = 'wp_anon'", OBJECT_K);  // TODO: review role metagroup storage with netwide groups
        $role_metagroups_pp = $wpdb->get_results("SELECT metagroup_id, ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'", OBJECT_K);

        $stored_pp_groups = $wpdb->get_results("SELECT group_name, ID FROM $pp_groups_table WHERE metagroup_type != 'wp_role'", OBJECT_K);
        $rs_group_names = $wpdb->get_results("SELECT ID, group_name FROM $rs_groups_table WHERE ( group_meta_id IS NULL OR group_meta_id NOT LIKE 'wp_role_%' )", OBJECT_K);
        /*----------- end group mapping setup -------------*/

        $results = $wpdb->get_results("SELECT assignment_id AS source_id, role_name, obj_or_term_id AS item_id, assign_for, inherited_from, scope, src_or_tx_name, user_id, group_id, assigner_id FROM $wpdb->user2role2object_rs WHERE role_type = 'rs' AND scope IN ( 'term', 'object' ) AND date_limited = '0' AND content_date_limited = '0'", OBJECT_K);
        $results = array_diff_key($results, $imported_roles);

        foreach ($results as $row) {
            parent::checkTimeout();

            if (('object' == $row->scope) && ('group' != $row->src_or_tx_name) && !in_array($row->item_id, $this->all_post_ids))
                continue;

            if ('term' == $row->scope) {
                if (!isset($this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id]))
                    continue;

                // convert term_id to term_taxonomy_id
                $row->item_id = $this->tt_ids_by_taxonomy[$row->src_or_tx_name][$row->item_id];
            }

            if ('group' == $row->src_or_tx_name) {
                // for group management exception, item_id is pp_groups ID (need to convert from group_rs ID)
                if (isset($imported_pp_groups[$row->item_id])) {    // match against group import record if available
                    $row->item_id = $imported_pp_groups[$row->item_id]->import_id;
                } elseif (isset($rs_group_names[$row->item_id])) {
                    if (isset($stored_pp_groups[$rs_group_names[$row->item_id]->group_name]))    // otherwise match group name
                        $row->item_id = $stored_pp_groups[$rs_group_names[$row->item_id]->group_name]->ID;
                    else
                        continue;
                }
            }

            if (!$data = $this->get_exception_fields($row))
                continue;

            $data['agent_type'] = ($row->user_id) ? 'user' : $group_agent_type;

            if ($row->user_id && !is_null($row->user_id))
                $data['agent_id'] = $row->user_id;
            else {
                if (isset($role_metagroups_rs[$row->group_id])) {
                    // this is a WP role metagroup
                    $data['agent_type'] = 'pp_group'; // netwide groups setting does not apply to role metagroups

                    $role_name = str_replace('wp_role_', '', $role_metagroups_rs[$row->group_id]->group_meta_id);
                    if (isset($role_metagroups_pp[$role_name]))
                        $data['agent_id'] = $role_metagroups_pp[$role_name]->ID;
                    else
                        continue;
                } elseif (isset($imported_pp_groups[$row->group_id])) {    // match against group import record if available
                    $data['agent_id'] = $imported_pp_groups[$row->group_id]->import_id;
                } elseif (isset($rs_group_names[$row->group_id])) {
                    if (isset($stored_pp_groups[$rs_group_names[$row->group_id]->group_name]))    // otherwise match group name
                        $data['agent_id'] = $stored_pp_groups[$rs_group_names[$row->group_id]->group_name]->ID;
                    else
                        continue;
                } else
                    continue;
            }

            // ------ if we are converting an RS Contributor or Author Category Role to an include exception, also need to ensure that user or group has appropriate sitewide caps ------
            if (!empty($data['need_site_role'])) {
                $need_caps = $data['need_site_role']['need_caps'];
                $assign_pp_role = $data['need_site_role']['assign_pp_role'];
                unset($data['need_site_role']);

                $has_caps = false;

                $agent_clause = "( agent_type = '" . $data['agent_type'] . "' AND agent_id = '" . $data['agent_id'] . "' )";

                if ('user' == $data['agent_type']) {
                    // consider caps in user's roles
                    $meta_key = $wpdb->prefix . 'capabilities';
                    if ($_user_roles = (array)maybe_unserialize($wpdb->get_row("SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = '$meta_key' LIMIT 1"))) {
                        $_user_roles = array_intersect_key($wp_roles->role_objects, array_flip($_user_roles));
                        foreach (array_keys($_user_roles) as $role_name) {
                            if (!array_diff($need_caps, array_keys(array_filter($wp_roles->role_objects[$role_name]->capabilities)))) {
                                $has_caps = true;
                                break;
                            }
                        }
                    }

                    // consider caps in PP site roles (for user or group)
                    if (!$has_caps) {
                        if ($_user_groups = presspermit()->groups()->getGroupsForUser($data['agent_id'], $group_agent_type, ['cols' => 'ids'])) {
                            $agent_clause .= " OR ( agent_type = '$group_agent_type' AND agent_id IN ('" . implode("','", array_keys($_user_groups)) . "') )";
                        }
                    }
                } elseif ('pp_group' == $data['agent_type']) {
                    // if group is a WP Role metagroup, consider WP rolecaps
                    foreach ($role_metagroups_pp as $role_name => $_pp_group) {
                        if ($_pp_group->ID == $data['agent_id']) {
                            if (isset($wp_roles->role_objects[$role_name])) {
                                if (!array_diff($need_caps, array_keys(array_filter($wp_roles->role_objects[$role_name]->capabilities)))) {
                                    $has_caps = true;
                                }
                            }

                            break;
                        }
                    }
                }

                if (!$has_caps) {
                    $has_pp_roles = $wpdb->get_col("SELECT role_name FROM $wpdb->ppc_roles WHERE $agent_clause");
                    foreach ($has_pp_roles as $pp_role_name) {
                        if ($_role_caps = $cap_caster->getTypecastCaps($pp_role_name)) {
                            if (!array_diff($need_caps, $_role_caps)) {
                                $has_caps = true;
                                break;
                            }
                        }
                    }
                }

                if (!$has_caps) {
                    $wpdb->insert($wpdb->ppc_roles, ['role_name' => $assign_pp_role, 'agent_type' => $data['agent_type'], 'agent_id' => $data['agent_id']]);
                }
            }
            // --------------------------------- end site caps analysis / site role assignment to compensate for term include exception ------------------------------------------


            if ('both' == $row->assign_for)
                $arr_assign_for = ['item', 'children'];
            else
                $arr_assign_for = ('entity' == $row->assign_for) ? ['item'] : ['children'];

            foreach ($arr_assign_for as $assign_for) {
                $exception_id = $this->get_exception_id($stored_exceptions, $data, $row->source_id);

                $inherited_from = ($row->inherited_from && isset($log_eitem_ids[$row->inherited_from])) ? $log_eitem_ids[$row->inherited_from] : 0;

                $wpdb->insert_id = 0;
                $sql = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, assigner_id, item_id, inherited_from) SELECT * FROM ( SELECT '$assign_for' AS a, '$exception_id' AS b, '$row->assigner_id' AS c, '$row->item_id' AS d, '$inherited_from' AS e ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_exception_items WHERE assign_for = '$assign_for' AND exception_id = '$exception_id' AND item_id = '$row->item_id') LIMIT 1";
                $wpdb->query($sql);
                if ($wpdb->insert_id) {
                    $eitem_id = (int)$wpdb->insert_id;

                    $log_eitem_ids[$row->source_id] = $eitem_id;

                    if ($row->inherited_from) {
                        $rs_role_inherited_from[$eitem_id] = $row->inherited_from;
                    }

                    $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->user2role2object_rs), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exception_items), 'import_id' => $eitem_id, 'site' => $blog_id];
                    $wpdb->insert($wpdb->ppi_imported, $log_data);

                    $this->total_imported++;
                    $this->num_imported['item_roles']++;
                }
            }
        }

        // convert inherited_from values from user2role2object_rs.assignment_id to ppc_exception_items.eitem_id
        foreach ($rs_role_inherited_from as $eitem_id => $rs_id) {
            if (isset($log_eitem_ids[$rs_id])) {
                $data = ['inherited_from' => $log_eitem_ids[$rs_id]];
                $where = ['eitem_id' => $eitem_id];
                $wpdb->update($wpdb->ppc_exception_items, $data, $where);
            }
        }
    }

    private function import_rs_options()
    {
        global $wpdb, $wp_roles;

        parent::checkTimeout();

        $imported_options = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->options)), OBJECT_K);

        $rs_options = [];
        $results = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'scoper_%'");
        $results = array_diff_key($results, $imported_options);
        foreach ($results as $row)
            $rs_options[$row->option_name] = maybe_unserialize($row->option_value);

        // mirrored verbatim
        $options = [
            'scoper_strip_private_caption',
            'scoper_display_hints',
            'scoper_rss_private_feed_mode',
            'scoper_rss_nonprivate_feed_mode',
            'scoper_feed_teaser',
            'scoper_lock_top_pages',
            'scoper_display_user_profile_groups',
            'scoper_display_user_profile_roles',
            'scoper_admin_others_attached_files',
            'scoper_admin_others_unattached_files',
            'scoper_limit_user_edit_by_level',
            'scoper_admin_nav_menu_filter_items',
            'scoper_define_create_posts_cap',
        ];

        foreach ($options as $opt_name) {
            if (isset($rs_options[$opt_name])) {
                $pp_opt_name = str_replace('scoper_', 'presspermit_', $opt_name);
                $this->import_option($pp_opt_name, $rs_options[$opt_name], $opt_name, $imported_options);
            }
        }

        if (isset($rs_options['scoper_hide_non_editor_admin_divs'])) {
            $this->import_option("presspermit_editor_ids_sitewide_requirement", $rs_options['scoper_hide_non_editor_admin_divs'], 'scoper_hide_non_editor_admin_divs', $imported_options);
        }

        if (isset($rs_options['scoper_admin_css_ids'])) {
            $div_ids = [];
            foreach ((array)$rs_options['scoper_admin_css_ids'] as $id_csv) {  // separate storage for post, page
                $arr_ids = explode(';', $id_csv);
                foreach (array_keys($arr_ids) as $key)
                    $arr_ids[$key] = trim($arr_ids[$key]);

                $div_ids = array_merge($div_ids, $arr_ids);
            }

            $this->import_option('presspermit_hide_non_editor_admin_divs', implode('; ', array_unique($div_ids)), 'scoper_admin_css_ids', $imported_options);
        }

        if (!empty($rs_options['scoper_role_admin_blogwide_editor_only'])) {
            switch ($rs_options['scoper_role_admin_blogwide_editor_only']) {
                case 'admin':
                    $reqd_cap = 'manage_options';
                    break;

                case 'admin_content':
                    $reqd_cap = 'activate_plugins';
                    break;

                default:
                    $type_obj = get_post_type_object('page');
                    $reqd_cap = [$type_obj->cap->edit_others_posts, $type_obj->cap->edit_published_posts];
                    break;
            }

            $reqd_caps = array_fill_keys((array)$reqd_cap, true);

            foreach ($wp_roles->role_objects as $role_name => $role_obj) {
                if (!array_diff_key($reqd_caps, array_diff($role_obj->capabilities, [0, "0", false]))) {
                    $wp_roles->add_cap($role_name, 'pp_assign_roles');
                } else {
                    $wp_roles->remove_cap($role_name, 'pp_assign_roles');
                }
            }
        }

        if (isset($rs_options['scoper_use_post_types'])) {
            $this->import_option("presspermit_enabled_post_types", $rs_options['scoper_use_post_types'], 'scoper_use_post_types', $imported_options);
        }

        if (isset($rs_options['scoper_use_taxonomies'])) {
            $this->import_option("presspermit_enabled_taxonomies", $rs_options['scoper_use_taxonomies'], 'scoper_use_taxonomies', $imported_options);
        }

        if (!empty($rs_options['scoper_default_private'])) {
            $type_default_visibility = [];
            foreach ($rs_options['scoper_default_private'] as $src_otype => $setting) {
                $post_type = str_replace('post:', '', $src_otype);
                $type_default_visibility[$post_type] = ($setting) ? 'private' : '';
            }

            $this->import_option("presspermit_default_privacy", $type_default_visibility, 'scoper_default_private', $imported_options);
        }

        if (isset($rs_options['scoper_do_teaser'])) {
            $this->import_option("presspermit_post_teaser_enabled", !empty($rs_options['scoper_do_teaser']['post']), 'scoper_do_teaser', $imported_options);
        }

        if (!empty($rs_options['scoper_use_teaser'])) {
            $tease_types = [];
            foreach ($rs_options['scoper_use_teaser'] as $src_otype => $setting) {
                $post_type = str_replace('post:', '', $src_otype);
                $tease_types[$post_type] = $setting;
            }

            $this->import_option("presspermit_tease_post_types", $tease_types, 'scoper_use_teaser', $imported_options);
        }

        if (!empty($rs_options['scoper_teaser_hide_private'])) {
            $tease_types = [];
            foreach ($rs_options['scoper_teaser_hide_private'] as $src_otype => $setting) {
                $post_type = str_replace('post:', '', $src_otype);
                $tease_types[$post_type] = !$setting;
            }

            $this->import_option("presspermit_tease_public_posts_only", $tease_types, 'scoper_teaser_hide_private', $imported_options);
        }

        if (!empty($rs_options['scoper_teaser_logged_only'])) {
            $tease_types = [];
            foreach ($rs_options['scoper_teaser_logged_only'] as $src_otype => $setting) {
                $post_type = str_replace('post:', '', $src_otype);
                $tease_types[$post_type] = $setting;
            }

            $this->import_option("presspermit_tease_logged_only", $tease_types, 'scoper_teaser_logged_only', $imported_options);
        }

        $options = ['replace_content', 'replace_content_anon', 'prepend_content', 'prepend_content_anon', 'append_content', 'append_content_anon', 'prepend_name', 'prepend_name_anon', 'append_name', 'append_name_anon', 'replace_excerpt', 'replace_excerpt_anon', 'prepend_excerpt', 'prepend_excerpt_anon', 'append_excerpt', 'append_excerpt_anon'];
        foreach ($options as $opt) {
            if (!empty($rs_options["scoper_teaser_{$opt}"])) {
                $val = false;

                if (!empty($rs_options["scoper_teaser_{$opt}"]['post:post'])) {
                    $val = $rs_options["scoper_teaser_{$opt}"]['post:post'];
                } else {
                    foreach ($rs_options["scoper_teaser_{$opt}"] as $src_otype => $val) {
                        if ($val) {
                            break;
                        }
                    }
                }

                if (false !== $val)
                    $this->import_option("presspermit_tease_{$opt}", $val, "scoper_teaser_{$opt}", $imported_options);
            }
        }


        if (is_multisite()) {
            $rs_netwide = (int)get_site_option('scoper_mu_sitewide_groups');
            $pp_netwide = (int)get_site_option('presspermit_netwide_groups');

            if ($pp_netwide != $rs_netwide)
                update_site_option('presspermit_netwide_groups', $rs_netwide);
        }
    }

    private function import_option($opt_name, $opt_value, $source_opt_name, $imported_options)
    {
        global $wpdb;

        $blog_id = get_current_blog_id();

        if ($row = $wpdb->get_row("SELECT option_id, option_value FROM $wpdb->options WHERE option_name = '$source_opt_name' LIMIT 1")) {
            $source_id = $row->option_id;

            if (isset($imported_options[$source_id]))
                return;
        } else
            $source_id = 0;

        if ($row = $wpdb->get_row("SELECT option_id, option_value FROM $wpdb->options WHERE option_name = '$opt_name' LIMIT 1")) {
            if ($opt_value !== maybe_unserialize($row->option_value)) {
                $do_update = true;
                $import_id = $row->option_id;
            } else
                $do_update = false;
        } else {
            $import_id = 0;
            $do_update = true;
        }

        if (!empty($do_update)) {
            update_option($opt_name, $opt_value);

            if (!$import_id) {
                if ($row = $wpdb->get_row("SELECT option_id, option_value FROM $wpdb->options WHERE option_name = '$opt_name' LIMIT 1"))
                    $import_id = $row->option_id;
            }

            $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->options), 'source_id' => $source_id, 'import_tbl' => $this->getTableCode($wpdb->options), 'import_id' => $import_id, 'site' => $blog_id];
            $wpdb->insert($wpdb->ppi_imported, $log_data);

            $this->total_imported++;
            $this->num_imported['options']++;
        }
    }

    private function get_exception_fields($rs_obj, $extra_data = [])
    {
        $data = ['agent_type' => 'pp_group'];

        $importing_rs_restriction = isset($rs_obj->max_scope);

        $scope = (isset($rs_obj->topic)) ? $rs_obj->topic : $rs_obj->scope;

        if (!$rolename_arr = explode('_', $rs_obj->role_name))
            return false;

        $rs_base_role = $rolename_arr[count($rolename_arr) - 1];

        if ('private' == $rolename_arr[0]) {
            $data['for_item_type'] = implode('_', array_slice($rolename_arr, 1, count($rolename_arr) - 2));  // $arr[1];  - unknown number of elems because type name may have underscores

            if (('subscriber' == $rs_base_role) && ('term' != $scope))
                $data['for_item_status'] = '';
            else
                $data['for_item_status'] = 'post_status:private';
        } else {
            $data['for_item_type'] = implode('_', array_slice($rolename_arr, 0, count($rolename_arr) - 1));  // $arr[0];
            $data['for_item_status'] = '';
        }

        if ('group' == $data['for_item_type']) {
            $data['via_item_source'] = 'pp_group';
            $data['via_item_type'] = '';
            $data['for_item_type'] = 'pp_group';
            $data['for_item_source'] = 'pp_group';
        } elseif ('term' == $scope) {
            $data['via_item_source'] = 'term';
            $data['via_item_type'] = $rs_obj->src_or_tx_name;
            $data['for_item_source'] = 'post';  // modify below as needed
        } else {
            $data['via_item_source'] = 'post';
            $data['via_item_type'] = '';
            $data['for_item_source'] = 'post';
        }

        if (!isset($data['mod_type'])) {
            if ($importing_rs_restriction) // RS restriction
                $data['mod_type'] = ($rs_obj->topic == $rs_obj->max_scope) ? 'exclude' : 'include';  // unrestrictions stored as include exceptions for corresponding WP role(s)
            else
                $data['mod_type'] = 'additional';
        }

        switch ($rs_base_role) {
            case 'contributor':
                if (defined('REVISIONARY_VERSION') || defined('RVY_VERSION')) {
                    $data['operation'] = 'revise';
                } else {
                    if ($importing_rs_restriction) { // mod_type: exclude or include
                        if ('term' == $scope) {
                            $data['operation'] = ['assign', 'edit'];
                        } else {
                            $data['operation'] = 'edit';
                        }
                    } else { // RS item roles
                        if ('term' == $scope) {
                            // assigning additional edit exception would allow editing of published / others posts.  So assign include instead, relying on status caps from WP / supplemental roles.
                            $data['mod_type'] = 'include';
                            $data['operation'] = 'edit';

                            // TODO: also disable for_item_status

                            if ($type_obj = get_post_type_object($data['for_item_type'])) {
                                $data['need_site_role'] = [
                                    'need_caps' => [$type_obj->cap->edit_posts],
                                    'assign_pp_role' => "contributor:post:" . $data['for_item_type']
                                ];
                            }
                        }
                    }
                }
                break;

            case 'author':
                if ($importing_rs_restriction) { // mod_type: exclude or include
                    if ('term' == $scope) {
                        $data['operation'] = 'publish';
                        $this->importing_publish_exceptions = true;
                    } else {
                        $data['operation'] = 'edit';
                    }
                } else { // RS item roles
                    if ('term' == $scope) {
                        // assigning additional edit exception would allow editing of others posts.  So assign include instead, relying on status caps from WP / supplemental roles.
                        $data['mod_type'] = 'include';
                        $data['operation'] = 'edit';

                        if ($type_obj = get_post_type_object($data['for_item_type'])) {
                            $data['need_site_role'] = [
                                'need_caps' => [$type_obj->cap->edit_posts, $type_obj->cap->edit_published_posts, $type_obj->cap->publish_posts],
                                'assign_pp_role' => "author:post:" . $data['for_item_type']
                            ];
                        }
                    }
                }
                break;

            case 'editor':
            	if ('term' == $scope) {
                	$data['operation'] = ['assign', 'edit', 'publish'];
                	$this->importing_publish_exceptions = true;
            	} else {
            		$data['operation'] = 'edit';
            	}
                break;

            case 'associate':
                $data['operation'] = 'associate';
                break;

            case 'reader':
                $data['operation'] = 'read';
                break;

            case 'revisor':
                $data['operation'] = 'revise';
                break;

            case 'manager':
                $data['operation'] = 'manage';

                if ('pp_group' != $data['for_item_type']) {
                    $data['for_item_source'] = 'term';
                }

                break;

            case 'assigner':
                $data['operation'] = 'assign';
                break;

            default:
                return false;
        } // end switch

        if (isset($rs_obj->assigner_id))
            $data['assigner_id'] = $rs_obj->assigner_id;

        return $data;
    }

    private function get_exception_id(&$stored_exceptions, $data, $restriction_id = 0)
    {
        $exception_id = 0;

        // safeguard against invalid exception specs
        if ('post' == $data['via_item_source'])
            $data['for_item_status'] = '';

        foreach ($stored_exceptions as $exc) {
            foreach ($data as $key => $val) {
                if ($val != $exc->$key) {
                    continue 2;
                }
            }

            $exception_id = $exc->exception_id;
            break;
        }

        if (!$exception_id) {
            global $wpdb;

            $wpdb->insert($wpdb->ppc_exceptions, $data);
            $exception_id = (int)$wpdb->insert_id;
            $data['exception_id'] = $exception_id;

            $stored_exceptions[] = (object)$data;

            if ($restriction_id) {
                $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->role_scope_rs), 'source_id' => $restriction_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exceptions), 'import_id' => $exception_id, 'site' => get_current_blog_id()];
                $wpdb->insert($wpdb->ppi_imported, $log_data);
            }
        }

        return $exception_id;
    }

    // When importing restrictions as publish exceptions, ensure that PP publish exceptions are enabled.
    // Also mirror any enabling editing exceptions to corresponding publish exceptions
    function establish_publish_exceptions() {
        global $wpdb;
        
        update_option('presspermit_publish_exceptions', true);

        $edit_exceptions = $wpdb->get_results(
            "SELECT * FROM $wpdb->ppc_exceptions WHERE mod_type = 'additional' AND operation = 'edit'"
        );
        
        $publish_exceptions = $wpdb->get_results(
            "SELECT * FROM $wpdb->ppc_exceptions WHERE mod_type = 'additional' AND operation = 'publish'"
        );
        
        foreach ($edit_exceptions as $exc) {
            $exc->operation = 'publish';
            $edit_exception_id = $exc->exception_id;
            $publish_exception_id = 0;

            // For this exception row, find a corresponding publish exception row
            foreach ($publish_exceptions as $pub_exc) {
                foreach ($pub_exc as $key => $val) {
                    if (!in_array($key, ['exception_id', 'assigner_id']) && ($val != $exc->$key)) {
                        continue 2;
                    }
                }

                $publish_exception_id = $pub_exc->exception_id;
                break;
            }

            //pp_errlog("edit exception_id $edit_exception_id, publish exception_id $publish_exception_id");

            // If a corresponding publish exception row is not stored, insert one
            if (!$publish_exception_id) {
                $data = (array) $exc;
                unset($data['exception_id']);
                $wpdb->insert($wpdb->ppc_exceptions, $data);
                $publish_exception_id = (int)$wpdb->insert_id;
            }

            if ($edit_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $wpdb->ppc_exception_items WHERE exception_id = %d", 
                    $edit_exception_id
                )
            )) {
                $publish_items = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $wpdb->ppc_exception_items WHERE exception_id = %d", 
                        $publish_exception_id
                    )
                );
            
                // For each existing editing exception item, check whether a corresponding publish exception item is already stored
                $missing_items = [];
                foreach($edit_items as $e) {
                    $matched = false;
                    foreach($publish_items as $p) {
                        if (($e->item_id == $p->item_id) 
                        && ($e->assign_for == $p->assign_for)
                        && ($e->inherited_from == $p->inherited_from)
                        ) {
                            $matched = true;
                            break;
                        }
                    }

                    if (!$matched) {
                        $missing_items[]= $e;
                    }
                }

                // Insert publish exception items as needed
                if ($missing_items) {
                    $query = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, assigner_id, item_id, inherited_from) VALUES";
                    
                    foreach($missing_items as $e) {
                        $query .= "('$e->assign_for', $publish_exception_id, $e->assigner_id, $e->item_id, $e->inherited_from),";
                    }

                    $query = rtrim($query, ',');
                    //pp_errlog($query);

                    $wpdb->query($query);
                }
            }
        }
    }
}
