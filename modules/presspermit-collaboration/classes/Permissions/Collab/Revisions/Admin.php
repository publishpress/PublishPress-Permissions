<?php
namespace PublishPress\Permissions\Collab\Revisions;


// @todo: restore capability allowance for listing query only

// @todo: introduce copy_posts, revise_posts capabilities ?


//use \PressShack\LibArray as Arr;
class Admin
{
    function __construct() {
        add_filter('map_meta_cap', [$this, 'flt_mapMetaCap'], 3, 4);

        // @todo: adapt this for copy_posts exceptions
        /*
        add_filter('presspermit_get_exception_items', [$this, 'flt_get_exception_items'], 10, 5);
        add_filter('presspermit_term_restrictions_clause', [$this, 'fltTermRestrictionsClause'], 10, 2);
        add_filter('presspermit_additions_clause', [$this, 'flt_additions_clause'], 10, 4);
        add_filter('presspermit_term_include_clause', [$this, 'flt_term_include_clause'], 10, 2);
        add_filter('presspermit_exception_clause', [$this, 'flt_pp_exception_clause'], 10, 4);
        */

        add_filter('presspermit_administrator_caps', [$this, 'flt_pp_administrator_caps'], 5);
    }

    public function flt_pp_administrator_caps($caps)
    {
        // @todo: why is edit_others_revisions cap required for Administrators in Edit Posts listing (but not Edit Pages) ?

        $caps['edit_revisions'] = true;
        $caps['edit_others_revisions'] = true;
        $caps['list_others_revisions'] = true;
        $caps['delete_revisions'] = true;
        $caps['delete_others_revisions'] = true;

        return $caps;
    }

