<?php
namespace PublishPress\Permissions\Import\DB;

require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/Importer.php');

class PressPermitBeta extends \PublishPress\Permissions\Import\Importer
{
    private $all_post_ids = [];
    private $all_tt_ids = [];

    private static $instance = null;

    public static function instance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new PressPermitBeta();
        }

        return self::$instance;
    }

    public function __construct() // some PHP versions do not allow subclass constructor to be private
    {
        parent::__construct();
        $this->import_types = ['site_roles' => __('Site Roles', 'ppi'), 'item_roles' => __('Term / Object Roles', 'ppi'), 'item_conditions' => __('Conditions', 'ppi'), 'options' => __('Options', 'ppi')];
    }

    function doImport($import_type = 'pp')
    {
        global $wpdb;

        $blog_id = get_current_blog_id();

        parent::doImport('pp');

        $this->all_post_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type NOT IN ('revision', 'attachment') AND post_status NOT IN ('auto_draft')");
        $this->all_tt_ids = $wpdb->get_col("SELECT term_taxonomy_id FROM $wpdb->term_taxonomy");

        if (is_multisite() && is_main_site()) {
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs ORDER BY blog_id");
            $orig_blog_id = $blog_id;
            $this->sites_examined = 0;
        } else {
            $blog_ids = [$blog_id];
        }

        foreach ($blog_ids as $id) {
            if (count($blog_ids) > 1) {
                switch_to_blog($id);
                $this->sites_examined++;

                SourceConfig::setPressPermitBetaTables();

                if (!$wpdb->get_results("SHOW TABLES LIKE '$wpdb->pp_roles'")) {
                    continue;  // PP 1.x tables were never created for this site, so skip it
                }

                // early alpha versions of PPC didn't have this hooked to action
                require(PRESSPERMIT_ABSPATH . '/db-config.php');

                if (!$wpdb->get_results("SHOW TABLES LIKE '$wpdb->ppc_exceptions'")) {
                    require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');
                    new DB\DatabaseSetup();  // PP tables were not yet created for this site, so create them
                }
            }

            try {
                if (!defined('PPI_NO_OPTION_IMPORT'))
                    $this->import_pp_options();

                $this->import_pp_site_roles();
                $this->import_pp_item_conditions();
                $this->import_pp_item_roles();
                
            } catch (Exception $e) {
                $this->return_error = $e;
                $this->timed_out = true;
                parent::updateCounts();

                if (count($blog_ids) > 1)
                    switch_to_blog($orig_blog_id);

                return;
            }
        }

        $this->completed = true;
        parent::updateCounts();
    }

    function import_pp_site_roles()
    {
        global $wpdb;

        $blog_id = get_current_blog_id();

        // PP site roles import
        $imported_roles = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->pp_roles)), OBJECT_K);

        $stored_roles = $wpdb->get_results("SELECT * FROM $wpdb->ppc_roles WHERE agent_type IN ( 'pp_group', 'user' )");

        $stored_exceptions = $wpdb->get_results("SELECT * FROM $wpdb->ppc_exceptions WHERE agent_type IN ( 'pp_group', 'user' ) AND for_item_source IN ( 'post', 'term' ) AND via_item_source IN ( 'post', 'term' ) AND mod_type = 'additional' ");

        $results = $wpdb->get_results("SELECT assignment_id AS source_id, role_name, group_id, group_type, assigner_id FROM $wpdb->pp_roles WHERE scope = 'site'", OBJECT_K);
        $results = array_diff_key($results, $imported_roles);
        foreach ($results as $row) {
            parent::checkTimeout();

            $arr = explode(':', $row->role_name);

            if ('reviewer' == $arr[0])
                continue;

            // --- convert site roles for defunct attributes into term/post exceptions ---
            if (isset($arr[4]) && in_array($arr[3], ['editability', 'contributor_restrict', 'author_restrict'])) {
                $determine_taxonomy = false;

                switch ($arr[3]) {
                    case 'editability':
                        $data = ['scope' => 'object', 'via_item_source' => 'post', 'via_item_type' => '', 'for_item_type' => 'post', 'for_item_type' => $arr[2], 'for_item_status' => '', 'operation' => 'edit', 'mod_type' => 'additional'];

                        $cond_posts = $wpdb->get_col("SELECT item_id FROM $wpdb->pp_conditions WHERE scope = 'object' AND assign_for = 'item' AND item_source = 'post' AND attribute = 'editability' AND condition_name = '{$arr[4]}'");
                        $term_cond_posts = $wpdb->get_col("SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ( SELECT item_id FROM $wpdb->pp_conditions WHERE scope = 'term' AND assign_for = 'item' AND item_source = 'term_taxonomy' AND attribute = 'editability' AND condition_name = '{$arr[4]}' )");
                        $item_ids = array_unique(array_merge($cond_posts, $term_cond_posts));
                        break;

                    case 'contributor_restrict':
                    case 'author_restrict':
                        $data = ['scope' => 'term', 'via_item_source' => 'term', 'for_item_type' => 'post', 'for_item_type' => $arr[2], 'for_item_status' => '', 'operation' => 'assign', 'mod_type' => 'additional'];
                        $determine_taxonomy = true;
                        $item_ids = $wpdb->get_col("SELECT item_id FROM $wpdb->pp_conditions WHERE scope = 'term' AND assign_for = 'item' AND item_source = 'term_taxonomy' AND attribute = '{$arr[3]}' AND condition_name = '{$arr[4]}'");
                        break;

                    default:
                        continue;
                }

                $data['agent_type'] = $row->group_type;
                $data['agent_id'] = $row->group_id;
                unset($data['scope']);

                $exception_id = $this->get_exception_id($stored_exceptions, $data, $row->source_id);

                foreach ($item_ids as $item_id) {
                    if ($determine_taxonomy)
                        $data['via_item_type'] = $wpdb->get_var("SELECT taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = '$item_id'");

                    $wpdb->insert_id = 0;  // Thanks to Sumon and Warren: https://stackoverflow.com/a/14168822
                    $sql = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, assigner_id, item_id) SELECT * FROM ( SELECT 'item' AS a, '$exception_id' AS b, '$row->assigner_id' AS c, '$item_id' AS d ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_exception_items WHERE assign_for = 'item' AND exception_id = '$exception_id' AND item_id = '$item_id') LIMIT 1";
                    $wpdb->query($sql);
                    if ($wpdb->insert_id) {
                        $eitem_id = (int)$wpdb->insert_id;

                        $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->pp_roles), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exception_items), 'import_id' => $eitem_id, 'site' => $blog_id];
                        $wpdb->insert($wpdb->ppi_imported, $log_data);

                        $did_import = true;
                        $this->total_imported++;
                    }
                }

                if (!empty($did_import))
                    $this->num_imported['site_roles']++;

                // --- end conversion of site roles for defunct attributes into term/post exceptions ---

            } else {
                // normal site role import
                $wpdb->insert_id = 0;
                $sql = "INSERT INTO $wpdb->ppc_roles (agent_id, agent_type, role_name, assigner_id) SELECT * FROM ( SELECT '$row->group_id' AS a, '$row->group_type' AS b, '$row->role_name' AS c, '$row->assigner_id' AS d ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_roles WHERE agent_type = '$row->group_type' AND agent_id = '$row->group_id' AND role_name = '$row->role_name') LIMIT 1";
                $wpdb->query($sql);
                if ($wpdb->insert_id) {
                    $assignment_id = (int)$wpdb->insert_id;

                    $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->pp_roles), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_roles), 'import_id' => $assignment_id, 'site' => $blog_id];
                    $wpdb->insert($wpdb->ppi_imported, $log_data);

                    $this->total_imported++;
                    $this->num_imported['site_roles']++;
                }
            }
        }
    }

    function import_pp_item_conditions()
    {
        global $wpdb, $wp_roles;

        $blog_id = get_current_blog_id();

        $pp_only = (array)presspermit()->getOption('supplemental_role_defs');
        $applicable_roles = array_diff_key($wp_roles->role_objects, array_fill_keys($pp_only, true));

        $post_types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');
        $log_eitem_ids = [];  // conversion of pp_conditions.assignment_id to ppc_exception_items.eitem_id

        $imported_conditions = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->pp_conditions)), OBJECT_K);

        $pp_agent_id = [];
        $results = $wpdb->get_results("SELECT metagroup_id, ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'");
        foreach ($results as $row) {
            $pp_agent_id[$row->metagroup_id] = $row->ID;
        }

        $stored_exceptions = $wpdb->get_results("SELECT * FROM $wpdb->ppc_exceptions WHERE agent_type = 'pp_group' AND for_item_source IN ( 'post', 'term' ) AND via_item_source IN ( 'post', 'term' ) AND mod_type IN ( 'exclude', 'include' ) ");

        $results = $wpdb->get_results("SELECT assignment_id AS source_id, attribute, condition_name, scope, item_source, item_id, assign_for, mode, inherited_from FROM $wpdb->pp_conditions WHERE attribute IN ('editability', 'post_status', 'author_restrict', 'contributor_restrict')", OBJECT_K);
        $results = array_diff_key($results, $imported_conditions);

        foreach ($results as $row) {
            parent::checkTimeout();

            if (('post' == $row->item_source) and !in_array($row->item_id, $this->all_post_ids))
                continue;

            if (('term_taxonomy' == $row->item_source) and !in_array($row->item_id, $this->all_tt_ids))
                continue;

            if (!$data = $this->get_condition_exception_fields($row))
                continue;

            $data['agent_type'] = 'pp_group';

            if ('term_taxonomy' == $row->item_source) {
                $data['for_item_type'] = '';
            } else {
                $data['for_item_type'] = $wpdb->get_var("SELECT post_type FROM $wpdb->posts WHERE ID = '$row->item_id'");

                if (!post_type_exists($data['for_item_type']))
                    continue;
            }

            if ($data['via_item_type'] && !post_type_exists($data['via_item_type']) && !taxonomy_exists($data['via_item_type']))
                continue;

            // this is only used to exempt roles with condition caps for the post type.
            $post_type = ($data['for_item_type']) ? $data['for_item_type'] : 'post';
            $type_obj = get_post_type_object($post_type);

            // convert item conditions into exceptions
            // item condition only corresponds to WP role exclusion for roles which do not have the status cap via allcaps or supplemental site role (for term scope, need to check each post type for separate exception)
            switch ($row->attribute) {
                case 'post_status': // term-assigned post privacy status (convert to term-assigned exclusion)
                    $data['operation'] = 'read';

                    $pp_role = "subscriber:post:$post_type:post_status:{$row->condition_name}";  // used to retrieve role assigments that negate the condition

                    $basic_cap = $type_obj->cap->read_private_posts;
                    $cond_caps = [str_replace('read_private', "read_{$row->condition_name}_", $basic_cap) => true];
                    break;

                case 'editability':
                    $data['operation'] = 'edit';
                    $pp_role = "editor:post:$post_type:editability:{$row->condition_name}";
                    $basic_cap = $type_obj->cap->edit_posts;
                    $cond_caps = [str_replace('edit_', "edit_{$row->condition_name}_", $basic_cap) => true];
                    break;

                case 'author_restrict':
                    $data['operation'] = 'assign';
                    $pp_role = "author:post:$post_type:author_restrict:{$row->condition_name}";
                    $cond_caps = false;
                    break;

                case 'contributor_restrict':
                    $data['operation'] = 'assign';
                    $pp_role = "contributor:post:$post_type:contributor_restrict:{$row->condition_name}";
                    $cond_caps = false;
                    break;

                default:
                    continue;
            }

            $attrib_exempt_caps = ["pp_no_{$row->attribute}"];

            $have_site_roles = [];
            if ('post' == $row->item_source) {
                if ($_results = $wpdb->get_results("SELECT group_id FROM $wpdb->pp_roles WHERE group_type = 'pp_group' AND scope = 'site' AND role_name = '$pp_role'")) {
                    foreach ($_results as $_row)
                        $have_site_roles[$_row->group_id] = true;
                }
            }

            // users with these caps in their WP role won't be restricted
            $exempt_caps = array_fill_keys(['activate_plugins', 'administer_content', 'pp_administer_content'], true);
            $exempt_caps["pp_no_{$row->attribute}"] = true;
            if (defined('SCOPER_CONTENT_ADMIN_CAP'))
                $exempt_caps[constant('SCOPER_CONTENT_ADMIN_CAP')] = true;

            if ('contributor_restrict' == $row->attribute)
                $exempt_caps['level_2'] = true;

            if ('author_restrict' == $row->attribute)
                $exempt_caps['level_7'] = true;

            foreach (array_keys($applicable_roles) as $wp_rolename) {
                if (isset($have_site_roles[$pp_agent_id[$wp_rolename]]))
                    continue;

                $role_caps = array_intersect($wp_roles->role_objects[$wp_rolename]->capabilities, [true, 1, '1']);
                if ($cond_caps && !array_intersect_key($cond_caps, $role_caps))
                    continue;

                if (array_intersect_key($role_caps, $exempt_caps))
                    continue;

                $data['agent_id'] = $pp_agent_id[$wp_rolename];

                $exception_id = $this->get_exception_id($stored_exceptions, $data, $row->source_id);

                $inherited_from = ($row->inherited_from && isset($log_eitem_ids[$row->inherited_from])) ? $log_eitem_ids[$row->inherited_from] : 0;

                $wpdb->insert_id = 0;
                $sql = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, item_id, inherited_from) SELECT * FROM ( SELECT '$row->assign_for' AS a, '$exception_id' AS b, '$row->item_id' AS c, '$inherited_from' AS d ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_exception_items WHERE assign_for = '$row->assign_for' AND exception_id = '$exception_id' AND item_id = '$row->item_id') LIMIT 1";
                $wpdb->query($sql);
                if ($wpdb->insert_id) {
                    $eitem_id = (int)$wpdb->insert_id;

                    $log_eitem_ids[$row->source_id] = $eitem_id;

                    $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->pp_conditions), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exception_items), 'import_id' => $eitem_id, 'site' => $blog_id];
                    $wpdb->insert($wpdb->ppi_imported, $log_data);

                    $this->total_imported++;
                    $this->num_imported['item_conditions']++;
                }
            }
        }

        /*
        // convert inherited_from values from pp_conditions to ppc_exception_items.eitem_id
        foreach( $old_inherited_from as $eitem_id => $rs_id ) {
            if ( isset( $log_eitem_ids[$rs_id] ) ) {
                $data = ['inherited_from' => $log_eitem_ids[$rs_id] ];
                $where = ['eitem_id' => $eitem_id];
                $wpdb->update( $wpdb->ppc_exception_items, $data, $where );
            }
        }
        */
        // === end PP conditions import ===
    }

    function import_pp_item_roles()
    {
        global $wpdb, $wp_roles;

        $blog_id = get_current_blog_id();

        $cap_caster = presspermit()->capCaster();
        $role_metagroups_pp = $wpdb->get_results("SELECT metagroup_id, ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'", OBJECT_K);

        $pp_agent_id = [];
        $results = $wpdb->get_results("SELECT metagroup_id, ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'");
        foreach ($results as $row) {
            $pp_agent_id[$row->metagroup_id] = $row->ID;
        }

        // === PP item roles import ("additional" exceptions) ===
        $imported_roles = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->pp_roles)), OBJECT_K);

        $stored_exceptions = $wpdb->get_results("SELECT * FROM $wpdb->ppc_exceptions WHERE agent_type IN ( 'pp_group', 'user' ) AND for_item_source IN ( 'post', 'term' ) AND via_item_source IN ( 'post', 'term' ) AND mod_type = 'additional' ");
        $old_inherited_from = [];
        $log_eitem_ids = [];

        $results = $wpdb->get_results("SELECT assignment_id AS source_id, role_name, item_id, assign_for, inherited_from, scope, item_source, group_type, group_id, assigner_id FROM $wpdb->pp_roles WHERE scope IN ( 'term', 'object' ) ORDER BY assignment_id", OBJECT_K);
        $results = array_diff_key($results, $imported_roles);

        $editability_posts = [];

        foreach ($results as $row) {
            parent::checkTimeout();

            if (('post' == $row->item_source) and !in_array($row->item_id, $this->all_post_ids))
                continue;

            if (('term_taxonomy' == $row->item_source) and !in_array($row->item_id, $this->all_tt_ids))
                continue;

            if (!$data = $this->get_item_role_exception_fields($row, $pp_agent_id))
                continue;

            if ($data['for_item_type'] && !post_type_exists($data['for_item_type']) && !taxonomy_exists($data['for_item_type']))
                continue;

            if ($data['via_item_type'] && !post_type_exists($data['via_item_type']) && !taxonomy_exists($data['via_item_type']))
                continue;

            $item_ids = (array)$row->item_id;

            // for term manager role, term "additional manage" exception
            if ((0 === strpos($row->role_name, 'pp_') && (strpos($row->role_name, '_manager')) || 0 === strpos($row->role_name, 'editor:term_taxonomy'))) {
                $data['operation'] = 'manage';

                // for now-undefined "bypass contrib/author restrict" term role, assign term "additional" exceptions for "assign" operation
            } elseif (strpos($row->role_name, ':contributor_restrict') || strpos($row->role_name, ':author_restrict')) { // PP 1.x assigned for term scope only
                $data['for_item_source'] = 'post';
                $data['for_item_type'] = '';
                $data['for_item_status'] = '';
                $data['operation'] = 'assign';
            } elseif (strpos($row->role_name, ':editability')) {  // PP 1.x assigned for term scope only
                if (!isset($editability_posts[$data['for_item_status']])) { // buffering editability_posts query for use with subsequent item role rows
                    $_arr = explode(':', $data['for_item_status']);
                    $_condition_name = isset($_arr[1]) ? $_arr[1] : '';

                    // all posts with this editability condition direct-assigned
                    $cond_posts = $wpdb->get_col("SELECT item_id FROM $wpdb->pp_conditions WHERE scope = 'object' AND assign_for = 'item' AND item_source = 'post' AND attribute = 'editability' AND condition_name = '$_condition_name'");

                    // all posts that have this editability condition via term assignment
                    $term_cond_posts = $wpdb->get_col("SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ( SELECT item_id FROM $wpdb->pp_conditions WHERE scope = 'term' AND assign_for = 'item' AND item_source = 'term_taxonomy' AND attribute = 'editability' AND condition_name = '$_condition_name' )");

                    $editability_posts[$data['for_item_status']] = array_unique(array_merge($cond_posts, $term_cond_posts));
                }

                if (!empty($editability_posts[$data['for_item_status']])) {
                    // for now-undefined Editability condition term role, query for post IDs and assign post-specific "additional edit" exception (for posts with matching Editability cond)
                    $term_posts = $wpdb->get_col("SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = '$row->item_id'");

                    if (!$item_ids = array_intersect($term_posts, $editability_posts[$data['for_item_status']]))
                        continue;

                    $data['via_item_source'] = 'post';
                    $data['via_item_type'] = '';
                    $data['for_item_status'] = '';
                }
            } elseif ('term' == $row->scope) {
                $arr = explode(':', $row->role_name);
                $base_role_name = $arr[0];

                // for Contributor or Author term role, assign specified PPC site role + term "include assign" exception
                if (in_array($base_role_name, ['contributor', 'author'], true)) {
                    if ($need_caps = $cap_caster->getTypecastCaps($row->role_name)) {
                        $has_caps = false;

                        if ('pp_group' == $row->group_type) {
                            // if group is a WP Role metagroup, consider WP rolecaps
                            foreach ($role_metagroups_pp as $role_name => $_pp_group) {
                                if ($_pp_group->ID == $row->group_id) {
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
                            // does this group already have a site role assignment with the same caps?
                            $has_pp_roles = $wpdb->get_col("SELECT role_name FROM $wpdb->ppc_roles WHERE agent_type = '$row->group_type' AND agent_id = '$row->group_id'");
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
                            // insert the site role
                            $wpdb->insert_id = 0;
                            $sql = "INSERT INTO $wpdb->ppc_roles (agent_id, agent_type, role_name, assigner_id) SELECT * FROM ( SELECT '$row->group_id' AS a, '$row->group_type' AS b, '$row->role_name' AS c, '$row->assigner_id' AS d ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_roles WHERE agent_type = '$row->group_type' AND agent_id = '$row->group_id' AND role_name = '$row->role_name') LIMIT 1";
                            $wpdb->query($sql);
                            if ($wpdb->insert_id) {
                                $assignment_id = (int)$wpdb->insert_id;

                                $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->pp_roles), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_roles), 'import_id' => $assignment_id, 'site' => $blog_id];
                                $wpdb->insert($wpdb->ppi_imported, $log_data);

                                $this->total_imported++;
                                $this->num_imported['item_roles']++;
                            }
                        }
                    }  // --- end site role assignment ---

                    $data['operation'] = 'assign';
                    $data['mod_type'] = 'include';
                }
            } // note: object roles and other term roles fall through with no further modification to $data array. PP2 allows post exception via term for any post privacy or moderation status

            $data['agent_type'] = $row->group_type;
            $data['agent_id'] = $row->group_id;

            $operations = (array)$data['operation'];  // allow for multiple exceptions per item role (bbpress)
            foreach ($operations as $operation) {
                $data['operation'] = $operation;  // replace value for matching in get_exception_id()

                $exception_id = $this->get_exception_id($stored_exceptions, $data, $row->source_id);

                foreach ($item_ids as $item_id) {
                    $inherited_from = ($row->inherited_from && isset($log_eitem_ids[$row->inherited_from])) ? $log_eitem_ids[$row->inherited_from] : 0;

                    $wpdb->insert_id = 0;
                    $sql = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, assigner_id, item_id, inherited_from) SELECT * FROM ( SELECT '$row->assign_for' AS a, '$exception_id' AS b, '$row->assigner_id' AS c, '$item_id' AS d, '$inherited_from' AS e ) AS tmp WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_exception_items WHERE assign_for = '$row->assign_for' AND exception_id = '$exception_id' AND item_id = '$item_id') LIMIT 1";

                    $wpdb->query($sql);
                    if ($wpdb->insert_id) {
                        $eitem_id = (int)$wpdb->insert_id;

                        $log_eitem_ids[$row->source_id] = $eitem_id;

                        $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->pp_roles), 'source_id' => $row->source_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exception_items), 'import_id' => $eitem_id, 'site' => $blog_id];
                        $wpdb->insert($wpdb->ppi_imported, $log_data);

                        $this->total_imported++;
                        $this->num_imported['item_roles']++;
                    }
                }
            } // end foreach operations
        }

        /*
        // convert inherited_from values from pp_conditions to ppc_exception_items.eitem_id
        foreach( $old_inherited_from as $eitem_id => $rs_id ) {
            if ( isset( $log_eitem_ids[$rs_id] ) ) {
                $data = ['inherited_from' => $log_eitem_ids[$rs_id] ];
                $where = ['eitem_id' => $eitem_id ];
                $wpdb->update( $wpdb->ppc_exception_items, $data, $where );
            }
        }
        */
    }

    function import_pp_options()
    {
        global $wpdb, $wp_roles;

        parent::checkTimeout();

        $imported_options = $wpdb->get_results($wpdb->prepare("SELECT source_id, import_tbl, import_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d", $this->getTableCode($wpdb->options)), OBJECT_K);

        $old_options = [];
        $results = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'pp_%'");
        foreach ($results as $row)
            $old_options[$row->option_name] = maybe_unserialize($row->option_value);

        if (!empty($old_options['pp_editor_hide_html_ids'])) {
            $this->import_option('presspermit_hide_non_editor_admin_divs', $old_options['pp_editor_hide_html_ids'], 'pp_editor_hide_html_ids', $imported_options);
        }

        if (!empty($old_options['pp_role_admin_blogwide_editor_only'])) {
            switch ($old_options['pp_role_admin_blogwide_editor_only']) {
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

        if (!empty($old_options['pp_use_teaser'])) {
            $tease_types = [];
            foreach ($old_options['pp_use_teaser'] as $src_otype => $setting) {
                $post_type = str_replace('post:', '', $src_otype);
                $tease_types[$post_type] = $setting;
            }

            $this->import_option("presspermit_tease_post_types", $tease_types, 'pp_use_teaser', $imported_options);
        }

        if (!empty($old_options['pp_teaser_hide_private'])) {
            $tease_types = [];
            foreach ($old_options['pp_teaser_hide_private'] as $src_otype => $setting) {
                $post_type = str_replace('post:', '', $src_otype);
                $tease_types[$post_type] = !$setting;
            }

            $this->import_option("presspermit_tease_public_posts_only", $tease_types, 'pp_teaser_hide_private', $imported_options);
        }

        if (!empty($old_options['pp_teaser_logged_only'])) {
            $tease_types = [];
            foreach ($old_options['pp_teaser_logged_only'] as $src_otype => $setting) {
                $post_type = str_replace('post:', '', $src_otype);
                $tease_types[$post_type] = $setting;
            }

            $this->import_option("presspermit_tease_logged_only", $tease_types, 'pp_teaser_logged_only', $imported_options);
        }

        $options = ['replace_content', 'replace_content_anon', 'prepend_content', 'prepend_content_anon', 'append_content', 'append_content_anon', 'prepend_name', 'prepend_name_anon', 'append_name', 'append_name_anon', 'replace_excerpt', 'replace_excerpt_anon', 'prepend_excerpt', 'prepend_excerpt_anon', 'append_excerpt', 'append_excerpt_anon'];
        foreach ($options as $opt) {
            if (!empty($old_options["pp_teaser_{$opt}"])) {
                $val = false;

                if (isset($old_options["pp_teaser_{$opt}"]['post:post'])) {
                    $val = $old_options["pp_teaser_{$opt}"]['post:post'];
                } else {
                    foreach ($old_options["pp_teaser_{$opt}"] as $src_otype => $val) {
                        if ($val) {
                            break;
                        }
                    }
                }

                if (false !== $val)
                    $this->import_option("presspermit_tease_{$opt}", $val, "pp_teaser_{$opt}", $imported_options);
            }
        }

        if (isset($old_options['pp_mirror_post_translation_roles'])) {
            $this->import_option("presspermit_mirror_post_translation_exceptions", $old_options['pp_mirror_post_translation_roles'], 'pp_mirror_post_translation_roles', $imported_options);
        }

        if (isset($old_options['pp_mirror_term_translation_roles'])) {
            $this->import_option("presspermit_mirror_term_translation_exceptions", $old_options['pp_mirror_term_translation_roles'], 'pp_mirror_term_translation_roles', $imported_options);
        }
    }

    function import_option($opt_name, $opt_value, $source_opt_name, $imported_options)
    {
        global $wpdb;

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

            $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->options), 'source_id' => $source_id, 'import_tbl' => $this->getTableCode($wpdb->options), 'import_id' => $import_id, 'site' => get_current_blog_id()];
            $wpdb->insert($wpdb->ppi_imported, $log_data);

            $this->total_imported++;
            $this->num_imported['options']++;
        }
    }

    function get_condition_exception_fields($import_obj)
    {
        $data = ['agent_type' => 'pp_group'];

        global $wpdb;

        if ('term' == $import_obj->scope) {
            $data['via_item_source'] = 'term';
            if (!$data['via_item_type'] = $wpdb->get_var("SELECT taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = '$import_obj->item_id'"))
                return false;

            $data['for_item_type'] = '';
        } else {
            $data['via_item_source'] = 'post';
            $data['via_item_type'] = '';
            if (!$data['for_item_type'] = $wpdb->get_var("SELECT post_type FROM $wpdb->posts WHERE ID = '$import_obj->item_id'"))
                return false;
        }

        $data['for_item_source'] = 'post';
        $data['for_item_status'] = '';

        $data['mod_type'] = 'exclude';

        switch ($import_obj->attribute) {
            case 'post_status':
                $data['operation'] = 'read';
                break;

            case 'editability':
                $data['operation'] = 'edit';
                break;

            case 'author_restrict':
                $data['operation'] = 'assign';
                break;

            case 'contributor_restrict':
                $data['operation'] = 'assign';
                break;
        }
        return $data;
    }

    function get_item_role_exception_fields($import_obj)
    {
        $data = [];

        global $wpdb;

        if ('term' == $import_obj->scope) {
            $data['via_item_source'] = 'term';
            if (!$data['via_item_type'] = $wpdb->get_var("SELECT taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = '$import_obj->item_id'"))
                return false;
        } else {
            $data['via_item_source'] = 'post';
            $data['via_item_type'] = '';
        }

        $arr = explode(':', $import_obj->role_name);

        $role_name = $arr[0];

        if (count($arr) < 2) {
            if (count($arr) == 0) {
                return false;
            }

            if (in_array($role_name, ['bbp_spectator', 'bbp_participant', 'bbp_moderator', 'bbp_keymaster'], true)) {
                // direct-assigned term/object roles will have an array count of 1 here
                $data['for_item_type'] = get_post_field('post_type', $import_obj->item_id);

                //if ( 'forum' != $data['for_item_type'] )
                // return false;  // topic and reply assignments will be propagated from forum

                $data['for_item_source'] = 'post';
                $data['for_item_status'] = '';
            }
        } else {
            $data['for_item_source'] = ('term_taxonomy' == $arr[1]) ? 'term' : $arr[1];
            $data['for_item_type'] = $arr[2];

            if (isset($arr[4]))
                $data['for_item_status'] = $arr[3] . ':' . $arr[4];  // subsequent script execution will convert editability, contributor_restrict and author_restrict conditions and modify for_item_status
            else
                $data['for_item_status'] = '';
        }

        switch ($role_name) {
            case 'reviewer':
                return false;
                break;

            case 'subscriber':
            case 'bbp_spectator':
                $data['operation'] = 'read';
                break;

            case 'contributor':
                if (!$data['for_item_status'] && ('post' == $data['via_item_source'])) {
                    $data['for_item_status'] = 'post_status:{unpublished}';
                }
            // no break
            case 'author':
            case 'editor':
                $data['operation'] = 'edit';
                break;

            case 'revisor':
                $data['operation'] = 'revise';
                break;

            case 'bbp_spectator':
                $data['operation'] = 'read';
                break;

            case 'bbp_participant':
                $data['operation'] = ['read', 'publish_topics', 'publish_replies'];
                break;

            case 'bbp_moderator':
            case 'bbp_keymaster':
                $data['operation'] = ['read', 'publish_topics', 'publish_replies', 'edit'];
                break;

            default:
                global $wp_roles;

                if ('post' != $data['via_item_source'])
                    return false;

                if (isset($wp_roles->role_objects[$arr[0]]) && !empty($wp_roles->role_objects[$arr[0]]->capabilities['edit_posts'])) {
                    if (!$data['for_item_status']) {
                        $type_obj = get_post_type_object($data['for_item_type']);
                        if ($type_obj && empty($wp_roles->role_objects[$arr[0]]->capabilities[$type_obj->cap->edit_published_posts])) {
                            $data['for_item_status'] = 'post_status:{unpublished}';
                        }
                    }

                    $data['operation'] = 'edit';
                } else
                    $data['operation'] = 'read';
        }

        if ($data['for_item_status'] && ('read' == $data['operation']) && ('term' != $import_obj->scope))
            $data['for_item_status'] = '';

        $data['mod_type'] = 'additional';

        $data['assigner_id'] = $import_obj->assigner_id;

        return $data;
    }

    function get_exception_id(&$stored_exceptions, $data, $restriction_id)
    {
        $exception_id = 0;
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

            $log_data = ['run_id' => $this->run_id, 'source_tbl' => $this->getTableCode($wpdb->pp_conditions), 'source_id' => $restriction_id, 'import_tbl' => $this->getTableCode($wpdb->ppc_exceptions), 'import_id' => $exception_id, 'site' => get_current_blog_id()];
            $wpdb->insert($wpdb->ppi_imported, $log_data);
            //$this->num_imported['exceptions']++;
        }

        return $exception_id;
    }
}
