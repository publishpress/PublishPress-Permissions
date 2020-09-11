<?php

namespace PublishPress\Permissions\DB;

//use \PressShack\LibArray as Arr;

class Permissions
{
    public static function getRoles($agent_id, $agent_type = 'pp_group', $args = [])
    {
        global $wpdb;

        $pp = presspermit();

        $query_agent_ids = (isset($args['query_agent_ids'])) ? $args['query_agent_ids'] : false;

        $roles = [];

        $agent_type = sanitize_key($agent_type);

        if ($query_agent_ids) {
            static $agent_roles;
            static $last_query_key;
            if (!isset($agent_roles)) {
                $agent_roles = [];
                $last_query_agent_ids = false;
            }
            $query_key = serialize($query_agent_ids) . $agent_type;
        } else {
            $agent_roles = [];
            $query_agent_ids = (array)$agent_id;
            $query_key = false;
        }

        if (('pp_group' == $agent_type) && (!defined('PP_ALL_ANON_ROLES'))) {
            if ($anon_group = $pp->groups()->getMetagroup('wp_role', 'wp_anon')) {
                $query_agent_ids = array_diff($query_agent_ids, (array)$anon_group->ID);
            }

            if ($all_group = $pp->groups()->getMetagroup('wp_role', 'wp_all')) {
                $query_agent_ids = array_diff($query_agent_ids, (array)$all_group->ID);
            }
        }

        if (!isset($agent_roles[$agent_id]) || ($last_query_key !== $query_key)) {
            foreach ($query_agent_ids as $_id)
                $agent_roles[$_id] = [];

            $results = $wpdb->get_results(
                "SELECT assignment_id, role_name, agent_id FROM $wpdb->ppc_roles WHERE agent_type = '$agent_type'"
                . " AND agent_id IN ('" . implode("','", array_map('intval', $query_agent_ids)) . "') ORDER BY role_name"
            );

            $no_ext = !$pp->moduleActive('collaboration') && !$pp->moduleActive('status-control');
            $no_custom_stati = !$pp->moduleActive('status-control');

            foreach ($results as $row) {
                // roles for these post statuses will not be applied if corresponding modules are inactive, so do not indicate in users/groups listing or profile
                if ($no_ext && strpos($row->role_name, ':post_status:') && !strpos($row->role_name, ':post_status:private')) {
                    continue;
                } elseif (
                    $no_custom_stati && strpos($row->role_name, ':post_status:')
                    && !strpos($row->role_name, ':post_status:private') && !strpos($row->role_name, ':post_status:draft')
                ) {
                    continue;
                }

                $agent_roles[$row->agent_id][$row->role_name] = $row->assignment_id;
            }

            $last_query_key = $query_key;
        }

        return isset($agent_roles[$agent_id]) ? $agent_roles[$agent_id] : [];
    }

