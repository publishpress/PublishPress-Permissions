<?php
namespace PublishPress\Permissions\Collab;

class RESTInit
{
    function __construct() {
        add_action('init', [$this, 'add_post_type_filters'], 99);

        add_filter("rest_post_collection_params", [$this, 'post_collection_params'], 1, 2);
    }

    function add_post_type_filters() {
        foreach (presspermit()->getEnabledPostTypes() as $post_type) {
            add_filter("rest_{$post_type}_collection_params", [$this, 'post_collection_params'], 99, 2);

            if (is_post_type_hierarchical($post_type)) {
                add_filter("rest_{$post_type}_query", [$this, 'page_parent_query_args'], 10, 2);
            }
        }
    }

    function post_collection_params($params, $post_type_obj)
    {
        if (!presspermit()->isContentAdministrator()) {
            if (isset($_REQUEST['context']) && ('edit' == $_REQUEST['context'])) {
                $params['status']['default'] = '';
            }
        }

        return $params;
    }

    function page_parent_query_args($args, $request) {
        $params = $request->get_params();

        if (is_array($params) && !empty($params['parent_exclude']) && !empty($params['context'] && ('edit' == $params['context']))) {
            $post_statuses = apply_filters(
                'presspermit_guten_parent_statuses', 
                PWP::getPostStatuses(['internal' => false, 'post_type' => $args['post_type']], 'names'),
                $args,
                $request
            );
            
            if ($is_administrator = presspermit()->isContentAdministrator()) {
                $pages = get_pages(
                    ['post_type' => $args['post_type'], 
                    'suppress_filters' => 1,
                    'post_status' => $post_statuses,
                    ]
                );
            } else {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostEdit.php');
                new \PublishPress\Permissions\Collab\UI\Dashboard\PostEdit();
                
                $pages = get_pages(
                    ['post_type' => $args['post_type'], 
                    'exclude' => (!empty($params['exclude'])) ? $params['exclude'] : [],
                    'parent_exclude' => (!empty($params['parent_exclude'])) ? $params['parent_exclude'] : [],
                    'required_operation' => 'associate',
                    'suppress_filters' => 0,
                    'name' => 'parent_id',
                    'post_status' => $post_statuses,
                    ]
                );
            }

            $include_page_ids = [];
            foreach($pages as $page) {
            	$include_page_ids []= $page->ID;
            }

            // always include existing page parent value as a dropdown option
            if (!$post_id = PWP::getPostID()) {
                if (!empty($args['post_parent__not_in'])) {
                    $parent_not_in = (array) $args['post_parent__not_in'];
                    if (count($parent_not_in) == 1) {
                        $post_id = reset($parent_not_in);
                    }
                }
            }

            if ($post_id) {
                if ($current_parent = get_post_field('post_parent', $post_id)) {
                    $include_page_ids []= $current_parent;
                }
            }

            $args['post__in'] = $include_page_ids;
            $args['post_status'] = $post_statuses;

            if (defined('PP_PAGE_PARENT_NOPAGING')) {
            	$args['nopaging'] = 1;
			}

            $args['orderby'] = presspermit()->getOption('page_parent_order') ? 'post_title' : 'menu_order';

            // results are already filtered, no further PressPermit filtering
            do_action('presspermit_refresh_administrator_check');
            add_filter('presspermit_unfiltered_content', [$this, 'fltUnfilteredContent'], 50);

            add_filter('posts_results', [$this, 'page_parent_results'], 50, 3);
        }

        return $args;
    }

    function fltUnfilteredContent($unfiltered) {
        return true;
    }

    function page_parent_results($results, $query_obj) {
        if (!$results) {
            return $results;
        }

        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');
        
        $post_type = (isset($query_obj->query_vars['post_type'])) ? $query_obj->query_vars['post_type'] : 'page';

        $ancestors = \PressShack\Ancestry::getPageAncestors(0, $post_type); // array of all ancestor IDs for keyed page_id, with direct parent first

        $orderby = presspermit()->getOption('page_parent_order') ? 'post_title' : 'menu_order';

        $exclude = (!empty($query_obj->query_vars['post__not_in'])) ? $query_obj->query_vars['post__not_in'] : [];
        $remap_args = compact('exclude', 'orderby');

        \PressShack\Ancestry::remapTree($results, $ancestors, $remap_args);

        remove_filter('posts_results', [$this, 'page_parent_results'], 50, 3);

        return $results;
    }
}