    public function flt_mapMetaCap($caps, $meta_cap, $user_id, $wp_args)
    {
        if ($user_id && in_array($meta_cap, ['edit_post', 'edit_page'], true) && !empty($wp_args[0]) 
			&& function_exists('rvy_get_option') && function_exists('rvy_default_options') // Revisions plugin does not initialize on plugins.php URL
		) {
            global $current_user;
            if ($user_id != $current_user->ID)
                return $caps;

            if ( false !== strpos($_SERVER['SCRIPT_NAME'], 'update.php') ) { // Revisionary does not load on update.php
                return $caps;
            }

            $_post = (is_object($wp_args[0])) ? $wp_args[0] : get_post($wp_args[0]);

            if (!$_post) {
                return $caps;
            }

            if (rvy_get_option('require_edit_others_drafts') && apply_filters('revisionary_require_edit_others_drafts', true, $_post->post_type, $_post->post_status, $wp_args)) {
                // for \PublishPress\Permissions\PostFilters::mapMetaCap()
                global $current_user;

                if ($current_user->ID == $_post->post_author) {
                    return $caps;
                }

                $status_obj = get_post_status_object($_post->post_status);
                if (($status_obj && ($status_obj->public || $status_obj->private)) || rvy_in_revision_workflow($_post) || ('revision' == $_post->post_type)) {
                    return $caps;
                }

                $post_type_obj = get_post_type_object($_post->post_type);

                if (isset($post_type_obj->cap->edit_others_posts) && !in_array($post_type_obj->cap->edit_others_posts, $caps)) {
                    return $caps;
                }

                // don't require any additional caps for sitewide Editors
                if (!empty($current_user->allcaps[$post_type_obj->cap->edit_published_posts])) {
                    return $caps;
                }

                if (!presspermit()->doing_cap_check && $post_type_obj) {
                    if (!empty($post_type_obj->cap->edit_others_posts) && empty($current_user->allcaps[$post_type_obj->cap->edit_others_posts])) {
                        $revise_cap = str_replace('edit_', 'revise_', $type_obj->cap->edit_others_posts);
                        
                        if (!empty($current_user->allcaps[$revise_cap])) {
                            $caps[]= $revise_cap;
                        } else {
                            $caps []= str_replace('edit_', 'list_', $post_type_obj->cap->edit_others_posts);
                        }
                    }
                }

                if (empty($current_user->allcaps['edit_others_drafts'])) {
                	$caps[] = "edit_others_drafts";
                }
            }
        }

        return $caps;
    }

    
    /*
    public function flt_pp_exception_clause($clause, $required_operation, $post_type, $args = [])
    {
        $defaults = ['logic' => 'NOT IN', 'ids' => [], 'src_table' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $revision_base_status_csv = rvy_revision_base_statuses(['return' => 'csv']);
	    $revision_status_csv = rvy_revision_statuses(['return' => 'csv']);

        return "( $clause OR ( $src_table.post_status IN ($revision_base_status_csv) AND $src_table.post_mime_type IN ($revision_status_csv) AND $src_table.comment_count $logic ('" . implode("','", $ids) . "') ) )";
    }

    public function flt_term_include_clause($clause, $args = [])
    {
        global $wpdb;

        $defaults = ['tt_ids' => [], 'src_table' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $revision_base_status_csv = rvy_revision_base_statuses(['return' => 'csv']);
	    $revision_status_csv = rvy_revision_statuses(['return' => 'csv']);

        $clause .= " OR ( $src_table.post_status IN ($revision_base_status_csv) AND $src_table.post_mime_type IN ($revision_status_csv) AND $src_table.comment_count IN" 
        . " ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $tt_ids) . "') ) )";
        
        return $clause;
    }

    public function flt_additions_clause($clause, $operation, $post_type, $args)
    {
        //$args = compact( 'status', 'in_clause', 'src_table' ) 

        $append_clause = '';

        if (in_array($operation, ['edit', 'delete'], true) && empty($args['status']) 
        && !in_array($post_type, apply_filters('presspermit_unrevisable_types', []), true)) {
            $user = presspermit()->getUser();

            if ('post' == $args['via_item_source']) {
                $hide_others_revisions = rvy_get_option('revisor_lock_others_revisions') 
                && (
                    (!empty($type_obj->cap->edit_posts) && empty($type_obj->cap->edit_others_posts))
                    || (!empty($type_obj->cap->edit_others_posts) && empty($user->allcaps[$type_obj->cap->edit_others_posts]))
                );

                $revision_base_status_csv = rvy_revision_base_statuses(['return' => 'csv']);
	            $revision_status_csv = rvy_revision_statuses(['return' => 'csv']);
                $revision_statuses_clause = "({$args['src_table']}.post_status IN ($revision_base_statuses_csv) AND {$args['src_table']}.post_mime_type IN ($revision_status_csv) )";

                // If we are hiding other revisions from revisors, need to distinguish 
                // between 'edit' exceptions and 'revise' exceptions (which are merged upstream for other reasons).
                if ($hide_others_revisions && empty($user->allcaps['edit_others_revisions'])) {
                    $revise_ids = [];

                    $via_item_type = (isset($args['via_item_type'])) ? $args['via_item_type'] : $post_type;
                    $revise_ids = $user->getExceptionPosts( 
                        'revise', 
                        'additional', 
                        $via_item_type, 
                        ['status' => $args['status']]
                    );

                    $edit_ids = ($revise_ids) ? array_diff($args['ids'], $revise_ids) : $args['ids'];
                    
                    if ( $edit_ids || $revise_ids ) {
                        $parent_clause = [];
                        
                        if ( $edit_ids ) {
                            $parent_clause []= "( {$args['src_table']}.comment_count IN ('" . implode("','", $edit_ids) . "') )";
                        }
                        
                        if ( $revise_ids ) {
                            //$status_csv = "'" . implode("','", get_post_stati(['public' => true, 'private' => true], 'names', 'or')) . "'";

                            $parent_clause []= "( " . PWP::postAuthorClause($args) 
                            . " AND " . "{$args['src_table']}.comment_count IN ('" . implode("','", $revise_ids) . "') )";  // AND {$args['src_table']}.post_status IN ($status_csv) )";
                        }
                        
                        $parent_clause = 'AND (' . Arr::implode(' OR ', $parent_clause) . ' )';

                        $append_clause .= " OR ( $revision_statuses_clause $parent_clause )";
                    }
                } else {
                    // Not hiding other users' revisions from Revisors, so list all posts with 'edit' or 'revise' exceptions regardless of author.
                    $append_clause .= " OR ( $revision_statuses_clause AND {$args['src_table']}.comment_count {$args['in_clause']} )";
                }
            } elseif ('term' == $args['via_item_source']) {
                $revise_tt_ids = [];

                foreach(presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
                    $tt_ids = $user->getExceptionTerms( 
                        'revise', 
                        'additional', 
                        $post_type, 
                        $taxonomy, 
                        ['status' => $args['status'], 'merge_universals' => true]
                    );
                    
                    $revise_tt_ids = array_merge($revise_tt_ids, $tt_ids);
                }

                if ($revise_tt_ids) {
                    global $wpdb;

                    $parent_tt_clause = "( {$args['src_table']}.comment_count IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $revise_tt_ids) . "') ) )";
                
                    $append_clause .= " OR ( $revision_statuses_clause AND $parent_tt_clause )";
                }
			}
		}

        if ($append_clause) {
            $clause = "({$args['src_table']}.post_mime_type NOT IN ($revision_statuses_csv) AND ($clause)) $append_clause";
        }

		return $clause;
    }

    // merge revise exceptions into edit exceptions
    public function flt_get_exception_items($exception_items, $operation, $mod_type, $for_item_type, $args = [])
    {
        global $revisionary;

        if ('edit' != $operation)
            return $exception_items;

        // Modify Posts listing, but not 'edit_post' capability check
        if (presspermit()->doing_cap_check) {
            return $exception_items;
        }

        $defaults = ['via_item_source' => 'post', 'via_item_type' => '', 'status' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if ('term' == $via_item_source) {
            // Don't implement term exceptions by merging with edit_post exceptions, due to complication of applying revision exceptions for published posts only
            return $exception_items;
        }

        $user = presspermit()->getUser();

        if (!isset($user->except['revise_post'])) {
            $user->retrieveExceptions('revise', 'post');
        }

        if (!isset($user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type])) {
            return $exception_items;
        }

        $exception_items = (isset($user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type])) 
        ? $user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type] 
        : [];

        foreach (array_keys($user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type]) as $_status) {
            Arr::setElem($exception_items, [$_status]);

            $exception_items[$_status] = array_merge(
                $exception_items[$_status], 
                $user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type][$_status]
            );
        }

        if (true === $status) {
            return $exception_items;
        } else {
            return Arr::flatten(array_intersect_key($exception_items, [$status => true]));
        }

        return $exception_items;
    }

    // Apply term revision restrictions separately with status clause to avoid removing unpublished posts from the listing 
    function fltTermRestrictionsClause($where, $args) {
        global $wpdb;

        // Modify Posts listing, but not 'edit_post' capability check
        if (presspermit()->doing_cap_check) {
            return $where;
        }

        $defaults = array_fill_keys(
            ['required_operation',
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
            ], ''
        );

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $user = presspermit()->getUser();

        $excluded_ttids_published = [];

        foreach (presspermit()->getEnabledTaxonomies($tx_args) as $taxonomy) {
            $tx_additional_ids = ($merge_additions)
                ? $user->getExceptionTerms($required_operation, 'additional', $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])
                : [];

            foreach ($mod_types as $mod) {
                if ($tt_ids = $user->getExceptionTerms('revise', $mod, $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])) {    
                    $tx_additional_ids = ($merge_additions)
                    ? $user->getExceptionTerms('revise', 'additional', $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])
                    : [];
                    
                    $published_stati_csv = implode("','", get_post_stati(['public' => true, 'private' => true], 'names', 'OR' ));

                    if ('include' == $mod) {
                        if ($tx_additional_ids) {
                            $tt_ids = array_merge($tt_ids, $tx_additional_ids);
                        }

                        $term_include_clause = apply_filters(
                            'presspermit_term_include_clause',
                            "( $src_table.post_status NOT IN ('$published_stati_csv') OR $src_table.ID IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $tt_ids) . "') ) )",
                            compact('tt_ids', 'src_table')
                        );
                
                        $where .= " AND ( $term_include_clause $term_additions_clause $post_additions_clause $type_exemption_clause )";
                        continue 2;

                    } else {
                        if ($tx_additional_ids) {
                            $tt_ids = array_diff($tt_ids, $tx_additional_ids);
                        }

                        $excluded_ttids_published = array_merge($excluded_ttids_published, $tt_ids);
                    }
                }
            }
        }

        if ($excluded_ttids_published) {
            $where .= " AND ( ($src_table.post_status NOT IN ('$published_stati_csv') OR $src_table.ID NOT IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('"
                . implode("','", $excluded_ttids_published)
                . "') ) $type_exemption_clause ) $term_additions_clause $post_additions_clause )";
        }

        return $where;
    }
    */
}