    public static function getExceptions($args = [])
    {
        $defaults = [
            'operations' => [],
            'inherited_from' => '',
            'for_item_source' => false,
            'via_item_source' => false,
            'assign_for' => 'item',
            'for_item_status' => false,
            'post_types' => true,
            'taxonomies' => true,
            'item_id' => false,
            'agent_type' => '',
            'agent_id' => 0,
            'query_agent_ids' => [],
            'ug_clause' => '',
            'return_raw_results' => false,
            'extra_cols' => [],
            'cols' => []
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        // todo: debug usage w/ switch_to_blog() on a blog with PressPermit activated
        if (empty($wpdb->ppc_exceptions)) {
            return [];
        }

        $pp = presspermit();

        $except = [];

        $operations = (array)$operations;

        if ($operations) {
            // avoid application of exceptions which are disabled due to plugin deactivation
            $operations = array_intersect($operations, $pp->getOperations());
        } else {
            $operations = $pp->getOperations();
        }

        if (!$operations) {
            return [];
        }

        if (!is_array($post_types)) {
            $post_types = $pp->getEnabledPostTypes(['layer' => 'exceptions']);
        }

        if (!is_array($taxonomies)) {
            $taxonomies = $pp->getEnabledTaxonomies();
        }

        $default_arr = ['include', 'exclude'];
        if (!defined('PP_NO_ADDITIONAL_ACCESS')) {
            $default_arr[] = 'additional';
        }

        foreach ($post_types as $_type) {
            $type_sub = strtoupper($_type);
            if (defined("PP_NO_{$type_sub}_EXCEPTIONS") && constant("PP_NO_{$type_sub}_EXCEPTIONS")) {
                $post_types = array_diff($post_types, (array)$_type);
            }
        }

        $valid_src_types = [
            'post' => [ // post exceptions can come from posts or terms
                'post' => ['' => array_fill_keys($default_arr, array_fill_keys($post_types, []))],
                'term' => [],
            ],
            'term' => [ // term exceptions only come from terms
                'term' => [],
            ],
            'pp_group' => [ // pp group management exceptions only come from groups
                'pp_group' => [],
            ],
        ];

        // valid return array is arr[for_item_source] = arr[via_item_src][via_item_type] = ['include', 'exclude']
        if ($add_source_types = apply_filters('presspermit_add_exception_source_types', [])) {
            $valid_src_types = array_merge($valid_src_types, $add_source_types);
        }

        if ($for_item_source) {
            $for_item_source = array_flip((array)$for_item_source);

            if (!$for_item_sources = array_intersect_key($for_item_source, $valid_src_types)) {
                return [];
            }
        } else {
            $for_item_sources = $valid_src_types;
        }

        $for_item_clauses = [];

        foreach (array_keys($for_item_sources) as $for_source_name) {
            if (isset($valid_src_types[$for_source_name])) {

                foreach ($operations as $op) {
                    $except["{$op}_{$for_source_name}"] = $valid_src_types[$for_source_name];
                }

                $for_types = [];

                foreach (array_keys($valid_src_types[$for_source_name]) as $via_source_name) {
                    if ('post' == $via_source_name) {
                        foreach (array_keys($valid_src_types[$for_source_name][$via_source_name]) as $via_type) {
                            foreach (array_keys($valid_src_types[$for_source_name][$via_source_name][$via_type]) as $mod_type) {
                                $for_types = array_merge($for_types, array_keys($valid_src_types[$for_source_name][$via_source_name][$via_type][$mod_type]));
                            }
                        }
                    } elseif ('term' == $via_source_name) {
                        $for_types = ('term' == $for_source_name) ? $taxonomies : $post_types;
                    } else {
                        $for_types = false;
                    }
                }

                if (false === $for_types) {
                    $for_item_clauses[] = "e.for_item_source = '$for_source_name'";
                } else {
                    $for_item_clauses[] = "e.for_item_source = '$for_source_name' AND e.for_item_type IN ('', '" . implode("','", array_unique($for_types)) . "')";
                }
            }
        }

        if ($type_clause = Arr::implode('OR', $for_item_clauses)) {
            $type_clause = "AND ( $type_clause )";
        }

        if ($via_item_source) {
            $type_clause .= $wpdb->prepare("AND e.via_item_source = %s", $via_item_source);
        }

        if ($agent_type && !$ug_clause) {
            $ug_clause = $wpdb->prepare(" AND e.agent_type = %s AND e.agent_id IN ('" . implode("','", array_map('intval', (array)$agent_id)) . "')", $agent_type);
        }

        $operation_clause = "AND e.operation IN ('" . implode("','", $operations) . "')";

        $mod_clause = (defined('PP_NO_ADDITIONAL_ACCESS')) ? "AND e.mod_type != 'additional'" : '';

        if (!defined('PP_GROUP_RESTRICTIONS')) {
            $wp_group_ids = [];
            $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, 'pp_group');
            $mod_clause .= "AND ( e.mod_type != 'exclude' OR e.agent_type = 'user' OR ( e.agent_type = 'pp_group' AND e.agent_id IN (SELECT ID FROM $groups_table WHERE metagroup_type = 'wp_role') ) )";
        }

        $assign_for_clause = ($assign_for) ? $wpdb->prepare("AND i.assign_for = %s", $assign_for) : '';
        $inherited_from_clause = ($inherited_from !== '') ? $wpdb->prepare("AND i.inherited_from = %d", $inherited_from) : '';
        $status_clause = (false !== $for_item_status) ? $wpdb->prepare("AND e.for_item_status = %s", $for_item_status) : '';

        if (!$status_clause && !$pp->moduleActive('status-control')) {
            $stati = ['', 'post_status:private', 'post_status:draft'];
            if ($pp->moduleActive('collaboration')) {
                $stati[] = 'post_status:{unpublished}';
            }

            // exceptions for other statuses will not be applied correctly without status control module
            $status_clause = "AND e.for_item_status IN ('" . implode("','", $stati) . "')";
        }

        if (!$cols) {
            $cols = "e.operation, e.for_item_source, e.for_item_type, e.mod_type, e.via_item_source, e.via_item_type, e.for_item_status, i.item_id, i.assign_for";
        }

        $extra_cols_clause = ($extra_cols) ? ', ' . implode(",", $extra_cols) : '';

        $id_clause = (false !== $item_id) ? $wpdb->prepare("AND i.item_id = %d", $item_id) : '';

        $results = $wpdb->get_results(
            "SELECT $cols{$extra_cols_clause} FROM $wpdb->ppc_exceptions AS e"
            . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
            . " WHERE ( 1=1 $operation_clause $assign_for_clause $inherited_from_clause $mod_clause $type_clause $status_clause $id_clause ) $ug_clause"
        );

        if ($return_raw_results) {
            return $results;
        }

        foreach ($results as $row) {
            // note: currently only additional access can be status-specific
            $except["{$row->operation}_{$row->for_item_source}"][$row->via_item_source][$row->via_item_type][$row->mod_type][$row->for_item_type][$row->for_item_status][] = $row->item_id;
        }

        return $except;
    }

    public static function getExceptionsClause($operation, $post_type, $args = [])
    {
        $defaults = ['col_id' => 'ID', 'user' => 0];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$user) {
            $user = presspermit()->getUser();
        }

        // TODO: why is this needed on some installations?
        $user->retrieveExceptions($operation, 'post');

        // Note: this does not apply term exceptions (not needed for current implementation, which only uses this function for 'associate' op )

        $additional_ids = $user->getExceptionPosts($operation, 'additional', $post_type);

        if ($include_ids = $user->getExceptionPosts($operation, 'include', $post_type)) {
            if ($additional_ids)
                $include_ids = array_unique(array_merge($include_ids, $additional_ids));

            // @todo: how can this ever have array elements (PHP error log from one user)
            $include_ids = array_filter($include_ids, 'is_scalar');

            $where = " AND $col_id IN ('" . implode("','", array_unique($include_ids)) . "')";
        } elseif ($exclude_ids = array_diff($user->getExceptionPosts($operation, 'exclude', $post_type), $additional_ids)) {
            $where = " AND $col_id NOT IN ('" . implode("','", $exclude_ids) . "')";
        } else
            $where = '';

        return $where;
    }

    public static function addExceptionClauses($where, $required_operation, $post_type, $args = [])
    {
        $defaults = ['src_table' => '', 'source_alias' => '', 'apply_term_restrictions' => true, 'append_post_type_clause' => true, 'additions_only' => false, 'query_contexts' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $user = presspermit()->getUser();

        if (!$src_table) {
            $src_table = ($source_alias) ? $source_alias : $wpdb->posts;
        }

        $exc_post_type = apply_filters('presspermit_exception_post_type', $post_type, $required_operation, $args);

        $additions = [];
        $additional_ids = $user->getExceptionPosts($required_operation, 'additional', $exc_post_type, ['status' => true]);

        foreach ($additional_ids as $_status => $_ids) {
            if ($_status) {  // db storage is with "post_status:" prefix to allow for implementation of other attributes
                if (0 === strpos($_status, 'post_status:'))
                    $_status = str_replace('post_status:', '', $_status);
                else
                    continue;
            }

            // facilitates user-add on post edit form without dealing with status caps
            $in_clause = "IN ('" . implode("','", $_ids) . "')";
            $additions[$_status][] = apply_filters(
                'presspermit_additions_clause',
                "$src_table.ID $in_clause",
                $required_operation,
                $post_type,
                [
                    'via_item_source' => 'post', 
                    'via_item_type' => $exc_post_type,
                    'status' => $_status, 
                    'in_clause' => $in_clause, 
                    'src_table' => $src_table,
                    'ids' => $_ids
                ]
            );
        }

		if (!$additions_only) {
            if ($where) {  // where clause already indicates sitewide caps for one or more statuses (or just want the exceptions clause generated)
                if ($append_clause = apply_filters('presspermit_append_query_clause', '', $post_type, $required_operation, $args)) {
                    $where .= $append_clause;
                }

                $post_blockage_priority = presspermit()->getOption('post_blockage_priority');
                $post_blockage_clause = '';

                foreach (['include' => 'IN', 'exclude' => 'NOT IN'] as $mod => $logic) {
                    if ($ids = $user->getExceptionPosts($required_operation, $mod, $exc_post_type)) {
                        if (!defined('PP_RESTRICTION_PRIORITY') && !empty($additional_ids['']) && !defined('PP_LEGACY_POST_BLOCKAGE')) {
                        	$ids = array_diff($ids, $additional_ids['']);
                    	}
                        
                        $_args = array_merge($args, compact('mod', 'ids', 'src_table', 'logic'));

                        $clause_var = ($post_blockage_priority) ? 'post_blockage_clause' : 'where';

                        $$clause_var .= " AND " . apply_filters(
                                'presspermit_exception_clause',
                                "$src_table.ID $logic ('" . implode("','", $ids) . "')",
                                $required_operation,
                                $post_type,
                                $_args
                            );

                        break;  // don't use both include and exclude clauses
                    }
                }

                // term restrictions which apply only to this post type
                if ($apply_term_restrictions) {
                    $where .= self::addTermRestrictionsClause($required_operation, $post_type, $src_table);
                }
            } elseif (in_array('comments', $args['query_contexts'], true) && defined('REST_REQUEST') && REST_REQUEST) {
                // if PPCE is not activated, don't filter comments
                $where = '1=1';
            } else {
                $where = '1=2';
            }
        }

        $additional_ttids = [];
        $revise_ttids = ['{published}' => []];

        foreach (presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
            $tt_ids = $user->getExceptionTerms($required_operation, 'additional', $post_type, $taxonomy, ['status' => true, 'merge_universals' => true]);

            if ('edit' == $required_operation) {
                if (!empty($user->except['revise_post']['term'][$taxonomy]['additional'][$post_type][''])) {
                        $revise_ttids['{published}'] = array_merge($revise_ttids['{published}'], $user->except['revise_post']['term'][$taxonomy]['additional'][$post_type]['']);
                }
            }

            // merge this taxonomy exceptions with other taxonomies
            foreach (array_keys($tt_ids) as $_status) {
                if (!isset($additional_ttids[$_status])) {
                    $additional_ttids[$_status] = [];
                }

                $additional_ttids[$_status] = array_merge($additional_ttids[$_status], $tt_ids[$_status]);
            }
        }

        if (('edit' == $required_operation) && !empty($revise_ttids['{published}'])) {
            $in_clause = "IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $revise_ttids['{published}']) . "') )";

            $additions['{published}'][] = apply_filters(
                'presspermit_additions_clause',
                "$src_table.ID $in_clause",
                'revise',
                $post_type,
                [   'via_item_source' => 'term', 
                    'status' => '{published}', 
                    'in_clause' => $in_clause, 
                    'src_table' => $src_table,
                    'ids' => $revise_ttids['{published}']
                ]
            );
        }

        if ($additional_ttids) {
            foreach ($additional_ttids as $_status => $_ttids) {
                if ($_status) {
                    if (0 === strpos($_status, 'post_status:'))
                        $_status = str_replace('post_status:', '', $_status);
                    else
                        continue;
                }

                $in_clause = "IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $_ttids) . "') )";
                $additions[$_status][] = apply_filters(
                    'presspermit_additions_clause',
                    "$src_table.ID $in_clause",
                    $required_operation,
                    $post_type,
                    [   'via_item_source' => 'term', 
                        'status' => $_status, 
                        'in_clause' => $in_clause, 
                        'src_table' => $src_table,
                        'ids' => $_ttids
                    ]
                );
            }
        }

        foreach (array_keys($additions) as $_status) {
            switch ($_status) {
                case '{published}':
                    $_stati = array_merge(
                        PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
                        PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
                    );

                    $_status_clause = "$src_table.post_status IN ('" . implode("','", $_stati) . "') AND ";
                    break;
                
                case '':
                    $_status_clause = '';
                    break;

                case '{unpublished}':
                    if ('read' != $required_operation) { // sanity check
                        $_stati = array_merge(
                            PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
                            PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
                        );

                        $_status_clause = "$src_table.post_status NOT IN ('" . implode("','", $_stati) . "') AND ";
                        break;
                    }
                default:
                    $_status_clause = "$src_table.post_status = '$_status' AND ";
                    break;
            }

            $additions[$_status] = $_status_clause . Arr::implode(' OR ', $additions[$_status]);
        }

        if ($additions = apply_filters('presspermit_apply_additions', $additions, $where, $required_operation, $post_type, $args)) {
            $where = "( $where ) OR ( " . Arr::implode(' OR ', $additions) . " )";

            if (defined('PP_RESTRICTION_PRIORITY') && PP_RESTRICTION_PRIORITY) {  // this constant forces exclusions to take priority over additions
                if ($ids = $user->getExceptionPosts($required_operation, 'exclude', $exc_post_type)) {
                    $_args = array_merge($args, ['mod' => 'exclude', 'ids' => $ids, 'src_table' => $src_table, 'logic' => "NOT IN"]);

                    $restriction_clause = apply_filters(
                        'presspermit_exception_clause',
                        "$src_table.ID NOT IN ('" . implode("','", $ids) . "')",
                        $required_operation,
                        $post_type,
                        $_args
                    );
                } else {
                    $restriction_clause = '1=1';
				}

                if ($apply_term_restrictions) {
                	$restriction_clause .= self::addTermRestrictionsClause($required_operation, $post_type, $src_table, ['mod_types' => 'exclude']);
            	}

            	if ($restriction_clause != '1=1') {
                	$where = "( $where ) AND ( $restriction_clause )";
                }
            }

            if (!empty($post_blockage_clause)) {
            	if (defined('PP_LEGACY_POST_BLOCKAGE')) {
                	$post_blockage_clause = "AND ( ( 1=1 $post_blockage_clause ) OR ( " . Arr::implode(' OR ', $additions) . " ) )";
            	} else {
                	$post_blockage_clause = "AND ( 1=1 $post_blockage_clause )";
            	}
            }
        }

        if (!empty($post_blockage_clause)) {
            $where = "( $where ) $post_blockage_clause";
		}

        if ($append_post_type_clause) {
            $where = "$src_table.post_type = '$post_type' AND ( $where )";
		}

        return $where;
    }

    public static function addTermRestrictionsClause($required_operation, $post_type, $src_table, $args = [])
    {
        global $wpdb;

        $defaults = ['merge_additions' => false, 'exempt_post_types' => [], 'mod_types' => ['include', 'exclude'], 'additional_ttids' => [], 'apply_object_additions' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $user = presspermit()->getUser();

        $mod_types = (array)$mod_types;

        $where = '';
        $excluded_ttids = [];

        $type_exemption_clause = ($exempt_post_types) ? " OR $src_table.post_type IN ('" . implode("','", $exempt_post_types) . "')" : '';

        $tx_args = ($post_type) ? ['object_type' => $post_type] : [];

        // This argument is passed by PostFilters::getPostsWhere() to allow universal term restrictions to be overridden 
        // by other taxonomy exceptions (possibly based on a different taxonomy than the restrictions).
        //
        // Type-specific term restrictions are offset by additions applied by Permissions::addExceptionClauses()
        if ($additional_ttids) {
            $in_clause = "IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $additional_ttids) . "') )";
            $term_additions_clause = apply_filters(
                'presspermit_additions_clause',
                "$src_table.ID $in_clause",
                $required_operation,
                $post_type,
                [   'via_item_source' => 'term', 
                    'status' => '', 
                    'in_clause' => $in_clause, 
                    'src_table' => $src_table,
                    'ids' => $additional_ttids
                ]
            );

            if ($term_additions_clause) {
                $term_additions_clause = "OR ( $term_additions_clause )";
            }
        } else {
            $term_additions_clause = '';
        }

        // This argument is passed by PostFilters::getPostsWhere() to allow universal term restrictions to be overridden by post exceptions.
        //
        // Type-specific term restrictions are offset by additions applied by Permissions::addExceptionClauses()
        $post_additions_clause = '';
        if ($apply_object_additions) {
            if ($post_additions_clause = self::addExceptionClauses(
                '1=2',
                $required_operation, 
                $apply_object_additions, 
                ['additions_only' => true, 'apply_term_restrictions' => false, 'src_table' => $src_table]) 
            ) {
                $post_additions_clause = "OR ( $post_additions_clause )";
            }
        }

        foreach (presspermit()->getEnabledTaxonomies($tx_args) as $taxonomy) {
            $tx_additional_ids = ($merge_additions)
                ? $user->getExceptionTerms($required_operation, 'additional', $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])
                : [];

            // post may be required to be IN a term set for one taxonomy, and NOT IN a term set for another taxonomy
            foreach ($mod_types as $mod) {
                if ($tt_ids = $user->getExceptionTerms($required_operation, $mod, $post_type, $taxonomy, array_merge($args, ['merge_universals' => true]))) {
                    if ('include' == $mod) {
                        if ($tx_additional_ids)
                            $tt_ids = array_merge($tt_ids, $tx_additional_ids);

                        $term_include_clause = apply_filters(
                            'presspermit_term_include_clause',
                            "$src_table.ID IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $tt_ids) . "') )",
                            compact('tt_ids', 'src_table')
                        );

                        $where .= " AND ( $term_include_clause $term_additions_clause $post_additions_clause $type_exemption_clause )";

                        continue 2;
                    } else {
                        if ($tx_additional_ids)
                            $tt_ids = array_diff($tt_ids, $tx_additional_ids);

                        $excluded_ttids = array_merge($excluded_ttids, $tt_ids);
                    }
                }
            }
        }

        if ($excluded_ttids) {
            $where .= " AND ( ( $src_table.ID NOT IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('"
                . implode("','", $excluded_ttids)
                . "') ) $type_exemption_clause ) $term_additions_clause $post_additions_clause )";
        }

        $args = compact(
            'required_operation', 
            'post_type', 
            'src_table', 
            'merge_additions', 
            'exempt_post_types', 
            'mod_types', 
            'tx_args',
            'additional_ttids', 
            'apply_object_additions', 
            'term_additions_clause', 
            'post_additions_clause', 
            'type_exemption_clause'
        );
       
        return apply_filters('presspermit_term_restrictions_clause', $where, $args);
    }

    // returns propagated exceptions items for which (a) the base eitem no longer exists, or (b) the base eitem was changed to "item only"
    private static function get_orphaned_exception_items()
    {
        global $wpdb;
        return $wpdb->get_col(
            "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE inherited_from > 0"
            . " AND inherited_from NOT IN ( SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE assign_for = 'children' )"
        );
    }

    public static function expose_orphaned_exception_items()
    {
        global $wpdb;

        if ($eitem_ids = self::get_orphaned_exception_items()) {
            $wpdb->query("UPDATE $wpdb->ppc_exception_items SET inherited_from = 0 WHERE eitem_id IN ('" . implode("','", $eitem_ids) . "')");

            // keep a log in case questions arise
            if (!$arr = get_option('ppc_exposed_eitem_orphans'))
                $arr = [];

            $arr = array_merge($arr, $eitem_ids);
            update_option('ppc_exposed_eitem_orphans', $arr);
        }
    }
}
